<?php
// Central DB connection for Supabase (PostgreSQL)
$host = getenv('SUPABASE_DB_HOST') ?: 'db.YOUR_SUPABASE_REF.supabase.co';
$port = getenv('SUPABASE_DB_PORT') ?: '5432';
$db   = getenv('SUPABASE_DB_NAME') ?: 'postgres';
$user = getenv('SUPABASE_DB_USER') ?: 'postgres';
$pass = getenv('SUPABASE_DB_PASS') ?: '0926440279@Kiyu';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '.php') !== false && !headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
?>