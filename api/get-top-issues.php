<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/region-coverage-helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
}

// System-wide (all non-deleted assessments, not scoped to a month)
$assData    = supabaseFetchAll('assessments?select=id,readiness_score&deleted_at=is.null');
$healthData = supabaseFetchAll('health_info?select=assessment_id,has_ongoing_health_conditions,has_barriers_to_healthcare');
$eduData    = supabaseFetchAll('education_info?select=assessment_id,is_currently_enrolled');
$disData    = supabaseFetchAll('child_education_health?select=assessment_id,disabilities');
$econData   = supabaseFetchAll('economic_capacity?select=assessment_id,income_classification');

$assessmentIds = [];
$severeIds     = [];
foreach ($assData as $a) {
    $assessmentIds[] = $a['id'];
    if (($a['readiness_score'] ?? '') === 'severe') $severeIds[] = $a['id'];
}
$totalAssessments = count($assessmentIds);
$idSet = array_flip($assessmentIds);

// Count: severe cases
$severeCases = count($severeIds);

// Count: healthcare barriers / ongoing health conditions
$healthBarriers = 0;
$ongoingHealth  = 0;
foreach ($healthData as $h) {
    if (!isset($idSet[$h['assessment_id']])) continue;
    if (!empty($h['has_ongoing_health_conditions'])) $ongoingHealth++;
    if (!empty($h['has_barriers_to_healthcare']))    $healthBarriers++;
}

// Count: not enrolled in school
$notEnrolled = 0;
foreach ($eduData as $e) {
    if (!isset($idSet[$e['assessment_id']])) continue;
    if (isset($e['is_currently_enrolled']) && $e['is_currently_enrolled'] === false) $notEnrolled++;
}

// Count: multi-disability (2+ disabilities)
$multiDisability = 0;
foreach ($disData as $d) {
    if (!isset($idSet[$d['assessment_id']])) continue;
    $dis = $d['disabilities'] ?? [];
    if (is_string($dis)) $dis = json_decode($dis, true) ?? [];
    if (count($dis) >= 2) $multiDisability++;
}

// Count: subsistence/poor income classification
$extremePoverty = 0;
foreach ($econData as $e) {
    if (!isset($idSet[$e['assessment_id']])) continue;
    $cls = trim($e['income_classification'] ?? '');
    if ($cls === 'Below Minimum / Low Income') {
        $extremePoverty++;
    }
}

// Build issues list, sorted by count desc
$issues = [
    [
        'key'         => 'severe',
        'title'       => 'Severe Readiness Cases',
        'icon'        => 'emergency',
        'count'       => $severeCases,
        'severity'    => 'critical',
        'description' => 'Children flagged as needing immediate intervention.',
        'link_tab'    => 'beneficiaries',
    ],
    [
        'key'         => 'health_barriers',
        'title'       => 'Healthcare Access Barriers',
        'icon'        => 'local_hospital',
        'count'       => $healthBarriers,
        'severity'    => 'high',
        'description' => 'Cases reporting barriers to accessing healthcare services.',
        'link_tab'    => 'beneficiaries',
    ],
    [
        'key'         => 'ongoing_health',
        'title'       => 'Ongoing Health Conditions',
        'icon'        => 'monitor_heart',
        'count'       => $ongoingHealth,
        'severity'    => 'high',
        'description' => 'Children with active health conditions requiring monitoring.',
        'link_tab'    => 'beneficiaries',
    ],
    [
        'key'         => 'not_enrolled',
        'title'       => 'Out-of-School Children',
        'icon'        => 'school',
        'count'       => $notEnrolled,
        'severity'    => 'moderate',
        'description' => 'Children not currently enrolled in any educational program.',
        'link_tab'    => 'beneficiaries',
    ],
    [
        'key'         => 'multi_disability',
        'title'       => 'Multiple Disabilities',
        'icon'        => 'accessibility_new',
        'count'       => $multiDisability,
        'severity'    => 'moderate',
        'description' => 'Children with two or more reported disabilities.',
        'link_tab'    => 'beneficiaries',
    ],
    [
        'key'         => 'extreme_poverty',
        'title'       => 'Extreme Poverty Cases',
        'icon'        => 'attach_money',
        'count'       => $extremePoverty,
        'severity'    => 'high',
        'description' => 'Households classified as subsistence poor.',
        'link_tab'    => 'beneficiaries',
    ],
];

// Sort by count desc, show all 6
usort($issues, fn($a, $b) => $b['count'] - $a['count']);

echo json_encode([
    'success'          => true,
    'total_assessments'=> $totalAssessments,
    'issues'           => $issues,
]);
