<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle    = 'Materials';
$pageSubtitle = 'Chapters and topics inside each subject';
$activeNav    = 'materials';
$subjects     = get_subjects();
$materials    = get_materials();
$extraScripts = ['assets/js/materials.js'];

$statusLabels = [
    'not_started' => ['Not started', 'badge-muted'],
    'in_progress' => ['In progress', 'badge-teal'],
    'learned'     => ['Learned', 'badge-amber'],
    'mastered'    => ['Mastered', 'badge-amber'],
];

require __DIR__ . '/includes/header.php';
?>

<div style="display:flex; justify-content:flex-end; margin-bottom:14px;">
  <button class="btn btn-primary" onclick="openModal('material-modal')" <?= empty($subjects) ? 'disabled' : '' ?>>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
    New material
  </button>
</div>

<div class="panel">
  <?php if (empty($materials)): ?>
    <div class="empty">
      <h3>No materials yet</h3>
      <p><?= empty($subjects) ? 'Add a subject first, then break it into materials.' : 'Add the first chapter or topic to a subject.' ?></p>
    </div>
  <?php else: ?>
    <div class="table-scroll">
    <table>
      <thead><tr><th>Material</th><th>Subject</th><th>Time</th><th>Difficulty</th><th>Status</th><th></th></tr></thead>
      <tbody id="materials-body">
        <?php foreach ($materials as $m): [$label, $cls] = $statusLabels[$m['status']]; ?>
          <tr data-id="<?= $m['id'] ?>">
            <td data-label="Material"><?= htmlspecialchars($m['title']) ?></td>
            <td class="muted" data-label="Subject"><span class="dot" style="background:<?= htmlspecialchars($m['color_hex']) ?>; display:inline-block; margin-right:8px;"></span><?= htmlspecialchars($m['subject_name']) ?></td>
            <td class="muted" data-label="Time"><span class="num"><?= (int)$m['total_cycles'] ?> cycles</span> · <?= minutes_to_hm((int)$m['total_minutes']) ?></td>
            <td class="muted" data-label="Difficulty"><?= str_repeat('●', (int)$m['difficulty']) . str_repeat('○', 5 - (int)$m['difficulty']) ?></td>
            <td data-label="Status">
              <select class="status-select" data-id="<?= $m['id'] ?>" style="padding:4px 8px; font-size:12.5px;">
                <?php foreach ($statusLabels as $val => [$lbl,]): ?>
                  <option value="<?= $val ?>" <?= $m['status'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td data-label=""><button class="btn btn-ghost btn-delete-material" data-id="<?= $m['id'] ?>">Delete</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>

<div class="modal-backdrop" id="material-modal">
  <div class="modal">
    <div class="modal-header">
      <h2>New material</h2>
      <button type="button" class="btn-close" onclick="closeModal('material-modal')" aria-label="Close">&times;</button>
    </div>
    <form id="material-form">
      <div class="field">
        <label for="m_subject">Subject</label>
        <select id="m_subject" required>
          <?php foreach ($subjects as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="m_title">Title</label>
        <input type="text" id="m_title" required placeholder="e.g. Chapter 4 — Memory management">
      </div>
      <div class="field">
        <label for="m_difficulty">Difficulty (1 easy – 5 hard)</label>
        <input type="number" id="m_difficulty" min="1" max="5" value="3">
      </div>
      <div class="form-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModal('material-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save material</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
