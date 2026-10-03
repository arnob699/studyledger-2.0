<?php
/**
 * Expects $pageTitle, $pageSubtitle (optional), $activeNav to be set
 * by the including page before this file is required.
 */
require_once __DIR__ . '/functions.php';
require_auth();
$currentUser = current_user();
$activeCycle = get_active_cycle();
$appSettings = get_app_settings();
$logSubjects = get_subjects();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($appSettings['theme']) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Study Ledger') ?> · Study Ledger</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="shell">

  <aside class="sidebar">
    <div class="brand">
      <div class="brand-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      </div>
      <div class="brand-text">
        <span class="brand-name">Study Ledger</span>
        <span class="brand-tag">Study smarter</span>
      </div>
    </div>

    <nav class="nav">
      <a href="index.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
        Dashboard
      </a>
      <a href="cycle.php" class="<?= $activeNav === 'cycle' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>
        Study cycle
        <?php if ($activeCycle): ?><span class="badge badge-amber badge-pulse" style="margin-left:auto;"><span class="pulse-dot"></span>live</span><?php endif; ?>
      </a>
      <a href="subjects.php" class="<?= $activeNav === 'subjects' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16.5a1.5 1.5 0 0 1-1.5 1.5H6.5A2.5 2.5 0 0 1 4 18.5v-13Z"/><path d="M4 18.5A2.5 2.5 0 0 1 6.5 16H20"/></svg>
        Subjects
      </a>
      <a href="materials.php" class="<?= $activeNav === 'materials' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 4h9a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H9"/><path d="M9 4v16"/><path d="M5 4h4v16H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z"/></svg>
        Materials
      </a>
      <a href="goals.php" class="<?= $activeNav === 'goals' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/></svg>
        Goals
      </a>
      <a href="settings.php" class="<?= $activeNav === 'settings' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>
        Settings
      </a>
    </nav>

    <?php $streak = get_streak(); ?>
    <div class="sidebar-streak-card">
      <div class="streak-badge-row">
        <span class="streak-flame">🔥</span>
        <div>
          <div class="streak-count"><span class="num"><?= (int)$streak['current_streak'] ?></span> <span class="streak-label">day streak</span></div>
          <div class="streak-sub">Best: <span class="num"><?= (int)$streak['longest_streak'] ?></span> days</div>
        </div>
      </div>
      <div class="streak-mini-progress">
        <div class="streak-mini-fill" style="width: <?= min(100, max(15, ((int)$streak['current_streak'] % 7) / 7 * 100)) ?>%;"></div>
      </div>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <div class="topbar-title">
        <h1><?= htmlspecialchars($pageTitle ?? '') ?></h1>
        <?php if (!empty($pageSubtitle)): ?><p><?= htmlspecialchars($pageSubtitle) ?></p><?php endif; ?>
      </div>
      <div style="display:flex; gap:10px;">
        <span class="topbar-user"><?= htmlspecialchars($currentUser['name'] ?? '') ?></span>
        <a href="logout.php" class="btn btn-ghost">Log out</a>
        <button type="button" class="btn btn-secondary" onclick="openModal('manual-log-modal')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 13h6M9 17h4"/></svg>
          Log manually
        </button>
        <?php if ($activeCycle): ?>
          <a href="cycle.php" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>
            Resume cycle
          </a>
        <?php else: ?>
          <a href="cycle.php?new=1" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            Start cycle
          </a>
        <?php endif; ?>
      </div>
    </div>
    <div class="content">
