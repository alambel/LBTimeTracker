<?php
function api_dispatch(string $action, PDO $db): void {
    try {
        switch ($action) {
            case 'api_entries':
                $from = $_GET['from'] ?? '';
                $to = $_GET['to'] ?? '';
                if (!valid_date($from) || !valid_date($to)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid date range']);
                    return;
                }
                echo json_encode(['entries' => get_entries_between($db, $from, $to)]);
                return;

            case 'api_save_entry':
                $raw = file_get_contents('php://input');
                $data = $raw ? json_decode($raw, true) : null;
                if (!is_array($data)) { $data = $_POST; }
                $date = (string)($data['date'] ?? '');
                $period = (string)($data['period'] ?? '');
                $projectId = null;
                if (isset($data['project_id']) && $data['project_id'] !== null && $data['project_id'] !== '') {
                    $projectId = (int)$data['project_id'];
                }
                $note = isset($data['note']) ? trim((string)$data['note']) : null;
                if (!valid_date($date) || !valid_period($period)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid params']);
                    return;
                }
                if ($projectId !== null && !get_project($db, $projectId)) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Project not found']);
                    return;
                }
                set_entry($db, $date, $period, $projectId, $note);
                echo json_encode(['ok' => true]);
                return;

            case 'api_batch_save':
                $raw = file_get_contents('php://input');
                $data = $raw ? json_decode($raw, true) : null;
                if (!is_array($data)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid payload']);
                    return;
                }
                $targets = $data['targets'] ?? [];
                if (!is_array($targets) || empty($targets) || count($targets) > 200) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid targets']);
                    return;
                }
                $projectId = null;
                if (isset($data['project_id']) && $data['project_id'] !== null && $data['project_id'] !== '') {
                    $projectId = (int)$data['project_id'];
                }
                $note = isset($data['note']) ? trim((string)$data['note']) : null;
                if ($projectId !== null && !get_project($db, $projectId)) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Project not found']);
                    return;
                }
                batch_save_entries($db, $targets, $projectId, $note);
                echo json_encode(['ok' => true, 'count' => count($targets)]);
                return;
        }
        http_response_code(404);
        echo json_encode(['error' => 'Unknown action']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
