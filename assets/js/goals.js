(function () {
  document.getElementById('goal-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    await api('api/goals.php', {
      method: 'POST',
      body: {
        action: 'create',
        goal_type: document.getElementById('goal_type').value,
        target_cycles: document.getElementById('goal_cycles').value,
        target_minutes: document.getElementById('goal_minutes').value || null,
      },
    });
    showToast('Goal saved.');
    window.location.reload();
  });

  document.querySelectorAll('[data-deactivate]').forEach(btn => {
    btn.addEventListener('click', async () => {
      await api('api/goals.php', { method: 'POST', body: { action: 'deactivate', id: btn.dataset.deactivate } });
      window.location.reload();
    });
  });
})();
