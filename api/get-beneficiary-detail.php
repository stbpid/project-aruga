<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://project-aruga.vercel.app');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') { echo json_encode(['success'=>false]); exit; }

$arugaId = trim($_GET['aruga_id'] ?? '');
if (!$arugaId) { echo json_encode(['success'=>false,'message'=>'aruga_id required']); exit; }

$aRes = supabaseRequest('GET', 'assessments?select=id,aruga_id,interviewer_code,readiness_score,status,created_at,completed_at&aruga_id=eq.'.urlencode($arugaId).'&limit=1');
if (!$aRes['success'] || empty($aRes['data'])) { echo json_encode(['success'=>false,'message'=>'Not found']); exit; }
$a = $aRes['data'][0];
$id = $a['id'];

function fetchOne($table, $id) {
    $res = supabaseRequest('GET', $table.'?assessment_id=eq.'.urlencode($id).'&limit=1');
    return ($res['success'] && !empty($res['data'])) ? $res['data'][0] : [];
}
function fetchMany($table, $id) {
    $res = supabaseRequest('GET', $table.'?assessment_id=eq.'.urlencode($id).'&order=member_number.asc');
    return ($res['success'] && is_array($res['data'])) ? $res['data'] : [];
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
