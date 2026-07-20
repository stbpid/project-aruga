<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
}

$region = getStr('region');

// Fetch assessments with child data, paginating since PostgREST caps rows per request
// regardless of the `limit` query param (project max-rows setting).
$pageSize = 1000;
$offset = 0;
$assessments = [];
while (true) {
    $endpoint = 'assessments?select=id,aruga_id,status,children(first_name,last_name,middle_name,name_extension,region)'
        . '&deleted_at=is.null&order=created_at.asc&limit=' . $pageSize . '&offset=' . $offset;
    if ($region) {
        $endpoint .= '&children.region=eq.' . urlencode($region);
    }

    $result = supabaseRequest('GET', $endpoint);

    if (!$result['success']) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch data']); exit;
    }

    $page = $result['data'] ?? [];
    $assessments = array_merge($assessments, $page);

    if (count($page) < $pageSize) break;
    $offset += $pageSize;
}

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

// Fetch family members marked as authorized claimant for each assessment (paginated)
$familyMap = [];
$famOffset = 0;
while (true) {
    $familyResult = supabaseRequest('GET',
        'family_members?select=assessment_id,full_name,is_authorized_claimant&is_authorized_claimant=eq.true'
        . '&limit=' . $pageSize . '&offset=' . $famOffset
    );

    if (!$familyResult['success']) break;

    $famPage = $familyResult['data'] ?? [];
    foreach ($famPage as $fm) {
        $aid = $fm['assessment_id'];
        $name = trim($fm['full_name'] ?? '');
        if ($name === '') continue;
        if (!isset($familyMap[$aid])) {
            $familyMap[$aid] = [];
        }
        $familyMap[$aid][] = $name;
    }

    if (count($famPage) < $pageSize) break;
    $famOffset += $pageSize;
}

// Build final payout rows
$rows = [];
foreach ($assessments as $a) {
    $child = $a['children'] ?? [];
    $firstName     = trim($child['first_name']     ?? '');
    $lastName      = trim($child['last_name']      ?? '');
    $middleName    = trim($child['middle_name']    ?? '');
    $nameExtension = trim($child['name_extension'] ?? '');

    // Format: Last Name, First Name Middle Name Extension
    $beneficiaryName = $lastName;
    if ($firstName) $beneficiaryName .= ', ' . $firstName;
    if ($middleName) $beneficiaryName .= ' ' . $middleName;
    if ($nameExtension) $beneficiaryName .= ' ' . $nameExtension;

    // Authorized claimant (only one allowed)
    $claimantNames = $familyMap[$a['id']] ?? [];
    $claimantName = $claimantNames[0] ?? '';

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
