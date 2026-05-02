// ── Dashboard Auth ──────────────────────────────────────────────

const DASH_SESSION_KEYS = ['session_id', 'interviewer_code', 'privacyAccepted'];

function dashCheckAuth() {
  const missing = DASH_SESSION_KEYS.some(k => !sessionStorage.getItem(k));
  if (missing) {
    window.location.href = '/dashboard.html';
    return false;
  }
  return true;
}

function dashGetUser() {
  return {
    session_id: sessionStorage.getItem('session_id'),
    interviewer_id: sessionStorage.getItem('interviewer_id'),
    code: sessionStorage.getItem('interviewer_code'),
    name: sessionStorage.getItem('interviewer_name') || 'User',
    region: sessionStorage.getItem('interviewer_region') || '',
    province: sessionStorage.getItem('interviewer_province') || '',
    office: sessionStorage.getItem('interviewer_office') || '',
    position: sessionStorage.getItem('interviewer_position') || '',
  };
}

function dashLogout() {
  sessionStorage.clear();
  window.location.href = '/dashboard.html';
}

// Populate user info elements if present
function dashPopulateUser() {
  const user = dashGetUser();
  const nameEl = document.getElementById('dash-user-name');
  const roleEl = document.getElementById('dash-user-role');
  const avatarEl = document.getElementById('dash-user-avatar');
  const headerNameEl = document.getElementById('dash-header-user');

  if (nameEl) nameEl.textContent = user.name;
  if (roleEl) roleEl.textContent = user.position || user.code;
  if (avatarEl) avatarEl.textContent = user.name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
  if (headerNameEl) headerNameEl.textContent = user.name;
}

// On pages that require auth, call on load
function dashRequireAuth() {
  if (!dashCheckAuth()) return;
  document.addEventListener('DOMContentLoaded', dashPopulateUser);
}
