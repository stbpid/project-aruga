<?php
/**
 * Beneficiaries Router — consolidates:
 *   - beneficiaries.php           (action=list)
 *   - get-all-beneficiaries.php   (action=all)
 *   - get-beneficiary-detail.php  (action=detail)
 *   - get-recent-beneficiaries.php(action=recent)
 *   - update-beneficiary.php      (action=update)
 *   - submit-assessment.php       (action=submit-assessment)
 *
 * All six source files require auth.php unconditionally, so it is required once here.
 */
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/region-coverage-helper.php';

$action = $_GET['action'] ?? '';

// Shared helper — identical across update-beneficiary.php and submit-assessment.php
if (!function_exists('safe')) {
    function safe($arr, $key, $default = null) {
        return (isset($arr[$key]) && $arr[$key] !== '' && $arr[$key] !== null) ? $arr[$key] : $default;
    }
}

switch ($action) {

    // ================================================================
    // action=list  (was api/beneficiaries.php)
    // ================================================================
    case 'list': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

        $interviewerCode = getStr('interviewerCode');
        $search          = getStr('search');
        $page            = getInt('page', 1, 1);
        $limit           = 20;
        $offset          = ($page - 1) * $limit;

        if (empty($interviewerCode)) {
            echo json_encode(['success' => false, 'message' => 'interviewerCode required']);
            exit;
        }

        $code = urlencode($interviewerCode);

        // Fetch all matching assessments for this interviewer (with child data)
        $res = supabaseRequest('GET',
            "assessments?select=id,aruga_id,status,created_at,readiness_score,children(first_name,last_name,date_of_birth,sex,barangay,region),child_education_health(disabilities)&interviewer_code=eq.$code&deleted_at=is.null&order=created_at.desc&limit=10000"
        );

        if (!$res['success']) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch data']);
            exit;
        }

        $rows = [];
        foreach ($res['data'] as $a) {
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

            $arugaId  = $a['aruga_id']        ?? '—';
            $region   = $child['region']      ?? '—';
            $readiness= $a['readiness_score'] ?? '—';
            $date     = $a['created_at'] ? date('M j, Y', strtotime($a['created_at'])) : '—';

            $cehRaw = $a['child_education_health'] ?? [];
            $ceh = is_array($cehRaw) ? (isset($cehRaw[0]) ? $cehRaw[0] : $cehRaw) : [];
            $disabilities = $ceh['disabilities'] ?? [];
            if (is_string($disabilities)) $disabilities = json_decode($disabilities, true) ?? [];
            if (!is_array($disabilities)) $disabilities = [];
            $disability = !empty($disabilities) ? $disabilities[0] : '—';

            // Search filter
            if ($search !== '') {
                $hay = strtolower($fullName . ' ' . $arugaId . ' ' . $region . ' ' . implode(' ', $disabilities));
                if (strpos($hay, strtolower($search)) === false) continue;
            }

            $rows[] = [
                'id'            => $a['id']   ?? '',
                'arugaId'       => $arugaId,
                'name'          => $fullName,
                'age'           => $age,
                'disability'    => $disability,
                'disabilities'  => $disabilities,
                'region'        => $region,
                'interviewer'   => $interviewerCode,
                'readinessScore'=> $readiness,
                'dateAssessed'  => $date,
                'status'        => $a['status'] ?? 'pending',
            ];
        }

        $total      = count($rows);
        $totalPages = $limit > 0 ? (int)ceil($total / $limit) : 1;
        $paged      = array_slice($rows, $offset, $limit);

        echo json_encode([
            'success' => true,
            'data'    => $paged,
            'pagination' => [
                'page'       => $page,
                'limit'      => $limit,
                'total'      => $total,
                'totalPages' => $totalPages,
            ]
        ]);
        break;
    }

    // ================================================================
    // action=all  (was api/get-all-beneficiaries.php)
    // ================================================================
    case 'all': {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false]); exit;
        }

        $limit          = isset($_GET['limit'])          ? (int)$_GET['limit']              : 50;
        $offset         = isset($_GET['offset'])         ? (int)$_GET['offset']             : 0;
        $search         = isset($_GET['search'])         ? trim($_GET['search'])            : '';
        $region         = isset($_GET['region'])         ? trim($_GET['region'])            : '';
        $readinessScore = isset($_GET['readiness_score'])? trim($_GET['readiness_score'])   : '';
        $disabilityType = isset($_GET['disability_type'])? trim($_GET['disability_type'])   : '';
        $only4ps        = isset($_GET['is_4ps_member'])  && $_GET['is_4ps_member'] === '1';

        // Fetch assessments with readiness_score (paginated — Supabase caps each response at 1000 rows)
        $assessments = supabaseFetchAll(
            'assessments?select=id,aruga_id,interviewer_code,created_at,readiness_score,children(first_name,last_name,name_extension,date_of_birth,region),child_education_health(disabilities)&deleted_at=is.null&order=created_at.desc'
        );

        // Fetch 4Ps data
        $pqData = supabaseFetchAll('pre_qualification?select=assessment_id,is_4ps_member');
        $pqMap = [];
        foreach ($pqData as $pq) {
            $pqMap[$pq['assessment_id']] = (bool)($pq['is_4ps_member'] ?? false);
        }

        $rows = [];
        foreach ($assessments as $a) {
            $child = is_array($a['children'])
                ? (isset($a['children'][0]) ? $a['children'][0] : $a['children'])
                : null;
            $edu = is_array($a['child_education_health'])
                ? (isset($a['child_education_health'][0]) ? $a['child_education_health'][0] : $a['child_education_health'])
                : null;

            $firstName     = $child['first_name'] ?? '';
            $lastName      = $child['last_name']  ?? '';
            $nameExtension = $child['name_extension'] ?? '';
            if (strcasecmp(trim($nameExtension), 'None') === 0) $nameExtension = '';
            $fullName      = trim(implode(' ', array_filter([$firstName, $lastName, $nameExtension]))) ?: 'Unknown';

            $dob = $child['date_of_birth'] ?? null;
            $age = '—';
            if ($dob) {
                $birth = new DateTime($dob);
                $age   = (int)$birth->diff(new DateTime())->y;
            }

            $disabilities = $edu['disabilities'] ?? [];
            if (is_string($disabilities)) $disabilities = json_decode($disabilities, true) ?? [];
            if (!is_array($disabilities)) $disabilities = [];
            $disability = !empty($disabilities) ? $disabilities[0] : '—';

            $childRegion   = normalizeRegion($child['region'] ?? '') ?: '—';
            $arugaId       = $a['aruga_id']         ?? '—';
            $code          = $a['interviewer_code'] ?? '—';
            $date          = $a['created_at'] ? date('M j, Y', strtotime($a['created_at'])) : '—';
            $readiness     = $a['readiness_score']  ?? '—';
            $is4ps         = $pqMap[$a['id']] ?? false;

            // Filters
            if ($region !== '' && $childRegion !== normalizeRegion($region)) continue;
            if ($readinessScore !== '' && strtolower($readiness) !== strtolower($readinessScore)) continue;
            if ($disabilityType !== '') {
                $found = false;
                foreach ($disabilities as $d) {
                    if (stripos($d, $disabilityType) !== false) { $found = true; break; }
                }
                if (!$found) continue;
            }
            if ($only4ps && !$is4ps) continue;
            if ($search !== '') {
                $hay = strtolower($fullName . ' ' . $arugaId . ' ' . $code . ' ' . $childRegion . ' ' . $readiness);
                if (strpos($hay, strtolower($search)) === false) continue;
            }

            $rows[] = [
                'name'            => $fullName,
                'aruga_id'        => $arugaId,
                'age'             => $age,
                'disability'      => $disability,
                'disabilities'    => $disabilities,
                'region'          => $childRegion,
                'interviewer'     => $code,
                'date'            => $date,
                'readiness_score' => $readiness,
                'is_4ps_member'   => $is4ps,
            ];
        }

        $total = count($rows);
        $paged = array_slice($rows, $offset, $limit);

        // Disability type counts (from full unfiltered set — run separately if needed)
        echo json_encode(['success' => true, 'data' => $paged, 'total' => $total]);
        break;
    }

    // ================================================================
    // action=detail  (was api/get-beneficiary-detail.php)
    // ================================================================
    case 'detail': {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { echo json_encode(['success'=>false]); exit; }

        $arugaId = getStr('aruga_id');
        if (!$arugaId) { echo json_encode(['success'=>false,'message'=>'aruga_id required']); exit; }

        $aRes = supabaseRequest('GET', 'assessments?select=id,aruga_id,interviewer_code,readiness_score,status,created_at,completed_at&aruga_id=eq.'.urlencode($arugaId).'&deleted_at=is.null&limit=1');
        if (!$aRes['success'] || empty($aRes['data'])) { echo json_encode(['success'=>false,'message'=>'Not found']); exit; }
        $a = $aRes['data'][0];
        $id = $a['id'];

        if (!function_exists('fetchOne')) {
            function fetchOne($table, $id) {
                $res = supabaseRequest('GET', $table.'?assessment_id=eq.'.urlencode($id).'&limit=1');
                return ($res['success'] && !empty($res['data'])) ? $res['data'][0] : [];
            }
        }
        if (!function_exists('fetchMany')) {
            function fetchMany($table, $id) {
                $res = supabaseRequest('GET', $table.'?assessment_id=eq.'.urlencode($id).'&order=member_number.asc');
                return ($res['success'] && is_array($res['data'])) ? $res['data'] : [];
            }
        }

        echo json_encode([
            'success'               => true,
            'assessment'            => $a,
            'pre_qualification'     => fetchOne('pre_qualification',   $id),
            'respondent'            => fetchOne('respondents',          $id),
            'child'                 => fetchOne('children',             $id),
            'child_education_health'=> fetchOne('child_education_health',$id),
            'family_members'        => fetchMany('family_members',      $id),
            'socio_economic'        => fetchOne('socio_economic',       $id),
            'health_info'           => fetchOne('health_info',          $id),
            'education_info'        => fetchOne('education_info',       $id),
            'economic_capacity'     => fetchOne('economic_capacity',    $id),
            'service_availment'     => fetchOne('service_availment',    $id),
            'assessment_notes'      => fetchOne('assessment_notes',     $id),
        ]);
        break;
    }

    // ================================================================
    // action=recent  (was api/get-recent-beneficiaries.php)
    // ================================================================
    case 'recent': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

        // Fetch 5 most recent assessments with joined children and child_education_health
        $res = supabaseRequest('GET',
            'assessments?select=id,aruga_id,interviewer_code,created_at,children(first_name,last_name,date_of_birth,region),child_education_health(disabilities)&deleted_at=is.null&order=created_at.desc&limit=5'
        );

        if (!$res['success']) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch data']);
            exit;
        }

        $rows = [];
        foreach ($res['data'] as $a) {
            $child = is_array($a['children']) ? (isset($a['children'][0]) ? $a['children'][0] : $a['children']) : null;
            $edu   = is_array($a['child_education_health']) ? (isset($a['child_education_health'][0]) ? $a['child_education_health'][0] : $a['child_education_health']) : null;

            $firstName = $child['first_name'] ?? '';
            $lastName  = $child['last_name'] ?? '';
            $fullName  = trim($firstName . ' ' . $lastName) ?: 'Unknown';

            $dob = $child['date_of_birth'] ?? null;
            $age = '';
            if ($dob) {
                $birth = new DateTime($dob);
                $now   = new DateTime();
                $age   = (int)$birth->diff($now)->y;
            }

            $disabilities = $edu['disabilities'] ?? [];
            if (is_string($disabilities)) {
                $disabilities = json_decode($disabilities, true) ?? [];
            }
            $disability = !empty($disabilities) ? $disabilities[0] : '—';

            $rawRegion = $child['region'] ?? '';
            $regionMap = ['NCR'=>'NCR (National Capital Region)','NCR – Metro Manila'=>'NCR (National Capital Region)','NCR - Metro Manila'=>'NCR (National Capital Region)','National Capital Region'=>'NCR (National Capital Region)','Region I – Ilocos Region'=>'Region I (Ilocos Region)','Region I - Ilocos Region'=>'Region I (Ilocos Region)','Region II – Cagayan Valley'=>'Region II (Cagayan Valley)','Region II - Cagayan Valley'=>'Region II (Cagayan Valley)','Region III – Central Luzon'=>'Region III (Central Luzon)','Region III - Central Luzon'=>'Region III (Central Luzon)','Region IV-A – CALABARZON'=>'Region IV-A (CALABARZON)','Region IV-A - CALABARZON'=>'Region IV-A (CALABARZON)','CALABARZON'=>'Region IV-A (CALABARZON)','Region IV-B – MIMAROPA'=>'Region IV-B (MIMAROPA)','Region IV-B - MIMAROPA'=>'Region IV-B (MIMAROPA)','MIMAROPA'=>'Region IV-B (MIMAROPA)','Region V – Bicol Region'=>'Region V (Bicol Region)','Region V - Bicol Region'=>'Region V (Bicol Region)','Bicol Region'=>'Region V (Bicol Region)','Region VI – Western Visayas'=>'Region VI (Western Visayas)','Region VI - Western Visayas'=>'Region VI (Western Visayas)','Region VII – Central Visayas'=>'Region VII (Central Visayas)','Region VII - Central Visayas'=>'Region VII (Central Visayas)','Region VIII – Eastern Visayas'=>'Region VIII (Eastern Visayas)','Region VIII - Eastern Visayas'=>'Region VIII (Eastern Visayas)','Region IX – Zamboanga Peninsula'=>'Region IX (Zamboanga Peninsula)','Region IX - Zamboanga Peninsula'=>'Region IX (Zamboanga Peninsula)','Region X – Northern Mindanao'=>'Region X (Northern Mindanao)','Region X - Northern Mindanao'=>'Region X (Northern Mindanao)','Region XI – Davao Region'=>'Region XI (Davao Region)','Region XI - Davao Region'=>'Region XI (Davao Region)','Region XII – SOCCSKSARGEN'=>'Region XII (SOCCSKSARGEN)','Region XII - SOCCSKSARGEN'=>'Region XII (SOCCSKSARGEN)','Region XIII – Caraga'=>'Region XIII (Caraga)','Region XIII - Caraga'=>'Region XIII (Caraga)','Caraga'=>'Region XIII (Caraga)','CAR – Cordillera'=>'CAR (Cordillera Administrative Region)','CAR - Cordillera'=>'CAR (Cordillera Administrative Region)','CAR'=>'CAR (Cordillera Administrative Region)','Cordillera'=>'CAR (Cordillera Administrative Region)','BARMM'=>'BARMM (Bangsamoro)','Bangsamoro'=>'BARMM (Bangsamoro)'];
            $region = $regionMap[trim($rawRegion)] ?? ($rawRegion ?: '—');
            $arugaId = $a['aruga_id'] ?? '—';
            $code    = $a['interviewer_code'] ?? '—';
            $date    = $a['created_at'] ? date('M j, Y', strtotime($a['created_at'])) : '—';

            $rows[] = [
                'name'        => $fullName,
                'aruga_id'    => $arugaId,
                'age'         => $age !== '' ? $age : '—',
                'disability'  => $disability,
                'region'      => $region,
                'interviewer' => $code,
                'date'        => $date,
            ];
        }

        echo json_encode(['success' => true, 'data' => $rows]);
        break;
    }

    // ================================================================
    // action=update  (was api/update-beneficiary.php)
    // ================================================================
    case 'update': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false]); exit; }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) { echo json_encode(['success'=>false,'message'=>'Invalid JSON']); exit; }

        $arugaId = trim($body['aruga_id'] ?? '');
        if (!$arugaId) { echo json_encode(['success'=>false,'message'=>'aruga_id required']); exit; }

        $aRes = supabaseRequest('GET', 'assessments?select=id&aruga_id=eq.'.urlencode($arugaId).'&deleted_at=is.null&limit=1');
        if (!$aRes['success'] || empty($aRes['data'])) { echo json_encode(['success'=>false,'message'=>'Assessment not found']); exit; }
        $assessmentId = $aRes['data'][0]['id'];

        $GLOBALS['updateWarnings'] = [];

        if (!function_exists('patchTable')) {
            function patchTable($table, $assessmentId, $data) {
                // Remove null/empty values to avoid overwriting with blanks unintentionally
                $clean = array_filter($data, fn($v) => $v !== null && $v !== '');
                // Always allow boolean false and 0 and arrays
                foreach ($data as $k => $v) {
                    if (is_bool($v) || is_array($v) || is_numeric($v)) $clean[$k] = $v;
                }
                if (empty($clean)) return;
                $res = supabaseRequest('PATCH', $table.'?assessment_id=eq.'.urlencode($assessmentId), $clean);
                if (!$res['success']) {
                    $detail = is_array($res['data']) ? json_encode($res['data']) : ($res['error'] ?? 'unknown error');
                    $GLOBALS['updateWarnings'][] = "$table: $detail";
                    return;
                }
                // If no row exists for this assessment yet, PATCH affects 0 rows but still "succeeds" - insert instead
                if (is_array($res['data']) && empty($res['data'])) {
                    $clean['assessment_id'] = $assessmentId;
                    $insRes = supabaseRequest('POST', $table, $clean);
                    if (!$insRes['success']) {
                        $detail = is_array($insRes['data']) ? json_encode($insRes['data']) : ($insRes['error'] ?? 'unknown error');
                        $GLOBALS['updateWarnings'][] = "$table: $detail";
                    }
                }
            }
        }

        // Pre-qualification
        if (isset($body['pre_qualification'])) {
            $pq = $body['pre_qualification'];
            patchTable('pre_qualification', $assessmentId, [
                'is_4ps_member' => (bool)($pq['is_4ps_member'] ?? false),
                'household_id'  => safe($pq, 'household_id'),
            ]);
        }

        // Respondent
        if (isset($body['respondent'])) {
            $r = $body['respondent'];
            patchTable('respondents', $assessmentId, [
                'full_name'             => safe($r, 'full_name'),
                'relationship_to_child' => safe($r, 'relationship_to_child'),
                'email'                 => safe($r, 'email'),
                'contact_number'        => safe($r, 'contact_number'),
            ]);
        }

        // Child
        if (isset($body['child'])) {
            $c = $body['child'];
            patchTable('children', $assessmentId, [
                'first_name'        => safe($c, 'first_name'),
                'middle_name'       => safe($c, 'middle_name'),
                'last_name'         => safe($c, 'last_name'),
                'name_extension'    => safe($c, 'name_extension'),
                'region'            => safe($c, 'region'),
                'province'          => safe($c, 'province'),
                'city_municipality' => safe($c, 'city_municipality'),
                'barangay'          => safe($c, 'barangay'),
                'street_address'    => safe($c, 'street_address'),
                'contact_number'    => safe($c, 'contact_number'),
                'date_of_birth'     => safe($c, 'date_of_birth'),
                'sex'               => safe($c, 'sex'),
                'religion'          => safe($c, 'religion'),
                'religion_other'    => safe($c, 'religion_other'),
                'ip_membership'     => safe($c, 'ip_membership'),
                'ip_membership_other' => safe($c, 'ip_membership_other'),
            ]);
        }

        // Child Education & Health
        if (isset($body['child_education_health'])) {
            $ceh = $body['child_education_health'];
            patchTable('child_education_health', $assessmentId, [
                'highest_education'       => safe($ceh, 'highest_education'),
                'highest_education_other' => safe($ceh, 'highest_education_other'),
                'disabilities'      => $ceh['disabilities'] ?? [],
                'critical_illnesses'=> $ceh['critical_illnesses'] ?? [],
                'illness_other'     => safe($ceh, 'illness_other'),
            ]);
        }

        // Socio Economic
        if (isset($body['socio_economic'])) {
            $se = $body['socio_economic'];
            patchTable('socio_economic', $assessmentId, [
                'housing_materials'               => safe($se, 'housing_materials'),
                'housing_materials_other'         => safe($se, 'housing_materials_other'),
                'tenure_status'                   => safe($se, 'tenure_status'),
                'tenure_status_other'             => safe($se, 'tenure_status_other'),
                'has_accessibility_modifications' => (bool)($se['has_accessibility_modifications'] ?? false),
                'modification_details'            => safe($se, 'modification_details'),
                'water_source'                    => safe($se, 'water_source'),
                'water_source_other'              => safe($se, 'water_source_other'),
                'electricity_source'              => safe($se, 'electricity_source'),
                'electricity_source_other'        => safe($se, 'electricity_source_other'),
                'toilet_type'                     => safe($se, 'toilet_type'),
                'toilet_type_other'               => safe($se, 'toilet_type_other'),
                'is_toilet_accessible'            => (bool)($se['is_toilet_accessible'] ?? false),
                'garbage_disposal'                => safe($se, 'garbage_disposal'),
                'garbage_disposal_other'          => safe($se, 'garbage_disposal_other'),
            ]);
        }

        // Health Info
        if (isset($body['health_info'])) {
            $hi = $body['health_info'];
            patchTable('health_info', $assessmentId, [
                'has_all_vaccinations'         => (bool)($hi['has_all_vaccinations'] ?? false),
                'has_ongoing_health_conditions'=> (bool)($hi['has_ongoing_health_conditions'] ?? false),
                'health_conditions_details'    => safe($hi, 'health_conditions_details'),
                'availed_services_6months'     => (bool)($hi['availed_services_6months'] ?? false),
                'availed_services_details'     => safe($hi, 'availed_services_details'),
                'expense_food'                 => (float)($hi['expense_food'] ?? 0),
                'expense_medication'           => (float)($hi['expense_medication'] ?? 0),
                'expense_therapy'              => (float)($hi['expense_therapy'] ?? 0),
                'expense_hygiene'              => (float)($hi['expense_hygiene'] ?? 0),
                'expense_assistive_device'     => (float)($hi['expense_assistive_device'] ?? 0),
                'expense_other'                => (float)($hi['expense_other'] ?? 0),
                'has_barriers_to_healthcare'   => (bool)($hi['has_barriers_to_healthcare'] ?? false),
                'healthcare_barriers_details'  => safe($hi, 'healthcare_barriers_details'),
            ]);
        }

        // Education Info
        if (isset($body['education_info'])) {
            $ei = $body['education_info'];
            patchTable('education_info', $assessmentId, [
                'is_currently_enrolled'      => (bool)($ei['is_currently_enrolled'] ?? false),
                'grade_year_level'           => safe($ei, 'grade_year_level'),
                'not_enrolled_reason'        => safe($ei, 'not_enrolled_reason'),
                'has_accessibility_features' => (bool)($ei['has_accessibility_features'] ?? false),
                'accessibility_features_details' => safe($ei, 'accessibility_features_details'),
                'has_sped_programs'          => (bool)($ei['has_sped_programs'] ?? false),
                'sped_programs_details'      => safe($ei, 'sped_programs_details'),
                'receives_learning_support'  => (bool)($ei['receives_learning_support'] ?? false),
                'learning_support_details'   => safe($ei, 'learning_support_details'),
            ]);
        }

        // Economic Capacity
        if (isset($body['economic_capacity'])) {
            $ec = $body['economic_capacity'];
            patchTable('economic_capacity', $assessmentId, [
                'primary_income_source' => safe($ec, 'primary_income_source'),
                'monthly_income'        => is_numeric($ec['monthly_income'] ?? null) ? (float)$ec['monthly_income'] : null,
                'income_classification' => safe($ec, 'income_classification'),
                'are_parents_employed'  => (bool)($ec['are_parents_employed'] ?? false),
                'employment_details'    => safe($ec, 'employment_details'),
            ]);
        }

        // Service Availment
        if (isset($body['service_availment'])) {
            $sa = $body['service_availment'];
            patchTable('service_availment', $assessmentId, [
                'receives_financial_assistance' => (bool)($sa['receives_financial_assistance'] ?? false),
                'financial_assistance_details'  => safe($sa, 'financial_assistance_details'),
                'is_aware_of_social_services'   => (bool)($sa['is_aware_of_social_services'] ?? false),
                'awareness_details'             => safe($sa, 'awareness_details'),
                'has_availed_services'          => (bool)($sa['has_availed_services'] ?? false),
                'service_challenges'            => safe($sa, 'service_challenges'),
                'service_challenges_other'      => safe($sa, 'service_challenges_other'),
            ]);
        }

        // Family Members - replace all rows for this assessment
        if (isset($body['family_members']) && is_array($body['family_members'])) {
            supabaseRequest('DELETE', 'family_members?assessment_id=eq.'.urlencode($assessmentId));
            foreach ($body['family_members'] as $i => $m) {
                $row = [
                    'assessment_id'       => $assessmentId,
                    'member_number'       => $m['member_number'] ?? ($i + 1),
                    'full_name'           => safe($m, 'full_name'),
                    'relationship_to_head'=> safe($m, 'relationship_to_head'),
                    'age'                 => is_numeric($m['age'] ?? null) ? (int)$m['age'] : null,
                    'sex'                 => safe($m, 'sex'),
                    'civil_status'        => safe($m, 'civil_status'),
                    'is_solo_parent'      => (bool)($m['is_solo_parent'] ?? false),
                    'is_authorized_claimant' => (bool)($m['is_authorized_claimant'] ?? false),
                    'occupation'          => safe($m, 'occupation'),
                    'occupation_class'    => safe($m, 'occupation_class'),
                    'disabilities'        => $m['disabilities'] ?? [],
                    'critical_illnesses'  => $m['critical_illnesses'] ?? [],
                ];
                supabaseRequest('POST', 'family_members', $row);
            }
        }

        // Assessment Notes + readiness
        if (isset($body['assessment_notes'])) {
            $an = $body['assessment_notes'];
            patchTable('assessment_notes', $assessmentId, [
                'strengths'           => safe($an, 'strengths'),
                'assessment_details'  => safe($an, 'assessment_details'),
                'recommended_actions' => safe($an, 'recommended_actions'),
                'readiness_score'     => safe($an, 'readiness_score'),
            ]);
            if (!empty($an['readiness_score'])) {
                $rsRes = supabaseRequest('PATCH', 'assessments?id=eq.'.urlencode($assessmentId), ['readiness_score' => $an['readiness_score']]);
                if (!$rsRes['success']) {
                    $detail = is_array($rsRes['data']) ? json_encode($rsRes['data']) : ($rsRes['error'] ?? 'unknown error');
                    $GLOBALS['updateWarnings'][] = "assessments.readiness_score: $detail";
                }
            }
        }

        logAudit('update', 'assessments', $assessmentId, null, ['aruga_id'=>$arugaId], null, $assessmentId);

        if (!empty($GLOBALS['updateWarnings'])) {
            echo json_encode(['success'=>false,'message'=>'Some sections failed to save: '.implode('; ', $GLOBALS['updateWarnings'])]);
            exit;
        }

        echo json_encode(['success'=>true,'message'=>'Beneficiary updated successfully']);
        break;
    }

    // ================================================================
    // action=submit-assessment  (was api/submit-assessment.php)
    // ================================================================
    case 'submit-assessment': {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendResponse(false, 'Method not allowed', null, 405);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            sendResponse(false, 'Invalid JSON body', null, 400);
        }

        if (!defined('VALID_DISABILITIES_SA')) {
            define('VALID_DISABILITIES_SA', [
                'None', 'Visual Disability', 'Hearing Disability',
                'Speech and Language Impairment', 'Orthopedic / Physical Disability',
                'Mental / Intellectual Disability', 'Learning Disability',
                'Psychosocial Disability', 'Disability Resulting from a Chronic Illness',
                'Multiple Disabilities', 'Other (specify)',
            ]);
        }
        $VALID_DISABILITIES = VALID_DISABILITIES_SA;

        if (!defined('VALID_ILLNESSES_SA')) {
            define('VALID_ILLNESSES_SA', [
                'None', 'Cancer', 'Heart Disease', 'Kidney Disease', 'Diabetes',
                'Respiratory Disease', 'Neurological Disorder', 'Blood Disorder',
                'Chronic Illness', 'Others',
            ]);
        }
        $VALID_ILLNESSES = VALID_ILLNESSES_SA;

        if (!function_exists('sanitizeArray')) {
            function sanitizeArray($value, array $allowedValues, int $maxItems = 20, string $fieldName = 'field'): array {
                if (!is_array($value)) return [];
                if (count($value) > $maxItems) {
                    http_response_code(400);
                    echo json_encode(['error' => "Too many values for {$fieldName}."]);
                    exit;
                }
                $result = [];
                foreach ($value as $item) {
                    if (!is_string($item) || !in_array($item, $allowedValues, true)) {
                        http_response_code(400);
                        echo json_encode(['error' => "Invalid value for {$fieldName}: " . json_encode($item)]);
                        exit;
                    }
                    $result[] = $item;
                }
                return $result;
            }
        }

        if (!function_exists('getRegionCode')) {
            function getRegionCode($region) {
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
        }

        if (!function_exists('generateArugaId')) {
            function generateArugaId($regionCode) {
                $year = (int)date('Y');
                $prefix = "ARUGA-{$year}-{$regionCode}-";

                // Atomic counter via Postgres RPC — avoids the race condition where two
                // concurrent submissions read the same COUNT() and collide on the same ID.
                $rpc = supabaseRPC('increment_aruga_counter', [
                    'p_region_code' => $regionCode,
                    'p_year'        => $year,
                ]);

                if ($rpc['success'] && is_numeric($rpc['data'])) {
                    return sprintf('%s%04d', $prefix, (int)$rpc['data']);
                }

                // Fallback (RPC unreachable/misconfigured) — old count-based method.
                // Retry loop in the caller still guards against collisions.
                $countResult = supabaseRequest('GET', 'assessments?select=id&aruga_id=like.' . urlencode($prefix . '*') . '&limit=100000');
                $count = is_array($countResult['data']) ? count($countResult['data']) + 1 : 1;
                return sprintf('%s%04d', $prefix, $count);
            }
        }

        // ----------------------------------------------------------------
        // 1. Generate aruga_id then create assessment record
        // ----------------------------------------------------------------
        $childRegion   = safe($input['child'] ?? [], 'region');
        $regionCode    = getRegionCode($childRegion);
        $arugaId       = generateArugaId($regionCode);
        $readinessScore = safe($input, 'readiness_score');

        $assessmentData = [
            'session_id'          => safe($input, 'session_id'),
            'interviewer_id'      => safe($input, 'interviewer_id'),
            'interviewer_code'    => $input['interviewer_code'] ?? '',
            'privacy_accepted'    => true,
            'privacy_accepted_at' => date('c'),
            'current_step'        => 11,
            'status'              => 'completed',
            'readiness_score'     => $readinessScore,
            'aruga_id'            => $arugaId,
            'completed_at'        => date('c'),
            'submitted_at'        => date('c'),
        ];

        $sessionId = $assessmentData['session_id'];
        if ($sessionId) {
            $dupCheck = supabaseRequest('GET', 'assessments?select=id&session_id=eq.' . urlencode($sessionId) . '&status=eq.completed&limit=1');
            if (!empty($dupCheck['data'])) {
                sendResponse(false, 'This session has already been submitted. Please start a new session.', null, 409);
            }
        }

        $maxAttempts = 5;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $result = supabaseRequest('POST', 'assessments', $assessmentData);
            if ($result['success'] && !empty($result['data'][0]['id'])) {
                break;
            }
            $isDuplicateArugaId = ($result['data']['code'] ?? '') === '23505'
                && strpos($result['data']['message'] ?? '', 'aruga_id') !== false;
            if ($isDuplicateArugaId && $attempt < $maxAttempts) {
                $arugaId = generateArugaId($regionCode);
                $assessmentData['aruga_id'] = $arugaId;
                continue;
            }
            error_log('submit-assessment error: ' . ($result['error'] ?? 'Unknown'));
            sendResponse(false, 'Failed to submit assessment. Please try again.', null, 500);
        }
        $assessmentId = $result['data'][0]['id'];

        // ----------------------------------------------------------------
        // 2. Pre-Qualification (Step 1)
        // ----------------------------------------------------------------
        $pq = $input['pre_qualification'] ?? [];
        supabaseRequest('POST', 'pre_qualification', [
            'assessment_id' => $assessmentId,
            'is_4ps_member' => (bool)($pq['is_4ps_member'] ?? false),
            'household_id'  => safe($pq, 'household_id'),
        ]);

        // ----------------------------------------------------------------
        // 3. Respondent (Step 2)
        // ----------------------------------------------------------------
        $resp = $input['respondent'] ?? [];
        supabaseRequest('POST', 'respondents', [
            'assessment_id'        => $assessmentId,
            'full_name'            => $resp['full_name'] ?? '',
            'relationship_to_child'=> safe($resp, 'relationship_to_child'),
            'email'                => safe($resp, 'email'),
            'contact_number'       => safe($resp, 'contact_number'),
        ]);

        // ----------------------------------------------------------------
        // 4. Child (Step 3a)
        // ----------------------------------------------------------------
        $child = $input['child'] ?? [];
        $childResult = supabaseRequest('POST', 'children', [
            'assessment_id'      => $assessmentId,
            'first_name'         => $child['first_name'] ?? '',
            'middle_name'        => safe($child, 'middle_name'),
            'last_name'          => $child['last_name'] ?? '',
            'name_extension'     => safe($child, 'name_extension'),
            'region'             => safe($child, 'region'),
            'province'           => safe($child, 'province'),
            'city_municipality'  => safe($child, 'city_municipality'),
            'barangay'           => safe($child, 'barangay'),
            'street_address'     => safe($child, 'street_address'),
            'contact_number'     => safe($child, 'contact_number'),
            'date_of_birth'      => safe($child, 'date_of_birth'),
            'sex'                => safe($child, 'sex'),
            'religion'           => safe($child, 'religion'),
            'religion_other'     => safe($child, 'religion_other'),
            'ip_membership'      => safe($child, 'ip_membership'),
            'ip_membership_other'=> safe($child, 'ip_membership_other'),
        ]);
        $childId = $childResult['data'][0]['id'] ?? null;

        // ----------------------------------------------------------------
        // 5. Child Education & Health (Step 3b)
        // ----------------------------------------------------------------
        $ceh = $input['child_education_health'] ?? [];
        supabaseRequest('POST', 'child_education_health', [
            'assessment_id'           => $assessmentId,
            'child_id'                => $childId,
            'highest_education'       => safe($ceh, 'highest_education'),
            'highest_education_other' => safe($ceh, 'highest_education_other'),
            'disabilities'            => sanitizeArray($ceh['disabilities'] ?? [], $VALID_DISABILITIES, 20, 'disabilities'),
            'critical_illnesses'      => sanitizeArray($ceh['critical_illnesses'] ?? [], $VALID_ILLNESSES, 20, 'critical_illnesses'),
            'illness_other'           => safe($ceh, 'illness_other'),
        ]);

        // ----------------------------------------------------------------
        // 6. Family Members (Step 4) — one row per member
        // ----------------------------------------------------------------
        $members = $input['family_members'] ?? [];
        foreach ($members as $member) {
            $ageVal = isset($member['age']) && is_numeric($member['age']) ? (int)$member['age'] : null;
            supabaseRequest('POST', 'family_members', [
                'assessment_id'       => $assessmentId,
                'member_number'       => (int)($member['member_number'] ?? 1),
                'full_name'           => $member['full_name'] ?? '',
                'relationship_to_head'=> safe($member, 'relationship_to_head'),
                'is_solo_parent'      => (bool)($member['is_solo_parent'] ?? false),
                'is_authorized_claimant' => (bool)($member['is_authorized_claimant'] ?? false),
                'civil_status'        => safe($member, 'civil_status'),
                'age'                 => $ageVal,
                'sex'                 => safe($member, 'sex'),
                'occupation'          => safe($member, 'occupation'),
                'occupation_class'    => safe($member, 'occupation_class'),
                'disabilities'        => sanitizeArray($member['disabilities'] ?? [], $VALID_DISABILITIES, 20, 'member disabilities'),
                'critical_illnesses'  => sanitizeArray($member['critical_illnesses'] ?? [], $VALID_ILLNESSES, 20, 'member critical_illnesses'),
            ]);
        }

        // ----------------------------------------------------------------
        // 7. Socio Economic (Step 5)
        // ----------------------------------------------------------------
        $se = $input['socio_economic'] ?? [];
        supabaseRequest('POST', 'socio_economic', [
            'assessment_id'                  => $assessmentId,
            'housing_materials'              => safe($se, 'housing_materials'),
            'housing_materials_other'        => safe($se, 'housing_materials_other'),
            'tenure_status'                  => safe($se, 'tenure_status'),
            'tenure_status_other'            => safe($se, 'tenure_status_other'),
            'has_accessibility_modifications'=> (bool)($se['has_accessibility_modifications'] ?? false),
            'modification_details'           => safe($se, 'modification_details'),
            'electricity_source'             => safe($se, 'electricity_source'),
            'electricity_source_other'       => safe($se, 'electricity_source_other'),
            'water_source'                   => safe($se, 'water_source'),
            'water_source_other'             => safe($se, 'water_source_other'),
            'toilet_type'                    => safe($se, 'toilet_type'),
            'toilet_type_other'              => safe($se, 'toilet_type_other'),
            'is_toilet_accessible'           => (bool)($se['is_toilet_accessible'] ?? false),
            'garbage_disposal'               => safe($se, 'garbage_disposal'),
            'garbage_disposal_other'         => safe($se, 'garbage_disposal_other'),
        ]);

        // ----------------------------------------------------------------
        // 8. Health Info (Step 6)
        // ----------------------------------------------------------------
        $hi = $input['health_info'] ?? [];
        supabaseRequest('POST', 'health_info', [
            'assessment_id'               => $assessmentId,
            'has_all_vaccinations'        => (bool)($hi['has_all_vaccinations'] ?? false),
            'has_ongoing_health_conditions'=> (bool)($hi['has_ongoing_health_conditions'] ?? false),
            'health_conditions_details'   => safe($hi, 'health_conditions_details'),
            'expense_food'                => (float)($hi['expense_food'] ?? 0),
            'expense_medication'          => (float)($hi['expense_medication'] ?? 0),
            'expense_therapy'             => (float)($hi['expense_therapy'] ?? 0),
            'expense_hygiene'             => (float)($hi['expense_hygiene'] ?? 0),
            'expense_assistive_device'    => (float)($hi['expense_assistive_device'] ?? 0),
            'expense_other'               => (float)($hi['expense_other'] ?? 0),
            'availed_services_6months'    => (bool)($hi['availed_services_6months'] ?? false),
            'availed_services_details'    => safe($hi, 'availed_services_details'),
            'is_facility_accessible'      => (bool)($hi['is_facility_accessible'] ?? false),
            'has_barriers_to_healthcare'  => (bool)($hi['has_barriers_to_healthcare'] ?? false),
            'healthcare_barriers_details' => safe($hi, 'healthcare_barriers_details'),
        ]);

        // ----------------------------------------------------------------
        // 9. Education Info (Step 7)
        // ----------------------------------------------------------------
        $ei = $input['education_info'] ?? [];
        supabaseRequest('POST', 'education_info', [
            'assessment_id'                 => $assessmentId,
            'is_currently_enrolled'         => (bool)($ei['is_currently_enrolled'] ?? false),
            'grade_year_level'              => safe($ei, 'grade_year_level'),
            'not_enrolled_reason'           => safe($ei, 'not_enrolled_reason'),
            'has_accessibility_features'    => (bool)($ei['has_accessibility_features'] ?? false),
            'accessibility_features_details'=> safe($ei, 'accessibility_features_details'),
            'has_sped_programs'             => (bool)($ei['has_sped_programs'] ?? false),
            'sped_programs_details'         => safe($ei, 'sped_programs_details'),
            'receives_learning_support'     => (bool)($ei['receives_learning_support'] ?? false),
            'learning_support_details'      => safe($ei, 'learning_support_details'),
        ]);

        // ----------------------------------------------------------------
        // 10. Economic Capacity (Step 8)
        // ----------------------------------------------------------------
        $ec = $input['economic_capacity'] ?? [];
        $monthlyIncome = null;
        if (isset($ec['monthly_income']) && is_numeric($ec['monthly_income']) && (float)$ec['monthly_income'] > 0) {
            $monthlyIncome = (float)$ec['monthly_income'];
        }
        supabaseRequest('POST', 'economic_capacity', [
            'assessment_id'         => $assessmentId,
            'primary_income_source' => safe($ec, 'primary_income_source'),
            'monthly_income'        => $monthlyIncome,
            'income_classification' => safe($ec, 'income_classification'),
            'are_parents_employed'  => (bool)($ec['are_parents_employed'] ?? false),
            'employment_details'    => safe($ec, 'employment_details'),
        ]);

        // ----------------------------------------------------------------
        // 11. Service Availment (Step 9)
        // ----------------------------------------------------------------
        $sa = $input['service_availment'] ?? [];
        supabaseRequest('POST', 'service_availment', [
            'assessment_id'                => $assessmentId,
            'receives_financial_assistance'=> (bool)($sa['receives_financial_assistance'] ?? false),
            'financial_assistance_details' => safe($sa, 'financial_assistance_details'),
            'is_aware_of_social_services'  => (bool)($sa['is_aware_of_social_services'] ?? false),
            'awareness_details'            => safe($sa, 'awareness_details'),
            'has_availed_services'         => (bool)($sa['has_availed_services'] ?? false),
            'availed_services_details'     => safe($sa, 'availed_services_details'),
            'service_challenges'           => safe($sa, 'service_challenges'),
            'service_challenges_other'     => safe($sa, 'service_challenges_other'),
        ]);

        // ----------------------------------------------------------------
        // 12. Assessment Notes (Step 10)
        // ----------------------------------------------------------------
        $an = $input['assessment_notes'] ?? [];
        supabaseRequest('POST', 'assessment_notes', [
            'assessment_id'      => $assessmentId,
            'strengths'          => safe($an, 'strengths'),
            'assessment_details' => safe($an, 'assessment_details'),
            'recommended_actions'=> safe($an, 'recommended_actions'),
            'readiness_score'    => safe($an, 'readiness_score'),
        ]);

        $child     = $input['child'] ?? [];
        $resp      = $input['respondent'] ?? [];
        $childName = trim(($child['first_name'] ?? '') . ' ' . ($child['last_name'] ?? ''));
        $email     = $resp['email'] ?? '';

        logAudit('create', 'assessments', $assessmentId,
            null,
            ['aruga_id' => $arugaId, 'child_name' => $childName, 'readiness_score' => $assessmentData['readiness_score'] ?? null, 'status' => 'completed'],
            $assessmentData['interviewer_id'] ?? null,
            $assessmentId
        );

        sendResponse(true, 'Assessment submitted successfully', [
            'assessment_id' => $assessmentId,
            'aruga_id'      => $arugaId,
            'child_name'    => $childName,
            'email'         => $email,
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
