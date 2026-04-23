<?php
require_once __DIR__ . '/config.php';

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

function safe($arr, $key, $default = null) {
    return (isset($arr[$key]) && $arr[$key] !== '' && $arr[$key] !== null) ? $arr[$key] : $default;
}

// ----------------------------------------------------------------
// 1. Create assessment record
// ----------------------------------------------------------------
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
    'completed_at'        => date('c'),
    'submitted_at'        => date('c'),
];

$result = supabaseRequest('POST', 'assessments', $assessmentData);
if (!$result['success'] || empty($result['data'][0]['id'])) {
    sendResponse(false, 'Failed to create assessment: ' . ($result['error'] ?? 'Unknown error'), null, 500);
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
    'disabilities'            => $ceh['disabilities'] ?? [],
    'critical_illnesses'      => $ceh['critical_illnesses'] ?? [],
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
        'civil_status'        => safe($member, 'civil_status'),
        'age'                 => $ageVal,
        'sex'                 => safe($member, 'sex'),
        'occupation'          => safe($member, 'occupation'),
        'occupation_class'    => safe($member, 'occupation_class'),
        'disabilities'        => $member['disabilities'] ?? [],
        'critical_illnesses'  => $member['critical_illnesses'] ?? [],
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

sendResponse(true, 'Assessment submitted successfully', ['assessment_id' => $assessmentId]);
