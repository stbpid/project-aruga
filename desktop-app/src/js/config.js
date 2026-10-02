// ============================================================================
// PROJECT ARUGA - PRETEST DESKTOP APP SETTINGS
// No keys or secrets belong here: the app only talks to the pretest API.
// ============================================================================

window.ARUGA_CONFIG = {
  APP_VERSION: '0.1.0',

  // Where api/pretest-router.php is hosted.
  // Production: https://projectaruga.com
  // Local testing (desktop-app/scripts/dev-api.sh): http://localhost:8080
  API_BASE: 'https://projectaruga.com',
};
