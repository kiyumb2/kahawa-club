<?php
// Catch-all router to serve PHP requests through 1 single Serverless Function
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$parsedUrl = parse_url($requestUri, PHP_URL_PATH);

// Map endpoint routes to their corresponding PHP scripts
$routes = [
    '/admin/dashboard'   => __DIR__ . '/admin_dashboard.php',
    '/admin/logout'      => __DIR__ . '/admin_logout.php',
    '/api/admin_login'   => __DIR__ . '/admin_login.php',
];

// Check direct API path matching
if (isset($routes[$parsedUrl]) && file_exists($routes[$parsedUrl])) {
    require $routes[$parsedUrl];
    exit;
}

// Fallback dynamic loading for /api/<filename>
$cleanPath = preg_replace('#^/api/#', '', $parsedUrl);
$targetFile = __DIR__ . '/' . basename($cleanPath) . '.php';

if (file_exists($targetFile)) {
    require $targetFile;
    exit;
}

http_response_code(404);
echo json_encode(["error" => "Endpoint not found"]);
