<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$arugaId = isset($_GET['id']) ? trim($_GET['id']) : '';
if (!$arugaId) {
    sendResponse(false, 'Missing id parameter', null, 400);
}

// Fetch assessment by aruga_id
$encoded  = urlencode($arugaId);
$result   = supabaseRequest('GET', "assessments?aruga_id=eq.{$encoded}&select=id,aruga_id,created_at,readiness_score,interviewer_code");
if (!$result['success'] || empty($result['data'][0])) {
    sendResponse(false, 'Profile not found', null, 404);
}

$assessment   = $result['data'][0];
$assessmentId = $assessment['id'];

// Fetch child name
$childResult = supabaseRequest('GET', "children?assessment_id=eq.{$assessmentId}&select=first_name,middle_name,last_name");
$child       = $childResult['data'][0] ?? [];
$childName   = trim(($child['first_name'] ?? '') . ' ' . ($child['middle_name'] ? $child['middle_name'] . ' ' : '') . ($child['last_name'] ?? ''));

sendResponse(true, 'Profile found', [
    'aruga_id'        => $assessment['aruga_id'],
    'child_name'      => $childName,
    'registered_at'   => $assessment['created_at'],
    'readiness_score' => $assessment['readiness_score'],
]);
