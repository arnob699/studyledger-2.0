<?php
require_once __DIR__ . '/../includes/functions.php';
require_auth(true);

$pdo = get_db();
$userId = current_user_id();

try {
    $pdo->beginTransaction();

    // Cycles left behind by a subject that was deleted under the old code
    // (only this user's rows — never touches other accounts' data).
    $cycleStmt = $pdo->prepare(
        'DELETE c FROM cycles c
         LEFT JOIN subjects s ON s.id = c.subject_id AND s.user_id = c.user_id
         WHERE c.user_id = ? AND s.id IS NULL'
    );
    $cycleStmt->execute([$userId]);
    $deletedCycles = $cycleStmt->rowCount();

    // Materials left behind the same way.
    $materialStmt = $pdo->prepare(
        'DELETE m FROM materials m
         LEFT JOIN subjects s ON s.id = m.subject_id AND s.user_id = m.user_id
         WHERE m.user_id = ? AND s.id IS NULL'
    );
    $materialStmt->execute([$userId]);
    $deletedMaterials = $materialStmt->rowCount();

    // Make sure this account has an app_settings row (upsert in
    // api/settings.php handles this going forward too).
    $settingsStmt = $pdo->prepare(
        "INSERT INTO app_settings (user_id, theme, default_study_minutes, default_revision_minutes, timezone)
         SELECT ?, 'light', 90, 30, 'Asia/Dhaka'
         WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE user_id = ?)"
    );
    $settingsStmt->execute([$userId, $userId]);
    $createdSettings = $settingsStmt->rowCount();

    $pdo->commit();

    json_out([
        'ok' => true,
        'deleted_cycles' => $deletedCycles,
        'deleted_materials' => $deletedMaterials,
        'created_settings' => $createdSettings,
    ]);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_out(['error' => $e->getMessage()], 500);
}
