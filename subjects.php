<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle    = 'Subjects';
$pageSubtitle = 'What you\'re studying — and how it adds up';
$activeNav    = 'subjects';
$subjects     = get_subjects(true);
$extraScripts = ['assets/js/subjects.js'];

require __DIR__ . '/includes/header.php';
?>

<div style="display:flex; justify-content:flex-end; margin-bottom: 14px;">
  <button class="btn btn-primary" id="btn-new-subject" onclick="openModal('subject-modal')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
    New subject
  </button>
</div>

<?php if (empty($subjects)): ?>
  <div class="panel empty">
    <h3>No subjects yet</h3>
    <p>Add your first subject to start logging cycles against it.</p>
  </div>
<?php else: ?>
  <div class="cards" id="subject-cards">
    <?php foreach ($subjects as $s): ?>
      <div class="card" style="--card-accent: <?= htmlspecialchars($s['color_hex']) ?>;" data-id="<?= $s['id'] ?>">
        <div class="card-top">
            <div>
            <h3><?= htmlspecialchars($s['name']) ?></h3>
            <div class="meta"><?= $s['last_studied_at'] ? 'last studied ' . friendly_date($s['last_studied_at']) : 'not studied yet' ?></div>
            </div>
          <?php if ($s['is_archived']): ?><span class="badge badge-muted">archived</span><?php endif; ?>
        </div>
        <div class="card-stats">
          <div><div class="v num"><?= (int)$s['total_cycles'] ?></div><div class="l">cycles</div></div>
          <div><div class="v num"><?= minutes_to_hm((int)$s['total_study_minutes']) ?></div><div class="l">studied</div></div>
          <div><div class="v num"><?= minutes_to_hm((int)$s['total_revision_minutes']) ?></div><div class="l">revised</div></div>
        </div>
        <div class="card-actions">
          <button class="btn btn-ghost btn-edit"
                  data-id="<?= $s['id'] ?>" data-name="<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>" data-color="<?= htmlspecialchars($s['color_hex']) ?>">
            Edit
          </button>
          <button class="btn btn-ghost btn-archive" data-id="<?= $s['id'] ?>" data-archived="<?= $s['is_archived'] ?>">
            <?= $s['is_archived'] ? 'Unarchive' : 'Archive' ?>
          </button>
          <button class="btn btn-danger btn-delete" data-id="<?= $s['id'] ?>">Delete</button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="modal-backdrop" id="subject-modal">
  <div class="modal">
    <div class="modal-header">
      <h2 id="subject-modal-title">New subject</h2>
      <button type="button" class="btn-close" onclick="closeModal('subject-modal')" aria-label="Close">&times;</button>
    </div>
    <form id="subject-form">
      <input type="hidden" id="subject_id" value="">
      <div class="field">
        <label for="subject_name">Name</label>
        <input type="text" id="subject_name" required placeholder="e.g. Operating Systems">
      </div>
      <div class="field">
        <label>Color</label>
        <div class="color-row" id="color-row">
          <?php foreach (['#E8A33D','#4FA69C','#C1666B','#7C9EF2','#B48EE0','#6FBF73'] as $c): ?>
            <span class="color-dot" style="background:<?= $c ?>;" data-color="<?= $c ?>"></span>
          <?php endforeach; ?>
        </div>
        <input type="hidden" id="subject_color" value="#E8A33D">
      </div>
      <div class="form-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModal('subject-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save subject</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
