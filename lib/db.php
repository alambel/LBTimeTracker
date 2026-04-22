<?php
function db_init(string $path): PDO {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    // Ceinture + bretelles : empêcher l'accès web direct au dossier data/
    // (complémentaire au .htaccess racine, utile si le serveur ignore le
    // parent ou si data/ est déplacé manuellement).
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Require all denied\n");
    }
    $db = new PDO('sqlite:' . $path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON');
    db_migrate($db);
    return $db;
}

function db_migrate(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            color TEXT NOT NULL DEFAULT '#4a90e2',
            archived INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $entriesSql = (string)$db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='entries'")->fetchColumn();

    if ($entriesSql === '') {
        $db->exec("
            CREATE TABLE entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                date TEXT NOT NULL,
                period TEXT NOT NULL CHECK (period IN ('AM','PM','EV','NT')),
                project_id INTEGER NOT NULL,
                note TEXT,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(date, period),
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
            )
        ");
    } elseif (strpos($entriesSql, "'EV'") === false) {
        $db->exec('BEGIN');
        try {
            $db->exec("
                CREATE TABLE entries_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    date TEXT NOT NULL,
                    period TEXT NOT NULL CHECK (period IN ('AM','PM','EV','NT')),
                    project_id INTEGER NOT NULL,
                    note TEXT,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(date, period),
                    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
                )
            ");
            $db->exec("
                INSERT INTO entries_new (id, date, period, project_id, note, updated_at)
                SELECT id, date, period, project_id, note, updated_at FROM entries
            ");
            $db->exec("DROP TABLE entries");
            $db->exec("ALTER TABLE entries_new RENAME TO entries");
            $db->exec('COMMIT');
        } catch (Throwable $e) {
            $db->exec('ROLLBACK');
            throw $e;
        }
    }

    $db->exec("CREATE INDEX IF NOT EXISTS idx_entries_date ON entries(date)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_entries_project ON entries(project_id)");
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
        SELECT e.*, p.name AS project_name, p.color AS project_color
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
    // Compatible avec SQLite < 3.24 (pas d'UPSERT "ON CONFLICT DO UPDATE")
    $noteValue = ($note !== null && $note !== '') ? $note : null;
    $db->beginTransaction();
    try {
        $del = $db->prepare('DELETE FROM entries WHERE date = ? AND period = ?');
        $del->execute([$date, $period]);
        $ins = $db->prepare('INSERT INTO entries (date, period, project_id, note, updated_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)');
        $ins->execute([$date, $period, $projectId, $noteValue]);
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
        GROUP BY p.id
        HAVING half_days > 0
        ORDER BY half_days DESC, p.name
    ");
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}
