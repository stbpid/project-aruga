<?php
/**
 * Pretest Router — the ONLY server endpoint for the v2 pretest desktop app.
 *   - action=ping     (GET)  connection check, no database call
 *   - action=login    (POST) Interviewer Code login (pretest users only)
 *   - action=session  (GET)  is my session still valid?
 *   - action=submit   (POST) save one complete profile to the pretest schema
 *   - action=logout   (POST) end the session
 *
 * Isolation from the live tool:
 *   - Does NOT include lib/auth.php (that checks LIVE sessions).
 *   - Reads and writes only through the public.pretest_* database functions,
 *     which touch only the "pretest" schema (see supabase-migrations/pretest_v2_001_schema.sql).
 *   - Never writes to live tables, including sessions and audit_logs.
 *
 * Identity comes from the verified session (X-Session-ID / X-Interviewer-ID
 * headers), never from the request body.
 */
require_once __DIR__ . '/lib/config.php';

// ----------------------------------------------------------------
// CORS for the Tauri desktop app (the live allowlist in config.php is unchanged)
// ----------------------------------------------------------------
const PRETEST_APP_ORIGINS = [
    'http://tauri.localhost',   // Tauri 2 on Windows
    'https://tauri.localhost',
    'tauri://localhost',        // Tauri on macOS/Linux (dev machines)
];
$pretestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($pretestOrigin, PRETEST_APP_ORIGINS, true)) {
    header('Access-Control-Allow-Origin: ' . $pretestOrigin);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Session-ID, X-Interviewer-ID');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

header('Cache-Control: no-store');

const PRETEST_UUID_RE = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
const PRETEST_MAX_BODY = 262144; // 256 KB is far above a real profile

// ----------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------
function pretestRequireMethod($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        sendResponse(false, 'Method not allowed', null, 405);
    }
}

function pretestReadJson() {
    $raw = file_get_contents('php://input', false, null, 0, PRETEST_MAX_BODY + 1);
    if ($raw === false || strlen($raw) > PRETEST_MAX_BODY) {
        sendResponse(false, 'Request too large', null, 413);
    }
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        sendResponse(false, 'Invalid JSON body', null, 400);
    }
    return $input;
}

/** Validates session headers against pretest.sessions. Returns [sessionId, interviewerId, interviewer]. */
function pretestRequireSession() {
    $sessionId     = $_SERVER['HTTP_X_SESSION_ID']     ?? '';
    $interviewerId = $_SERVER['HTTP_X_INTERVIEWER_ID'] ?? '';
    if (!preg_match(PRETEST_UUID_RE, $sessionId) || !preg_match(PRETEST_UUID_RE, $interviewerId)) {
        sendResponse(false, 'Authentication required.', null, 401);
    }
    $rpc = supabaseRPC('pretest_check_session', [
        'p_session_id'     => $sessionId,
        'p_interviewer_id' => $interviewerId,
    ]);
    if (!$rpc['success']) {
        error_log('pretest session check failed: HTTP ' . $rpc['httpCode']);
        sendResponse(false, 'A server error occurred. Please try again.', null, 500);
    }
    if (empty($rpc['data'])) {
        sendResponse(false, 'Session expired or invalid. Please log in again.', null, 401);
    }
    return [$sessionId, $interviewerId, $rpc['data']];
}

// The v2 form's region list (docs/v2-profiling-preview.html, REGIONS) → the
// region codes used in PRETEST IDs. Exact match, so "Region VIII" can never be
// read as "Region VII".
const PRETEST_V2_REGIONS = [
    'NCR'                             => 'NCR',
    'CAR'                             => 'CAR',
    'Region I (Ilocos)'               => 'R1',
    'Region II (Cagayan Valley)'      => 'R2',
    'Region III (Central Luzon)'      => 'R3',
    'Region IV-A (CALABARZON)'        => 'R4A',
    'MIMAROPA'                        => 'R4B',
    'Region V (Bicol)'                => 'R5',
    'Region VI (Western Visayas)'     => 'R6',
    'NIR (Negros Island Region)'      => 'NIR',
    'Region VII (Central Visayas)'    => 'R7',
    'Region VIII (Eastern Visayas)'   => 'R8',
    'Region IX (Zamboanga Peninsula)' => 'R9',
    'Region X (Northern Mindanao)'    => 'R10',
    'Region XI (Davao)'               => 'R11',
    'Region XII (SOCCSKSARGEN)'       => 'R12',
    'Region XIII (Caraga)'            => 'R13',
    'BARMM'                           => 'BARMM',
];

// v2 answer keys are the pretest.v2_profiles column names: s2_1, s4b_10,
// s6_2a, s2_5_region, s6_5_spec, s13_functional_notes, ...
const PRETEST_ANSWER_KEY_RE = '/^s[0-9]{1,2}[a-z]?_[a-z0-9_]{1,40}$/';
const PRETEST_ARRAY_KEYS    = ['s3_types'];

/**
 * Checks the v2 answers. The app checks every question too, but the server
 * cannot trust the device, so the answer shape and the key identifying answers
 * are checked here. Returns [cleanAnswers, problems] (problems empty = OK).
 */
function pretestValidateAnswers($answers) {
    $errors = [];
    $clean  = [];

    if (!is_array($answers) || count($answers) > 250) {
        return [[], ['answers']];
    }

    foreach ($answers as $key => $value) {
        if (!is_string($key) || !preg_match(PRETEST_ANSWER_KEY_RE, $key)) { $errors[] = 'answers.key'; continue; }
        if (in_array($key, PRETEST_ARRAY_KEYS, true)) {
            if (!is_array($value) || count($value) > 20) { $errors[] = $key; continue; }
            $items = [];
            foreach ($value as $item) {
                if (!is_string($item) || mb_strlen($item) > 100) { $errors[] = $key; continue 2; }
                $items[] = trim($item);
            }
            $clean[$key] = $items;
            continue;
        }
        if (is_int($value) || is_float($value)) $value = (string)$value;
        if (!is_string($value) || mb_strlen($value) > 2000) { $errors[] = $key; continue; }
        $clean[$key] = trim($value);
    }

    $name = $clean['s2_1'] ?? '';
    if (mb_strlen($name) < 2 || mb_strlen($name) > 255) $errors[] = 's2_1';
    $age = $clean['s2_3'] ?? '';
    if (!preg_match('/^[0-9]{1,2}$/', $age) || (int)$age > 17) $errors[] = 's2_3';
    if (!in_array($clean['s2_4'] ?? '', ['Male', 'Female'], true)) $errors[] = 's2_4';
    if (!isset(PRETEST_V2_REGIONS[$clean['s2_5_region'] ?? ''])) $errors[] = 's2_5_region';
    foreach (['s2_5_province', 's2_5_city'] as $k) {
        if (($clean[$k] ?? '') === '') $errors[] = $k;
    }
    foreach (['s9_1', 's9_2'] as $k) {
        if (($clean[$k] ?? '') !== '' && (!is_numeric($clean[$k]) || (float)$clean[$k] < 0 || (float)$clean[$k] > 9999999999)) $errors[] = $k;
    }

    return [$clean, array_values(array_unique($errors))];
}

// ----------------------------------------------------------------
// Routes
// ----------------------------------------------------------------
$action = $_GET['action'] ?? '';

switch ($action) {

    // ================================================================
    // action=ping — lets the app tell "no internet" from "server error"
    // ================================================================
    case 'ping': {
        sendResponse(true, 'ok', ['server_time' => date('c')]);
        break;
    }

    // ================================================================
    // action=login
    // ================================================================
    case 'login': {
        pretestRequireMethod('POST');
        $input = pretestReadJson();
        $code  = strtoupper(trim((string)($input['interviewer_code'] ?? '')));
        if (!preg_match('/^[A-Z0-9]{8}$/', $code)) {
            sendResponse(false, 'Invalid interviewer code format. Must be 8 alphanumeric characters.', null, 400);
        }

        $rpc = supabaseRPC('pretest_login', [
            'p_code'       => $code,
            'p_ip'         => getUserIP(),
            'p_user_agent' => getUserAgent(),
        ]);
        if (!$rpc['success'] || !is_array($rpc['data'])) {
            error_log('pretest login failed: HTTP ' . $rpc['httpCode']);
            sendResponse(false, 'A server error occurred. Please try again.', null, 500);
        }

        $r = $rpc['data'];
        if (empty($r['ok'])) {
            $err = $r['error'] ?? '';
            if ($err === 'rate_limited') sendResponse(false, 'Too many failed login attempts. Please try again in 15 minutes.', null, 429);
            if ($err === 'invalid_format') sendResponse(false, 'Invalid interviewer code format.', null, 400);
            sendResponse(false, 'Invalid interviewer code, inactive account, or not enrolled in the pretest.', null, 401);
        }

        $int = $r['interviewer'] ?? [];
        sendResponse(true, 'Login successful', [
            'session_id'       => $r['session_id'],
            'started_at'       => $r['started_at'],
            'expires_at'       => $r['expires_at'],
            'interviewer_id'   => $int['id'] ?? null,
            'interviewer_code' => $int['interviewer_code'] ?? null,
            'full_name'        => $int['full_name'] ?? null,
            'region'           => $int['region'] ?? null,
            'province'         => $int['province'] ?? null,
            'office'           => $int['office'] ?? null,
            'position'         => $int['position'] ?? null,
        ]);
        break;
    }

    // ================================================================
    // action=session
    // ================================================================
    case 'session': {
        pretestRequireMethod('GET');
        [, , $interviewer] = pretestRequireSession();
        sendResponse(true, 'Session valid', $interviewer);
        break;
    }

    // ================================================================
    // action=submit
    // ================================================================
    case 'submit': {
        pretestRequireMethod('POST');
        [$sessionId, $interviewerId] = pretestRequireSession();
        $in = pretestReadJson();

        $assessmentId = (string)($in['assessment_id'] ?? '');
        if (!preg_match(PRETEST_UUID_RE, $assessmentId)) {
            sendResponse(false, 'Missing or invalid record ID.', null, 400);
        }

        [$answers, $problems] = pretestValidateAnswers($in['answers'] ?? null);
        if ($problems) {
            sendResponse(false, 'Some required answers are missing or invalid.', ['fields' => $problems], 422);
        }

        $createdOnDevice = null;
        if (!empty($in['created_on_device_at']) && strtotime((string)$in['created_on_device_at']) !== false) {
            $createdOnDevice = date('c', strtotime((string)$in['created_on_device_at']));
        }
        $appVersion = preg_match('/^[0-9A-Za-z.\-+]{1,32}$/', (string)($in['app_version'] ?? '')) ? $in['app_version'] : null;

        $feedback = [];
        foreach (array_slice(is_array($in['feedback'] ?? null) ? $in['feedback'] : [], 0, 50) as $f) {
            $comment = trim((string)($f['comment'] ?? ''));
            if ($comment === '') continue;
            $step = isset($f['step']) && is_numeric($f['step']) && $f['step'] >= 1 && $f['step'] <= 12 ? (int)$f['step'] : null;
            $feedback[] = ['step' => $step, 'comment' => mb_substr($comment, 0, 2000)];
        }

        $data = [
            'assessment_id'        => $assessmentId,
            'created_on_device_at' => $createdOnDevice,
            'app_version'          => $appVersion,
            'answers'              => $answers,
            'feedback'             => $feedback,
        ];

        $rpc = supabaseRPC('pretest_submit_assessment', [
            'p_session_id'     => $sessionId,
            'p_interviewer_id' => $interviewerId,
            'p_region_code'    => PRETEST_V2_REGIONS[$answers['s2_5_region']],
            'p_data'           => $data,
        ]);

        if (!$rpc['success'] || !is_array($rpc['data'])) {
            // Log only the database error code, never the submitted answers.
            error_log('pretest submit failed: HTTP ' . $rpc['httpCode'] . ' code=' . ($rpc['data']['code'] ?? '-'));
            sendResponse(false, 'Failed to save the pretest profile. Please try again.', null, 500);
        }

        $r = $rpc['data'];
        if (empty($r['ok'])) {
            $err = $r['error'] ?? '';
            if ($err === 'session_invalid') sendResponse(false, 'Session expired or invalid. Please log in again.', null, 401);
            if ($err === 'id_conflict')     sendResponse(false, 'This record ID belongs to another interviewer.', null, 409);
            sendResponse(false, 'Failed to save the pretest profile.', null, 400);
        }

        sendResponse(true, $r['duplicate'] ? 'Already uploaded' : 'Pretest profile saved', [
            'assessment_id' => $r['assessment_id'],
            'aruga_id'      => $r['aruga_id'],
            'duplicate'     => (bool)$r['duplicate'],
        ]);
        break;
    }

    // ================================================================
    // action=logout
    // ================================================================
    case 'logout': {
        pretestRequireMethod('POST');
        $sessionId     = $_SERVER['HTTP_X_SESSION_ID']     ?? '';
        $interviewerId = $_SERVER['HTTP_X_INTERVIEWER_ID'] ?? '';
        if (preg_match(PRETEST_UUID_RE, $sessionId) && preg_match(PRETEST_UUID_RE, $interviewerId)) {
            supabaseRPC('pretest_logout', [
                'p_session_id'     => $sessionId,
                'p_interviewer_id' => $interviewerId,
            ]);
        }
        sendResponse(true, 'Logged out');
        break;
    }

    default: {
        sendResponse(false, 'Unknown action', null, 400);
    }
}
