(function () {
  const startForm = document.getElementById('start-form');

  if (startForm) {
    const subjectSelect = document.getElementById('subject_id');
    const materialSelect = document.getElementById('material_id');

    async function loadMaterials(subjectId) {
      materialSelect.innerHTML = '<option value="">No specific material</option>';
      if (!subjectId) return;
      const materials = await api('api/materials.php?subject_id=' + subjectId);
      materials.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.title + (m.status !== 'not_started' ? ` (${m.status.replace('_', ' ')})` : '');
        materialSelect.appendChild(opt);
      });
    }

    subjectSelect.addEventListener('change', () => loadMaterials(subjectSelect.value));
    loadMaterials(subjectSelect.value);

    startForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = startForm.querySelector('button[type="submit"]');
      btn.disabled = true;
      try {
        await api('api/cycle.php', {
          method: 'POST',
          body: {
            action: 'start',
            subject_id: subjectSelect.value,
            material_id: materialSelect.value || null,
            planned_study_minutes: document.getElementById('planned_study').value,
            planned_revision_minutes: document.getElementById('planned_revision').value,
          },
        });
        window.location.href = 'cycle.php';
      } catch (e) {
        btn.disabled = false;
      }
    });
    return;
  }

  /* ---------- active timer ---------- */

  const state = window.__CYCLE__;
  if (!state) return;

  const timeEl = document.getElementById('timer-time');
  const phaseLabel = document.getElementById('timer-phase-label');
  const ring = document.getElementById('ring-progress');
  const circumference = state.circumference;

  let remaining = state.remaining;

  function paint() {
    const m = Math.floor(remaining / 60).toString().padStart(2, '0');
    const s = Math.floor(remaining % 60).toString().padStart(2, '0');
    timeEl.textContent = `${m}:${s}`;

    const pct = Math.max(0, remaining / state.plannedSeconds);
    ring.style.strokeDashoffset = (circumference * (1 - pct)).toFixed(1);

    if (remaining <= 0) {
      phaseLabel.textContent = "time's up — wrap up when ready";
    }
  }

  paint();
  const tick = setInterval(() => {
    remaining -= 1;
    if (remaining < -1) remaining = -1; // let overtime keep the "time's up" message, no runaway negative display
    paint();
  }, 1000);

  document.getElementById('btn-advance').addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    btn.disabled = true;
    clearInterval(tick);
    try {
      await api('api/cycle.php', { method: 'POST', body: { action: state.phase === 'study' ? 'finish_study' : 'complete' } });
      window.location.href = state.phase === 'study' ? 'cycle.php' : 'index.php';
    } catch (e) {
      btn.disabled = false;
    }
  });

  document.getElementById('btn-abandon').addEventListener('click', async (e) => {
    if (!confirm('Abandon this cycle? Time logged so far will not be saved.')) return;
    const btn = e.currentTarget;
    btn.disabled = true;
    try {
      await api('api/cycle.php', { method: 'POST', body: { action: 'abandon' } });
      window.location.href = 'index.php';
    } catch (e) {
      btn.disabled = false;
    }
  });
})();
