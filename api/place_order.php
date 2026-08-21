<?php
session_start();
header('Content-Type: application/json');

// 1. Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// 2. Include central database connection (Supabase PostgreSQL)
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize inputs
    $item_name = trim($_POST['item_name'] ?? '');
    $price     = floatval($_POST['price'] ?? 0);

    if (empty($item_name) || $price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order item details.']);
        exit;
    }

    try {
        // Insert order record into Supabase PostgreSQL
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, item_name, price) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $item_name, $price]);

        echo json_encode([
            'success' => true, 
            'message' => 'Order placed successfully!'
        ]);
        exit;

    } catch (PDOException $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}
?>