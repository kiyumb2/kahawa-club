<?php
// Central DB connection for Supabase (PostgreSQL)

// Option 1: Parse full connection string if DATABASE_URL is set in Vercel
$db_url = getenv('DATABASE_URL');

if ($db_url) {
    $dbopts = parse_url($db_url);
    $host = $dbopts['host'];
    $port = $dbopts['port'] ?? 5432;
    $user = $dbopts['user'];
    $pass = $dbopts['pass'];
    $db   = ltrim($dbopts['path'], '/');
} else {
    // Option 2: Fallback to individual environment variables or direct values
   $host = getenv('SUPABASE_DB_HOST') ?: 'db.vxpqsbjjegvkjusiktxd.supabase.co'; // Replace YOUR_SUPABASE_REF with your real reference ID
    $port = getenv('SUPABASE_DB_PORT') ?: '5432';
    $db   = getenv('SUPABASE_DB_NAME') ?: 'postgres';
    $user = getenv('SUPABASE_DB_USER') ?: 'postgres';
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
