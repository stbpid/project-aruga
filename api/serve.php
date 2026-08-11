<?php
/**
 * Static HTML router for clean URLs.
 * Maps clean URL paths to HTML files in /public/.
 * Called by vercel.json catch-all for non-API, non-asset requests.
 */

$map = [
    '/'                      => 'index.html',
    '/login'                 => 'login-dashboard.html',
    '/contact'               => 'contact.html',
    '/privacy'               => 'privacy.html',
    '/phcwddata'             => 'phcwddata.html',
    '/profiling'             => 'profiling.html',
    '/success'               => 'success.html',
    '/profile'               => 'profile.html',
    '/docs'                  => 'docs.html',
    '/user-manual'           => 'user-manual.html',
    '/dashboard'             => 'dashboard.html',
    '/dashboard-admin'       => 'dashboard-admin.html',
    '/dashboard-central'     => 'dashboard-central.html',
    '/dashboard-stu-head'    => 'dashboard-stu-head.html',
    '/dashboard-field-officer' => 'dashboard-field-officer.html',
];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip trailing slash (except root)
if ($path !== '/' && substr($path, -1) === '/') {
    $path = rtrim($path, '/');
}

// Redirect .html URLs to clean URL
if (preg_match('/^(.+)\.html$/', $path, $m)) {
    $clean = $m[1];
    // login-dashboard.html -> /login
    if ($clean === '/login-dashboard') $clean = '/login';
    $location = $clean;
    header('Location: ' . $location, true, 301);
    exit;
}

if (isset($map[$path])) {
    $file = __DIR__ . '/../public/' . $map[$path];
    if (file_exists($file)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($file);
        exit;
    }
}

http_response_code(404);
echo '404 Not Found';
