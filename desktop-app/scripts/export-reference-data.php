<?php
/**
 * Copies the profiling dropdown lists and locations out of api/admin-router.php
 * into desktop-app/src/data/*.json, so the app uses exactly the same lists as
 * the live tool without calling the live server.
 *
 * Usage (from the repo root):  php desktop-app/scripts/export-reference-data.php
 *
 * Runs locally only. No database is contacted: the options and locations
 * actions are static arrays. config.php just needs placeholder values to load.
 */

$repoRoot = realpath(__DIR__ . '/../..');

// Child mode: print one action's JSON (each action runs in its own PHP process
// because admin-router.php can only be included once).
if (isset($argv[1])) {
    putenv('SUPABASE_URL=http://placeholder.invalid');
    putenv('SUPABASE_SERVICE_ROLE_KEY=placeholder');
    $_GET['action'] = $argv[1];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    include $repoRoot . '/api/admin-router.php';
    exit;
}

$outDir = $repoRoot . '/desktop-app/src/data';
if (!is_dir($outDir)) mkdir($outDir, 0777, true);

foreach (['options', 'locations'] as $action) {
    $json = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . $action);
    $data = json_decode((string)$json, true);
    if (!is_array($data) || !$data) {
        fwrite(STDERR, "Failed to export $action\n");
        exit(1);
    }
    file_put_contents(
        "$outDir/$action.json",
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    echo "Wrote desktop-app/src/data/$action.json (" . count($data) . " top-level entries)\n";
}

file_put_contents("$outDir/exported-at.json", json_encode(['exported_at' => date('c')]));
