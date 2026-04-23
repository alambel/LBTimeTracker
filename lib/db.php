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

function db_migrate(PDO $db): void {
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
    $db->exec("
        CREATE TABLE IF NOT EXISTS entries (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            period ENUM('AM','PM','EV','NT') NOT NULL,
            project_id INT UNSIGNED NOT NULL,
            note VARCHAR(255) NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_date_period (date, period),
            KEY idx_date (date),
            KEY idx_project (project_id),
            CONSTRAINT fk_entries_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function get_projects(PDO $db, bool $includeArchived = false): array {
    $sql = 'SELECT * FROM projects' . ($includeArchived ? '' : ' WHERE archived = 0') . ' ORDER BY archived, name';
    return $db->query($sql)->fetchAll();
}

function get_project(PDO $db, int $id): ?array {
    $stmt = $db->prepare('SELECT * FROM projects WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_project(PDO $db, string $name, string $color): int {
    $stmt = $db->prepare('INSERT INTO projects (name, color) VALUES (?, ?)');
    $stmt->execute([$name, $color]);
    return (int)$db->lastInsertId();
}

function update_project(PDO $db, int $id, string $name, string $color, bool $archived): void {
    $stmt = $db->prepare('UPDATE projects SET name = ?, color = ?, archived = ? WHERE id = ?');
    $stmt->execute([$name, $color, $archived ? 1 : 0, $id]);
}

function delete_project(PDO $db, int $id): void {
    $stmt = $db->prepare('DELETE FROM projects WHERE id = ?');
    $stmt->execute([$id]);
}

function get_entries_between(PDO $db, string $from, string $to): array {
    $stmt = $db->prepare('
        SELECT e.id, e.date, e.period, e.project_id, e.note, e.updated_at,
               p.name AS project_name, p.color AS project_color
        FROM entries e
        JOIN projects p ON p.id = e.project_id
        WHERE e.date BETWEEN ? AND ?
        ORDER BY e.date, e.period
    ');
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}

function set_entry(PDO $db, string $date, string $period, ?int $projectId, ?string $note = null): void {
    if ($projectId === null) {
        $stmt = $db->prepare('DELETE FROM entries WHERE date = ? AND period = ?');
        $stmt->execute([$date, $period]);
        return;
    }
    $noteValue = ($note !== null && $note !== '') ? $note : null;
    $db->beginTransaction();
    try {
        $del = $db->prepare('DELETE FROM entries WHERE date = ? AND period = ?');
        $del->execute([$date, $period]);
        $ins = $db->prepare('INSERT INTO entries (date, period, project_id, note) VALUES (?, ?, ?, ?)');
        $ins->execute([$date, $period, $projectId, $noteValue]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function project_entry_counts(PDO $db): array {
    $sql = 'SELECT project_id, COUNT(*) AS c FROM entries GROUP BY project_id';
    $out = [];
    foreach ($db->query($sql)->fetchAll() as $row) {
        $out[(int)$row['project_id']] = (int)$row['c'];
    }
    return $out;
}

function batch_save_entries(PDO $db, array $targets, ?int $projectId, ?string $note = null): void {
    $noteValue = ($note !== null && $note !== '') ? $note : null;
    $db->beginTransaction();
    try {
        $del = $db->prepare('DELETE FROM entries WHERE date = ? AND period = ?');
        $ins = $db->prepare('INSERT INTO entries (date, period, project_id, note) VALUES (?, ?, ?, ?)');
        foreach ($targets as $t) {
            $date = $t['date'] ?? '';
            $period = $t['period'] ?? '';
            if (!valid_date($date) || !valid_period($period)) continue;
            $del->execute([$date, $period]);
            if ($projectId !== null) {
                $ins->execute([$date, $period, $projectId, $noteValue]);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function summary_between(PDO $db, string $from, string $to): array {
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.color,
               COUNT(e.id) AS half_days,
               COUNT(e.id) / 2.0 AS full_days
        FROM projects p
        LEFT JOIN entries e ON e.project_id = p.id AND e.date BETWEEN ? AND ?
        GROUP BY p.id, p.name, p.color
        HAVING half_days > 0
        ORDER BY half_days DESC, p.name
    ");
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}
