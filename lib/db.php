<?php
function db_init(array $dbConfig): PDO {
    $host = (string)($dbConfig['host'] ?? 'localhost');
    $port = (int)($dbConfig['port'] ?? 3306);
    $dbname = (string)($dbConfig['dbname'] ?? '');
    $user = (string)($dbConfig['user'] ?? '');
    $pass = (string)($dbConfig['password'] ?? '');
    $charset = (string)($dbConfig['charset'] ?? 'utf8mb4');

    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbname . ';charset=' . $charset;
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    db_migrate($pdo);
    return $pdo;
}

/* ================================================================
 * Schema introspection (idempotent migrations)
 * ================================================================ */
function schema_has_column(PDO $db, string $table, string $col): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
    ");
    $stmt->execute([$table, $col]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function schema_has_index(PDO $db, string $table, string $index): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
    ");
    $stmt->execute([$table, $index]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function schema_has_fk(PDO $db, string $table, string $fkName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM information_schema.table_constraints
        WHERE table_schema = DATABASE() AND table_name = ?
          AND constraint_name = ? AND constraint_type = 'FOREIGN KEY'
    ");
    $stmt->execute([$table, $fkName]);
    return ((int)$stmt->fetchColumn()) > 0;
}

/* ================================================================
 * Migration
 * ================================================================ */
function db_migrate(PDO $db): void {
    // 1) users
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(64) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            is_app_admin TINYINT(1) NOT NULL DEFAULT 0,
            slot_mode VARCHAR(8) NOT NULL DEFAULT 'hd4',
            archived TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 2) projects (base)
    $db->exec("
        CREATE TABLE IF NOT EXISTS projects (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            color VARCHAR(7) NOT NULL DEFAULT '#4a90e2',
            archived TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    if (!schema_has_column($db, 'projects', 'created_by')) {
        $db->exec("ALTER TABLE projects ADD COLUMN created_by INT UNSIGNED NULL AFTER archived");
    }

    // 3) entries (base — old single-user schema)
    $db->exec("
        CREATE TABLE IF NOT EXISTS entries (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            period VARCHAR(4) NOT NULL,
            project_id INT UNSIGNED NOT NULL,
            note VARCHAR(255) NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_date (date),
            KEY idx_project (project_id),
            CONSTRAINT fk_entries_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 3a) entries.period: ENUM → VARCHAR(4) si ancien schéma
    $stmt = $db->prepare("
        SELECT COLUMN_TYPE FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'entries' AND column_name = 'period'
    ");
    $stmt->execute();
    $type = (string)($stmt->fetchColumn() ?: '');
    if (stripos($type, 'enum') === 0) {
        $db->exec("ALTER TABLE entries MODIFY COLUMN period VARCHAR(4) NOT NULL");
    }

    // 3b) entries.user_id (nullable d'abord pour backfill)
    if (!schema_has_column($db, 'entries', 'user_id')) {
        $db->exec("ALTER TABLE entries ADD COLUMN user_id INT UNSIGNED NULL AFTER id");
    }

    // 4) project_members
    $db->exec("
        CREATE TABLE IF NOT EXISTS project_members (
            project_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            role ENUM('admin','member') NOT NULL DEFAULT 'member',
            added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (project_id, user_id),
            KEY idx_user (user_id),
            CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            CONSTRAINT fk_pm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 5) Backfill legacy single-user install depuis config.php
    $usersCount = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($usersCount === 0 && defined('CONFIG_PATH') && file_exists(CONFIG_PATH)) {
        $cfg = @include CONFIG_PATH;
        if (is_array($cfg) && !empty($cfg['username']) && !empty($cfg['password_hash'])) {
            $ins = $db->prepare("
                INSERT INTO users (username, password_hash, is_app_admin, slot_mode)
                VALUES (?, ?, 1, 'hd4')
            ");
            $ins->execute([(string)$cfg['username'], (string)$cfg['password_hash']]);
            $usersCount = 1;
        }
    }

    // 6) Backfill entries.user_id + projects.created_by + project_members
    //    vers l'user #1 (l'admin legacy ou le premier créé par setup).
    if ($usersCount > 0) {
        $firstUserId = (int)$db->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($firstUserId > 0) {
            $db->exec("UPDATE entries SET user_id = " . $firstUserId . " WHERE user_id IS NULL");
            $db->exec("UPDATE projects SET created_by = " . $firstUserId . " WHERE created_by IS NULL");
            // Ajoute l'user #1 comme admin de chaque projet sans membre
            $db->exec("
                INSERT IGNORE INTO project_members (project_id, user_id, role)
                SELECT p.id, " . $firstUserId . ", 'admin'
                FROM projects p
                LEFT JOIN project_members pm ON pm.project_id = p.id
                WHERE pm.project_id IS NULL
            ");
        }
    }

    // 7) entries.user_id NOT NULL + FK + UNIQUE (user,date,period)
    // (ces ALTER sont no-op si déjà en place)
    // Forcer NOT NULL uniquement si toutes les lignes ont une valeur
    $nullLeft = (int)$db->query("SELECT COUNT(*) FROM entries WHERE user_id IS NULL")->fetchColumn();
    if ($nullLeft === 0) {
        // MODIFY idempotent : si déjà NOT NULL, ré-applique sans effet
        $db->exec("ALTER TABLE entries MODIFY COLUMN user_id INT UNSIGNED NOT NULL");
    }
    if (schema_has_index($db, 'entries', 'uq_date_period')) {
        $db->exec("ALTER TABLE entries DROP INDEX uq_date_period");
    }
    if (!schema_has_index($db, 'entries', 'uq_user_date_period')) {
        $db->exec("ALTER TABLE entries ADD UNIQUE KEY uq_user_date_period (user_id, date, period)");
    }
    if (!schema_has_index($db, 'entries', 'idx_entries_user')) {
        $db->exec("ALTER TABLE entries ADD KEY idx_entries_user (user_id)");
    }
    if (!schema_has_fk($db, 'entries', 'fk_entries_user')) {
        $db->exec("ALTER TABLE entries ADD CONSTRAINT fk_entries_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
    }
}

/* ================================================================
 * Users
 * ================================================================ */
function get_user_by_username(PDO $db, string $username): ?array {
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_user(PDO $db, int $id): ?array {
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_users(PDO $db, bool $includeArchived = false): array {
    $sql = 'SELECT id, username, is_app_admin, slot_mode, archived, created_at FROM users'
        . ($includeArchived ? '' : ' WHERE archived = 0')
        . ' ORDER BY archived, username';
    return $db->query($sql)->fetchAll();
}

function create_user(PDO $db, string $username, string $passwordHash, bool $isAppAdmin, string $slotMode): int {
    $stmt = $db->prepare('INSERT INTO users (username, password_hash, is_app_admin, slot_mode) VALUES (?, ?, ?, ?)');
    $stmt->execute([$username, $passwordHash, $isAppAdmin ? 1 : 0, $slotMode]);
    return (int)$db->lastInsertId();
}

function update_user_password(PDO $db, int $userId, string $newHash): void {
    $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([$newHash, $userId]);
}

function update_user_slot_mode(PDO $db, int $userId, string $slotMode): void {
    $stmt = $db->prepare('UPDATE users SET slot_mode = ? WHERE id = ?');
    $stmt->execute([$slotMode, $userId]);
}

function set_user_archived(PDO $db, int $userId, bool $archived): void {
    $stmt = $db->prepare('UPDATE users SET archived = ? WHERE id = ?');
    $stmt->execute([$archived ? 1 : 0, $userId]);
}

function app_admin_count(PDO $db): int {
    return (int)$db->query('SELECT COUNT(*) FROM users WHERE is_app_admin = 1 AND archived = 0')->fetchColumn();
}

/* ================================================================
 * Projects (scoped par user courant via project_members)
 * ================================================================ */

/** Liste des projets dont l'user est membre (ordre : actifs par nom, puis archivés). */
function get_projects_for_user(PDO $db, int $userId, bool $includeArchived = false): array {
    $sql = '
        SELECT p.*, pm.role AS my_role
        FROM projects p
        JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = ?
    ' . ($includeArchived ? '' : ' WHERE p.archived = 0') . '
        ORDER BY p.archived, p.name
    ';
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function get_project(PDO $db, int $id): ?array {
    $stmt = $db->prepare('SELECT * FROM projects WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_project(PDO $db, string $name, string $color, int $createdBy): int {
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO projects (name, color, created_by) VALUES (?, ?, ?)');
        $stmt->execute([$name, $color, $createdBy]);
        $id = (int)$db->lastInsertId();
        // Créateur = admin automatique
        $stmt = $db->prepare("INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, 'admin')");
        $stmt->execute([$id, $createdBy]);
        $db->commit();
        return $id;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function update_project(PDO $db, int $id, string $name, string $color, bool $archived): void {
    $stmt = $db->prepare('UPDATE projects SET name = ?, color = ?, archived = ? WHERE id = ?');
    $stmt->execute([$name, $color, $archived ? 1 : 0, $id]);
}

/* ================================================================
 * Project members
 * ================================================================ */
function get_project_members(PDO $db, int $projectId): array {
    $stmt = $db->prepare('
        SELECT u.id, u.username, u.slot_mode, u.archived, pm.role
        FROM project_members pm
        JOIN users u ON u.id = pm.user_id
        WHERE pm.project_id = ?
        ORDER BY pm.role ASC, u.username ASC
    ');
    $stmt->execute([$projectId]);
    return $stmt->fetchAll();
}

function get_project_member_role(PDO $db, int $projectId, int $userId): ?string {
    $stmt = $db->prepare('SELECT role FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$projectId, $userId]);
    $r = $stmt->fetchColumn();
    return $r === false ? null : (string)$r;
}

function user_is_project_member(PDO $db, int $projectId, int $userId): bool {
    return get_project_member_role($db, $projectId, $userId) !== null;
}

function user_is_project_admin(PDO $db, int $projectId, int $userId): bool {
    return get_project_member_role($db, $projectId, $userId) === 'admin';
}

function add_project_member(PDO $db, int $projectId, int $userId, string $role): void {
    if (!in_array($role, ['admin','member'], true)) $role = 'member';
    $stmt = $db->prepare('INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, ?)
                          ON DUPLICATE KEY UPDATE role = VALUES(role)');
    $stmt->execute([$projectId, $userId, $role]);
}

function remove_project_member(PDO $db, int $projectId, int $userId): void {
    $stmt = $db->prepare('DELETE FROM project_members WHERE project_id = ? AND user_id = ?');
    $stmt->execute([$projectId, $userId]);
}

function set_project_member_role(PDO $db, int $projectId, int $userId, string $role): void {
    if (!in_array($role, ['admin','member'], true)) return;
    $stmt = $db->prepare('UPDATE project_members SET role = ? WHERE project_id = ? AND user_id = ?');
    $stmt->execute([$role, $projectId, $userId]);
}

function project_admin_count(PDO $db, int $projectId): int {
    $stmt = $db->prepare("SELECT COUNT(*) FROM project_members WHERE project_id = ? AND role = 'admin'");
    $stmt->execute([$projectId]);
    return (int)$stmt->fetchColumn();
}

/* ================================================================
 * Entries (scoped par user courant)
 * ================================================================ */

/** Entrées d'un user pour une plage de dates, jointes au projet. */
function get_entries_between(PDO $db, int $userId, string $from, string $to): array {
    $stmt = $db->prepare('
        SELECT e.id, e.user_id, e.date, e.period, e.project_id, e.note, e.updated_at,
               p.name AS project_name, p.color AS project_color
        FROM entries e
        JOIN projects p ON p.id = e.project_id
        WHERE e.user_id = ? AND e.date BETWEEN ? AND ?
        ORDER BY e.date, e.period
    ');
    $stmt->execute([$userId, $from, $to]);
    return $stmt->fetchAll();
}

/** Entrées de *tous les membres* d'un projet pour une plage. */
function get_project_entries_between(PDO $db, int $projectId, string $from, string $to): array {
    $stmt = $db->prepare('
        SELECT e.id, e.user_id, e.date, e.period, e.project_id, e.note, e.updated_at,
               u.username, u.slot_mode,
               p.name AS project_name, p.color AS project_color
        FROM entries e
        JOIN users u ON u.id = e.user_id
        JOIN projects p ON p.id = e.project_id
        WHERE e.project_id = ? AND e.date BETWEEN ? AND ?
        ORDER BY e.date, e.period, u.username
    ');
    $stmt->execute([$projectId, $from, $to]);
    return $stmt->fetchAll();
}

function set_entry(PDO $db, int $userId, string $date, string $period, ?int $projectId, ?string $note = null): void {
    if ($projectId === null) {
        $stmt = $db->prepare('DELETE FROM entries WHERE user_id = ? AND date = ? AND period = ?');
        $stmt->execute([$userId, $date, $period]);
        return;
    }
    $noteValue = ($note !== null && $note !== '') ? $note : null;
    $db->beginTransaction();
    try {
        $del = $db->prepare('DELETE FROM entries WHERE user_id = ? AND date = ? AND period = ?');
        $del->execute([$userId, $date, $period]);
        $ins = $db->prepare('INSERT INTO entries (user_id, date, period, project_id, note) VALUES (?, ?, ?, ?, ?)');
        $ins->execute([$userId, $date, $period, $projectId, $noteValue]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

/** Nb saisies par projet, SCOPÉ à un user (pour la page Projets). */
function project_entry_counts_for_user(PDO $db, int $userId): array {
    $stmt = $db->prepare('SELECT project_id, COUNT(*) AS c FROM entries WHERE user_id = ? GROUP BY project_id');
    $stmt->execute([$userId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(int)$row['project_id']] = (int)$row['c'];
    }
    return $out;
}

/** Résumé par projet (totalisation en HEURES, dérivées du code de période). */
function summary_between_for_user(PDO $db, int $userId, string $from, string $to): array {
    // Tous les codes connus avec leur durée en minutes
    $allCodes = [];
    foreach (slot_modes() as $cfg) {
        foreach ($cfg['codes'] as $code) {
            $allCodes[$code] = (int)$cfg['minutes'];
        }
    }
    // SQL CASE pour projeter minutes
    $caseParts = [];
    foreach ($allCodes as $code => $min) {
        $caseParts[] = "WHEN '" . $code . "' THEN " . $min;
    }
    $caseSql = count($caseParts)
        ? 'CASE e.period ' . implode(' ', $caseParts) . ' ELSE 0 END'
        : '0';

    $stmt = $db->prepare("
        SELECT p.id, p.name, p.color,
               COUNT(e.id) AS entry_count,
               COALESCE(SUM($caseSql), 0) AS total_minutes
        FROM projects p
        JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = ?
        LEFT JOIN entries e ON e.project_id = p.id AND e.user_id = ? AND e.date BETWEEN ? AND ?
        GROUP BY p.id, p.name, p.color
        HAVING entry_count > 0
        ORDER BY total_minutes DESC, p.name
    ");
    $stmt->execute([$userId, $userId, $from, $to]);
    return $stmt->fetchAll();
}

/** Résumé d'un projet (totalisation en HEURES), breakdown par membre. */
function project_summary_by_member(PDO $db, int $projectId, string $from, string $to): array {
    $allCodes = [];
    foreach (slot_modes() as $cfg) {
        foreach ($cfg['codes'] as $code) {
            $allCodes[$code] = (int)$cfg['minutes'];
        }
    }
    $caseParts = [];
    foreach ($allCodes as $code => $min) {
        $caseParts[] = "WHEN '" . $code . "' THEN " . $min;
    }
    $caseSql = count($caseParts)
        ? 'CASE e.period ' . implode(' ', $caseParts) . ' ELSE 0 END'
        : '0';

    $stmt = $db->prepare("
        SELECT u.id AS user_id, u.username, u.slot_mode, u.archived,
               COUNT(e.id) AS entry_count,
               COALESCE(SUM($caseSql), 0) AS total_minutes
        FROM project_members pm
        JOIN users u ON u.id = pm.user_id
        LEFT JOIN entries e ON e.project_id = pm.project_id AND e.user_id = u.id AND e.date BETWEEN ? AND ?
        WHERE pm.project_id = ?
        GROUP BY u.id, u.username, u.slot_mode, u.archived
        ORDER BY total_minutes DESC, u.username
    ");
    $stmt->execute([$from, $to, $projectId]);
    return $stmt->fetchAll();
}
