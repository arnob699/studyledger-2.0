// Shared utilities used across every page.

function showToast(message, tone = 'default') {
  const el = document.getElementById('toast');
  if (!el) return;
  el.textContent = message;
  el.classList.add('show');
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => el.classList.remove('show'), 2600);
}

async function api(url, options = {}) {
  const opts = Object.assign({ headers: { 'Content-Type': 'application/json' } }, options);
  if (opts.body && typeof opts.body !== 'string') opts.body = JSON.stringify(opts.body);
  const res = await fetch(url, opts);
  let data = null;
  try { data = await res.json(); } catch (e) { /* no body */ }
  if (!res.ok) {
    const msg = (data && data.error) ? data.error : 'Something went wrong.';
    showToast(msg);
    throw new Error(msg);
  }
  return data;
}

function openModal(id) {
  document.getElementById(id)?.classList.add('show');
}
function closeModal(id) {
  document.getElementById(id)?.classList.remove('show');
}
document.addEventListener('click', (e) => {
  if (e.target.classList && e.target.classList.contains('modal-backdrop')) {
    e.target.classList.remove('show');
  }
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-backdrop.show').forEach(el => el.classList.remove('show'));
  }
});

/* ---------- custom cursor (desktop / fine-pointer only) ---------- */

(function () {
  if (!window.matchMedia || !window.matchMedia('(pointer: fine)').matches) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const dot = document.createElement('div');
  dot.className = 'cursor-dot';
  const ring = document.createElement('div');
  ring.className = 'cursor-ring';
  document.body.append(dot, ring);
  document.body.classList.add('custom-cursor-active');

  let mx = window.innerWidth / 2, my = window.innerHeight / 2;
  let rx = mx, ry = my;
  let visible = false;

  window.addEventListener('mousemove', (e) => {
    mx = e.clientX; my = e.clientY;
    if (!visible) { visible = true; dot.style.opacity = 1; ring.style.opacity = 1; }
    dot.style.transform = `translate(${mx}px, ${my}px)`;
  }, { passive: true });

  window.addEventListener('mouseleave', () => { dot.style.opacity = 0; ring.style.opacity = 0; visible = false; });
  window.addEventListener('mousedown', () => ring.classList.add('is-pressed'));
  window.addEventListener('mouseup', () => ring.classList.remove('is-pressed'));

  function loop() {
    rx += (mx - rx) * 0.18;
    ry += (my - ry) * 0.18;
    ring.style.transform = `translate(${rx}px, ${ry}px)`;
    requestAnimationFrame(loop);
  }
  requestAnimationFrame(loop);

  const HOVER_SELECTOR = 'a, button, .btn, .card, .row, .progress-row, .color-dot, input, select, textarea, [onclick], canvas';
  document.addEventListener('mouseover', (e) => {
    const target = e.target.closest(HOVER_SELECTOR);
    if (!target) { ring.classList.remove('is-hover', 'is-text'); return; }
    const isTextField = target.matches('input, select, textarea');
    ring.classList.toggle('is-text', isTextField);
    ring.classList.toggle('is-hover', !isTextField);
  });
})();

/* ---------- click ripple feedback ---------- */

(function () {
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const RIPPLE_SELECTOR = '.btn, .card, .row, .progress-row, nav a, .color-dot, .badge';

  document.addEventListener('pointerdown', (e) => {
    const target = e.target.closest(RIPPLE_SELECTOR);
    if (!target || target.classList.contains('btn') && target.disabled) return;

    const rect = target.getBoundingClientRect();
    const ripple = document.createElement('span');
    ripple.className = 'ripple';
    const size = Math.max(rect.width, rect.height) * 1.6;
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
    ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';

    const prevPosition = getComputedStyle(target).position;
    if (prevPosition === 'static') target.classList.add('ripple-host');

    target.appendChild(ripple);
    ripple.addEventListener('animationend', () => ripple.remove());
  }, { passive: true });
})();
