<?php
function api_dispatch(string $action, PDO $db): void {
    try {
        $me = current_user_row($db);
        if (!$me) {
            http_response_code(401);
            echo json_encode(['error' => 'unauthorized']);
            return;
        }
        $uid = (int)$me['id'];
        $slotMode = (string)$me['slot_mode'];

        switch ($action) {
            case 'api_entries':
                $from = $_GET['from'] ?? '';
                $to = $_GET['to'] ?? '';
                if (!valid_date($from) || !valid_date($to)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid date range']);
                    return;
                }
                echo json_encode(['entries' => get_entries_between($db, $uid, $from, $to)]);
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
                $note = isset($data['note']) ? sanitize_note((string)$data['note']) : null;
                // Période doit être valide pour le mode courant de l'user
                if (!valid_date($date) || !valid_period($period, $slotMode)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid params']);
                    return;
                }
                if ($projectId !== null) {
                    if (!get_project($db, $projectId)) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Project not found']);
                        return;
                    }
                    // Doit être membre du projet pour y rattacher une entrée
                    if (!user_is_project_member($db, $projectId, $uid)) {
                        http_response_code(403);
                        echo json_encode(['error' => 'Not a member of this project']);
                        return;
                    }
                }
                set_entry($db, $uid, $date, $period, $projectId, $note);
                echo json_encode(['ok' => true]);
                return;
        }
        http_response_code(404);
        echo json_encode(['error' => 'Unknown action']);
    } catch (Throwable $e) {
        error_log('LBTT API error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal error']);
    }
}
