<?php
/**
 * Data Backup Export — Admin only
 * Returns all tables as JSON or triggers a CSV zip download.
 */
require_once __DIR__ . '/config.php';

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
$debug  = !empty($body['debug']); // TEMP: when true, report fatal errors as JSON instead of a bare 500

if ($debug) {
    ini_set('display_errors', 0);
    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            echo json_encode(['success' => false, 'debug_fatal' => $e]);
        }
    });
}

// All exportable tables (exclude nothing — admin has full access)
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
    'audit_logs',
];

// If a specific table is requested, validate it
if ($table !== 'all') {
    if (!in_array($table, $tables, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown table: ' . $table]);
        exit;
    }
    $tables = [$table];
}

// Fetch data for each table (paginated in chunks of 1000)
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

$timestamp = date('Y-m-d_His');

// ── JSON export ──────────────────────────────────────────────────────────────
// Streamed table-by-table so memory never holds more than one table at a time
// (holding all 15 tables, especially audit_logs, in memory at once caused 500s).
if ($format === 'json') {
    $filename = 'aruga-backup-' . ($table === 'all' ? 'all-tables' : $table) . '-' . $timestamp . '.json';
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    if ($debug) {
        // TEMP: report per-table timing/row counts as plain JSON, no file download,
        // so we can see exactly where "all tables" is failing.
        header('Content-Type: application/json');
        $report = [];
        $start = microtime(true);
        foreach ($tables as $t) {
            $tStart = microtime(true);
            $rows = fetchAllRows($t);
            $report[] = [
                'table'        => $t,
                'rows'         => count($rows),
                'seconds'      => round(microtime(true) - $tStart, 2),
                'elapsed_total'=> round(microtime(true) - $start, 2),
            ];
            unset($rows);
        }
        echo json_encode(['success' => true, 'debug_report' => $report], JSON_PRETTY_PRINT);
        exit;
    }

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
