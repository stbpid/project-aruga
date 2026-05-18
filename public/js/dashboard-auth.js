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
  document.addEventListener('DOMContentLoaded', dashStartIdleTimer);
}

// Authenticated fetch — attaches session headers to every API request
function authFetch(url, options = {}) {
  const user = dashGetUser();
  const headers = Object.assign({}, options.headers || {});
  if (user.session_id)     headers['X-Session-ID']     = user.session_id;
  if (user.interviewer_id) headers['X-Interviewer-ID'] = user.interviewer_id;
  return fetch(url, Object.assign({}, options, { headers }));
}

// ── Idle Session Timeout ─────────────────────────────────────────
// Logs out after 30 mins of inactivity, with a 2-min warning popup.

const IDLE_TIMEOUT_MS  = 30 * 60 * 1000; // 30 minutes
const IDLE_WARNING_MS  =  2 * 60 * 1000; //  2 minutes before logout
let _idleTimer, _idleWarnTimer, _idleWarnShown = false;

function _idleResetTimers() {
  clearTimeout(_idleTimer);
  clearTimeout(_idleWarnTimer);

  // Hide warning if it was shown and user is active again
  if (_idleWarnShown) {
    _idleWarnShown = false;
    const w = document.getElementById('idle-warning-modal');
    if (w) w.remove();
  }

  // Warn 2 mins before logout
  _idleWarnTimer = setTimeout(_idleShowWarning, IDLE_TIMEOUT_MS - IDLE_WARNING_MS);
  // Logout after full timeout
  _idleTimer = setTimeout(_idleLogout, IDLE_TIMEOUT_MS);
}

function _idleShowWarning() {
  _idleWarnShown = true;
  let modal = document.getElementById('idle-warning-modal');
  if (modal) modal.remove();

  modal = document.createElement('div');
  modal.id = 'idle-warning-modal';
  modal.style.cssText = 'position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);';
  modal.innerHTML = `
    <div style="background:#fff;border-radius:1rem;padding:2rem;max-width:360px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
      <div style="width:3rem;height:3rem;background:#fef3c7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
        <span class="material-symbols-outlined" style="color:#d97706;font-size:1.5rem;">timer</span>
      </div>
      <div style="font-weight:700;font-size:1.1rem;color:#111;margin-bottom:0.5rem;">Session Expiring Soon</div>
      <div style="color:#6b7280;font-size:0.875rem;margin-bottom:0.25rem;">You've been inactive for 28 minutes.</div>
      <div style="color:#6b7280;font-size:0.875rem;margin-bottom:1.5rem;">You will be logged out in <strong id="idle-countdown">2:00</strong>.</div>
      <button id="idle-stay-btn" style="background:#1152d4;color:#fff;border:none;border-radius:0.5rem;padding:0.65rem 1.5rem;font-size:0.9rem;font-weight:600;cursor:pointer;width:100%;">Stay Logged In</button>
    </div>
  `;
  document.body.appendChild(modal);
  document.getElementById('idle-stay-btn').addEventListener('click', () => {
    _idleResetTimers();
  });

  // Countdown timer display
  let remaining = IDLE_WARNING_MS / 1000;
  const countdownEl = document.getElementById('idle-countdown');
  const countdownInterval = setInterval(() => {
    remaining--;
    if (!document.getElementById('idle-countdown')) { clearInterval(countdownInterval); return; }
    const m = Math.floor(remaining / 60);
    const s = remaining % 60;
    countdownEl.textContent = m + ':' + String(s).padStart(2, '0');
    if (remaining <= 0) clearInterval(countdownInterval);
  }, 1000);
}

function _idleLogout() {
  const modal = document.getElementById('idle-warning-modal');
  if (modal) modal.remove();
  sessionStorage.clear();
  window.location.replace('/dashboard.html?reason=idle');
}

function dashStartIdleTimer() {
  const events = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click'];
  events.forEach(e => document.addEventListener(e, _idleResetTimers, { passive: true }));
  _idleResetTimers();
}
