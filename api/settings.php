<?php
require_once __DIR__ . '/../includes/functions.php';
require_auth(true);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$pdo   = get_db();
$current = get_app_settings();

$theme    = in_array($input['theme'] ?? null, ['dark', 'light'], true) ? $input['theme'] : $current['theme'];
$study    = isset($input['default_study_minutes']) ? max(5, (int)$input['default_study_minutes']) : $current['default_study_minutes'];
$revision = isset($input['default_revision_minutes']) ? max(5, (int)$input['default_revision_minutes']) : $current['default_revision_minutes'];
$timezone = isset($input['timezone']) ? trim($input['timezone']) : $current['timezone'];

try {
    // Upsert instead of a plain UPDATE: if this user's app_settings row is
    // missing for any reason, a bare UPDATE matches 0 rows and silently
    // "succeeds" without saving anything. INSERT ... ON DUPLICATE KEY UPDATE
    // guarantees the row exists and the new values are always persisted.
    $stmt = $pdo->prepare(
        'INSERT INTO app_settings (user_id, theme, default_study_minutes, default_revision_minutes, timezone)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            theme = VALUES(theme),
            default_study_minutes = VALUES(default_study_minutes),
            default_revision_minutes = VALUES(default_revision_minutes),
            timezone = VALUES(timezone)'
    );
    $stmt->execute([current_user_id(), $theme, $study, $revision, $timezone ?: $current['timezone']]);
    json_out(['ok' => true]);
} catch (Throwable $e) {
    json_out(['error' => $e->getMessage()], 500);
}
