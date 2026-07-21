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
