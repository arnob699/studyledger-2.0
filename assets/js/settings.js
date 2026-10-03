(function () {
  document.querySelectorAll('[data-theme-btn]').forEach(btn => {
    btn.addEventListener('click', async () => {
      await api('api/settings.php', { method: 'POST', body: { theme: btn.dataset.themeBtn } });
      window.location.reload();
    });
  });

  document.getElementById('defaults-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    await api('api/settings.php', {
      method: 'POST',
      body: {
        default_study_minutes: document.getElementById('default_study').value,
        default_revision_minutes: document.getElementById('default_revision').value,
        timezone: document.getElementById('timezone').value,
      },
    });
    showToast('Defaults saved.');
    window.location.reload();
  });

  document.getElementById('btn-run-cleanup')?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    const out = document.getElementById('cleanup-result');
    btn.disabled = true;
    btn.textContent = 'Cleaning up...';
    try {
      const result = await api('api/cleanup.php', { method: 'POST' });
      out.hidden = false;
      out.textContent = `Removed ${result.deleted_cycles} orphaned cycle(s) and ${result.deleted_materials} orphaned material(s).`;
      showToast('Cleanup complete.');
    } catch (err) {
      /* api() already shows a toast on failure */
    } finally {
      btn.disabled = false;
      btn.textContent = 'Clean up old data';
    }
  });
})();
