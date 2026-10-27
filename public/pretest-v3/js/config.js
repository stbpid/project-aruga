// ============================================================================
// PROJECT ARUGA - PRETEST v3 APP SETTINGS (desktop, Android and web)
// No keys or secrets belong here: the app only talks to the pretest API.
// ============================================================================

// Desktop/Android (Tauri) pages load from tauri.localhost; the web copy loads
// from projectaruga.com/pretestv3/ and calls the API on its own site.
const ARUGA_IS_NATIVE_APP = location.hostname === 'tauri.localhost' || location.protocol === 'tauri:';

window.ARUGA_CONFIG = {
  // 3.x = v3 question set (pretest.v3_profiles).
  APP_VERSION: '3.0.0',

  // Sent as &v=3 on every API call, so pretest-router.php uses the v3 tester
  // list and database functions.
  FORM_VERSION: '3',

  // Where api/pretest-router.php is hosted.
  // Production: https://projectaruga.com
  // Local testing (scripts/dev-api.sh): http://localhost:8080
  API_BASE: ARUGA_IS_NATIVE_APP ? 'https://projectaruga.com' : '',

  IS_NATIVE_APP: ARUGA_IS_NATIVE_APP,

  // Shows the "fill sample answers" button in the form header. Developer use
  // only: keep false in installers so testers never upload made-up profiles.
  SHOW_SAMPLE_FILL: true,
};

// Web only: keep the app's files on this device so it opens without internet.
if (!ARUGA_IS_NATIVE_APP && 'serviceWorker' in navigator && location.protocol === 'https:') {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('sw.js').catch(() => { /* app still works online */ });
  });
}
