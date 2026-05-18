// ── Dashboard Auth ──────────────────────────────────────────────

const DASH_SESSION_KEYS = ['session_id', 'interviewer_id'];

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
        <div class="logout-modal-top">
          <div class="logout-modal-icon"><span class="material-symbols-outlined">warning</span></div>
        </div>
        <div class="logout-modal-body">
          <div class="logout-modal-title">Confirm Logout</div>
          <div class="logout-modal-msg">Are you sure you want to log out?<br><strong>You will be redirected to the login page.</strong></div>
        </div>
        <div class="logout-modal-actions">
          <button id="logout-cancel-btn">Cancel</button>
          <button id="logout-confirm-btn">Yes, Logout</button>
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

// Authenticated fetch — attaches session headers to every API request
function authFetch(url, options = {}) {
  const user = dashGetUser();
  const headers = Object.assign({}, options.headers || {});
  if (user.session_id)     headers['X-Session-ID']     = user.session_id;
  if (user.interviewer_id) headers['X-Interviewer-ID'] = user.interviewer_id;
  return fetch(url, Object.assign({}, options, { headers }));
}
