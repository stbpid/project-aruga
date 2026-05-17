<?php
// Ensure no output before headers
ob_start();
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
}

ob_end_clean();
requireAuth();
?>
