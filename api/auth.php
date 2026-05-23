<?php
/**
 * Authentication middleware for all protected API endpoints.
 * Include at the top of any endpoint that requires a valid session:
 *   require_once __DIR__ . '/auth.php';  (adjust path for subdirectories)
 *
 * Expects HTTP headers:
 *   X-Session-ID:     <session UUID>
 *   X-Interviewer-ID: <interviewer UUID>
 *
 * On success, sets globals:
 *   $authSession      - the sessions row
 *   $authInterviewer  - the interviewers row
 *
 * On failure, responds 401 and exits.
 */

// config.php is already included by the calling endpoint — do not re-include it.
// SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY constants and supabaseRequest() are available.

if (!function_exists('requireAuth')) {
    function requireAuth() {
        global $authSession, $authInterviewer;

        $sessionId     = $_SERVER['HTTP_X_SESSION_ID']     ?? '';
        $interviewerId = $_SERVER['HTTP_X_INTERVIEWER_ID'] ?? '';

        if (empty($sessionId) || empty($interviewerId)) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Authentication required.']);
            exit;
        }

        // Validate UUID format to prevent injection
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        if (!preg_match($uuidPattern, $sessionId) || !preg_match($uuidPattern, $interviewerId)) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid session.']);
            exit;
        }

        // Look up session in database
        $endpoint = 'sessions?id=eq.' . urlencode($sessionId)
                  . '&interviewer_id=eq.' . urlencode($interviewerId)
                  . '&status=eq.active'
                  . '&select=*'
                  . '&limit=1';

        $result = supabaseRequest('GET', $endpoint);

        if (!$result['success'] || empty($result['data'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Session expired or invalid. Please log in again.']);
            exit;
        }

        $authSession = $result['data'][0];

        // Also fetch the interviewer to make role available to endpoints
        $intResult = supabaseRequest('GET', 'interviewers?id=eq.' . urlencode($interviewerId) . '&status=eq.active&select=*&limit=1');

        if (!$intResult['success'] || empty($intResult['data'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Account not found or inactive.']);
            exit;
        }

        $authInterviewer = $intResult['data'][0];
    }

    // Require one of the given roles — call after requireAuth()
    function requireRole(array $allowedRoles) {
        global $authInterviewer;
        $role = $authInterviewer['dashboard_role'] ?? '';
        if (!in_array($role, $allowedRoles, true)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
    }

    // Require that the user's assigned region matches the given region string
    function requireRegion(string $requestedRegion) {
        global $authInterviewer;
        $role = $authInterviewer['dashboard_role'] ?? '';
        // admin sees all regions
        if ($role === 'admin') return;
        $userRegion = trim($authInterviewer['region'] ?? '');
        // Normalize both sides: strip HTML entities, collapse dash variants, trim
        $normalize = function(string $r): string {
            $r = html_entity_decode($r, ENT_QUOTES, 'UTF-8');
            $r = trim($r);
            // Treat en-dash, em-dash, and hyphen as equivalent separators
            $r = preg_replace('/\s*[\x{2013}\x{2014}-]\s*/u', ' - ', $r);
            return mb_strtolower($r);
        };
        if (empty($userRegion) || $normalize($userRegion) !== $normalize($requestedRegion)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
    }

    // Require that the interviewer_code param matches the logged-in user's own code
    function requireOwnCode(string $requestedCode) {
        global $authInterviewer;
        $role = $authInterviewer['dashboard_role'] ?? '';
        if ($role === 'admin') return;
        $ownCode = $authInterviewer['interviewer_code'] ?? '';
        if (empty($ownCode) || $ownCode !== $requestedCode) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
    }
}

requireAuth();
?>
