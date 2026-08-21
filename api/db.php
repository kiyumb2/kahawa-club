<?php
// Central DB connection for Supabase Pooler (IPv4 Compatible)
$host = 'eu-west-1.pooler.supabase.com';
$port = '6543';
$db   = 'postgres';
$user = 'postgres.vxpqsbjjegvkjusiktxd';
$pass = '0926440279@Kiyu';

try {
    // We add the project option parameter to ensure Supavisor routes to your tenant correctly
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require;options='--project=vxpqsbjjegvkjusiktxd'";
    
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
