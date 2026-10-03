<?php
require_once __DIR__ . '/../includes/functions.php';
require_auth(true);

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$method = $_SERVER['REQUEST_METHOD'];
$pdo    = get_db();

try {
    if ($method === 'POST') {
        $action = $input['action'] ?? 'create';

        if ($action === 'create') {
            $name  = trim($input['name'] ?? '');
            $color = $input['color_hex'] ?? '#E8A33D';
            if ($name === '') json_out(['error' => 'Subject name is required.'], 422);

            $stmt = $pdo->prepare('INSERT INTO subjects (user_id, name, color_hex) VALUES (?, ?, ?)');
            $stmt->execute([current_user_id(), $name, $color]);
            json_out(['id' => (int)$pdo->lastInsertId()]);
        }

        if ($action === 'update') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) json_out(['error' => 'Missing subject id.'], 422);
            $stmt = $pdo->prepare('UPDATE subjects SET name = ?, color_hex = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([trim($input['name'] ?? ''), $input['color_hex'] ?? '#E8A33D', $id, current_user_id()]);
            json_out(['ok' => true]);
        }

        if ($action === 'archive') {
            $id = (int)($input['id'] ?? 0);
            $archived = !empty($input['archived']) ? 1 : 0;
            $stmt = $pdo->prepare('UPDATE subjects SET is_archived = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$archived, $id, current_user_id()]);
            json_out(['ok' => true]);
        }

        if ($action === 'delete') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) json_out(['error' => 'Missing subject id.'], 422);

            $pdo->beginTransaction();
            // No FK cascades exist in the schema, so cycles and materials
            // tied to this subject must be removed explicitly or they become
            // orphaned rows that stats keep counting forever.
            $cycleStmt = $pdo->prepare('DELETE FROM cycles WHERE subject_id = ? AND user_id = ?');
            $cycleStmt->execute([$id, current_user_id()]);
            $materialStmt = $pdo->prepare('DELETE FROM materials WHERE subject_id = ? AND user_id = ?');
            $materialStmt->execute([$id, current_user_id()]);
            $subjectStmt = $pdo->prepare('DELETE FROM subjects WHERE id = ? AND user_id = ?');
            $subjectStmt->execute([$id, current_user_id()]);
            if ($subjectStmt->rowCount() !== 1) {
                $pdo->rollBack();
                json_out(['error' => 'Subject not found.'], 404);
            }
            $pdo->commit();
            json_out(['ok' => true]);
        }

        json_out(['error' => 'Unknown action.'], 400);
    }

    json_out(get_subjects(true));
} catch (Throwable $e) {
    json_out(['error' => $e->getMessage()], 500);
}
