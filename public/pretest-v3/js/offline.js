// ============================================================================
// PROJECT ARUGA - OFFLINE SUPPORT (pretest v3: desktop, Android and web)
//   - Outbox: every submitted profile is saved on this device first, then
//     uploaded. Profiles saved with no internet upload automatically later.
//     A profile is deleted from the device as soon as the server confirms it.
//   - Offline login: checks the Interviewer Code against src/data/testers.json
//     (fingerprints only, see scripts/export-testers.php) plus testers who have
//     logged in online on this device. The server checks the real code again
//     before anything is uploaded.
//   - Status pill (● Online / ● Offline · N waiting) in the page header.
//
// Uploads are safe to repeat: the database ignores a profile it already has
// (same device-generated assessment_id), so a lost reply never duplicates.
// ============================================================================

const AppOffline = (() => {
  // v3 keys, so v3 profiles never mix with a v2 outbox on the same device/site.
  const OUTBOX_KEY  = 'aruga_outbox_v3';
  const LEARNED_KEY = 'aruga_testers_learned_v3';
  const PING_EVERY_MS = 30000;

  let online = null;   // null until the first check
  let syncing = false;

  // --------------------------------------------------------------------------
  // Outbox (localStorage persists on this device across app restarts)
  // --------------------------------------------------------------------------
  function readOutbox() {
    try {
      const v = JSON.parse(localStorage.getItem(OUTBOX_KEY) || '[]');
      return Array.isArray(v) ? v : [];
    } catch (e) {
      return [];
    }
  }

  function writeOutbox(list) {
    localStorage.setItem(OUTBOX_KEY, JSON.stringify(list));
    render();
  }

  /** Throws if the device storage is full; the caller must handle that. */
  function addToOutbox(item) {
    writeOutbox(readOutbox().filter(i => i.id !== item.id).concat([item]));
  }

  function removeFromOutbox(id) {
    writeOutbox(readOutbox().filter(i => i.id !== id));
  }

  function setItemError(id, message) {
    writeOutbox(readOutbox().map(i => (i.id === id ? { ...i, error: message } : i)));
  }

  // Profiles are uploaded only under the interviewer who made them.
  function myItems() {
    const code = sessionStorage.getItem('interviewer_code');
    return code ? readOutbox().filter(i => i.interviewer_code === code) : [];
  }

  function waitingCount() {
    return sessionStorage.getItem('interviewer_code') ? myItems().length : readOutbox().length;
  }

  // --------------------------------------------------------------------------
  // Server session
  // --------------------------------------------------------------------------
  function saveServerLogin(d) {
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
    sessionStorage.setItem('session_mode',         'online');
  }

  // After an offline login (or an expired session), log in to the server in
  // the background with the code typed at login. That code is kept only in
  // sessionStorage, which is cleared when the app closes or the session ends.
  async function ensureServerSession() {
    if (sessionStorage.getItem('session_id')) return true;
    const code = sessionStorage.getItem('login_code');
    if (!code) return false;
    const r = await PretestApi.call('login', { method: 'POST', body: { interviewer_code: code } });
    if (!r.ok) return false;
    saveServerLogin(r.json.data);
    return true;
  }

  async function uploadOne(item) {
    let r = await PretestApi.call('submit', { method: 'POST', body: item.body });
    if (r.status === 401) {
      sessionStorage.removeItem('session_id');
      if (await ensureServerSession()) r = await PretestApi.call('submit', { method: 'POST', body: item.body });
    }
    return r;
  }

  // --------------------------------------------------------------------------
  // Online check: navigator.onLine only knows about the network cable/Wi-Fi,
  // so the app asks the pretest server directly.
  // --------------------------------------------------------------------------
  function setOnline(value) {
    const was = online;
    online = value;
    render();
    if (was === null || was === value || !window.toast) return;
    if (value) {
      const n = myItems().length;
      window.toast.success(n ? `Back online. Uploading ${n} profile${n > 1 ? 's' : ''}...` : 'Back online.', 'Connection');
    } else {
      window.toast.warning('You\'re offline. Profiles will be saved on this device.', 'Connection');
    }
  }

  async function checkOnline() {
    const r = await PretestApi.call('ping', { timeoutMs: 8000 });
    setOnline(r.ok);
    return r.ok;
  }

  function isOnline() { return online === true; }

  // --------------------------------------------------------------------------
  // Sync: upload this interviewer's waiting profiles
  // --------------------------------------------------------------------------
  async function sync({ manual = false } = {}) {
    if (syncing) return;
    // A profile the server refused is only retried when Sync now is pressed,
    // so the automatic check does not repeat the same error every 30 seconds.
    const items = manual ? myItems() : myItems().filter(i => !i.error);
    if (!items.length) {
      if (manual && window.toast) window.toast.info('Nothing is waiting to upload.', 'Upload');
      return;
    }
    syncing = true;
    render();
    try {
      if (!(await checkOnline())) {
        if (manual) window.toast.warning('Still offline. Profiles stay saved on this device.', 'Upload');
        return;
      }
      if (!(await ensureServerSession())) {
        window.toast.error('Could not log in to upload. End the session and log in again while online.', 'Upload');
        return;
      }
      let uploaded = 0;
      let failed = 0;
      for (const item of items) {
        const r = await uploadOne(item);
        if (r.ok) {
          removeFromOutbox(item.id);
          uploaded++;
        } else if (r.status === 0) {
          setOnline(false);
          break;
        } else {
          failed++;
          setItemError(item.id, (r.json && r.json.message) || `Upload refused (error ${r.status}).`);
        }
      }
      if (uploaded) window.toast.success(`${uploaded} profile${uploaded > 1 ? 's' : ''} uploaded.`, 'Upload');
      if (failed) window.toast.error(`${failed} profile${failed > 1 ? 's' : ''} could not be uploaded. Click the status in the header for details.`, 'Upload');
    } finally {
      syncing = false;
      render();
    }
  }

  // --------------------------------------------------------------------------
  // Offline login
  // --------------------------------------------------------------------------
  async function fingerprint(salt, code) {
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(salt + code));
    return Array.from(new Uint8Array(digest), b => b.toString(16).padStart(2, '0')).join('').slice(0, 4);
  }

  function readLearned() {
    try { return JSON.parse(localStorage.getItem(LEARNED_KEY) || 'null'); } catch (e) { return null; }
  }

  /** Returns { name } when the code matches an enrolled tester, else null. */
  async function offlineLogin(code) {
    if (!window.crypto || !crypto.subtle) return null;
    const lists = [];
    try { lists.push(await (await fetch('data/testers.json', { cache: 'no-store' })).json()); } catch (e) { /* not bundled */ }
    lists.push(readLearned());
    for (const list of lists) {
      if (!list || !list.salt || !Array.isArray(list.testers)) continue;
      const p = await fingerprint(list.salt, code);
      const hits = list.testers.filter(t => t.p === p);
      if (hits.length) return { name: hits.length === 1 ? hits[0].n : '' };
    }
    return null;
  }

  // After a successful ONLINE login, remember this tester so they can also
  // log in offline on this device later (even if the bundled list is older).
  async function learnTester(code, name) {
    if (!window.crypto || !crypto.subtle) return;
    let list = readLearned();
    if (!list || !list.salt || !Array.isArray(list.testers)) {
      list = { salt: Array.from(crypto.getRandomValues(new Uint8Array(16)), b => b.toString(16).padStart(2, '0')).join(''), testers: [] };
    }
    const p = await fingerprint(list.salt, code);
    list.testers = list.testers.filter(t => t.p !== p).concat([{ p, n: name || '' }]);
    try { localStorage.setItem(LEARNED_KEY, JSON.stringify(list)); } catch (e) { /* storage full: offline login just won't learn */ }
  }

  // --------------------------------------------------------------------------
  // Status pill + panel (needs <div id="net-status" class="relative"> in the header)
  // --------------------------------------------------------------------------
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function mount() {
    const host = document.getElementById('net-status');
    if (!host) return;
    host.innerHTML = `
      <button type="button" id="net-pill" onclick="AppOffline.togglePanel()" title="Connection and uploads"
        class="flex items-center gap-1.5 bg-white border border-gray-200 px-2 h-7 rounded shadow-sm text-[11px] font-bold text-gray-600 whitespace-nowrap hover:bg-gray-50">
        <span id="net-dot" class="w-2 h-2 rounded-full bg-gray-300"></span><span id="net-text">Checking...</span><span id="net-wait"></span>
      </button>
      <div id="net-panel" class="hidden fixed left-4 right-4 top-[4.75rem] sm:absolute sm:left-auto sm:right-0 sm:top-9 sm:w-72 z-[60] bg-white border border-gray-200 rounded-lg shadow-lg p-3 text-left"></div>`;
    document.addEventListener('click', e => {
      if (!host.contains(e.target)) document.getElementById('net-panel').classList.add('hidden');
    });
    render();
  }

  function render() {
    const dot = document.getElementById('net-dot');
    if (!dot) return;
    const n = waitingCount();
    dot.className = 'w-2 h-2 rounded-full ' + (online === null ? 'bg-gray-300' : online ? 'bg-green-500' : 'bg-gray-400');
    let text = online === null ? 'Checking...' : online ? 'Online' : 'Offline';
    if (syncing) text = 'Uploading...';
    document.getElementById('net-text').textContent = text;
    // Narrow phones show "· 3"; wider screens "· 3 waiting".
    document.getElementById('net-wait').innerHTML = !syncing && n
      ? ` · ${n}<span class="hidden sm:inline"> waiting</span>` : '';
    const panel = document.getElementById('net-panel');
    if (!panel.classList.contains('hidden')) renderPanel();
  }

  function renderPanel() {
    const panel = document.getElementById('net-panel');
    const loggedIn = !!sessionStorage.getItem('interviewer_code');
    const items = loggedIn ? myItems() : readOutbox();
    const others = loggedIn ? readOutbox().length - items.length : 0;
    const status = online ? 'Connected to the pretest server.' : 'No connection to the pretest server. Profiles are saved on this device.';
    const list = items.length
      ? `<ul class="space-y-1.5 max-h-48 overflow-y-auto thin-scroll mb-3">${items.map(i => `
          <li class="border border-gray-100 rounded px-2 py-1.5">
            <p class="text-xs font-bold text-brand-dark truncate">${esc(i.child_name || 'Unnamed child')}</p>
            <p class="text-[10px] text-gray-400">Saved ${esc(new Date(i.saved_at).toLocaleString())}</p>
            ${i.error ? `<p class="text-[10px] text-red-600 mt-0.5">${esc(i.error)}</p>` : ''}
          </li>`).join('')}</ul>`
      : '<p class="text-xs text-gray-500 mb-3">Nothing is waiting to upload.</p>';
    panel.innerHTML = `
      <p class="text-xs font-bold text-brand-dark mb-0.5">${online ? 'Online' : 'Offline'}</p>
      <p class="text-[11px] text-gray-500 mb-3">${status}</p>
      <p class="text-[10px] uppercase tracking-wider font-bold text-gray-400 mb-1.5">Waiting to upload</p>
      ${list}
      ${others ? `<p class="text-[11px] text-gray-500 mb-3">${others} more saved by another interviewer. They upload when that interviewer logs in.</p>` : ''}
      ${loggedIn
        ? `<button type="button" onclick="AppOffline.sync({ manual: true })" ${syncing || !items.length ? 'disabled' : ''}
             class="w-full h-8 bg-brand-blue text-white rounded-md text-xs font-bold hover:bg-brand-blueHover disabled:opacity-50 disabled:cursor-not-allowed">${syncing ? 'Uploading...' : 'Sync now'}</button>`
        : (items.length ? '<p class="text-[11px] text-gray-500">Log in to upload them.</p>' : '')}`;
  }

  function togglePanel() {
    const panel = document.getElementById('net-panel');
    const opening = panel.classList.contains('hidden');
    if (opening) renderPanel();
    panel.classList.toggle('hidden', !opening);
  }

  // --------------------------------------------------------------------------
  // Start: show the pill, check the connection, upload anything waiting
  // --------------------------------------------------------------------------
  function init() {
    mount();
    checkOnline().then(ok => { if (ok) sync(); });
    window.addEventListener('online', () => checkOnline().then(ok => { if (ok) sync(); }));
    window.addEventListener('offline', () => setOnline(false));
    setInterval(() => {
      if (document.hidden) return;
      checkOnline().then(ok => { if (ok && myItems().some(i => !i.error)) sync(); });
    }, PING_EVERY_MS);
  }

  return {
    init, sync, checkOnline, isOnline, togglePanel,
    addToOutbox, removeFromOutbox, readOutbox, myItems, uploadOne, ensureServerSession,
    saveServerLogin, offlineLogin, learnTester,
  };
})();
