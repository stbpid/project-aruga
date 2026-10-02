#!/usr/bin/env bash
# Runs the pretest API on this computer (http://localhost:8080) so the desktop
# app can be tested before api/pretest-router.php is deployed to Vercel.
#
# Needs:
#   - PHP with the curl extension enabled (php.ini: extension=curl)
#   - SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY set in this terminal only.
#     Never save the key in a file inside the repo.
#   - In desktop-app/src/js/config.js set API_BASE to 'http://localhost:8080'
#     while testing (and change it back before building the installer).
#
# Usage (from the repo root):
#   export SUPABASE_URL="https://<project>.supabase.co"
#   export SUPABASE_SERVICE_ROLE_KEY="<service role key>"
#   bash desktop-app/scripts/dev-api.sh
set -euo pipefail

if [ -z "${SUPABASE_URL:-}" ] || [ -z "${SUPABASE_SERVICE_ROLE_KEY:-}" ]; then
  echo "Set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY in this terminal first." >&2
  exit 1
fi
if ! php -m | grep -qi '^curl$'; then
  echo "PHP's curl extension is not enabled. Enable 'extension=curl' in php.ini." >&2
  exit 1
fi

cd "$(dirname "$0")/../.."
echo "Pretest API running at http://localhost:8080/api/pretest-router.php (Ctrl+C to stop)"
php -S localhost:8080 -t .
