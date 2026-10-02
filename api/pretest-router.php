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

function pretestSafe($arr, $key, $default = null) {
    return (is_array($arr) && isset($arr[$key]) && $arr[$key] !== '' && $arr[$key] !== null) ? $arr[$key] : $default;
}

function pretestBool($arr, $key) {
    return (bool)(is_array($arr) ? ($arr[$key] ?? false) : false);
}

function pretestFloat($arr, $key) {
    $v = is_array($arr) ? ($arr[$key] ?? 0) : 0;
    return is_numeric($v) ? (float)$v : 0.0;
}

function pretestSanitizeArray($value, array $allowed, $fieldName) {
    if (!is_array($value)) return [];
    if (count($value) > 20) {
        sendResponse(false, "Too many values for {$fieldName}.", null, 400);
    }
    foreach ($value as $item) {
        if (!is_string($item) || !in_array($item, $allowed, true)) {
            sendResponse(false, "Invalid value for {$fieldName}.", null, 400);
        }
    }
    return array_values($value);
}

// Same mapping as the live submit-assessment, so PRETEST IDs use the same region codes.
function pretestRegionCode($region) {
    if (!$region) return 'XX';
    $map = [
        'national capital' => 'NCR', 'ncr' => 'NCR',
        'cordillera' => 'CAR',       'car' => 'CAR',
        'bangsamoro' => 'BARMM',     'barmm' => 'BARMM',
        'region i '  => 'R1',  'region 1'  => 'R1',
        'region ii ' => 'R2',  'region 2'  => 'R2',
        'region iii' => 'R3',  'region 3'  => 'R3',
        'region iv-a'=> 'R4A', 'calabarzon'=> 'R4A',
        'region iv-b'=> 'R4B', 'mimaropa'  => 'R4B',
        'region iv'  => 'R4',  'region 4'  => 'R4',
        'region v '  => 'R5',  'region 5'  => 'R5',  'bicol' => 'R5',
        'region vi ' => 'R6',  'region 6'  => 'R6',
        'region vii' => 'R7',  'region 7'  => 'R7',
        'region viii'=> 'R8',  'region 8'  => 'R8',
        'region ix ' => 'R9',  'region 9'  => 'R9',
        'region x '  => 'R10', 'region 10' => 'R10',
        'region xi ' => 'R11', 'region 11' => 'R11',
        'region xii' => 'R12', 'region 12' => 'R12',
        'caraga'     => 'R13', 'region xiii'=>'R13', 'region 13' => 'R13',
    ];
    $lower = strtolower(trim($region));
    foreach ($map as $pattern => $code) {
        if (strpos($lower, $pattern) !== false) return $code;
    }
    return 'XX';
}

/**
 * Server-side checks of the required answers. The app checks these too, but
 * the server cannot trust the device. Returns a list of problems (empty = OK).
 */
function pretestValidate(array $in) {
    $errors = [];
    $nameRe = "/^[A-Za-zÑñ\\s\\-']+$/u";

    $resp = $in['respondent'] ?? [];
    $respName = trim((string)($resp['full_name'] ?? ''));
    if (mb_strlen($respName) < 2 || mb_strlen($respName) > 255 || !preg_match($nameRe, $respName)) $errors[] = 'respondent.full_name';
    if (!pretestSafe($resp, 'relationship_to_child')) $errors[] = 'respondent.relationship_to_child';

    $pq = $in['pre_qualification'] ?? [];
    if (!empty($pq['is_4ps_member']) && !preg_match('/^[A-Za-z0-9]{13,18}$/', (string)($pq['household_id'] ?? ''))) {
        $errors[] = 'pre_qualification.household_id';
    }

    $child = $in['child'] ?? [];
    foreach (['first_name', 'last_name'] as $k) {
        $v = trim((string)($child[$k] ?? ''));
        if (mb_strlen($v) < 2 || mb_strlen($v) > 100 || !preg_match($nameRe, $v)) $errors[] = "child.$k";
    }
    foreach (['region', 'province', 'city_municipality', 'barangay'] as $k) {
        if (!pretestSafe($child, $k)) $errors[] = "child.$k";
    }
    $street = trim((string)($child['street_address'] ?? ''));
    if (mb_strlen($street) < 5 || mb_strlen($street) > 255) $errors[] = 'child.street_address';
    $dob = (string)($child['date_of_birth'] ?? '');
    $dobDate = DateTime::createFromFormat('!Y-m-d', $dob);
    if (!$dobDate || $dobDate->format('Y-m-d') !== $dob || $dobDate > new DateTime('tomorrow')) $errors[] = 'child.date_of_birth';

    $members = $in['family_members'] ?? [];
    if (!is_array($members) || count($members) < 1 || count($members) > 50) {
        $errors[] = 'family_members';
    } else {
        foreach ($members as $i => $m) {
            if (!is_array($m)) { $errors[] = "family_members[$i]"; continue; }
            $n = trim((string)($m['full_name'] ?? ''));
            if (mb_strlen($n) < 2 || !preg_match($nameRe, $n)) $errors[] = "family_members[$i].full_name";
            if (isset($m['age']) && $m['age'] !== null && (!is_numeric($m['age']) || $m['age'] < 0 || $m['age'] > 150)) $errors[] = "family_members[$i].age";
        }
    }

    $ec = $in['economic_capacity'] ?? [];
    if (!pretestSafe($ec, 'primary_income_source')) $errors[] = 'economic_capacity.primary_income_source';

    if (!in_array($in['readiness_score'] ?? null, ['severe', 'moderate', 'low', 'stable'], true)) $errors[] = 'readiness_score';

    return $errors;
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

        $problems = pretestValidate($in);
        if ($problems) {
            sendResponse(false, 'Some required answers are missing or invalid.', ['fields' => $problems], 422);
        }

        $createdOnDevice = null;
        if (!empty($in['created_on_device_at']) && strtotime((string)$in['created_on_device_at']) !== false) {
            $createdOnDevice = date('c', strtotime((string)$in['created_on_device_at']));
        }
        $appVersion = preg_match('/^[0-9A-Za-z.\-+]{1,32}$/', (string)($in['app_version'] ?? '')) ? $in['app_version'] : null;

        $VALID_DISABILITIES = [
            'None', 'Visual Disability', 'Hearing Disability',
            'Speech and Language Impairment', 'Orthopedic / Physical Disability',
            'Mental / Intellectual Disability', 'Learning Disability',
            'Psychosocial Disability', 'Disability Resulting from a Chronic Illness',
            'Multiple Disabilities', 'Other (specify)',
        ];
        $VALID_ILLNESSES = [
            'None', 'Cancer', 'Heart Disease', 'Kidney Disease', 'Diabetes',
            'Respiratory Disease', 'Neurological Disorder', 'Blood Disorder',
            'Chronic Illness', 'Others',
        ];

        // Normalise exactly like the live submit-assessment does.
        $pq    = $in['pre_qualification'] ?? [];
        $resp  = $in['respondent'] ?? [];
        $child = $in['child'] ?? [];
        $ceh   = $in['child_education_health'] ?? [];
        $se    = $in['socio_economic'] ?? [];
        $hi    = $in['health_info'] ?? [];
        $ei    = $in['education_info'] ?? [];
        $ec    = $in['economic_capacity'] ?? [];
        $sa    = $in['service_availment'] ?? [];
        $an    = $in['assessment_notes'] ?? [];

        $members = [];
        foreach (($in['family_members'] ?? []) as $m) {
            $members[] = [
                'member_number'          => (int)($m['member_number'] ?? 1),
                'full_name'              => trim((string)($m['full_name'] ?? '')),
                'relationship_to_head'   => pretestSafe($m, 'relationship_to_head'),
                'is_solo_parent'         => pretestBool($m, 'is_solo_parent'),
                'is_authorized_claimant' => pretestBool($m, 'is_authorized_claimant'),
                'civil_status'           => pretestSafe($m, 'civil_status'),
                'age'                    => isset($m['age']) && is_numeric($m['age']) ? (int)$m['age'] : null,
                'sex'                    => pretestSafe($m, 'sex'),
                'occupation'             => pretestSafe($m, 'occupation'),
                'occupation_class'       => pretestSafe($m, 'occupation_class'),
                'disabilities'           => pretestSanitizeArray($m['disabilities'] ?? [], $VALID_DISABILITIES, 'member disabilities'),
                'critical_illnesses'     => pretestSanitizeArray($m['critical_illnesses'] ?? [], $VALID_ILLNESSES, 'member critical_illnesses'),
            ];
        }

        $feedback = [];
        foreach (array_slice(is_array($in['feedback'] ?? null) ? $in['feedback'] : [], 0, 50) as $f) {
            $comment = trim((string)($f['comment'] ?? ''));
            if ($comment === '') continue;
            $step = isset($f['step']) && is_numeric($f['step']) && $f['step'] >= 1 && $f['step'] <= 11 ? (int)$f['step'] : null;
            $feedback[] = ['step' => $step, 'comment' => mb_substr($comment, 0, 2000)];
        }

        $monthlyIncome = (isset($ec['monthly_income']) && is_numeric($ec['monthly_income']) && (float)$ec['monthly_income'] > 0)
            ? (float)$ec['monthly_income'] : null;

        $data = [
            'assessment_id'        => $assessmentId,
            'readiness_score'      => $in['readiness_score'],
            'created_on_device_at' => $createdOnDevice,
            'app_version'          => $appVersion,
            'pre_qualification' => [
                'is_4ps_member' => pretestBool($pq, 'is_4ps_member'),
                'household_id'  => pretestSafe($pq, 'household_id'),
            ],
            'respondent' => [
                'full_name'             => trim((string)($resp['full_name'] ?? '')),
                'relationship_to_child' => pretestSafe($resp, 'relationship_to_child'),
                'email'                 => pretestSafe($resp, 'email'),
                'contact_number'        => pretestSafe($resp, 'contact_number'),
            ],
            'child' => [
                'first_name'          => trim((string)($child['first_name'] ?? '')),
                'middle_name'         => pretestSafe($child, 'middle_name'),
                'last_name'           => trim((string)($child['last_name'] ?? '')),
                'name_extension'      => pretestSafe($child, 'name_extension'),
                'region'              => pretestSafe($child, 'region'),
                'province'            => pretestSafe($child, 'province'),
                'city_municipality'   => pretestSafe($child, 'city_municipality'),
                'barangay'            => pretestSafe($child, 'barangay'),
                'street_address'      => pretestSafe($child, 'street_address'),
                'contact_number'      => pretestSafe($child, 'contact_number'),
                'date_of_birth'       => pretestSafe($child, 'date_of_birth'),
                'sex'                 => pretestSafe($child, 'sex'),
                'religion'            => pretestSafe($child, 'religion'),
                'religion_other'      => pretestSafe($child, 'religion_other'),
                'ip_membership'       => pretestSafe($child, 'ip_membership'),
                'ip_membership_other' => pretestSafe($child, 'ip_membership_other'),
            ],
            'child_education_health' => [
                'highest_education'       => pretestSafe($ceh, 'highest_education'),
                'highest_education_other' => pretestSafe($ceh, 'highest_education_other'),
                'disabilities'            => pretestSanitizeArray($ceh['disabilities'] ?? [], $VALID_DISABILITIES, 'disabilities'),
                'critical_illnesses'      => pretestSanitizeArray($ceh['critical_illnesses'] ?? [], $VALID_ILLNESSES, 'critical_illnesses'),
                'illness_other'           => pretestSafe($ceh, 'illness_other'),
            ],
            'family_members' => $members,
            'socio_economic' => [
                'housing_materials'               => pretestSafe($se, 'housing_materials'),
                'housing_materials_other'         => pretestSafe($se, 'housing_materials_other'),
                'tenure_status'                   => pretestSafe($se, 'tenure_status'),
                'tenure_status_other'             => pretestSafe($se, 'tenure_status_other'),
                'has_accessibility_modifications' => pretestBool($se, 'has_accessibility_modifications'),
                'modification_details'            => pretestSafe($se, 'modification_details'),
                'electricity_source'              => pretestSafe($se, 'electricity_source'),
                'electricity_source_other'        => pretestSafe($se, 'electricity_source_other'),
                'water_source'                    => pretestSafe($se, 'water_source'),
                'water_source_other'              => pretestSafe($se, 'water_source_other'),
                'toilet_type'                     => pretestSafe($se, 'toilet_type'),
                'toilet_type_other'               => pretestSafe($se, 'toilet_type_other'),
                'is_toilet_accessible'            => pretestBool($se, 'is_toilet_accessible'),
                'garbage_disposal'                => pretestSafe($se, 'garbage_disposal'),
                'garbage_disposal_other'          => pretestSafe($se, 'garbage_disposal_other'),
            ],
            'health_info' => [
                'has_all_vaccinations'          => pretestBool($hi, 'has_all_vaccinations'),
                'has_ongoing_health_conditions' => pretestBool($hi, 'has_ongoing_health_conditions'),
                'health_conditions_details'     => pretestSafe($hi, 'health_conditions_details'),
                'expense_food'                  => pretestFloat($hi, 'expense_food'),
                'expense_medication'            => pretestFloat($hi, 'expense_medication'),
                'expense_therapy'               => pretestFloat($hi, 'expense_therapy'),
                'expense_hygiene'               => pretestFloat($hi, 'expense_hygiene'),
                'expense_assistive_device'      => pretestFloat($hi, 'expense_assistive_device'),
                'expense_other'                 => pretestFloat($hi, 'expense_other'),
                'availed_services_6months'      => pretestBool($hi, 'availed_services_6months'),
                'availed_services_details'      => pretestSafe($hi, 'availed_services_details'),
                'is_facility_accessible'        => pretestBool($hi, 'is_facility_accessible'),
                'has_barriers_to_healthcare'    => pretestBool($hi, 'has_barriers_to_healthcare'),
                'healthcare_barriers_details'   => pretestSafe($hi, 'healthcare_barriers_details'),
            ],
            'education_info' => [
                'is_currently_enrolled'          => pretestBool($ei, 'is_currently_enrolled'),
                'grade_year_level'               => pretestSafe($ei, 'grade_year_level'),
                'not_enrolled_reason'            => pretestSafe($ei, 'not_enrolled_reason'),
                'has_accessibility_features'     => pretestBool($ei, 'has_accessibility_features'),
                'accessibility_features_details' => pretestSafe($ei, 'accessibility_features_details'),
                'has_sped_programs'              => pretestBool($ei, 'has_sped_programs'),
                'sped_programs_details'          => pretestSafe($ei, 'sped_programs_details'),
                'receives_learning_support'      => pretestBool($ei, 'receives_learning_support'),
                'learning_support_details'       => pretestSafe($ei, 'learning_support_details'),
            ],
            'economic_capacity' => [
                'primary_income_source' => pretestSafe($ec, 'primary_income_source'),
                'monthly_income'        => $monthlyIncome,
                'income_classification' => pretestSafe($ec, 'income_classification'),
                'are_parents_employed'  => pretestBool($ec, 'are_parents_employed'),
                'employment_details'    => pretestSafe($ec, 'employment_details'),
            ],
            'service_availment' => [
                'receives_financial_assistance' => pretestBool($sa, 'receives_financial_assistance'),
                'financial_assistance_details'  => pretestSafe($sa, 'financial_assistance_details'),
                'is_aware_of_social_services'   => pretestBool($sa, 'is_aware_of_social_services'),
                'awareness_details'             => pretestSafe($sa, 'awareness_details'),
                'has_availed_services'          => pretestBool($sa, 'has_availed_services'),
                'availed_services_details'      => pretestSafe($sa, 'availed_services_details'),
                'service_challenges'            => pretestSafe($sa, 'service_challenges'),
                'service_challenges_other'      => pretestSafe($sa, 'service_challenges_other'),
            ],
            'assessment_notes' => [
                'strengths'           => pretestSafe($an, 'strengths'),
                'assessment_details'  => pretestSafe($an, 'assessment_details'),
                'recommended_actions' => pretestSafe($an, 'recommended_actions'),
                'readiness_score'     => pretestSafe($an, 'readiness_score'),
            ],
            'feedback' => $feedback,
        ];

        $rpc = supabaseRPC('pretest_submit_assessment', [
            'p_session_id'     => $sessionId,
            'p_interviewer_id' => $interviewerId,
            'p_region_code'    => pretestRegionCode($data['child']['region']),
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
