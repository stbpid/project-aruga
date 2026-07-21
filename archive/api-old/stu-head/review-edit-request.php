<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false]); exit; }

requireRole(['stu_head', 'admin']);
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { echo json_encode(['success'=>false,'message'=>'Invalid JSON']); exit; }

$requestId     = trim($body['request_id'] ?? '');
$action        = trim($body['action'] ?? '');       // approved | for_update | declined
$reviewerNote  = trim($body['reviewer_note'] ?? '');
$reviewerCode  = trim($body['reviewer_code'] ?? '');

if (!$requestId)  { echo json_encode(['success'=>false,'message'=>'request_id required']); exit; }
if (!in_array($action, ['approved','for_update','declined'])) {
    echo json_encode(['success'=>false,'message'=>'action must be approved, for_update, or declined']); exit;
}
if ($action === 'for_update' && $reviewerNote === '') {
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
    'status'      => $action,
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
if ($action === 'approved') {
    $payload = $req['payload'];
    $arugaId = $req['aruga_id'];
    $assessmentId = $req['assessment_id'];

    function safe($arr, $key, $default = null) {
        return (isset($arr[$key]) && $arr[$key] !== '' && $arr[$key] !== null) ? $arr[$key] : $default;
    }
    function patchTable($table, $assessmentId, $data) {
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

    if (isset($payload['pre_qualification'])) {
        $pq = $payload['pre_qualification'];
        patchTable('pre_qualification', $assessmentId, [
            'is_4ps_member' => (bool)($pq['is_4ps_member'] ?? false),
            'household_id'  => safe($pq, 'household_id'),
        ]);
    }
    if (isset($payload['respondent'])) {
        $r = $payload['respondent'];
        patchTable('respondents', $assessmentId, [
            'full_name'             => safe($r, 'full_name'),
            'relationship_to_child' => safe($r, 'relationship_to_child'),
            'email'                 => safe($r, 'email'),
            'contact_number'        => safe($r, 'contact_number'),
        ]);
    }
    if (isset($payload['child'])) {
        $c = $payload['child'];
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
            'ip_membership'     => safe($c, 'ip_membership'),
        ]);
    }
    if (isset($payload['child_education_health'])) {
        $ceh = $payload['child_education_health'];
        patchTable('child_education_health', $assessmentId, [
            'highest_education'  => safe($ceh, 'highest_education'),
            'disabilities'       => $ceh['disabilities'] ?? [],
            'critical_illnesses' => $ceh['critical_illnesses'] ?? [],
        ]);
    }
    if (isset($payload['socio_economic'])) {
        $se = $payload['socio_economic'];
        patchTable('socio_economic', $assessmentId, [
            'housing_materials'               => safe($se, 'housing_materials'),
            'tenure_status'                   => safe($se, 'tenure_status'),
            'has_accessibility_modifications' => (bool)($se['has_accessibility_modifications'] ?? false),
            'modification_details'            => safe($se, 'modification_details'),
            'water_source'                    => safe($se, 'water_source'),
            'electricity_source'              => safe($se, 'electricity_source'),
            'toilet_type'                     => safe($se, 'toilet_type'),
            'is_toilet_accessible'            => (bool)($se['is_toilet_accessible'] ?? false),
            'garbage_disposal'                => safe($se, 'garbage_disposal'),
        ]);
    }
    if (isset($payload['health_info'])) {
        $hi = $payload['health_info'];
        patchTable('health_info', $assessmentId, [
            'has_all_vaccinations'          => (bool)($hi['has_all_vaccinations'] ?? false),
            'has_ongoing_health_conditions' => (bool)($hi['has_ongoing_health_conditions'] ?? false),
            'health_conditions_details'     => safe($hi, 'health_conditions_details'),
            'availed_services_6months'      => (bool)($hi['availed_services_6months'] ?? false),
            'availed_services_details'      => safe($hi, 'availed_services_details'),
            'expense_food'                  => (float)($hi['expense_food'] ?? 0),
            'expense_medication'            => (float)($hi['expense_medication'] ?? 0),
            'expense_therapy'               => (float)($hi['expense_therapy'] ?? 0),
            'expense_hygiene'               => (float)($hi['expense_hygiene'] ?? 0),
            'expense_assistive_device'      => (float)($hi['expense_assistive_device'] ?? 0),
            'expense_other'                 => (float)($hi['expense_other'] ?? 0),
            'has_barriers_to_healthcare'    => (bool)($hi['has_barriers_to_healthcare'] ?? false),
            'healthcare_barriers_details'   => safe($hi, 'healthcare_barriers_details'),
        ]);
    }
    if (isset($payload['education_info'])) {
        $ei = $payload['education_info'];
        patchTable('education_info', $assessmentId, [
            'is_currently_enrolled'      => (bool)($ei['is_currently_enrolled'] ?? false),
            'grade_year_level'           => safe($ei, 'grade_year_level'),
            'not_enrolled_reason'        => safe($ei, 'not_enrolled_reason'),
            'has_accessibility_features' => (bool)($ei['has_accessibility_features'] ?? false),
            'has_sped_programs'          => (bool)($ei['has_sped_programs'] ?? false),
            'receives_learning_support'  => (bool)($ei['receives_learning_support'] ?? false),
        ]);
    }
    if (isset($payload['economic_capacity'])) {
        $ec = $payload['economic_capacity'];
        patchTable('economic_capacity', $assessmentId, [
            'primary_income_source' => safe($ec, 'primary_income_source'),
            'monthly_income'        => is_numeric($ec['monthly_income'] ?? null) ? (float)$ec['monthly_income'] : null,
            'income_classification' => safe($ec, 'income_classification'),
            'are_parents_employed'  => (bool)($ec['are_parents_employed'] ?? false),
            'employment_details'    => safe($ec, 'employment_details'),
        ]);
    }
    if (isset($payload['service_availment'])) {
        $sa = $payload['service_availment'];
        patchTable('service_availment', $assessmentId, [
            'receives_financial_assistance' => (bool)($sa['receives_financial_assistance'] ?? false),
            'financial_assistance_details'  => safe($sa, 'financial_assistance_details'),
            'is_aware_of_social_services'   => (bool)($sa['is_aware_of_social_services'] ?? false),
            'has_availed_services'          => (bool)($sa['has_availed_services'] ?? false),
            'service_challenges'            => safe($sa, 'service_challenges'),
        ]);
    }
    if (isset($payload['assessment_notes'])) {
        $an = $payload['assessment_notes'];
        patchTable('assessment_notes', $assessmentId, [
            'strengths'           => safe($an, 'strengths'),
            'assessment_details'  => safe($an, 'assessment_details'),
            'recommended_actions' => safe($an, 'recommended_actions'),
            'readiness_score'     => safe($an, 'readiness_score'),
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
echo json_encode(['success'=>true,'message'=>$messages[$action]]);
