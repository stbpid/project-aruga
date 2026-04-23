<?php
/**
 * Validate Interviewer Code and Create Session
 * Endpoint: POST /api/validate-interviewer.php
 * 
 * Request Body:
 * {
 *   "interviewer_code": "R5001JAA"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Login successful",
 *   "data": {
 *     "interviewer_id": "uuid",
 *     "interviewer_code": "R5001JAA",
 *     "full_name": "Juan Antonio Alcantara",
 *     "region": "Region V (Bicol)",
 *     "session_id": "uuid",
 *     "started_at": "2026-04-23T10:30:00Z"
 *   }
 * }
 */

require_once 'config.php';

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed. Use POST.', null, 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['interviewer_code']) || empty(trim($input['interviewer_code']))) {
    sendResponse(false, 'Interviewer code is required', null, 400);
}

$interviewerCode = strtoupper(trim($input['interviewer_code']));

// Validate format: 8 characters, alphanumeric
if (!preg_match('/^[A-Z0-9]{8}$/', $interviewerCode)) {
    sendResponse(false, 'Invalid interviewer code format. Must be 8 alphanumeric characters.', null, 400);
}

// Query Supabase for interviewer
$endpoint = "interviewers?interviewer_code=eq.$interviewerCode&status=eq.active&select=*";
$result = supabaseRequest('GET', $endpoint);

if (!$result['success']) {
    sendResponse(false, 'Database error: ' . ($result['error'] ?? 'Unknown error'), null, 500);
}

// Check if interviewer exists and is active
if (empty($result['data']) || count($result['data']) === 0) {
    sendResponse(false, 'Invalid interviewer code or account is inactive', null, 401);
}

$interviewer = $result['data'][0];

// Create session record
$sessionData = [
    'interviewer_id' => $interviewer['id'],
    'interviewer_code' => $interviewer['interviewer_code'],
    'started_at' => date('c'),
    'status' => 'active',
    'ip_address' => getUserIP(),
    'user_agent' => getUserAgent()
];

$sessionResult = supabaseRequest('POST', 'sessions', $sessionData);

if (!$sessionResult['success']) {
    sendResponse(false, 'Failed to create session', null, 500);
}

$session = $sessionResult['data'][0] ?? null;

if (!$session) {
    sendResponse(false, 'Session creation failed', null, 500);
}

// Return success with interviewer and session data
sendResponse(true, 'Login successful', [
    'interviewer_id' => $interviewer['id'],
    'interviewer_code' => $interviewer['interviewer_code'],
    'full_name' => $interviewer['full_name'],
    'region' => $interviewer['region'],
    'province' => $interviewer['province'],
    'office' => $interviewer['office'] ?? null,
    'position' => $interviewer['position'] ?? null,
    'session_id' => $session['id'],
    'started_at' => $session['started_at']
], 200);
?>