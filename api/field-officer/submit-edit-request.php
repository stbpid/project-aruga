<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://project-aruga.vercel.app');
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
$aRes = supabaseRequest('GET', 'assessments?select=id,aruga_id&aruga_id=eq.'.urlencode($arugaId).'&limit=1');
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
