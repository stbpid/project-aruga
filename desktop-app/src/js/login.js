// ============================================================================
// PROJECT ARUGA - PRETEST DESKTOP APP LOGIN
// Same flow as the live index.js, but logs in through the pretest API.
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
  const codeInput = document.getElementById('code');
  const agree     = document.getElementById('agree');
  const button    = document.getElementById('btn-submit');
  const dialect   = document.getElementById('dialect-select');
  const tlNote    = document.getElementById('dialect-tl-note');

  document.getElementById('app-version').textContent = 'App version ' + window.ARUGA_CONFIG.APP_VERSION;

  // Remember language and code when coming back from the privacy page.
  if (sessionStorage.getItem('dialect')) dialect.value = sessionStorage.getItem('dialect');

  function validate() {
    button.disabled = !(codeInput.value.trim().length === 8 && agree.checked);
  }

  codeInput.addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 8);
    validate();
  });
  agree.addEventListener('change', validate);
  dialect.addEventListener('change', function() {
    sessionStorage.setItem('dialect', dialect.value);
    tlNote.classList.toggle('hidden', dialect.value !== 'tl');
  });
  tlNote.classList.toggle('hidden', dialect.value !== 'tl');
  codeInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !button.disabled) login();
  });
  button.addEventListener('click', login);
  validate();
  codeInput.focus();

  async function login() {
    const code = codeInput.value.trim().toUpperCase();
    const original = button.innerHTML;
    button.innerHTML = 'Checking...';
    button.disabled = true;

    const result = await PretestApi.call('login', { method: 'POST', body: { interviewer_code: code } });

    if (result.ok) {
      const d = result.json.data;
      sessionStorage.setItem('session_id',           d.session_id);
      sessionStorage.setItem('interviewer_id',       d.interviewer_id);
      sessionStorage.setItem('interviewer_code',     d.interviewer_code);
      sessionStorage.setItem('interviewer_name',     d.full_name || '');
      sessionStorage.setItem('interviewer_region',   d.region || '');
      sessionStorage.setItem('interviewer_province', d.province || '');
      sessionStorage.setItem('interviewer_office',   d.office || '');
      sessionStorage.setItem('interviewer_position', d.position || '');
      sessionStorage.setItem('session_started_at',   d.started_at || '');
      sessionStorage.setItem('session_expires_at',   d.expires_at || '');
      sessionStorage.setItem('dialect',              dialect.value);
      sessionStorage.setItem('privacyAccepted',      'true');
      sessionStorage.setItem('loginTime',            new Date().toISOString());

      toast.success(`Welcome, ${d.full_name}!`, 'Login Successful');
      button.innerHTML = '✓ Success! Opening form...';
      setTimeout(() => { window.location.href = 'profiling.html'; }, 800);
      return;
    }

    if (result.status === 0) {
      toast.error('Unable to reach the server. Please check your internet connection and try again.', 'Connection Error');
    } else {
      toast.error((result.json && result.json.message) || 'Login failed. Please try again.', 'Authentication Failed');
      codeInput.value = '';
      codeInput.focus();
    }
    button.innerHTML = original;
    validate();
  }
});
