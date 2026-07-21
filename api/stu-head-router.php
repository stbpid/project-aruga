<?php
/**
 * STU Head Router — consolidates:
 *   - stu-head/beneficiaries.php        (action=beneficiaries)
 *   - stu-head/edit-requests.php        (action=edit-requests)
 *   - stu-head/interviewers.php         (action=interviewers)
 *   - stu-head/review-edit-request.php  (action=review-edit-request)
 *   - stu-head/stats.php                (action=stats)
 *
 * All five source files require auth.php unconditionally.
 * beneficiaries.php, interviewers.php, and stats.php also require region-coverage-helper.php.
 */
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/region-coverage-helper.php';

$action = $_GET['action'] ?? '';

// Shared helpers used by review-edit-request.php's approval logic — also duplicated
// (with additional fields) in beneficiaries-router.php's update-beneficiary case.
// Kept as local functions here since they operate on a different (smaller) field set
// specific to the edit-request payload shape.
if (!function_exists('stuSafe')) {
    function stuSafe($arr, $key, $default = null) {
        return (isset($arr[$key]) && $arr[$key] !== '' && $arr[$key] !== null) ? $arr[$key] : $default;
    }
}
if (!function_exists('stuPatchTable')) {
    function stuPatchTable($table, $assessmentId, $data) {
        $clean = array_filter($data, fn($v) => $v !== null && $v !== '');
        foreach ($data as $k => $v) {
            if (is_bool($v) || is_array($v) || is_numeric($v)) $clean[$k] = $v;
        }
        if (empty($clean)) return;
        $res = supabaseRequest('PATCH', $table.'?assessment_id=eq.'.urlencode($assessmentId), $clean);
        if ($res['success'] && is_array($res['data']) && empty($res['data'])) {
            $clean['assessment_id'] = $assessmentId;
            supabaseRequest('POST', $table, $clean);
        }
    }
}

switch ($action) {

    // ================================================================
    // action=beneficiaries  (was api/stu-head/beneficiaries.php)
    // ================================================================
    case 'beneficiaries': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false]); exit;
        }

        requireRole(['stu_head', 'admin']);
        $region  = getStr('region');
        requireRegion($region);
        $search  = trim($_GET['search']  ?? '');
        $status  = trim($_GET['status']  ?? '');
        $limit   = max(1, min(10000, (int)($_GET['limit']  ?? 50)));
        $offset  = getInt('offset', 0, 0);

        if (!$region) {
            echo json_encode(['success' => false, 'message' => 'region required']); exit;
        }

        if (!function_exists('stuNormalizeRegion')) {
            function stuNormalizeRegion($r) {
                return normalizeRegion($r) ?: '';
            }
        }

        $normalizedTarget = stuNormalizeRegion($region);

        // Fetch all assessments with child region — filter by children.region match (paginated)
        $assessments = supabaseFetchAll(
            'assessments?select=id,aruga_id,interviewer_code,status,created_at,readiness_score,children(first_name,last_name,date_of_birth,sex,barangay,region),child_education_health(disabilities)&deleted_at=is.null&order=created_at.desc'
        );

        $rows = [];
        foreach ($assessments as $a) {
            $child = is_array($a['children'])
                ? (isset($a['children'][0]) ? $a['children'][0] : $a['children'])
                : null;

            $childRegion = stuNormalizeRegion($child['region'] ?? '');
            if ($childRegion !== $normalizedTarget) continue;

            $firstName = $child['first_name'] ?? '';
            $lastName  = $child['last_name']  ?? '';
            $fullName  = trim($firstName . ' ' . $lastName) ?: 'Unknown';

            $dob = $child['date_of_birth'] ?? null;
            $age = '—';
            if ($dob) {
                $age = (int)(new DateTime($dob))->diff(new DateTime())->y;
            }

            $arugaId  = $a['aruga_id']          ?? '—';
            $code     = $a['interviewer_code']  ?? '—';
            $readiness= $a['readiness_score']   ?? '—';
            $date     = $a['created_at'] ? date('M j, Y', strtotime($a['created_at'])) : '—';
            $rowStatus= $a['status']            ?? 'pending';

            $cehRaw = $a['child_education_health'] ?? [];
            $ceh = is_array($cehRaw) ? (isset($cehRaw[0]) ? $cehRaw[0] : $cehRaw) : [];
            $disabilities = $ceh['disabilities'] ?? [];
            if (is_string($disabilities)) $disabilities = json_decode($disabilities, true) ?? [];
            if (!is_array($disabilities)) $disabilities = [];
            $disability = !empty($disabilities) ? $disabilities[0] : '—';

            $childRegion = stuNormalizeRegion($child['region'] ?? '');

            if ($status !== '' && $rowStatus !== $status) continue;
            if ($search !== '') {
                $hay = strtolower($fullName . ' ' . $arugaId . ' ' . $childRegion . ' ' . $code . ' ' . implode(' ', $disabilities));
                if (strpos($hay, strtolower($search)) === false) continue;
            }

            $rows[] = [
                'id'             => $a['id']  ?? '',
                'aruga_id'       => $arugaId,
                'name'           => $fullName,
                'age'            => $age,
                'disability'     => $disability,
                'disabilities'   => $disabilities,
                'region'         => $childRegion,
                'interviewer'    => $code,
                'readiness_score'=> $readiness,
                'date'           => $date,
                'status'         => $rowStatus,
            ];
        }

        $total = count($rows);
        $paged = array_slice($rows, $offset, $limit);

        echo json_encode(['success' => true, 'data' => $paged, 'total' => $total]);
        break;
    }

    // ================================================================
    // action=edit-requests  (was api/stu-head/edit-requests.php)
    // ================================================================
    case 'edit-requests': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { echo json_encode(['success'=>false]); exit; }

        requireRole(['stu_head', 'admin']);
        $region = getStr('region');
        requireRegion($region);
        $status = getStr('status', 'pending');   // pending | approved | for_update | declined | all
        $page   = getInt('page', 1, 1);
        $limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        if (!$region) { echo json_encode(['success'=>false,'message'=>'region required']); exit; }

        // Allowed statuses
        $allowedStatuses = ['pending','approved','for_update','declined','superseded'];
        $statusFilter = '';
        if ($status !== 'all') {
            if (!in_array($status, $allowedStatuses)) {
                echo json_encode(['success'=>false,'message'=>'Invalid status']); exit;
            }
            $statusFilter = '&status=eq.'.urlencode($status);
        } else {
            // Exclude superseded (internal) unless explicitly requested
            $statusFilter = '&status=neq.superseded';
        }

        // Fetch edit requests joined with interviewer info via assessments → children (for region filter)
        // Strategy: get pending requests then cross-check region via children table
        $query = 'beneficiary_edit_requests?select=id,aruga_id,assessment_id,payload,status,reviewer_note,created_at,updated_at'
               . ',interviewers!interviewer_id(id,full_name,interviewer_code,province)'
               . $statusFilter
               . '&order=created_at.desc'
               . '&limit=' . $limit
               . '&offset=' . $offset;

        $res = supabaseRequest('GET', $query);
        if (!$res['success']) {
            echo json_encode(['success'=>false,'message'=>'Failed to fetch edit requests']); exit;
        }

        $rows = $res['data'] ?? [];

        // Filter by region: check each assessment's child region
        $filtered = [];
        foreach ($rows as $row) {
            $assessmentId = $row['assessment_id'];
            $childRes = supabaseRequest('GET', 'children?select=region,first_name,last_name&assessment_id=eq.'.urlencode($assessmentId).'&limit=1');
            if (!$childRes['success'] || empty($childRes['data'])) continue;
            $child = $childRes['data'][0];
            if (strtolower(trim($child['region'] ?? '')) !== strtolower(trim($region))) continue;

            $row['child_name'] = trim(($child['first_name'] ?? '') . ' ' . ($child['last_name'] ?? ''));
            $row['child_region'] = $child['region'];
            $filtered[] = $row;
        }

        // Count pending specifically for badge
        $countRes = supabaseRequest('GET', 'beneficiary_edit_requests?select=id&status=eq.pending');
        $pendingCount = 0;
        if ($countRes['success']) {
            // Filter by region too (approximate — count all then refine if needed)
            $pendingCount = count($countRes['data'] ?? []);
        }

        echo json_encode([
            'success'       => true,
            'data'          => $filtered,
            'pending_count' => $pendingCount,
            'page'          => $page,
            'limit'         => $limit,
        ]);
        break;
    }

    // ================================================================
    // action=interviewers  (was api/stu-head/interviewers.php)
    // ================================================================
    case 'interviewers': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false]); exit;
        }

        requireRole(['stu_head', 'admin']);
        $region = getStr('region');
        if (!$region) {
            echo json_encode(['success' => false, 'message' => 'region required']); exit;
        }
        requireRegion($region);

        if (!function_exists('stuIntNormalizeRegion')) {
            function stuIntNormalizeRegion($r) {
                return normalizeRegion($r) ?: '—';
            }
        }

        // Normalize the incoming region so it matches what's stored in various formats.
        // Fetch all interviewers and filter in PHP to handle inconsistent DB values.
        $normalizedRegion = stuIntNormalizeRegion($region);

        $interviewersData = supabaseFetchAll(
            'interviewers?select=id,full_name,interviewer_code,region,province,position,office,status&order=full_name.asc'
        );

        $monthStart = date('Y-m-01T00:00:00');
        $monthEnd   = date('Y-m-t') . 'T23:59:59';

        $codes = array_filter(array_column($interviewersData, 'interviewer_code'));

        $totalMap      = [];
        $completedMap  = [];
        $monthMap      = [];
        $completedMonthMap = [];
        $lastActiveMap = [];

        if (!empty($codes)) {
            $codeIn = implode(',', $codes);
            $assessmentsData = supabaseFetchAll(
                'assessments?select=interviewer_code,status,created_at&interviewer_code=in.(' . $codeIn . ')'
            );
            foreach ($assessmentsData as $a) {
                $code = $a['interviewer_code'] ?? '';
                if (!$code) continue;
                $totalMap[$code] = ($totalMap[$code] ?? 0) + 1;
                if ($a['status'] === 'completed') $completedMap[$code] = ($completedMap[$code] ?? 0) + 1;
                $ca = $a['created_at'] ?? '';
                if ($ca >= $monthStart && $ca <= $monthEnd) {
                    $monthMap[$code] = ($monthMap[$code] ?? 0) + 1;
                    if ($a['status'] === 'completed') $completedMonthMap[$code] = ($completedMonthMap[$code] ?? 0) + 1;
                }
                if ($ca && (!isset($lastActiveMap[$code]) || $ca > $lastActiveMap[$code])) {
                    $lastActiveMap[$code] = $ca;
                }
            }

            $sessionsData = supabaseFetchAll(
                'sessions?select=interviewer_code,created_at&interviewer_code=in.(' . $codeIn . ')'
            );
            foreach ($sessionsData as $s) {
                $code = $s['interviewer_code'] ?? '';
                $ca   = $s['created_at'] ?? '';
                if (!$code || !$ca) continue;
                if (!isset($lastActiveMap[$code]) || $ca > $lastActiveMap[$code]) {
                    $lastActiveMap[$code] = $ca;
                }
            }
        }

        // Filter by normalized region
        $filtered = array_filter($interviewersData, function($r) use ($normalizedRegion) {
            return stuIntNormalizeRegion($r['region'] ?? '') === $normalizedRegion;
        });

        $rows = array_map(function($r) use ($totalMap, $completedMap, $monthMap, $completedMonthMap, $lastActiveMap) {
            $code  = $r['interviewer_code'] ?? '—';
            return [
                'id'                => $r['id'] ?? null,
                'name'              => $r['full_name'] ?? '—',
                'code'              => $code,
                'region'            => stuIntNormalizeRegion($r['region'] ?? ''),
                'province'          => $r['province'] ?? '—',
                'position'          => $r['position'] ?? '—',
                'office'            => $r['office'] ?? '—',
                'status'            => $r['status'] ?? 'active',
                'submissions_total' => $totalMap[$code] ?? 0,
                'completed_total'   => $completedMap[$code] ?? 0,
                'submissions_month' => $monthMap[$code] ?? 0,
                'completed_month'   => $completedMonthMap[$code] ?? 0,
                'last_active'       => $lastActiveMap[$code] ?? null,
            ];
        }, $filtered);

        echo json_encode(['success' => true, 'data' => array_values($rows)]);
        break;
    }

    // ================================================================
    // action=review-edit-request  (was api/stu-head/review-edit-request.php)
    // ================================================================
    case 'review-edit-request': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false]); exit; }

        requireRole(['stu_head', 'admin']);
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) { echo json_encode(['success'=>false,'message'=>'Invalid JSON']); exit; }

        $requestId     = trim($body['request_id'] ?? '');
        $reviewAction  = trim($body['action'] ?? '');       // approved | for_update | declined
        $reviewerNote  = trim($body['reviewer_note'] ?? '');
        $reviewerCode  = trim($body['reviewer_code'] ?? '');

        if (!$requestId)  { echo json_encode(['success'=>false,'message'=>'request_id required']); exit; }
        if (!in_array($reviewAction, ['approved','for_update','declined'])) {
            echo json_encode(['success'=>false,'message'=>'action must be approved, for_update, or declined']); exit;
        }
        if ($reviewAction === 'for_update' && $reviewerNote === '') {
            echo json_encode(['success'=>false,'message'=>'reviewer_note required when action is for_update']); exit;
        }

        // Fetch the edit request
        $reqRes = supabaseRequest('GET', 'beneficiary_edit_requests?select=id,aruga_id,assessment_id,payload,status&id=eq.'.urlencode($requestId).'&limit=1');
        if (!$reqRes['success'] || empty($reqRes['data'])) {
            echo json_encode(['success'=>false,'message'=>'Edit request not found']); exit;
        }
        $req = $reqRes['data'][0];

        if ($req['status'] !== 'pending') {
            echo json_encode(['success'=>false,'message'=>'Edit request is no longer pending (status: '.$req['status'].')']); exit;
        }

        // Resolve reviewer interviewer id
        $reviewerId = null;
        if ($reviewerCode) {
            $iRes = supabaseRequest('GET', 'interviewers?select=id&interviewer_code=eq.'.urlencode($reviewerCode).'&limit=1');
            if ($iRes['success'] && !empty($iRes['data'])) {
                $reviewerId = $iRes['data'][0]['id'];
            }
        }

        // Update the edit request status
        $updateData = [
            'status'      => $reviewAction,
            'reviewer_note'=> $reviewerNote ?: null,
            'reviewed_at' => date('c'),
            'updated_at'  => date('c'),
        ];
        if ($reviewerId) $updateData['reviewed_by'] = $reviewerId;

        $updRes = supabaseRequest('PATCH', 'beneficiary_edit_requests?id=eq.'.urlencode($requestId), $updateData);
        if (!$updRes['success']) {
            echo json_encode(['success'=>false,'message'=>'Failed to update edit request']); exit;
        }

        // If approved → apply the payload to the actual beneficiary tables
        if ($reviewAction === 'approved') {
            $payload = $req['payload'];
            $arugaId = $req['aruga_id'];
            $assessmentId = $req['assessment_id'];

            if (isset($payload['pre_qualification'])) {
                $pq = $payload['pre_qualification'];
                stuPatchTable('pre_qualification', $assessmentId, [
                    'is_4ps_member' => (bool)($pq['is_4ps_member'] ?? false),
                    'household_id'  => stuSafe($pq, 'household_id'),
                ]);
            }
            if (isset($payload['respondent'])) {
                $r = $payload['respondent'];
                stuPatchTable('respondents', $assessmentId, [
                    'full_name'             => stuSafe($r, 'full_name'),
                    'relationship_to_child' => stuSafe($r, 'relationship_to_child'),
                    'email'                 => stuSafe($r, 'email'),
                    'contact_number'        => stuSafe($r, 'contact_number'),
                ]);
            }
            if (isset($payload['child'])) {
                $c = $payload['child'];
                stuPatchTable('children', $assessmentId, [
                    'first_name'        => stuSafe($c, 'first_name'),
                    'middle_name'       => stuSafe($c, 'middle_name'),
                    'last_name'         => stuSafe($c, 'last_name'),
                    'name_extension'    => stuSafe($c, 'name_extension'),
                    'region'            => stuSafe($c, 'region'),
                    'province'          => stuSafe($c, 'province'),
                    'city_municipality' => stuSafe($c, 'city_municipality'),
                    'barangay'          => stuSafe($c, 'barangay'),
                    'street_address'    => stuSafe($c, 'street_address'),
                    'contact_number'    => stuSafe($c, 'contact_number'),
                    'date_of_birth'     => stuSafe($c, 'date_of_birth'),
                    'sex'               => stuSafe($c, 'sex'),
                    'religion'          => stuSafe($c, 'religion'),
                    'ip_membership'     => stuSafe($c, 'ip_membership'),
                ]);
            }
            if (isset($payload['child_education_health'])) {
                $ceh = $payload['child_education_health'];
                stuPatchTable('child_education_health', $assessmentId, [
                    'highest_education'  => stuSafe($ceh, 'highest_education'),
                    'disabilities'       => $ceh['disabilities'] ?? [],
                    'critical_illnesses' => $ceh['critical_illnesses'] ?? [],
                ]);
            }
            if (isset($payload['socio_economic'])) {
                $se = $payload['socio_economic'];
                stuPatchTable('socio_economic', $assessmentId, [
                    'housing_materials'               => stuSafe($se, 'housing_materials'),
                    'tenure_status'                   => stuSafe($se, 'tenure_status'),
                    'has_accessibility_modifications' => (bool)($se['has_accessibility_modifications'] ?? false),
                    'modification_details'            => stuSafe($se, 'modification_details'),
                    'water_source'                    => stuSafe($se, 'water_source'),
                    'electricity_source'              => stuSafe($se, 'electricity_source'),
                    'toilet_type'                      => stuSafe($se, 'toilet_type'),
                    'is_toilet_accessible'            => (bool)($se['is_toilet_accessible'] ?? false),
                    'garbage_disposal'                => stuSafe($se, 'garbage_disposal'),
                ]);
            }
            if (isset($payload['health_info'])) {
                $hi = $payload['health_info'];
                stuPatchTable('health_info', $assessmentId, [
                    'has_all_vaccinations'          => (bool)($hi['has_all_vaccinations'] ?? false),
                    'has_ongoing_health_conditions' => (bool)($hi['has_ongoing_health_conditions'] ?? false),
                    'health_conditions_details'     => stuSafe($hi, 'health_conditions_details'),
                    'availed_services_6months'      => (bool)($hi['availed_services_6months'] ?? false),
                    'availed_services_details'      => stuSafe($hi, 'availed_services_details'),
                    'expense_food'                  => (float)($hi['expense_food'] ?? 0),
                    'expense_medication'            => (float)($hi['expense_medication'] ?? 0),
                    'expense_therapy'               => (float)($hi['expense_therapy'] ?? 0),
                    'expense_hygiene'               => (float)($hi['expense_hygiene'] ?? 0),
                    'expense_assistive_device'      => (float)($hi['expense_assistive_device'] ?? 0),
                    'expense_other'                 => (float)($hi['expense_other'] ?? 0),
                    'has_barriers_to_healthcare'    => (bool)($hi['has_barriers_to_healthcare'] ?? false),
                    'healthcare_barriers_details'   => stuSafe($hi, 'healthcare_barriers_details'),
                ]);
            }
            if (isset($payload['education_info'])) {
                $ei = $payload['education_info'];
                stuPatchTable('education_info', $assessmentId, [
                    'is_currently_enrolled'      => (bool)($ei['is_currently_enrolled'] ?? false),
                    'grade_year_level'           => stuSafe($ei, 'grade_year_level'),
                    'not_enrolled_reason'        => stuSafe($ei, 'not_enrolled_reason'),
                    'has_accessibility_features' => (bool)($ei['has_accessibility_features'] ?? false),
                    'has_sped_programs'          => (bool)($ei['has_sped_programs'] ?? false),
                    'receives_learning_support'  => (bool)($ei['receives_learning_support'] ?? false),
                ]);
            }
            if (isset($payload['economic_capacity'])) {
                $ec = $payload['economic_capacity'];
                stuPatchTable('economic_capacity', $assessmentId, [
                    'primary_income_source' => stuSafe($ec, 'primary_income_source'),
                    'monthly_income'        => is_numeric($ec['monthly_income'] ?? null) ? (float)$ec['monthly_income'] : null,
                    'income_classification' => stuSafe($ec, 'income_classification'),
                    'are_parents_employed'  => (bool)($ec['are_parents_employed'] ?? false),
                    'employment_details'    => stuSafe($ec, 'employment_details'),
                ]);
            }
            if (isset($payload['service_availment'])) {
                $sa = $payload['service_availment'];
                stuPatchTable('service_availment', $assessmentId, [
                    'receives_financial_assistance' => (bool)($sa['receives_financial_assistance'] ?? false),
                    'financial_assistance_details'  => stuSafe($sa, 'financial_assistance_details'),
                    'is_aware_of_social_services'   => (bool)($sa['is_aware_of_social_services'] ?? false),
                    'has_availed_services'          => (bool)($sa['has_availed_services'] ?? false),
                    'service_challenges'            => stuSafe($sa, 'service_challenges'),
                ]);
            }
            if (isset($payload['assessment_notes'])) {
                $an = $payload['assessment_notes'];
                stuPatchTable('assessment_notes', $assessmentId, [
                    'strengths'           => stuSafe($an, 'strengths'),
                    'assessment_details'  => stuSafe($an, 'assessment_details'),
                    'recommended_actions' => stuSafe($an, 'recommended_actions'),
                    'readiness_score'     => stuSafe($an, 'readiness_score'),
                ]);
                if (isset($an['readiness_score'])) {
                    supabaseRequest('PATCH', 'assessments?id=eq.'.urlencode($assessmentId), ['readiness_score' => $an['readiness_score']]);
                }
            }

            logAudit('update', 'assessments', $assessmentId, null, ['aruga_id'=>$arugaId,'via'=>'edit_request_approved'], null, $assessmentId);
        }

        $messages = [
            'approved'   => 'Edit request approved. Beneficiary record has been updated.',
            'for_update' => 'Edit request returned to field officer for revision.',
            'declined'   => 'Edit request declined.',
        ];
        echo json_encode(['success'=>true,'message'=>$messages[$reviewAction]]);
        break;
    }

    // ================================================================
    // action=stats  (was api/stu-head/stats.php)
    // ================================================================
    case 'stats': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false]); exit;
        }

        requireRole(['stu_head', 'admin']);
        $region = getStr('region');
        if (!$region) {
            echo json_encode(['success' => false, 'message' => 'region required']); exit;
        }
        requireRegion($region);

        if (!function_exists('stuStatsNormalizeRegion')) {
            function stuStatsNormalizeRegion($r) {
                return normalizeRegion($r) ?: '';
            }
        }

        $normalizedTarget = stuStatsNormalizeRegion($region);

        // Fetch all assessments + interviewers in parallel (paginated — Supabase caps each response at 1000 rows)
        $fetched = supabaseFetchAllMulti([
            'assessments'  => 'assessments?select=id,status,children(region)&deleted_at=is.null',
            'interviewers' => 'interviewers?select=interviewer_code,region',
        ]);

        $totalBeneficiaries = 0;
        $completedCount     = 0;

        foreach ($fetched['assessments'] as $a) {
            $child = is_array($a['children'])
                ? (isset($a['children'][0]) ? $a['children'][0] : $a['children'])
                : null;
            $childRegion = stuStatsNormalizeRegion($child['region'] ?? '');
            if ($childRegion !== $normalizedTarget) continue;
            $totalBeneficiaries++;
            if (($a['status'] ?? '') === 'completed') $completedCount++;
        }

        $pendingCount = $totalBeneficiaries - $completedCount;

        // Target coverage = active (non-deleted) beneficiaries in this region vs its target
        $regionTargets = getRegionTargets();
        $target = $regionTargets[$normalizedTarget] ?? null;
        $targetRate = $target ? round(($totalBeneficiaries / $target) * 100, 1) : null;

        // Pending vs target = how much of the target quota is still not completed
        $pendingVsTarget = $target ? max($target - $completedCount, 0) : null;
        $pendingTargetRate = $target ? round(($pendingVsTarget / $target) * 100, 1) : null;

        // Interviewer count — filter by normalized region to handle inconsistent stored values
        $interviewerCount = 0;
        foreach ($fetched['interviewers'] as $r) {
            if (!empty($r['interviewer_code']) && stuStatsNormalizeRegion($r['region'] ?? '') === $normalizedTarget) {
                $interviewerCount++;
            }
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'total_beneficiaries' => $totalBeneficiaries,
                'completed'           => $completedCount,
                'pending'             => $pendingCount,
                'interviewer_count'   => $interviewerCount,
                'target'              => $target,
                'target_rate'         => $targetRate,
                'pending_vs_target'   => $pendingVsTarget,
                'pending_target_rate' => $pendingTargetRate,
            ]
        ]);
        break;
    }

    default: {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
    }
}
