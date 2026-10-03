<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle    = 'Study cycle';
$activeNav    = 'cycle';
$active       = get_active_cycle();
$subjects     = get_subjects();
$defaults     = get_app_settings();
$extraScripts = ['assets/js/cycle.js'];

$pageSubtitle = $active ? 'A cycle is currently running' : '90 minutes of study, 30 minutes of revision';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$active): ?>

  <div class="panel" style="max-width:520px; margin: 0 auto;">
    <div class="panel-head"><h2>Start a new cycle</h2></div>

    <?php if (empty($subjects)): ?>
      <div class="empty">
        <h3>No subjects yet</h3>
        <p>Create a subject first so this cycle has something to log against.</p>
        <a href="subjects.php" class="btn btn-primary mt-4" style="display:inline-flex;">Add a subject</a>
      </div>
    <?php else: ?>
      <form id="start-form">
        <div class="field">
          <label for="subject_id">Subject</label>
          <select id="subject_id" name="subject_id" required>
            <?php foreach ($subjects as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="material_id">Material <span class="muted" style="font-weight:400;">(optional)</span></label>
          <select id="material_id" name="material_id">
            <option value="">No specific material</option>
          </select>
        </div>
        <div class="grid grid-2">
          <div class="field">
            <label for="planned_study">Study minutes</label>
            <input type="number" id="planned_study" value="<?= (int)$defaults['default_study_minutes'] ?>" min="5" max="240">
          </div>
          <div class="field">
            <label for="planned_revision">Revision minutes</label>
            <input type="number" id="planned_revision" value="<?= (int)$defaults['default_revision_minutes'] ?>" min="5" max="120">
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Start cycle</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

<?php else:
  $phase = $active['study_end'] ? 'revision' : 'study';
  $plannedSeconds = $phase === 'study'
      ? (int)$active['planned_study_minutes'] * 60
      : (int)$active['planned_revision_minutes'] * 60;
  $startedAt = $phase === 'study' ? $active['study_start'] : $active['revision_start'];
  $elapsed = time() - strtotime($startedAt);
  $remaining = max(0, $plannedSeconds - $elapsed);
?>

  <div class="panel" style="max-width:520px; margin:0 auto;">
    <div class="timer-meta">
      <span class="badge <?= $phase === 'study' ? 'badge-amber' : 'badge-teal' ?>"><span class="pulse-dot" style="<?= $phase === 'revision' ? 'background:var(--teal);' : '' ?>"></span> <?= $phase === 'study' ? 'Study phase' : 'Revision phase' ?></span>
      <h2 class="mt-4"><?= htmlspecialchars($active['subject_name']) ?></h2>
      <p><?= htmlspecialchars($active['material_title'] ?? 'General session') ?></p>
    </div>

    <div class="timer-wrap">
      <div class="timer-ring <?= $phase === 'revision' ? 'revision' : '' ?>" id="timer-ring">
        <svg viewBox="0 0 200 200" width="260" height="260">
          <circle class="track" cx="100" cy="100" r="90"></circle>
          <circle class="progress" id="ring-progress" cx="100" cy="100" r="90"
                  stroke-dasharray="565.5" stroke-dashoffset="0"></circle>
        </svg>
        <div class="timer-face">
          <div class="timer-time num" id="timer-time">--:--</div>
          <div class="timer-phase" id="timer-phase-label">remaining</div>
        </div>
      </div>

      <div class="timer-controls">
        <button class="btn btn-danger" id="btn-abandon">Abandon</button>
        <?php if ($phase === 'study'): ?>
          <button class="btn btn-primary" id="btn-advance">Finish studying</button>
        <?php else: ?>
          <button class="btn btn-teal" id="btn-advance">Complete cycle</button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    window.__CYCLE__ = {
      phase: <?= json_encode($phase) ?>,
      plannedSeconds: <?= json_encode($plannedSeconds) ?>,
      remaining: <?= json_encode($remaining) ?>,
      circumference: 565.5
    };
  </script>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
