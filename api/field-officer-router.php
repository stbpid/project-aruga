<?php
/**
 * Field Officer Router — consolidates:
 *   - field-officer/action-required.php     (action=action-required)
 *   - field-officer/monthly-progress.php    (action=monthly-progress)
 *   - field-officer/performance.php         (action=performance)
 *   - field-officer/qr-lookup.php           (action=qr-lookup)
 *   - field-officer/recent-beneficiaries.php(action=recent-beneficiaries)
 *   - field-officer/stats.php               (action=stats)
 *   - field-officer/submit-edit-request.php (action=submit-edit-request)
 *
 * All seven source files require auth.php unconditionally.
 */
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';

$action = $_GET['action'] ?? '';

// Shared count-via-Content-Range helper — identical copies existed in
// monthly-progress.php (inline), performance.php (foCnt), stats.php (foCount).
if (!function_exists('foRangeCount')) {
    function foRangeCount($endpoint) {
        $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
            'Prefer: count=exact',
            'Range: 0-0',
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $resp = curl_exec($ch);
        curl_close($ch);
        if (preg_match('/Content-Range:\s*[\d\*]+-?[\d\*]*\/(\d+)/i', $resp, $m)) {
            return (int)$m[1];
        }
        return 0;
    }
}

switch ($action) {

    // ================================================================
    // action=action-required  (was api/field-officer/action-required.php)
    // ================================================================
    case 'action-required': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

        requireRole(['field_officer', 'admin']);
        $interviewerCode = getStr('interviewerCode');
        if (empty($interviewerCode)) {
            echo json_encode(['success' => false, 'message' => 'interviewerCode required']);
            exit;
        }
        requireOwnCode($interviewerCode);

        $code = urlencode($interviewerCode);

        $res = supabaseRequest('GET',
            "assessments?select=id,aruga_id,status,updated_at,children(first_name,last_name)&interviewer_code=eq.$code&status=eq.correction&deleted_at=is.null&order=updated_at.desc"
        );

        if (!$res['success']) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch data']);
            exit;
        }

        $items = [];
        foreach ($res['data'] as $a) {
            $child    = is_array($a['children']) ? ($a['children'][0] ?? $a['children']) : null;
            $fullName = trim(($child['first_name'] ?? '') . ' ' . ($child['last_name'] ?? '')) ?: 'Unknown';
            $items[]  = [
                'id'               => $a['id'] ?? '',
                'aruga_id'         => $a['aruga_id'] ?? '—',
                'name'             => $fullName,
                'correction_notes' => 'This submission has been flagged for correction. Please review and update the required fields.',
                'updated_at'       => $a['updated_at'] ? date('M j, Y', strtotime($a['updated_at'])) : '—',
            ];
        }

        echo json_encode(['success' => true, 'data' => $items, 'count' => count($items)]);
        break;
    }

    // ================================================================
    // action=monthly-progress  (was api/field-officer/monthly-progress.php)
    // ================================================================
    case 'monthly-progress': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

        requireRole(['field_officer', 'admin']);
        $interviewerCode = getStr('interviewerCode');
        if (empty($interviewerCode)) {
            echo json_encode(['success' => false, 'message' => 'interviewerCode required']);
            exit;
        }
        requireOwnCode($interviewerCode);

        $code       = urlencode($interviewerCode);
        $monthStart = date('Y-m-01') . 'T00:00:00';
        $monthEnd   = date('Y-m-t') . 'T23:59:59';

        $submitted = foRangeCount('assessments?select=id&interviewer_code=eq.' . $code
            . '&deleted_at=is.null'
            . '&created_at=gte.' . urlencode($monthStart)
            . '&created_at=lte.' . urlencode($monthEnd));

        $target     = 20;
        $percentage = $target > 0 ? min(100, round(($submitted / $target) * 100)) : 0;

        echo json_encode([
            'success' => true,
            'data' => [
                'submitted'  => $submitted,
                'target'     => $target,
                'percentage' => $percentage,
                'month'      => date('F Y'),
            ]
        ]);
        break;
    }

    // ================================================================
    // action=performance  (was api/field-officer/performance.php)
    // ================================================================
    case 'performance': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

        requireRole(['field_officer', 'admin']);
        $interviewerCode = getStr('interviewerCode');
        if (empty($interviewerCode)) {
            echo json_encode(['success' => false, 'message' => 'interviewerCode required']);
            exit;
        }
        requireOwnCode($interviewerCode);

        $code = urlencode($interviewerCode);

        $total    = foRangeCount("assessments?select=id&interviewer_code=eq.$code&deleted_at=is.null");
        $accepted = foRangeCount("assessments?select=id&interviewer_code=eq.$code&status=eq.accepted&deleted_at=is.null");

        $approvalRate = $total > 0 ? round(($accepted / $total) * 100, 1) : 0;

        $monthStart = date('Y-m-01') . 'T00:00:00';
        $monthEnd   = date('Y-m-t') . 'T23:59:59';
        $datesRes   = supabaseRequest('GET',
            "assessments?select=created_at&interviewer_code=eq.$code&deleted_at=is.null&created_at=gte." . urlencode($monthStart) . "&created_at=lte." . urlencode($monthEnd)
        );

        $activeDays    = 0;
        $avgCompletion = 48;

        if ($datesRes['success'] && !empty($datesRes['data'])) {
            $uniqueDays = [];
            foreach ($datesRes['data'] as $row) {
                if (!empty($row['created_at'])) {
                    $uniqueDays[date('Y-m-d', strtotime($row['created_at']))] = true;
                }
            }
            $activeDays = count($uniqueDays);
        }

        $qualityScore = $total > 0 ? min(100, round(($accepted / $total) * 95 + 5)) : 0;

        echo json_encode([
            'success' => true,
            'data' => [
                'approvalRate'  => $approvalRate,
                'avgCompletion' => $avgCompletion,
                'qualityScore'  => $qualityScore,
                'activeDays'    => $activeDays,
            ]
        ]);
        break;
    }

    // ================================================================
    // action=qr-lookup  (was api/field-officer/qr-lookup.php)
    // NOTE: unlike the other field-officer actions, the original qr-lookup.php
    // does NOT call requireRole/requireOwnCode — any authenticated user may call it.
    // ================================================================
    case 'qr-lookup': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

        $arugaId = getStr('arugaId');
        if (empty($arugaId)) {
            echo json_encode(['success' => false, 'message' => 'arugaId required']);
            exit;
        }

        $encoded = urlencode($arugaId);

        $res = supabaseRequest('GET',
            "assessments?select=id,aruga_id,status,created_at,readiness_score,children(first_name,last_name,date_of_birth,barangay)&aruga_id=eq.$encoded&deleted_at=is.null&limit=1"
        );

        if (!$res['success'] || empty($res['data'])) {
            echo json_encode(['success' => false, 'message' => 'Beneficiary not found']);
            exit;
        }

        $a     = $res['data'][0];
        $child = is_array($a['children'])
            ? (isset($a['children'][0]) ? $a['children'][0] : $a['children'])
            : null;

        $firstName = $child['first_name'] ?? '';
        $lastName  = $child['last_name']  ?? '';
        $fullName  = trim($firstName . ' ' . $lastName) ?: 'Unknown';

        $dob = $child['date_of_birth'] ?? null;
        $age = '—';
        if ($dob) {
            $age = (int)(new DateTime($dob))->diff(new DateTime())->y;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'arugaId'      => $a['aruga_id']        ?? $arugaId,
                'name'         => $fullName,
                'age'          => $age,
                'barangay'     => $child['barangay']     ?? '—',
                'status'       => $a['status']           ?? 'pending',
                'readinessScore' => $a['readiness_score'] ?? '—',
                'dateAssessed' => $a['created_at'] ? date('M j, Y', strtotime($a['created_at'])) : '—',
            ]
        ]);
        break;
    }

    // ================================================================
    // action=recent-beneficiaries  (was api/field-officer/recent-beneficiaries.php)
    // ================================================================
    case 'recent-beneficiaries': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

        requireRole(['field_officer', 'admin']);
        $interviewerCode = getStr('interviewerCode');
        $limit = getInt('limit', 5, 1, 100);
        if (empty($interviewerCode)) {
            echo json_encode(['success' => false, 'message' => 'interviewerCode required']);
            exit;
        }
        requireOwnCode($interviewerCode);

        $code = urlencode($interviewerCode);

        $res = supabaseRequest('GET',
            "assessments?select=id,aruga_id,status,created_at,readiness_score,children(first_name,last_name,barangay,date_of_birth)&interviewer_code=eq.$code&deleted_at=is.null&order=created_at.desc&limit=$limit"
        );

        if (!$res['success']) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch data']);
            exit;
        }

        $rows = [];
        foreach ($res['data'] as $a) {
            $child = is_array($a['children']) ? ($a['children'][0] ?? $a['children']) : null;

            $fullName = trim(($child['first_name'] ?? '') . ' ' . ($child['last_name'] ?? '')) ?: 'Unknown';
            $barangay = $child['barangay'] ?? '—';

            $dob = $child['date_of_birth'] ?? null;
            $age = '—';
            if ($dob) {
                $age = (int)(new DateTime($dob))->diff(new DateTime())->y;
            }

            $rows[] = [
                'id'        => $a['id'] ?? '',
                'aruga_id'  => $a['aruga_id'] ?? '—',
                'name'      => $fullName,
                'age'       => $age,
                'barangay'  => $barangay,
                'readiness' => $a['readiness_score'] ?? '—',
                'date'      => $a['created_at'] ? date('M j, Y', strtotime($a['created_at'])) : '—',
                'status'    => $a['status'] ?? 'pending',
            ];
        }

        echo json_encode(['success' => true, 'data' => $rows]);
        break;
    }

    // ================================================================
    // action=stats  (was api/field-officer/stats.php)
    // ================================================================
    case 'stats': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

        requireRole(['field_officer', 'admin']);
        $interviewerCode = getStr('interviewerCode');
        if (empty($interviewerCode)) {
            echo json_encode(['success' => false, 'message' => 'interviewerCode required']);
            exit;
        }
        requireOwnCode($interviewerCode);

        $code = urlencode($interviewerCode);

        $total       = foRangeCount("assessments?select=id&interviewer_code=eq.$code&deleted_at=is.null");
        $accepted    = foRangeCount("assessments?select=id&interviewer_code=eq.$code&status=eq.accepted&deleted_at=is.null");
        $underReview = foRangeCount("assessments?select=id&interviewer_code=eq.$code&status=eq.pending&deleted_at=is.null");
        $needsCorr   = foRangeCount("assessments?select=id&interviewer_code=eq.$code&status=eq.correction&deleted_at=is.null");

        echo json_encode([
            'success' => true,
            'data' => [
                'totalSubmitted'  => $total,
                'accepted'        => $accepted,
                'underReview'     => $underReview,
                'needsCorrection' => $needsCorr,
            ]
        ]);
        break;
    }

    // ================================================================
    // action=submit-edit-request  (was api/field-officer/submit-edit-request.php)
    // ================================================================
    case 'submit-edit-request': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false]); exit; }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) { echo json_encode(['success'=>false,'message'=>'Invalid JSON']); exit; }

        $arugaId       = trim($body['aruga_id'] ?? '');
        $interviewerCode = trim($body['interviewer_code'] ?? '');

        if (!$arugaId)        { echo json_encode(['success'=>false,'message'=>'aruga_id required']); exit; }
        if (!$interviewerCode){ echo json_encode(['success'=>false,'message'=>'interviewer_code required']); exit; }

        // Resolve assessment
        $aRes = supabaseRequest('GET', 'assessments?select=id,aruga_id&aruga_id=eq.'.urlencode($arugaId).'&deleted_at=is.null&limit=1');
        if (!$aRes['success'] || empty($aRes['data'])) {
            echo json_encode(['success'=>false,'message'=>'Assessment not found']); exit;
        }
        $assessmentId = $aRes['data'][0]['id'];

        // Resolve interviewer
        $iRes = supabaseRequest('GET', 'interviewers?select=id&interviewer_code=eq.'.urlencode($interviewerCode).'&limit=1');
        if (!$iRes['success'] || empty($iRes['data'])) {
            echo json_encode(['success'=>false,'message'=>'Interviewer not found']); exit;
        }
        $interviewerId = $iRes['data'][0]['id'];

        // Drop routing keys from payload so only the data portion is stored
        $payload = $body;
        unset($payload['aruga_id'], $payload['interviewer_code']);

        // Cancel any previous pending request for the same assessment (replace with the latest)
        supabaseRequest('PATCH',
            'beneficiary_edit_requests?assessment_id=eq.'.urlencode($assessmentId).'&status=eq.pending',
            ['status' => 'superseded']
        );

        $insertRes = supabaseRequest('POST', 'beneficiary_edit_requests', [
            'aruga_id'       => $arugaId,
            'assessment_id'  => $assessmentId,
            'interviewer_id' => $interviewerId,
            'payload'        => $payload,
            'status'         => 'pending',
        ]);

        if (!$insertRes['success']) {
            $detail = is_array($insertRes['data']) ? json_encode($insertRes['data']) : ($insertRes['error'] ?? 'unknown');
            echo json_encode(['success'=>false,'message'=>'Failed to submit edit request: '.$detail]); exit;
        }

        logAudit('create', 'beneficiary_edit_requests', null, null, ['aruga_id'=>$arugaId,'interviewer_code'=>$interviewerCode], null, $assessmentId);
        echo json_encode(['success'=>true,'message'=>'Edit request submitted for Region Head approval']);
        break;
    }

    default: {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
    }
}
