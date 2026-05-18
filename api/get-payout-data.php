<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://project-aruga.vercel.app');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
}

$region = getStr('region');

// Fetch assessments with child data
$endpoint = 'assessments?select=id,aruga_id,status,children(first_name,last_name,middle_name,region)&order=created_at.asc&limit=10000';
if ($region) {
    $endpoint .= '&children.region=eq.' . urlencode($region);
}

$result = supabaseRequest('GET', $endpoint);

if (!$result['success']) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch data']); exit;
}

$assessments = $result['data'] ?? [];

// Filter by region on PHP side (Supabase embedded filter may not work as expected)
if ($region) {
    $assessments = array_filter($assessments, function($a) use ($region) {
        return isset($a['children']['region']) && $a['children']['region'] === $region;
    });
    $assessments = array_values($assessments);
}

if (empty($assessments)) {
    echo json_encode(['success' => true, 'data' => []]); exit;
}

// Get all assessment IDs
$ids = array_column($assessments, 'id');

// Fetch first family member for each assessment
$familyResult = supabaseRequest('GET',
    'family_members?select=assessment_id,first_name,last_name,middle_name,relationship&order=created_at.asc&limit=100000'
);

// Build family member map (assessment_id => first member)
$familyMap = [];
if ($familyResult['success'] && !empty($familyResult['data'])) {
    foreach ($familyResult['data'] as $fm) {
        $aid = $fm['assessment_id'];
        if (!isset($familyMap[$aid])) {
            $familyMap[$aid] = $fm;
        }
    }
}

// Build final payout rows
$rows = [];
foreach ($assessments as $a) {
    $child = $a['children'] ?? [];
    $firstName  = trim($child['first_name']  ?? '');
    $lastName   = trim($child['last_name']   ?? '');
    $middleName = trim($child['middle_name'] ?? '');

    // Format: Last Name, First Name Middle Name
    $beneficiaryName = $lastName;
    if ($firstName) $beneficiaryName .= ', ' . $firstName;
    if ($middleName) $beneficiaryName .= ' ' . $middleName;

    // Authorized claimant = first family member
    $fm = $familyMap[$a['id']] ?? null;
    $claimantName = '';
    if ($fm) {
        $fmLast   = trim($fm['last_name']   ?? '');
        $fmFirst  = trim($fm['first_name']  ?? '');
        $fmMiddle = trim($fm['middle_name'] ?? '');
        $claimantName = $fmLast;
        if ($fmFirst) $claimantName .= ', ' . $fmFirst;
        if ($fmMiddle) $claimantName .= ' ' . $fmMiddle;
    }

    $rows[] = [
        'aruga_id'         => $a['aruga_id']   ?? '—',
        'beneficiary_name' => $beneficiaryName ?: '—',
        'claimant_name'    => $claimantName    ?: '—',
        'region'           => $child['region'] ?? '—',
        'amount'           => 2000,
    ];
}

echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)]);
?>
