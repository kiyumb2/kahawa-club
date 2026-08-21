<?php
// Central DB connection for Supabase Pooler (IPv4 Compatible)
$host = 'aws-1-eu-west-1.pooler.supabase.com';
$port = '6543';
$db   = 'postgres';
$user = 'postgres.kqulwoqhbjwgskygmrhb';
$pass = '0926440279@Kiyu';

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
