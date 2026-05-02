// ── Dashboard Common Utilities ──────────────────────────────────

// Toast
class DashToast {
  constructor() {
    if (!document.getElementById('toast-container')) {
      const el = document.createElement('div');
      el.id = 'toast-container';
      el.className = 'toast-container';
      document.body.appendChild(el);
    }
  }
  show(msg, type = 'info', title = '') {
    const titles = { success: 'Success', error: 'Error', warning: 'Warning', info: 'Info' };
    const icons = { success: '✓', error: '✕', warning: '!', info: 'i' };
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<div class="toast-icon">${icons[type]||'i'}</div><div class="toast-content"><div class="toast-title">${title||titles[type]}</div><div class="toast-message">${msg}</div></div><button class="toast-close" onclick="this.closest('.toast').classList.add('hiding');setTimeout(()=>this.closest('.toast')?.remove(),300)">×</button>`;
    document.getElementById('toast-container').appendChild(t);
    setTimeout(() => { t.classList.add('hiding'); setTimeout(() => t.remove(), 300); }, 4000);
  }
  success(m, t) { this.show(m, 'success', t); }
  error(m, t) { this.show(m, 'error', t); }
  warning(m, t) { this.show(m, 'warning', t); }
  info(m, t) { this.show(m, 'info', t); }
}
window.dashToast = new DashToast();

// Sidebar toggle
function initSidebar() {
  const sidebar = document.getElementById('dash-sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  const hamburger = document.getElementById('hamburger-btn');

  function open() {
    sidebar?.classList.add('open');
    overlay?.classList.add('open');
  }
  function close() {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('open');
  }

  hamburger?.addEventListener('click', open);
  overlay?.addEventListener('click', close);
}

// Notification dropdown
function initNotifDropdown() {
  const btn = document.getElementById('notif-btn');
  const dropdown = document.getElementById('notif-dropdown');
  if (!btn || !dropdown) return;

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    dropdown.classList.toggle('open');
  });

  document.addEventListener('click', (e) => {
    if (!dropdown.contains(e.target) && e.target !== btn) {
      dropdown.classList.remove('open');
    }
  });
}

// Active nav item
function initActiveNav() {
  const path = window.location.pathname.split('/').pop();
  document.querySelectorAll('.nav-item').forEach(item => {
    const href = item.getAttribute('href') || '';
    if (href.includes(path)) item.classList.add('active');
  });
}

// Format numbers with commas
function fmtNum(n) {
  return Number(n).toLocaleString();
}

// Draw simple SVG donut chart
function drawDonut(canvasId, segments, colors) {
  const svg = document.getElementById(canvasId);
  if (!svg) return;
  const total = segments.reduce((a, b) => a + b, 0);
  let startAngle = -Math.PI / 2;
  const cx = 60, cy = 60, r = 45, ir = 28;

  let paths = '';
  segments.forEach((val, i) => {
    if (val === 0) return;
    const angle = (val / total) * Math.PI * 2;
    const endAngle = startAngle + angle;
    const x1 = cx + r * Math.cos(startAngle), y1 = cy + r * Math.sin(startAngle);
    const x2 = cx + r * Math.cos(endAngle), y2 = cy + r * Math.sin(endAngle);
    const ix1 = cx + ir * Math.cos(startAngle), iy1 = cy + ir * Math.sin(startAngle);
    const ix2 = cx + ir * Math.cos(endAngle), iy2 = cy + ir * Math.sin(endAngle);
    const large = angle > Math.PI ? 1 : 0;
    paths += `<path d="M${x1},${y1} A${r},${r} 0 ${large},1 ${x2},${y2} L${ix2},${iy2} A${ir},${ir} 0 ${large},0 ${ix1},${iy1} Z" fill="${colors[i]}" />`;
    startAngle = endAngle;
  });

  svg.innerHTML = paths;
}

// Draw bar chart
function drawBars(containerId, data, maxVal) {
  const container = document.getElementById(containerId);
  if (!container) return;
  const max = maxVal || Math.max(...data.map(d => d.value)) || 1;
  container.innerHTML = data.map(d => `
    <div class="bar-col">
      <div class="bar-fill" style="height:${Math.max((d.value/max)*120,4)}px" title="${d.label}: ${fmtNum(d.value)}"></div>
      <span class="bar-label">${d.label}</span>
    </div>
  `).join('');
}

document.addEventListener('DOMContentLoaded', () => {
  initSidebar();
  initNotifDropdown();
  initActiveNav();
});
