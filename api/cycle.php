<?php
require_once __DIR__ . '/../includes/functions.php';
require_auth(true);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';
$pdo = get_db();

try {
    switch ($action) {

        case 'start': {
            $subjectId  = (int)($input['subject_id'] ?? 0);
            $materialId = !empty($input['material_id']) ? (int)$input['material_id'] : null;
            if (!$subjectId) json_out(['error' => 'Pick a subject first.'], 422);
            $subjectCheck = $pdo->prepare('SELECT id FROM subjects WHERE id = ? AND user_id = ?');
            $subjectCheck->execute([$subjectId, current_user_id()]);
            if (!$subjectCheck->fetchColumn()) json_out(['error' => 'Subject not found.'], 404);
            if ($materialId) {
                $materialCheck = $pdo->prepare('SELECT id FROM materials WHERE id = ? AND subject_id = ? AND user_id = ?');
                $materialCheck->execute([$materialId, $subjectId, current_user_id()]);
                if (!$materialCheck->fetchColumn()) json_out(['error' => 'Material not found.'], 404);
            }

            $existing = get_active_cycle();
            if ($existing) json_out(['error' => 'A cycle is already in progress.'], 409);

            $stmt = $pdo->prepare(
                'INSERT INTO cycles (user_id, subject_id, material_id, cycle_date, study_start, planned_study_minutes, planned_revision_minutes)
                 VALUES (?, ?, ?, CURDATE(), NOW(), ?, ?)'
            );
            $planStudy = max(1, (int)($input['planned_study_minutes'] ?? 90));
            $planRev   = max(1, (int)($input['planned_revision_minutes'] ?? 30));
            $stmt->execute([current_user_id(), $subjectId, $materialId, $planStudy, $planRev]);

            json_out(['cycle_id' => (int)$pdo->lastInsertId()]);
        }

        case 'finish_study': {
            $cycle = get_active_cycle();
            if (!$cycle || $cycle['study_end']) json_out(['error' => 'No study phase in progress.'], 409);

            $stmt = $pdo->prepare(
                "UPDATE cycles
                 SET study_end = NOW(),
                     study_minutes = GREATEST(1, TIMESTAMPDIFF(MINUTE, study_start, NOW())),
                     revision_start = NOW()
                  WHERE id = ? AND user_id = ?"
            );
              $stmt->execute([$cycle['id'], current_user_id()]);
            json_out(['ok' => true]);
        }

        case 'complete': {
            $cycle = get_active_cycle();
            if (!$cycle || !$cycle['study_end']) json_out(['error' => 'Finish the study phase first.'], 409);

            $stmt = $pdo->prepare(
                "UPDATE cycles
                 SET revision_end = NOW(),
                     revision_minutes = GREATEST(1, TIMESTAMPDIFF(MINUTE, revision_start, NOW())),
                     status = 'completed'
                  WHERE id = ? AND user_id = ?"
            );
              $stmt->execute([$cycle['id'], current_user_id()]);
            json_out(['ok' => true]);
        }

        case 'abandon': {
            $cycle = get_active_cycle();
            if (!$cycle) json_out(['error' => 'Nothing to abandon.'], 409);
            $stmt = $pdo->prepare("UPDATE cycles SET status = 'abandoned' WHERE id = ? AND user_id = ?");
            $stmt->execute([$cycle['id'], current_user_id()]);
            json_out(['ok' => true]);
        }

        case 'status': {
            json_out(['active' => get_active_cycle()]);
        }

        case 'manual_add': {
            $subjectId  = (int)($input['subject_id'] ?? 0);
            $materialId = !empty($input['material_id']) ? (int)$input['material_id'] : null;
            if (!$subjectId) json_out(['error' => 'Pick a subject first.'], 422);
            $subjectCheck = $pdo->prepare('SELECT id FROM subjects WHERE id = ? AND user_id = ?');
            $subjectCheck->execute([$subjectId, current_user_id()]);
            if (!$subjectCheck->fetchColumn()) json_out(['error' => 'Subject not found.'], 404);
            if ($materialId) {
                $materialCheck = $pdo->prepare('SELECT id FROM materials WHERE id = ? AND subject_id = ? AND user_id = ?');
                $materialCheck->execute([$materialId, $subjectId, current_user_id()]);
                if (!$materialCheck->fetchColumn()) json_out(['error' => 'Material not found.'], 404);
            }

            $date = trim($input['cycle_date'] ?? '');
            $d = DateTime::createFromFormat('Y-m-d', $date);
            if (!$d || $d->format('Y-m-d') !== $date) json_out(['error' => 'Enter a valid date.'], 422);
            if ($date > date('Y-m-d')) json_out(['error' => 'Cannot log a session in the future.'], 422);

            $studyMinutes    = max(0, min(1440, (int)($input['study_minutes'] ?? 0)));
            $revisionMinutes = max(0, min(1440, (int)($input['revision_minutes'] ?? 0)));
            if ($studyMinutes === 0 && $revisionMinutes === 0) {
                json_out(['error' => 'Enter at least some study or revision minutes.'], 422);
            }

            $status = in_array($input['status'] ?? '', ['completed', 'abandoned'], true) ? $input['status'] : 'completed';

            $stmt = $pdo->prepare(
                'INSERT INTO cycles
                    (user_id, subject_id, material_id, cycle_date, study_minutes, revision_minutes,
                     planned_study_minutes, planned_revision_minutes, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)' 
            );
            $stmt->execute([
                current_user_id(), $subjectId, $materialId, $date, $studyMinutes, $revisionMinutes,
                max(1, $studyMinutes), max(1, $revisionMinutes), $status,
                // For today, use the real timestamp so live-refresh and
                // ORDER BY created_at both work correctly.
                // For past dates, backdate to noon so neglect/subject stats
                // reflect the actual historical day rather than today.
                $date === date('Y-m-d') ? date('Y-m-d H:i:s') : ($date . ' 12:00:00'),
            ]);

            json_out(['cycle_id' => (int)$pdo->lastInsertId()]);
        }

        case 'delete_unassigned': {
            $subjectId = (int)($input['subject_id'] ?? 0);
            if (!$subjectId) json_out(['error' => 'Missing subject id.'], 422);
            $subjectCheck = $pdo->prepare('SELECT id FROM subjects WHERE id = ? AND user_id = ?');
            $subjectCheck->execute([$subjectId, current_user_id()]);
            if (!$subjectCheck->fetchColumn()) json_out(['error' => 'Subject not found.'], 404);

            $stmt = $pdo->prepare('DELETE FROM cycles WHERE subject_id = ? AND material_id IS NULL AND user_id = ?');
            $stmt->execute([$subjectId, current_user_id()]);
            json_out(['ok' => true, 'deleted' => $stmt->rowCount()]);
        }

        default:
            json_out(['error' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    json_out(['error' => $e->getMessage()], 500);
}
