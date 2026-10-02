// ============================================================================
// PROJECT ARUGA - PRETEST API CLIENT
// The only code in the app that talks to the server (api/pretest-router.php).
// ============================================================================

const PretestApi = {
  url(action) {
    return window.ARUGA_CONFIG.API_BASE + '/api/pretest-router.php?action=' + encodeURIComponent(action);
  },

  /**
   * Calls the pretest API. Never throws.
   * Returns { ok, status, json }. status 0 means the server could not be
   * reached (no internet).
   */
  async call(action, { method = 'GET', body } = {}) {
    const headers = {};
    const sessionId     = sessionStorage.getItem('session_id');
    const interviewerId = sessionStorage.getItem('interviewer_id');
    if (sessionId)     headers['X-Session-ID']     = sessionId;
    if (interviewerId) headers['X-Interviewer-ID'] = interviewerId;
    if (body !== undefined) headers['Content-Type'] = 'application/json';

    let res;
    try {
      res = await fetch(this.url(action), {
        method,
        headers,
        cache: 'no-store',
        body: body !== undefined ? JSON.stringify(body) : undefined,
      });
    } catch (e) {
      return { ok: false, status: 0, json: null };
    }

    let json = null;
    try { json = await res.json(); } catch (e) { /* non-JSON error page */ }
    return { ok: res.ok && !!(json && json.success), status: res.status, json };
  },

  async logout() {
    await this.call('logout', { method: 'POST', body: {} });
    sessionStorage.clear();
  },

  // Random UUID (v4) for each new profile, created on this device.
  newRecordId() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }
    const b = window.crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    const h = Array.from(b, x => x.toString(16).padStart(2, '0')).join('');
    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
  },
};
