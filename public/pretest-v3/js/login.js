// ============================================================================
// PROJECT ARUGA - PRETEST v3 APP LOGIN (desktop, Android and web)
// UI copied from docs/v2-profiling-preview.html; logs in through the pretest API,
// or offline against the tester list when there is no internet (js/offline.js).
// ============================================================================

const QUICK_GUIDE = [
  'Ask only questions that are appropriate for the child’s age.',
  'Read questions neutrally. Do not suggest or imply an answer.',
  'Ask about the child’s usual or current situation unless a different reference period is stated.',
  'Keep information about diagnosis or condition separate from information about functioning.',
  'Record “No,” “Don’t know,” “Not applicable,” refusal, and missing responses separately.',
  'Use the response categories shown for each question. Do not change the scale during the interview.',
  'Use examples only to clarify the question; do not lead the respondent toward a particular answer.',
  'Check available records when verification is appropriate and permitted.',
  'If an urgent health, protection, education, care, or other support concern is identified, follow the approved Project Aruga referral procedure immediately.',
  'Protect privacy. Record only the information needed for assessment, referral, case planning, and approved reporting.'
];

const escHtml = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function showGuideModal() { document.getElementById('guide-modal').classList.remove('hidden'); }
function hideGuideModal() { document.getElementById('guide-modal').classList.add('hidden'); }

// Language: English only for v3 (Tagalog translation not available yet).
function toggleLangDropdown() {
  document.getElementById('land-lang_panel').classList.toggle('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
  const codeInput = document.getElementById('land-code');
  const agree     = document.getElementById('land-agree');
  const button    = document.getElementById('land-submit');
  const label     = document.getElementById('land-submit-label');

  document.getElementById('guide-list').innerHTML = QUICK_GUIDE.map((g, i) =>
    `<li class="flex items-start gap-3"><span class="w-5 h-5 rounded-md bg-blue-50 text-brand-blue text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5">${i + 1}</span><span class="text-xs sm:text-sm text-gray-600 leading-snug">${escHtml(g)}</span></li>`
  ).join('');
  document.addEventListener('keydown', e => { if (e.key === 'Escape') hideGuideModal(); });

  document.addEventListener('click', e => {
    const wrap = document.querySelector('[data-dropdown="land-lang"]');
    if (!wrap.contains(e.target)) document.getElementById('land-lang_panel').classList.add('hidden');
  });

  // Remember the checkbox when coming back from the privacy page.
  if (sessionStorage.getItem('privacyAccepted') === 'true') agree.checked = true;

  function validate() {
    button.disabled = !(codeInput.value.length === 8 && agree.checked);
  }

  codeInput.addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 8);
    validate();
  });
  agree.addEventListener('change', function() {
    sessionStorage.setItem('privacyAccepted', agree.checked ? 'true' : '');
    validate();
  });
  codeInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !button.disabled) login();
  });
  button.addEventListener('click', login);
  validate();
  codeInput.focus();
  AppOffline.init();

  async function login() {
    const code = codeInput.value;
    const original = label.textContent;
    label.textContent = 'Checking...';
    button.disabled = true;

    const result = await PretestApi.call('login', { method: 'POST', body: { interviewer_code: code } });

    if (result.ok) {
      const d = result.json.data;
      AppOffline.saveServerLogin(d);
      sessionStorage.setItem('login_code',      code);
      sessionStorage.setItem('dialect',         document.getElementById('land-lang').value);
      sessionStorage.setItem('privacyAccepted', 'true');
      sessionStorage.setItem('loginTime',       new Date().toISOString());
      await AppOffline.learnTester(code, d.full_name);

      toast.success(`Welcome, ${d.full_name}!`, 'Login Successful');
      label.textContent = 'Opening form...';
      setTimeout(() => { window.location.href = 'profiling.html'; }, 800);
      return;
    }

    if (result.status === 0) {
      // No internet: check the code against the offline tester list instead.
      const tester = await AppOffline.offlineLogin(code);
      if (tester) {
        sessionStorage.setItem('session_mode',     'offline');
        sessionStorage.setItem('interviewer_code', code);
        sessionStorage.setItem('interviewer_name', tester.name);
        sessionStorage.setItem('login_code',       code);
        sessionStorage.setItem('dialect',          document.getElementById('land-lang').value);
        sessionStorage.setItem('privacyAccepted',  'true');
        sessionStorage.setItem('loginTime',        new Date().toISOString());

        toast.warning('No internet. You are logged in offline: profiles will be saved on this device and uploaded when you are back online.', 'Offline Login');
        label.textContent = 'Opening form...';
        setTimeout(() => { window.location.href = 'profiling.html'; }, 1500);
        return;
      }
      toast.error('No internet, and this code cannot be checked offline on this device. Connect to the internet for this login, or check the code.', 'Connection Error');
    } else {
      toast.error((result.json && result.json.message) || 'Login failed. Please try again.', 'Authentication Failed');
      codeInput.value = '';
      codeInput.focus();
    }
    label.textContent = original;
    validate();
  }
});
