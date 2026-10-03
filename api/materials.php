<?php
require_once __DIR__ . '/../includes/functions.php';
require_auth(true);

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$method = $_SERVER['REQUEST_METHOD'];
$pdo    = get_db();

$validStatuses = ['not_started', 'in_progress', 'learned', 'mastered'];

try {
    if ($method === 'POST') {
        $action = $input['action'] ?? 'create';

        if ($action === 'create') {
            $subjectId = (int)($input['subject_id'] ?? 0);
            $title     = trim($input['title'] ?? '');
            $difficulty = max(1, min(5, (int)($input['difficulty'] ?? 3)));
            if (!$subjectId || $title === '') json_out(['error' => 'Subject and title are required.'], 422);
            $subjectCheck = $pdo->prepare('SELECT id FROM subjects WHERE id = ? AND user_id = ?');
            $subjectCheck->execute([$subjectId, current_user_id()]);
            if (!$subjectCheck->fetchColumn()) json_out(['error' => 'Subject not found.'], 404);

            $stmt = $pdo->prepare('INSERT INTO materials (user_id, subject_id, title, difficulty) VALUES (?, ?, ?, ?)');
            $stmt->execute([current_user_id(), $subjectId, $title, $difficulty]);
            json_out(['id' => (int)$pdo->lastInsertId()]);
        }

        if ($action === 'update_status') {
            $id = (int)($input['id'] ?? 0);
            $status = $input['status'] ?? '';
            if (!in_array($status, $validStatuses, true)) json_out(['error' => 'Invalid status.'], 422);
            $stmt = $pdo->prepare('UPDATE materials SET status = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$status, $id, current_user_id()]);
            json_out(['ok' => true]);
        }

        if ($action === 'update') {
            $id = (int)($input['id'] ?? 0);
            $title = trim($input['title'] ?? '');
            $difficulty = max(1, min(5, (int)($input['difficulty'] ?? 3)));
            $stmt = $pdo->prepare('UPDATE materials SET title = ?, difficulty = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$title, $difficulty, $id, current_user_id()]);
            json_out(['ok' => true]);
        }

        if ($action === 'delete') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) json_out(['error' => 'Missing material id.'], 422);

            $pdo->beginTransaction();
            $cycleStmt = $pdo->prepare('DELETE FROM cycles WHERE material_id = ? AND user_id = ?');
            $cycleStmt->execute([$id, current_user_id()]);
            $materialStmt = $pdo->prepare('DELETE FROM materials WHERE id = ? AND user_id = ?');
            $materialStmt->execute([$id, current_user_id()]);
            if ($materialStmt->rowCount() !== 1) {
                $pdo->rollBack();
                json_out(['error' => 'Material not found.'], 404);
            }
            $pdo->commit();
            json_out(['ok' => true]);
        }

        json_out(['error' => 'Unknown action.'], 400);
    }

    $subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : null;

    if (!empty($_GET['unassigned'])) {
        if (!$subjectId) json_out(['error' => 'Missing subject id.'], 422);
        json_out(get_unassigned_material_time($subjectId));
    }

    json_out(get_materials($subjectId));
} catch (Throwable $e) {
    json_out(['error' => $e->getMessage()], 500);
}
