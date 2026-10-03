(function () {
  const form = document.getElementById('manual-log-form');
  if (!form) return;

  const subjectSelect = document.getElementById('log_subject');
  const materialSelect = document.getElementById('log_material');

  async function loadMaterials(subjectId) {
    materialSelect.innerHTML = '<option value="">No specific material</option>';
    if (!subjectId) return;
    try {
      const materials = await api('api/materials.php?subject_id=' + subjectId);
      materials.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.title;
        materialSelect.appendChild(opt);
      });
    } catch (e) { /* keep the generic option on failure */ }
  }

  subjectSelect.addEventListener('change', () => loadMaterials(subjectSelect.value));
  loadMaterials(subjectSelect.value);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    try {
      await api('api/cycle.php', {
        method: 'POST',
        body: {
          action: 'manual_add',
          subject_id: subjectSelect.value,
          material_id: materialSelect.value || null,
          cycle_date: document.getElementById('log_date').value,
          study_minutes: document.getElementById('log_study').value,
          revision_minutes: document.getElementById('log_revision').value,
          status: document.getElementById('log_status').value,
        },
      });
      closeModal('manual-log-modal');
      showToast('Session logged.');
      setTimeout(() => window.location.reload(), 450);
    } catch (err) {
      btn.disabled = false;
    }
  });
})();
