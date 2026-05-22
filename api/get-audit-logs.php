<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
}

$limit  = getInt('limit', 500, 1, 1000);
$action = getStr('action');
$search = getStr('search');

$query = 'audit_logs?select=id,action,table_name,record_id,old_values,new_values,ip_address,user_agent,created_at,interviewer_id,assessment_id&order=created_at.desc&limit=' . $limit;

if ($action) $query .= '&action=eq.' . urlencode($action);

$res = supabaseRequest('GET', $query);

if (!$res['success']) {
    echo json_encode(['success' => false, 'data' => []]); exit;
}

// Fetch interviewer lookup
$intRes = supabaseRequest('GET', 'interviewers?select=id,full_name,interviewer_code,region&limit=10000');
$intMap = [];
if ($intRes['success'] && is_array($intRes['data'])) {
    foreach ($intRes['data'] as $i) {
        $intMap[$i['id']] = [
            'name'   => $i['full_name']       ?? '—',
            'code'   => $i['interviewer_code'] ?? '—',
            'region' => $i['region']           ?? '—',
        ];
    }
}

$rows = [];
foreach ($res['data'] as $log) {
    $intId   = $log['interviewer_id'] ?? null;
    $int     = $intId && isset($intMap[$intId]) ? $intMap[$intId] : ['name'=>'System','code'=>'—','region'=>'—'];

    $action  = $log['action']     ?? '—';
    $table   = $log['table_name'] ?? '—';

    // Build human-readable details
    $newVals = $log['new_values'] ?? null;
    if (is_string($newVals)) $newVals = json_decode($newVals, true);

    $event = is_array($newVals) ? ($newVals['event'] ?? null) : null;

    if ($event === 'login' && $table === 'sessions' && isset($newVals['interviewer_code'])) {
        $code   = $newVals['interviewer_code'] ?? '';
        $name   = $newVals['full_name'] ?? '';
        $region = $newVals['region'] ?? '';
        $details = 'Field interviewer logged in' . ($name ? ': ' . $name : '') . ($code ? ' (' . $code . ')' : '') . ($region ? ' — ' . $region : '');
    } elseif ($event === 'login') {
        $email = is_array($newVals) ? ($newVals['email'] ?? '') : '';
        $role  = is_array($newVals) ? ($newVals['role']  ?? '') : '';
        $details = 'Dashboard user logged in' . ($email ? ' as ' . $email : '') . ($role ? ' (' . $role . ')' : '');
    } elseif ($event === 'login_failed') {
        $email = is_array($newVals) ? ($newVals['email'] ?? '') : '';
        $code  = is_array($newVals) ? ($newVals['interviewer_code'] ?? '') : '';
        $reason = is_array($newVals) ? ($newVals['reason'] ?? '') : '';
        $who = $email ?: $code;
        $details = 'Failed login attempt' . ($who ? ' for ' . $who : '') . ($reason ? ' — ' . str_replace('_', ' ', $reason) : '');
    } elseif ($event === 'security_settings_changed') {
        $fields = is_array($newVals) && isset($newVals['fields']) ? implode(', ', $newVals['fields']) : '';
        $details = 'Security settings updated' . ($fields ? ' — fields: ' . $fields : '');
    } elseif ($action === 'create' && $table === 'assessments') {
        $childName = is_array($newVals) ? ($newVals['child_name'] ?? '') : '';
        $arugaId   = is_array($newVals) ? ($newVals['aruga_id']   ?? '') : '';
        $score     = is_array($newVals) ? ($newVals['readiness_score'] ?? '') : '';
        $details   = 'Assessment submitted' . ($childName ? ' for ' . $childName : '') . ($arugaId ? ' (' . $arugaId . ')' : '') . ($score ? ' — ' . ucfirst($score) : '');
    } elseif ($action === 'create' && $table === 'interviewers') {
        $name = is_array($newVals) ? ($newVals['full_name'] ?? '') : '';
        $code = is_array($newVals) ? ($newVals['interviewer_code'] ?? '') : '';
        $details = 'Interviewer added' . ($name ? ': ' . $name : '') . ($code ? ' (' . $code . ')' : '');
    } elseif ($action === 'update' && $table === 'interviewers') {
        $oldVals = $log['old_values'] ?? null;
        if (is_string($oldVals)) $oldVals = json_decode($oldVals, true);
        $changed = [];
        if (is_array($newVals)) {
            foreach ($newVals as $k => $v) {
                $old = is_array($oldVals) ? ($oldVals[$k] ?? null) : null;
                if ($v !== $old) $changed[] = $k;
            }
        }
        $details = 'Interviewer updated' . (count($changed) ? ' — fields: ' . implode(', ', $changed) : '');
    } else {
        $details = ucfirst($action) . ' on ' . str_replace('_', ' ', $table);
        if (is_array($newVals) && isset($newVals['status'])) {
            $details .= ' → status: ' . $newVals['status'];
        }
    }

    $ts = $log['created_at'] ?? null;
    $rows[] = [
        'id'            => $log['id'] ?? null,
        'timestamp'     => $ts ?? '—',
        'timestamp_raw' => $ts ?? '',
        'user'       => $int['name'],
        'code'       => $int['code'],
        'region'     => $int['region'],
        'action'     => $action,
        'table_name' => $table,
        'details'    => $details,
        'ip_address' => $log['ip_address'] ?? '—',
        'record_id'  => $log['record_id'] ?? null,
    ];
}

echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)]);
