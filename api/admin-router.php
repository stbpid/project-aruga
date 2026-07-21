<?php
/**
 * Admin Router — consolidates:
 *   - add-signatory.php     (action=add-signatory)     [requires auth.php]
 *   - get-signatories.php   (action=get-signatories)   [requires auth.php]
 *   - update-signatory.php  (action=update-signatory)  [requires auth.php]
 *   - get-audit-logs.php    (action=audit-logs)        [requires auth.php]
 *   - export-backup.php     (action=export-backup)     [NO auth.php — uses X-Admin-Token instead]
 *   - get-locations.php     (action=locations)         [NO auth.php]
 *   - get-options.php       (action=options)           [NO auth.php]
 *   - migrate-regions.php   (action=migrate-regions)   [NO auth.php]
 *
 * auth.php is required lazily per-case to preserve exact original auth behavior
 * (auth.php calls requireAuth() immediately on include).
 */
require_once __DIR__ . '/lib/config.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    // ================================================================
    // action=add-signatory  (was api/add-signatory.php)
    // ================================================================
    case 'add-signatory': {
        require_once __DIR__ . '/lib/auth.php';

        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        requireRole(['admin']);

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) { echo json_encode(['success' => false, 'message' => 'Invalid JSON']); exit; }

        $fullname = trim($body['signatory_fullname'] ?? '');
        $position = trim($body['signatory_position'] ?? '');
        $office   = trim($body['signatory_office']   ?? '');
        $region   = trim($body['signatory_region']   ?? '');
        $function = trim($body['signatory_function'] ?? '');
        $status   = trim($body['signatory_status']   ?? 'active');

        if (!$fullname || !$position || !$office || !$region || !$function) {
            echo json_encode(['success' => false, 'message' => 'Full Name, Position, Office, Region, and Function are required.']); exit;
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $payload = [
            'signatory_fullname' => $fullname,
            'signatory_position' => $position,
            'signatory_office'   => $office,
            'signatory_region'   => $region,
            'signatory_function' => $function,
            'signatory_status'   => $status,
        ];

        $res = supabaseRequest('POST', 'signatories', $payload);

        if (!$res['success']) {
            error_log('add-signatory error: ' . ($res['error'] ?? 'Unknown'));
            echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']); exit;
        }

        $newId = $res['data'][0]['id'] ?? null;
        logAudit('create', 'signatories', $newId, null, $payload, null);

        echo json_encode(['success' => true, 'message' => 'Signatory added successfully']);
        break;
    }

    // ================================================================
    // action=get-signatories  (was api/get-signatories.php)
    // ================================================================
    case 'get-signatories': {
        require_once __DIR__ . '/lib/auth.php';

        header('Content-Type: application/json');
        requireRole(['admin', 'central', 'stu_head']);

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false]); exit;
        }

        $res = supabaseRequest('GET',
            'signatories?select=id,signatory_fullname,signatory_position,signatory_office,signatory_region,signatory_function,signatory_status&order=signatory_fullname.asc&limit=10000'
        );

        if (!$res['success']) {
            echo json_encode(['success' => false, 'data' => []]); exit;
        }

        echo json_encode(['success' => true, 'data' => $res['data']]);
        break;
    }

    // ================================================================
    // action=update-signatory  (was api/update-signatory.php)
    // ================================================================
    case 'update-signatory': {
        require_once __DIR__ . '/lib/auth.php';

        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        requireRole(['admin']);

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) { echo json_encode(['success' => false, 'message' => 'Invalid JSON']); exit; }

        $id = trim($body['id'] ?? '');
        if (!$id) { echo json_encode(['success' => false, 'message' => 'id is required']); exit; }

        $fields = [];
        if (!empty($body['signatory_fullname'])) $fields['signatory_fullname'] = trim($body['signatory_fullname']);
        if (!empty($body['signatory_position'])) $fields['signatory_position'] = trim($body['signatory_position']);
        if (!empty($body['signatory_office']))   $fields['signatory_office']   = trim($body['signatory_office']);
        if (!empty($body['signatory_region']))   $fields['signatory_region']   = trim($body['signatory_region']);
        if (!empty($body['signatory_function'])) $fields['signatory_function'] = trim($body['signatory_function']);
        if (isset($body['signatory_status']) && in_array($body['signatory_status'], ['active', 'inactive'], true)) {
            $fields['signatory_status'] = $body['signatory_status'];
        }

        if (empty($fields)) {
            echo json_encode(['success' => false, 'message' => 'No fields to update']); exit;
        }

        $oldRes = supabaseRequest('GET', 'signatories?select=id,signatory_fullname,signatory_position,signatory_office,signatory_region,signatory_function,signatory_status&id=eq.' . urlencode($id) . '&limit=1');
        $old    = ($oldRes['success'] && !empty($oldRes['data'])) ? $oldRes['data'][0] : null;

        $res = supabaseRequest('PATCH', 'signatories?id=eq.' . urlencode($id), $fields);

        if (!$res['success']) {
            error_log('update-signatory error: ' . ($res['error'] ?? 'Unknown'));
            echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']); exit;
        }

        logAudit('update', 'signatories', $id, $old ? array_intersect_key($old, $fields) : null, $fields, null);

        echo json_encode(['success' => true, 'message' => 'Signatory updated successfully']);
        break;
    }

    // ================================================================
    // action=audit-logs  (was api/get-audit-logs.php)
    // ================================================================
    case 'audit-logs': {
        require_once __DIR__ . '/lib/auth.php';

        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');
        requireRole(['admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false]); exit;
        }

        $limit  = getInt('limit', 500, 1, 1000);
        $auditAction = getStr('action');
        $search = getStr('search');

        $query = 'audit_logs?select=id,action,table_name,record_id,old_values,new_values,ip_address,user_agent,created_at,interviewer_id,assessment_id&order=created_at.desc&limit=' . $limit;

        if ($auditAction) $query .= '&action=eq.' . urlencode($auditAction);

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
        break;
    }

    // ================================================================
    // action=export-backup  (was api/export-backup.php)
    // Admin only — NOT via auth.php session; uses X-Admin-Token header instead.
    // ================================================================
    case 'export-backup': {
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Token');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

        // Token must be sent via X-Admin-Token header only — never via URL parameter
        $token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
        $validToken = getenv('ADMIN_EXPORT_TOKEN') ?: ($_ENV['ADMIN_EXPORT_TOKEN'] ?? $_SERVER['ADMIN_EXPORT_TOKEN'] ?? '');

        if (empty($validToken) || empty($token) || !hash_equals($validToken, $token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $format = strtolower($body['format'] ?? 'json'); // 'json' or 'csv'
        $table  = $body['table'] ?? 'all'; // specific table name or 'all'

        // Exportable tables. audit_logs is excluded — it grows unbounded and was
        // causing the "all tables" backup to time out / 500 on Vercel. Export it
        // separately (table=audit_logs) or via the Supabase SQL editor if needed.
        $tables = [
            'interviewers',
            'sessions',
            'assessments',
            'pre_qualification',
            'respondents',
            'children',
            'child_education_health',
            'family_members',
            'socio_economic',
            'health_info',
            'education_info',
            'economic_capacity',
            'service_availment',
            'assessment_notes',
        ];

        // If a specific table is requested, validate it (audit_logs is only
        // exportable this way, not as part of "all", since it's excluded above)
        if ($table !== 'all') {
            if ($table !== 'audit_logs' && !in_array($table, $tables, true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Unknown table: ' . $table]);
                exit;
            }
            $tables = [$table];
        }

        // Fetch data for each table (paginated in chunks of 1000)
        if (!function_exists('fetchAllRows')) {
            function fetchAllRows($tableName) {
                $rows   = [];
                $limit  = 1000;
                $offset = 0;

                while (true) {
                    $endpoint = $tableName . '?select=*&limit=' . $limit . '&offset=' . $offset;
                    $result   = supabaseRequest('GET', $endpoint);

                    if (!$result['success'] || empty($result['data'])) break;

                    $chunk = $result['data'];
                    $rows  = array_merge($rows, $chunk);

                    if (count($chunk) < $limit) break;
                    $offset += $limit;
                }

                return $rows;
            }
        }

        $timestamp = date('Y-m-d_His');

        // ── JSON export ──────────────────────────────────────────────────────────────
        // Streamed table-by-table so memory never holds more than one table at a time
        // (holding all 15 tables, especially audit_logs, in memory at once caused 500s).
        if ($format === 'json') {
            $filename = 'aruga-backup-' . ($table === 'all' ? 'all-tables' : $table) . '-' . $timestamp . '.json';
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            echo '{"exported_at":' . json_encode(date('c')) . ',"total_tables":' . count($tables) . ',"tables":{';
            $totalRows = 0;
            $first = true;
            foreach ($tables as $t) {
                $rows = fetchAllRows($t);
                $totalRows += count($rows);
                if (!$first) echo ',';
                $first = false;
                echo json_encode($t, JSON_UNESCAPED_UNICODE) . ':' . json_encode($rows, JSON_UNESCAPED_UNICODE);
                unset($rows);
                flush();
            }
            echo '},"total_rows":' . $totalRows . '}';
            exit;
        }

        // ── CSV export (one file per table, delivered as a single CSV if one table,
        //    or as a JSON manifest listing per-table CSVs if multiple — browser-friendly) ─
        // Tables are fetched one at a time (not prefetched all at once) to avoid
        // holding every table in memory simultaneously, which caused 500s on "all tables".
        if ($format === 'csv') {
            if (count($tables) === 1) {
                // Single table → return raw CSV
                $t    = $tables[0];
                $rows = fetchAllRows($t);
                $filename = 'aruga-' . $t . '-' . $timestamp . '.csv';

                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');

                $out = fopen('php://output', 'w');
                // BOM for Excel UTF-8 compatibility
                fwrite($out, "\xEF\xBB\xBF");

                if (!empty($rows)) {
                    fputcsv($out, array_keys($rows[0]));
                    foreach ($rows as $row) {
                        // Flatten arrays/objects to JSON strings for CSV cells
                        $flat = array_map(function ($v) {
                            return is_array($v) || is_object($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
                        }, $row);
                        fputcsv($out, $flat);
                    }
                }
                fclose($out);
                exit;
            }

            // Multiple tables → return a JSON bundle describing each CSV payload
            // (browser will trigger one download per table via JS)
            header('Content-Type: application/json');
            $bundle = [];
            foreach ($tables as $t) {
                $rows = fetchAllRows($t);
                ob_start();
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                if (!empty($rows)) {
                    fputcsv($out, array_keys($rows[0]));
                    foreach ($rows as $row) {
                        $flat = array_map(function ($v) {
                            return is_array($v) || is_object($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
                        }, $row);
                        fputcsv($out, $flat);
                    }
                }
                fclose($out);
                $csv = ob_get_clean();
                $bundle[] = [
                    'table'    => $t,
                    'filename' => 'aruga-' . $t . '-' . $timestamp . '.csv',
                    'rows'     => count($rows),
                    'csv'      => base64_encode($csv),
                ];
            }
            echo json_encode(['success' => true, 'files' => $bundle], JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid format. Use json or csv.']);
        break;
    }

    // ================================================================
    // action=locations  (was api/get-locations.php) — NO auth.php
    // ================================================================
    case 'locations': {
        header('Content-Type: application/json');

        $locations = [
    'Region I (Ilocos Region)' => [
        'Ilocos Norte' => [
            'Laoag City' => [
                'Barangay 1 - San Lorenzo',
                'Barangay 2 - Santa Joaquina',
                'Barangay 3 - Nuestra Señora del Rosario',
                'Barangay 4 - San Guillermo',
                'Barangay 5 - San Pedro',
                'Barangay 6 - San Agustin',
                'Barangay 7-A - Nuestra Señora de Natividad',
                'Barangay 7-B - Nuestra Señora de Natividad',
                'Barangay 8 - San Vicente',
                'Barangay 9 - Santa Angela',
                'Barangay 10 - San Jose',
                'Barangay 11 - Santa Balbina',
                'Barangay 12 - San Isidro',
                'Barangay 13 - Nuestra Señora de Visitacion',
                'Barangay 14 - Santo Tomas',
                'Barangay 15 - San Guillermo',
                'Barangay 16 - San Jacinto',
                'Barangay 17 - San Francisco',
                'Barangay 18 - San Quirino',
                'Barangay 19 - Santa Marcela',
                'Barangay 20 - San Miguel',
                'Barangay 21 - San Pedro',
                'Barangay 22 - San Andres',
                'Barangay 23 - San Matias',
                'Barangay 24 - Nuestra Señora de Consolacion',
                'Barangay 25 - Santa Cayetana',
                'Barangay 26 - San Marcelino',
                'Barangay 27 - Nuestra Señora de Soledad',
                'Barangay 28 - San Bernardo',
                'Barangay 29 - Santo Tomas',
                'Barangay 30-A - Suyo',
                'Barangay 30-B - Santa Maria',
                'Barangay 31 - Talingaan',
                'Barangay 32-A - La Paz East',
                'Barangay 32-B - La Paz West',
                'Barangay 32-C - La Paz East',
                'Barangay 33-A - La Paz Proper',
                'Barangay 33-B - La Paz Proper',
                'Barangay 34-A - Gabu Norte West',
                'Barangay 34-B - Gabu Norte East',
                'Barangay 35 - Gabu Sur',
                'Barangay 36 - Araniw',
                'Barangay 37 - Calayab',
                'Barangay 38-A - Mangato East',
                'Barangay 38-B - Mangato West',
                'Barangay 39 - Santa Rosa',
                'Barangay 40 - Balatong',
                'Barangay 41 - Balacad',
                'Barangay 42 - Apaya',
                'Barangay 43 - Cavit',
                'Barangay 44 - Zamboanga',
                'Barangay 45 - Tangid',
                'Barangay 46 - Nalbo',
                'Barangay 47 - Bengcag',
                'Barangay 48-A - Cabungaan North',
                'Barangay 48-B - Cabungaan South',
                'Barangay 49-A - Darayday',
                'Barangay 49-B - Raraburan',
                'Barangay 50 - Buttong',
                'Barangay 51-A - Nangalisan East',
                'Barangay 51-B - Nangalisan West',
                'Barangay 52-A - San Mateo',
                'Barangay 52-B - Lataag',
                'Barangay 53 - Rioeng',
                'Barangay 54-A - Lagui-Sail',
                'Barangay 54-B - Camangaan',
                'Barangay 55-A - Barit-Pandan',
                'Barangay 55-B - Salet-Bulangon',
                'Barangay 55-C - Vira',
                'Barangay 56-A - Bacsil North',
                'Barangay 56-B - Bacsil South',
                'Barangay 57 - Pila',
                'Barangay 58 - Casili',
                'Barangay 59-A - Dibua South',
                'Barangay 59-B - Dibua North',
                'Barangay 60-A - Caaoacan',
                'Barangay 60-B - Madiladig',
                'Barangay 61 - Cataban',
                'Barangay 62-A - Navotas North',
                'Barangay 62-B - Navotas South'
            ]
        ]
    ],
    'Region II (Cagayan Valley)' => [
        'Isabela' => [
            'Ilagan City' => [
                'Aggasian',
                'Alibagu',
                'Alinguigan 1st',
                'Alinguigan 2nd',
                'Alinguigan 3rd',
                'Arusip',
                'Baculud',
                'Bagong Silang',
                'Bagumbayan',
                'Baligatan',
                'Ballacong',
                'Bangag',
                'Baraoan',
                'Batong-Labang',
                'Cabeseria 2',
                'Cabeseria 3',
                'Cabeseria 4',
                'Cabeseria 5',
                'Cabeseria 6 & 24',
                'Cabeseria 7',
                'Cabeseria 8',
                'Cabeseria 9 & 11',
                'Cabeseria 10',
                'Cabeseria 14 & 16',
                'Cabeseria 17 & 21',
                'Cabeseria 19',
                'Cabeseria 22',
                'Cabeseria 23',
                'Cabeseria 25',
                'Cabeseria 27',
                'Cabannungan 1st',
                'Cabannungan 2nd',
                'Cadu',
                'Calamagui 1st',
                'Calamagui 2nd',
                'Camunatan',
                'Capellan',
                'Capo',
                'Carikkikan Norte',
                'Carikkikan Sur',
                'Centro Poblacion',
                'Centro-San Antonio',
                'Fugu',
                'Fuyo',
                'Gayong-gayong Norte',
                'Gayong-gayong Sur',
                'Guinatan',
                'Hantas',
                'Imelda Bliss Village',
                'Indagan',
                'Ipalao',
                'Lullutan',
                'Malalam',
                'Malasin',
                'Manaring',
                'Mangcuram',
                'Marana I',
                'Marana II',
                'Marana III',
                'Minabang',
                'Morado',
                'Naguilian Norte',
                'Naguilian Sur',
                'Namnama',
                'Nanaguan',
                'Osmeña',
                'Paliueg',
                'Pilar',
                'Pasa',
                'Piñares',
                'Quimalabasa',
                'Rang-ayan',
                'Rugao',
                'Saguiguilid del Norte',
                'Saguiguilid del Sur',
                'Salindingan',
                'San Andres',
                'San Felipe',
                'San Ignacio',
                'San Isidro',
                'San Juan',
                'San Lorenzo',
                'San Pablo',
                'San Rodrigo',
                'San Vicente',
                'Santa Barbara',
                'Santa Catalina',
                'Santa Isabel Norte',
                'Santa Isabel Sur',
                'Santa Victoria',
                'Santo Tomas',
                'Siffu',
                'Sindon Bayabo',
                'Sindon Maride',
                'Sipay',
                'Tangcul',
                'Tegge',
                'Valleyan',
                'Vanutas',
                'Villa Imelda'
            ]
        ]
    ],
    'Region III (Central Luzon)' => [
        'Pampanga' => [
            'Lubao' => [
                'Balantacan',
                'Bancal Pugad',
                'Bancal Sinubli',
                'Baruya',
                'Calangain',
                'Concepcion',
                'De La Paz',
                'Del Carmen',
                'Don Ignacio Dimson',
                'Lourdes',
                'Prado Siongco',
                'Remedios',
                'San Agustin',
                'San Antonio',
                'San Francisco',
                'San Isidro',
                'San Jose Apunan',
                'San Jose Gumi',
                'San Juan',
                'San Matias',
                'San Miguel',
                'San Nicolas 1st',
                'San Nicolas 2nd',
                'San Pablo 1st',
                'San Pablo 2nd',
                'San Pedro Palcarangan',
                'San Pedro Saug',
                'San Roque Arbol',
                'San Roque Dau',
                'San Vicente',
                'Santa Barbara',
                'Santa Catalina',
                'Santa Cruz',
                'Santa Lucia',
                'Santa Maria',
                'Santa Monica',
                'Santa Rita',
                'Santa Teresa 1st',
                'Santa Teresa 2nd',
                'Santiago',
                'Santo Cristo',
                'Santo Domingo',
                'Santo Niño',
                'Santo Tomas'
            ]
        ]
    ],
    'Region IV-A (CALABARZON)' => [
        'Laguna' => [
            'Biñan City' => [
                'Biñan',
                'Bungahan',
                'Canlalay',
                'Casile',
                'De La Paz',
                'Ganado',
                'Langkiwa',
                'Loma',
                'Malaban',
                'Malamig',
                'Mampalasan',
                'Platero',
                'Poblacion',
                'San Antonio',
                'San Francisco',
                'San Jose',
                'San Vicente',
                'Santo Domingo',
                'Santo Niño',
                'Santo Tomas',
                'Soro-soro',
                'Timbao',
                'Tubigan',
                'Zapote'
            ]
        ]
    ],
    'Region IV-B (MIMAROPA)' => [
        'Palawan' => [
            'Puerto Princesa City' => [
                'Babuyan',
                'Bacungan',
                'Bagong Bayan',
                'Bagong Pag-asa',
                'Bagong Sikat',
                'Bagong Silang',
                'Bahile',
                'Bancao-bancao',
                'Barangay ng mga Mangingisda',
                'Binduyan',
                'Buenavista',
                'Cabayugan',
                'Concepcion',
                'Inagawan',
                'Inagawan Sub-Colony',
                'Irawan',
                'Iwahig',
                'Kalipay',
                'Kamuning',
                'Langogan',
                'Liwanag',
                'Lucbuan',
                'Luzviminda',
                'Mabuhay',
                'Macarascas',
                'Magkakaibigan',
                'Maligaya',
                'Manalo',
                'Mandaragat',
                'Manggahan',
                'Maningning',
                'Maoyon',
                'Marufinas',
                'Maruyogon',
                'Masigla',
                'Masikap',
                'Masipag',
                'Matahimik',
                'Matiyaga',
                'Maunlad',
                'Milagrosa',
                'Model',
                'Montible',
                'Napsan',
                'New Panggangan',
                'Pagkakaisa',
                'Princesa',
                'Salvacion',
                'San Jose',
                'San Manuel',
                'San Miguel',
                'San Pedro',
                'San Rafael',
                'Santa Cruz',
                'Santa Lourdes',
                'Santa Lucia',
                'Santa Monica',
                'Seaside',
                'Sicsican',
                'Simpocan',
                'Tagabinit',
                'Tagburos',
                'Tagumpay',
                'Tanabag',
                'Tanglaw',
                'Tiniguiban'
            ]
        ]
    ],
    'Region V (Bicol Region)' => [
        'Sorsogon' => [
            'Sorsogon City' => [
                'Abuyog',
                'Almendras-Cogon',
                'Balete',
                'Balogo (Bacon District)',
                'Balogo (Sorsogon East District)',
                'Barayong',
                'Basud',
                'Bato',
                'Bibincahan',
                'Bitan-o/Dalipay',
                'Bogña',
                'Bon-ot',
                'Bucalbucalan',
                'Buenavista (Bacon District)',
                'Buenavista (Sorsogon East District)',
                'Buenavista (West District)',
                'Buhatan',
                'Bulabog',
                'Burabod',
                'Cabarbuhan',
                'Cabid-an',
                'Cambulaga',
                'Capuy',
                'Caricaran',
                'Del Rosario',
                'Gatbo',
                'Gimaloto',
                'Guinlajon',
                'Jamislagan',
                'Macabog',
                'Maricrum',
                'Marinas',
                'Osiao',
                'Pamurayan',
                'Pangpang',
                'Panlayaan',
                'Peñafrancia',
                'Piot',
                'Poblacion',
                'Polvorista',
                'Rawis',
                'Rizal',
                'Salog',
                'Salvacion (Bacon District)',
                'Salvacion (Sorsogon East District)',
                'Sampaloc',
                'San Isidro (Bacon District)',
                'San Isidro (Sorsogon East District)',
                'San Isidro (West District)',
                'San Juan (Bacon District)',
                'San Juan (Roro)',
                'San Pascual',
                'San Ramon',
                'San Roque',
                'San Vicente',
                'Santa Cruz',
                'Santa Lucia',
                'Santo Domingo',
                'Santo Niño',
                'Sawanga',
                'Sirangan',
                'Sugod',
                'Sulucan',
                'Talisay',
                'Ticol',
                'Tugos'
            ]
        ]
    ],
    'Region VI (Western Visayas)' => [
        'Antique' => [
            'San Jose de Buenavista' => [
                'Atabay',
                'Badiang',
                'Barangay 1 (Poblacion)',
                'Barangay 2 (Poblacion)',
                'Barangay 3 (Poblacion)',
                'Barangay 4 (Poblacion)',
                'Barangay 5 (Poblacion)',
                'Barangay 6 (Poblacion)',
                'Barangay 7 (Poblacion)',
                'Barangay 8 (Poblacion)',
                'Bariri',
                'Bugarot',
                'Cansadan',
                'Durog',
                'Funda-Dalipe',
                'Igbonglo',
                'Inabasan',
                'Madrangca',
                'Magcalon',
                'Malaiba',
                'Maybato Norte',
                'Maybato Sur',
                'Mojon',
                'Pantao',
                'San Angel',
                'San Fernando',
                'San Pedro',
                'Supa'
            ]
        ]
    ],
    'Region XI (Davao Region)' => [
        'Davao del Sur' => [
            'Sta. Cruz' => [
                'Astorga',
                'Bato',
                'Coronon',
                'Darong',
                'Inawayan',
                'Jose Rizal',
                'Matutungan',
                'Melilia',
                'Saliducon',
                'Sibulan',
                'Sinoron',
                'Tagabuli',
                'Tibolo',
                'Tuban',
                'Zone I (Poblacion)',
                'Zone II (Poblacion)',
                'Zone III (Poblacion)',
                'Zone IV (Poblacion)'
            ]
        ]
    ],
    'NCR (National Capital Region)' => [
        'Metro Manila' => [
            'Caloocan City' => [
                'Barangay 1',
                'Barangay 2',
                'Barangay 3',
                'Barangay 4',
                'Barangay 5',
                'Barangay 6',
                'Barangay 7',
                'Barangay 8',
                'Barangay 9',
                'Barangay 10',
                'Barangay 11',
                'Barangay 12',
                'Barangay 13',
                'Barangay 14',
                'Barangay 15',
                'Barangay 16',
                'Barangay 17',
                'Barangay 18',
                'Barangay 19',
                'Barangay 20',
                'Barangay 21',
                'Barangay 22',
                'Barangay 23',
                'Barangay 24',
                'Barangay 25',
                'Barangay 26',
                'Barangay 27',
                'Barangay 28',
                'Barangay 29',
                'Barangay 30',
                'Barangay 31',
                'Barangay 32',
                'Barangay 33',
                'Barangay 34',
                'Barangay 35',
                'Barangay 36',
                'Barangay 37',
                'Barangay 38',
                'Barangay 39',
                'Barangay 40',
                'Barangay 41',
                'Barangay 42',
                'Barangay 43',
                'Barangay 44',
                'Barangay 45',
                'Barangay 46',
                'Barangay 47',
                'Barangay 48',
                'Barangay 49',
                'Barangay 50',
                'Barangay 51',
                'Barangay 52',
                'Barangay 53',
                'Barangay 54',
                'Barangay 55',
                'Barangay 56',
                'Barangay 57',
                'Barangay 58',
                'Barangay 59',
                'Barangay 60',
                'Barangay 61',
                'Barangay 62',
                'Barangay 63',
                'Barangay 64',
                'Barangay 65',
                'Barangay 66',
                'Barangay 67',
                'Barangay 68',
                'Barangay 69',
                'Barangay 70',
                'Barangay 71',
                'Barangay 72',
                'Barangay 73',
                'Barangay 74',
                'Barangay 75',
                'Barangay 76',
                'Barangay 77',
                'Barangay 78',
                'Barangay 79',
                'Barangay 80',
                'Barangay 81',
                'Barangay 82',
                'Barangay 83',
                'Barangay 84',
                'Barangay 85',
                'Barangay 86',
                'Barangay 87',
                'Barangay 88',
                'Barangay 89',
                'Barangay 90',
                'Barangay 91',
                'Barangay 92',
                'Barangay 93',
                'Barangay 94',
                'Barangay 95',
                'Barangay 96',
                'Barangay 97',
                'Barangay 98',
                'Barangay 99',
                'Barangay 100',
                'Barangay 101',
                'Barangay 102',
                'Barangay 103',
                'Barangay 104',
                'Barangay 105',
                'Barangay 106',
                'Barangay 107',
                'Barangay 108',
                'Barangay 109',
                'Barangay 110',
                'Barangay 111',
                'Barangay 112',
                'Barangay 113',
                'Barangay 114',
                'Barangay 115',
                'Barangay 116',
                'Barangay 117',
                'Barangay 118',
                'Barangay 119',
                'Barangay 120',
                'Barangay 121',
                'Barangay 122',
                'Barangay 123',
                'Barangay 124',
                'Barangay 125',
                'Barangay 126',
                'Barangay 127',
                'Barangay 128',
                'Barangay 129',
                'Barangay 130',
                'Barangay 131',
                'Barangay 132',
                'Barangay 133',
                'Barangay 134',
                'Barangay 135',
                'Barangay 136',
                'Barangay 137',
                'Barangay 138',
                'Barangay 139',
                'Barangay 140',
                'Barangay 141',
                'Barangay 142',
                'Barangay 143',
                'Barangay 144',
                'Barangay 145',
                'Barangay 146',
                'Barangay 147',
                'Barangay 148',
                'Barangay 149',
                'Barangay 150',
                'Barangay 151',
                'Barangay 152',
                'Barangay 153',
                'Barangay 154',
                'Barangay 155',
                'Barangay 156',
                'Barangay 157',
                'Barangay 158',
                'Barangay 159',
                'Barangay 160',
                'Barangay 161',
                'Barangay 162',
                'Barangay 163',
                'Barangay 164',
                'Barangay 165',
                'Barangay 166',
                'Barangay 167',
                'Barangay 168',
                'Barangay 169',
                'Barangay 170',
                'Barangay 171',
                'Barangay 172',
                'Barangay 173',
                'Barangay 174',
                'Barangay 175',
                'Barangay 176',
                'Barangay 177',
                'Barangay 178',
                'Barangay 179',
                'Barangay 180',
                'Barangay 181',
                'Barangay 182',
                'Barangay 183',
                'Barangay 184',
                'Barangay 185',
                'Barangay 186',
                'Barangay 187',
                'Barangay 188'
            ]
        ]
    ]
];

        echo json_encode($locations, JSON_PRETTY_PRINT);
        break;
    }

    // ================================================================
    // action=options  (was api/get-options.php) — NO auth.php
    // ================================================================
    case 'options': {
        header('Content-Type: application/json');

        $options = [
            'List_Religion' => [
                'No Religion',
                'Roman Catholic',
                'Islam',
                'Iglesia ni Cristo',
                'Protestant',
                'Born Again Christian',
                'Buddhism',
                'Hinduism',
                'Others'
            ],
            'List_IP' => [
                'Not a member',
                'Aeta',
                'Igorot',
                'Lumad',
                'Mangyan',
                'Tagbanua',
                'Badjao',
                'T\'boli',
                'Manobo',
                'Others'
            ],
            'List_Education' => [
                'No formal education',
                'Elementary Undergraduate',
                'Elementary Graduate',
                'High School Undergraduate',
                'High School Graduate',
                'Senior High School Graduate',
                'College Undergraduate',
                'College Graduate',
                'Vocational/Technical',
                'Post Graduate',
                'Others'
            ],
            'List_Relationship' => [
                'Parent',
                'Guardian',
                'Grandparent',
                'Sibling',
                'Aunt/Uncle',
                'Relative',
                'Social Worker',
                'Foster Parent'
            ],
            'List_Disability' => [
                'None',
                'Visual Disability',
                'Hearing Disability',
                'Speech and Language Impairment',
                'Orthopedic / Physical Disability',
                'Mental / Intellectual Disability',
                'Learning Disability',
                'Psychosocial Disability',
                'Disability Resulting from a Chronic Illness',
                'Multiple Disabilities',
                'Other (specify)'
            ],
            'List_Illness' => [
                'None',
                'Cancer',
                'Heart Disease',
                'Kidney Disease',
                'Diabetes',
                'Respiratory Disease',
                'Neurological Disorder',
                'Blood Disorder',
                'Chronic Illness',
                'Others'
            ],
            'List_Extension' => [
                'None',
                'Jr.',
                'Sr.',
                'II',
                'III',
                'IV',
                'V'
            ],
            'List_Occupation' => [
                'Unemployed',
                'Employed (Private Sector)',
                'Employed (Government)',
                'Self-employed',
                'Farmer',
                'Fisherman',
                'Laborer',
                'Vendor',
                'Driver',
                'Housewife/Househusband',
                'Student',
                'OFW (Overseas Filipino Worker)',
                'Retired',
                'Others'
            ],
            'List_Occupation_Class' => [
                'Professional',
                'Technical',
                'Clerical',
                'Sales',
                'Service Worker',
                'Agricultural Worker',
                'Production/Laborer',
                'Elementary Occupation',
                'Not Applicable'
            ],
            'List_Materials' => [
                'Strong materials (Concrete, brick, stone)',
                'Light materials (Wood, bamboo, nipa)',
                'Mixed strong and light materials',
                'Salvaged/Makeshift materials',
                'Others'
            ],
            'List_Tenure' => [
                'Own house and lot',
                'Own house, rented lot',
                'Rent house and lot',
                'Rent-free with consent of owner',
                'Informal settler',
                'Others'
            ],
            'List_Electricity' => [
                'Electricity from distribution company',
                'Community electricity system',
                'Solar panel',
                'Generator set',
                'Kerosene lamp/Candles',
                'No electricity',
                'Others'
            ],
            'List_Water' => [
                'Own use, faucet, community water system',
                'Own use, faucet, NAWASA/Water district',
                'Own use, tubed/piped deep well',
                'Shared, faucet, community water system',
                'Shared, faucet, NAWASA/Water district',
                'Shared, tubed/piped deep well',
                'Public tap/standpipe',
                'Tubed/piped shallow well',
                'Dug/open well',
                'Spring, lake, river, rain',
                'Peddler, bottled water',
                'Others'
            ],
            'List_Toilet' => [
                'Water-sealed, sewer/septic tank, used exclusively by household',
                'Water-sealed, sewer/septic tank, shared with other households',
                'Water-sealed, other depository',
                'Closed pit',
                'Open pit',
                'Drop/overhang',
                'None',
                'Others'
            ],
            'List_Garbage' => [
                'Picked up by garbage truck',
                'Burning',
                'Burying',
                'Composting',
                'Dumping in pit',
                'Throw in river/sea/creek',
                'Recycling',
                'Others'
            ]
        ];

        echo json_encode($options, JSON_PRETTY_PRINT);
        break;
    }

    // ================================================================
    // action=migrate-regions  (was api/migrate-regions.php) — NO auth.php
    // ================================================================
    case 'migrate-regions': {
        header('Content-Type: application/json');

        // Only allow POST requests for safety
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST only']);
            exit;
        }

        $migrations = [
            ['old' => 'Region V (Bicol)',       'new' => 'Region V (Bicol Region)'],
            ['old' => 'Region IV-A (MIMAROPA)', 'new' => 'Region IV-B (MIMAROPA)'],
        ];

        $results = [];

        foreach ($migrations as $m) {
            // Update children table
            $url = SUPABASE_URL . '/rest/v1/children?region=eq.' . urlencode($m['old']);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'PATCH',
                CURLOPT_HTTPHEADER     => [
                    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
                    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
                    'Content-Type: application/json',
                    'Prefer: return=representation',
                ],
                CURLOPT_POSTFIELDS => json_encode(['region' => $m['new']]),
            ]);
            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $results[] = [
                'table'    => 'children',
                'old'      => $m['old'],
                'new'      => $m['new'],
                'http'     => $httpCode,
                'response' => json_decode($resp),
            ];

            // Update interviewers table
            $url2 = SUPABASE_URL . '/rest/v1/interviewers?region=eq.' . urlencode($m['old']);
            $ch2 = curl_init($url2);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'PATCH',
                CURLOPT_HTTPHEADER     => [
                    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
                    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
                    'Content-Type: application/json',
                    'Prefer: return=representation',
                ],
                CURLOPT_POSTFIELDS => json_encode(['region' => $m['new']]),
            ]);
            $resp2 = curl_exec($ch2);
            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);

            $results[] = [
                'table'    => 'interviewers',
                'old'      => $m['old'],
                'new'      => $m['new'],
                'http'     => $httpCode2,
                'response' => json_decode($resp2),
            ];
        }

        echo json_encode(['success' => true, 'results' => $results], JSON_PRETTY_PRINT);
        break;
    }

    default: {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
    }
}
