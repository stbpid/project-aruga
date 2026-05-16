<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$DEFAULTS = [
    'idleTimeout'          => '30',
    'absoluteTimeout'      => '8',
    'timeoutWarning'       => '5',
    'rememberMeDuration'   => '30',
    'maxFailedAttempts'    => '5',
    'lockoutDuration'      => '30',
    'forcePasswordChange'  => 'true',
    'passwordMinLength'    => '8',
    'requireUppercase'     => 'true',
    'requireNumbers'       => 'true',
    'requireSpecialChars'  => 'true',
    'passwordExpiry'       => '90',
    'preventPasswordReuse' => 'false',
];

$FIELDS = array_keys($DEFAULTS);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $res = supabaseRequest('GET', 'system_settings?select=key,value&key=in.(' . implode(',', $FIELDS) . ')&limit=100');
    if (!$res['success']) {
        echo json_encode(['success' => true, 'data' => $DEFAULTS]); exit;
    }
    $data = $DEFAULTS;
    foreach (($res['data'] ?? []) as $row) {
        if (isset($DEFAULTS[$row['key']])) $data[$row['key']] = $row['value'];
    }
    echo json_encode(['success' => true, 'data' => $data]); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Invalid JSON']); exit; }

    $upserts = [];
    foreach ($FIELDS as $field) {
        if (isset($body[$field])) {
            $upserts[] = ['key' => $field, 'value' => (string)$body[$field]];
        }
    }

    if (empty($upserts)) { echo json_encode(['success' => true]); exit; }

    $res = supabaseRequest('POST', 'system_settings?on_conflict=key', $upserts);
    if (!$res['success']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save settings']);
        exit;
    }
    $changedKeys = array_column($upserts, 'key');
    logAudit('update', 'system_settings', null, null,
        ['event' => 'security_settings_changed', 'fields' => $changedKeys]
    );
    echo json_encode(['success' => true]); exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
