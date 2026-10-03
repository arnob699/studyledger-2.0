<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle    = 'Settings';
$pageSubtitle = 'Defaults for new cycles and how the app looks';
$activeNav    = 'settings';
$settings     = get_app_settings();
$extraScripts = ['assets/js/settings.js'];

require __DIR__ . '/includes/header.php';
?>

<div class="panel" style="max-width:480px;">
  <div class="panel-head"><h2>Appearance</h2></div>
  <div class="field">
    <label>Theme</label>
    <div style="display:flex; gap:10px;">
      <button type="button" class="btn <?= $settings['theme'] === 'light' ? 'btn-primary' : '' ?>" data-theme-btn="light">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
        Light mode
      </button>
      <button type="button" class="btn <?= $settings['theme'] === 'dark' ? 'btn-primary' : '' ?>" data-theme-btn="dark">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
        Dark mode
      </button>
    </div>
  </div>
</div>

<div class="panel mt-4" style="max-width:480px;">
  <div class="panel-head"><h2>Cycle defaults</h2></div>
  <form id="defaults-form">
    <div class="grid grid-2">
      <div class="field">
        <label for="default_study">Study minutes</label>
        <input type="number" id="default_study" min="5" max="240" value="<?= (int)$settings['default_study_minutes'] ?>">
      </div>
      <div class="field">
        <label for="default_revision">Revision minutes</label>
        <input type="number" id="default_revision" min="5" max="120" value="<?= (int)$settings['default_revision_minutes'] ?>">
      </div>
    </div>
    <div class="field">
      <label for="timezone">Timezone</label>
      <input type="text" id="timezone" value="<?= htmlspecialchars($settings['timezone']) ?>">
      <span class="help">Used to label dates only — the database stores plain timestamps.</span>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Save defaults</button>
    </div>
  </form>
</div>

<div class="panel mt-4" style="max-width:480px;">
  <div class="panel-head"><h2>Data cleanup</h2></div>
  <p class="help">
    If you deleted subjects before this fix shipped, the cycles and materials
    logged under them may still be sitting in the database, which can inflate
    your counts and hours. This scans your account only and removes anything
    left behind.
  </p>
  <div class="form-actions">
    <button type="button" class="btn" id="btn-run-cleanup">Clean up old data</button>
  </div>
  <div id="cleanup-result" class="help mt-4" hidden></div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
