<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://project-aruga.vercel.app');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { echo json_encode(['success'=>false]); exit; }

$region = trim($_GET['region'] ?? '');
$status = trim($_GET['status'] ?? 'pending');   // pending | approved | for_update | declined | all
$page   = max(1, (int)($_GET['page'] ?? 1));
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
