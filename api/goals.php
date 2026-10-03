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
            $type   = ($input['goal_type'] ?? 'daily') === 'weekly' ? 'weekly' : 'daily';
            $cycles = max(1, (int)($input['target_cycles'] ?? 4));
            $minutes = !empty($input['target_minutes']) ? (int)$input['target_minutes'] : null;

            // deactivate any existing active goal of the same type
            $pdo->prepare("UPDATE goals SET is_active = 0 WHERE goal_type = ? AND is_active = 1 AND user_id = ?")->execute([$type, current_user_id()]);

            $stmt = $pdo->prepare(
                 'INSERT INTO goals (user_id, goal_type, target_cycles, target_minutes, start_date, is_active)
                  VALUES (?, ?, ?, ?, CURDATE(), 1)'
            );
              $stmt->execute([current_user_id(), $type, $cycles, $minutes]);
            json_out(['id' => (int)$pdo->lastInsertId()]);
        }

        if ($action === 'deactivate') {
            $id = (int)($input['id'] ?? 0);
            $pdo->prepare('UPDATE goals SET is_active = 0 WHERE id = ? AND user_id = ?')->execute([$id, current_user_id()]);
            json_out(['ok' => true]);
        }

        json_out(['error' => 'Unknown action.'], 400);
    }

    json_out([
        'daily'  => get_active_goal('daily'),
        'weekly' => get_active_goal('weekly'),
    ]);
} catch (Throwable $e) {
    json_out(['error' => $e->getMessage()], 500);
}
