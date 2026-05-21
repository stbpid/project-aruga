<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://projectaruga.com');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Session-ID, X-Interviewer-ID');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$userId = $authInterviewer['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'success' => true,
        'data' => [
            'full_name'      => $authInterviewer['full_name']      ?? '',
            'position'       => $authInterviewer['position']       ?? '',
            'office'         => $authInterviewer['office']         ?? '',
            'email'          => $authInterviewer['email']          ?? '',
            'dashboard_role' => $authInterviewer['dashboard_role'] ?? '',
        ]
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    $updates = [];

    if (isset($body['full_name'])) {
        $name = trim($body['full_name']);
        if (strlen($name) < 2 || strlen($name) > 120) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Full name must be 2–120 characters.']);
            exit;
        }
        $updates['full_name'] = $name;
    }

    if (isset($body['position'])) {
        $pos = trim($body['position']);
        if (strlen($pos) > 120) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Position must be 120 characters or fewer.']);
            exit;
        }
        $updates['position'] = $pos;
    }

    // Office only editable for central_office role
    if (isset($body['office'])) {
        if (($authInterviewer['dashboard_role'] ?? '') !== 'central_office') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You are not allowed to change your office.']);
            exit;
        }
        $off = trim($body['office']);
        if (strlen($off) > 120) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Office must be 120 characters or fewer.']);
            exit;
        }
        $updates['office'] = $off;
    }

    // Password change
    if (!empty($body['new_password'])) {
        $newPw = $body['new_password'];
        $curPw = $body['current_password'] ?? '';

        if (empty($curPw)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Current password is required.']);
            exit;
        }

        // Verify current password
        $storedHash = $authInterviewer['password_hash'] ?? '';
        $valid = false;
        $rpc = supabaseRPC('verify_password', ['input_password' => $curPw, 'stored_hash' => $storedHash]);
        if ($rpc['success'] && isset($rpc['data'])) $valid = (bool)$rpc['data'];
        if (!$valid) {
            $compatible = preg_replace('/^\$2a\$/', '$2y$', $storedHash);
            $valid = password_verify($curPw, $compatible);
        }
        if (!$valid) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit;
        }

        if (strlen($newPw) < 8) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
            exit;
        }
        if (!preg_match('/[A-Z]/', $newPw)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New password must contain at least one uppercase letter.']);
            exit;
        }
        if (!preg_match('/[0-9]/', $newPw)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New password must contain at least one number.']);
            exit;
        }
        if (!preg_match('/[^A-Za-z0-9]/', $newPw)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New password must contain at least one special character.']);
            exit;
        }

        $updates['password_hash'] = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    if (empty($updates)) {
        echo json_encode(['success' => true, 'message' => 'No changes.']);
        exit;
    }

    $res = supabaseRequest('PATCH', 'interviewers?id=eq.' . urlencode($userId), $updates);
    if (!$res['success']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save profile.']);
        exit;
    }

    $changedFields = array_keys($updates);
    if (in_array('password_hash', $changedFields)) {
        $changedFields = array_diff($changedFields, ['password_hash']);
        $changedFields[] = 'password';
    }
    logAudit('update', 'interviewers', $userId, null,
        ['event' => 'profile_updated', 'fields' => array_values($changedFields)],
        $userId
    );

    echo json_encode(['success' => true, 'data' => array_filter($updates, fn($k) => $k !== 'password_hash', ARRAY_FILTER_USE_KEY)]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
