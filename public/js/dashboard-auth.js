// ── Dashboard Auth ──────────────────────────────────────────────

const DASH_SESSION_KEYS = ['session_id', 'interviewer_code', 'privacyAccepted'];

function dashCheckAuth() {
  const missing = DASH_SESSION_KEYS.some(k => !sessionStorage.getItem(k));
  if (missing) {
    window.location.replace('/dashboard.html');
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
  showLogoutModal();
}

function doLogout() {
  sessionStorage.clear();
  window.location.replace('/dashboard.html');
}

function showLogoutModal() {
  let modal = document.getElementById('logout-modal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'logout-modal';
    modal.innerHTML = `
      <div class="logout-modal-backdrop" id="logout-modal-backdrop"></div>
      <div class="logout-modal-box">
        <div class="logout-modal-icon"><span class="material-symbols-outlined">logout</span></div>
        <div class="logout-modal-title">Confirm Logout</div>
        <div class="logout-modal-msg">Are you sure you want to log out? Your session will be ended.</div>
        <div class="logout-modal-actions">
          <button class="btn btn-secondary" id="logout-cancel-btn">Cancel</button>
          <button class="btn btn-danger" id="logout-confirm-btn">Logout</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
    document.getElementById('logout-cancel-btn').addEventListener('click', hideLogoutModal);
    document.getElementById('logout-modal-backdrop').addEventListener('click', hideLogoutModal);
    document.getElementById('logout-confirm-btn').addEventListener('click', doLogout);
  }
  modal.classList.add('open');
}

function hideLogoutModal() {
  const modal = document.getElementById('logout-modal');
  if (modal) modal.classList.remove('open');
}

// Populate user info elements if present
function dashPopulateUser() {
  const user = dashGetUser();
  const nameEl = document.getElementById('dash-user-name');
  const roleEl = document.getElementById('dash-user-role');
  const avatarEl = document.getElementById('dash-user-avatar');
  const headerTitleEl = document.getElementById('dash-header-title');

  if (nameEl) nameEl.textContent = user.name;
  if (roleEl) roleEl.textContent = user.position || user.code;
  if (avatarEl) avatarEl.textContent = user.name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
  if (headerTitleEl) headerTitleEl.textContent = user.name;
}

// Block back navigation after logout
function dashBlockBack() {
  history.pushState(null, '', window.location.href);
  window.addEventListener('popstate', () => {
    if (!sessionStorage.getItem('session_id')) {
      window.location.replace('/dashboard.html');
    } else {
      history.pushState(null, '', window.location.href);
    }
  });
}

// On pages that require auth, call on load
function dashRequireAuth() {
  if (!dashCheckAuth()) return;
  dashBlockBack();
  document.addEventListener('DOMContentLoaded', dashPopulateUser);
}
