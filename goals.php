<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle    = 'Goals';
$pageSubtitle = 'Set a target, watch the dashboard track it';
$activeNav    = 'goals';
$daily        = get_active_goal('daily');
$weekly       = get_active_goal('weekly');
$today        = get_today_stats();
$extraScripts = ['assets/js/goals.js'];

require __DIR__ . '/includes/header.php';
?>

<div class="grid grid-2">

  <div class="panel">
    <div class="panel-head"><h2>Daily goal</h2></div>
    <?php if ($daily): ?>
      <div class="row-sub" style="margin-bottom:8px;"><?= (int)$today['completed_cycles'] ?> of <?= (int)$daily['target_cycles'] ?> cycles today</div>
      <div class="bar-track"><div class="bar-fill" style="width:<?= min(100, round($today['completed_cycles']/$daily['target_cycles']*100)) ?>%;"></div></div>
      <?php if ($daily['target_minutes']): ?>
        <div class="row-sub" style="margin:16px 0 8px;"><?= minutes_to_hm((int)$today['total_minutes']) ?> of <?= minutes_to_hm((int)$daily['target_minutes']) ?></div>
        <div class="bar-track"><div class="bar-fill teal" style="width:<?= min(100, round($today['total_minutes']/$daily['target_minutes']*100)) ?>%;"></div></div>
      <?php endif; ?>
      <button class="btn btn-ghost mt-4" data-deactivate="<?= $daily['id'] ?>">Clear this goal</button>
    <?php else: ?>
      <div class="empty"><p>No daily goal set.</p></div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Weekly goal</h2></div>
    <?php if ($weekly): ?>
      <div class="row-sub" style="margin-bottom:8px;">Target: <?= (int)$weekly['target_cycles'] ?> cycles this week<?= $weekly['target_minutes'] ? ' · ' . minutes_to_hm((int)$weekly['target_minutes']) : '' ?></div>
      <p class="muted" style="font-size:12.5px;">Weekly totals roll up from the daily stats table automatically.</p>
      <button class="btn btn-ghost mt-4" data-deactivate="<?= $weekly['id'] ?>">Clear this goal</button>
    <?php else: ?>
      <div class="empty"><p>No weekly goal set.</p></div>
    <?php endif; ?>
  </div>
</div>

<div class="panel mt-4" style="max-width:480px;">
  <div class="panel-head"><h2>Set a goal</h2></div>
  <form id="goal-form">
    <div class="field">
      <label for="goal_type">Type</label>
      <select id="goal_type">
        <option value="daily">Daily</option>
        <option value="weekly">Weekly</option>
      </select>
    </div>
    <div class="grid grid-2">
      <div class="field">
        <label for="goal_cycles">Target cycles</label>
        <input type="number" id="goal_cycles" min="1" value="4">
      </div>
      <div class="field">
        <label for="goal_minutes">Target minutes <span class="muted" style="font-weight:400;">(optional)</span></label>
        <input type="number" id="goal_minutes" min="0" placeholder="e.g. 480">
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Save goal</button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
