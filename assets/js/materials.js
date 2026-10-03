(function () {
  const form = document.getElementById('material-form');
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      await api('api/materials.php', {
        method: 'POST',
        body: {
          action: 'create',
          subject_id: document.getElementById('m_subject').value,
          title: document.getElementById('m_title').value.trim(),
          difficulty: document.getElementById('m_difficulty').value,
        },
      });
      showToast('Material added.');
      window.location.reload();
    });
  }

  document.querySelectorAll('.status-select').forEach(sel => {
    sel.addEventListener('change', async () => {
      await api('api/materials.php', { method: 'POST', body: { action: 'update_status', id: sel.dataset.id, status: sel.value } });
      showToast('Status updated.');
    });
  });

  document.querySelectorAll('.btn-delete-material').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('Delete this material?')) return;
      await api('api/materials.php', { method: 'POST', body: { action: 'delete', id: btn.dataset.id } });
      btn.closest('tr').remove();
      showToast('Material deleted.');
    });
  });
})();
