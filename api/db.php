<?php
// Central DB connection for Supabase via Connection Pooler (IPv4 Compatible)
$db_url = getenv('DATABASE_URL');

if ($db_url) {
    $dbopts = parse_url($db_url);
    $host = $dbopts['host'];
    $port = $dbopts['port'] ?? 6543;
    $user = $dbopts['user'];
    $pass = $dbopts['pass'];
    $db   = ltrim($dbopts['path'], '/');
} else {
    // Fallback using Supabase Transaction Pooler Host and Port 6543
    $host = getenv('SUPABASE_DB_HOST') ?: 'aws-0-eu-west-1.pooler.supabase.com';
    $port = getenv('SUPABASE_DB_PORT') ?: '6543';
    $db   = getenv('SUPABASE_DB_NAME') ?: 'postgres';
    
    // User format for pooler: postgres.vxpqsbjjegvkjusiktxd
    $user = getenv('SUPABASE_DB_USER') ?: 'postgres.vxpqsbjjegvkjusiktxd';
    $pass = getenv('SUPABASE_DB_PASS') ?: '0926440279@Kiyu';
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
?>
