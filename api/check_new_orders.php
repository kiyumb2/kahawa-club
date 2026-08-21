<?php
header('Content-Type: application/json');

// Include central database connection (Supabase PostgreSQL)
require_once 'db.php';

try {
    // Count total orders from Supabase database
    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM orders");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $count = isset($result['count']) ? intval($result['count']) : 0;

    echo json_encode(['orderCount' => $count]);
    exit;

} catch (PDOException $e) {
    // Return 0 if database error occurs to prevent polling failure on admin side
    echo json_encode(['orderCount' => 0]);
    exit;
}
?>