<?php
require_once __DIR__ . '/db.php';

define('ENCRYPTION_KEY', 'kahawa_secret_key_change_this_123456!');

function decryptCookie($cookie) {
    try {
        $decoded = base64_decode($cookie);
        $parts = explode('::', $decoded);
        if (count($parts) !== 2) return null;
        list($encrypted_data, $iv) = $parts;
        $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
        return json_decode($decrypted, true);
    } catch (Exception $e) {
        return null;
    }
}

header('Content-Type: application/json');

// 1. Verify Authentication
$session = isset($_COOKIE['user_session']) ? decryptCookie($_COOKIE['user_session']) : null;

if (!$session || empty($session['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please sign in again.']);
    exit;
}

$user_id = $session['user_id'];

// 2. Read input payload (JSON or Form POST)
$input = json_decode(file_get_contents('php://input'), true);
$item_name = trim($input['item_name'] ?? $_POST['item_name'] ?? '');
$price     = floatval($input['price'] ?? $_POST['price'] ?? 0);

if (empty($item_name) || $price <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order details provided.']);
    exit;
}

try {
    // 3. Insert order record
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, item_name, price, order_date) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, $item_name, $price]);

    // 4. Award order loyalty points (+5 points per order)
    $rewardStmt = $pdo->prepare("UPDATE users SET points = points + 5 WHERE id = ?");
    $rewardStmt->execute([$user_id]);

    $histStmt = $pdo->prepare("INSERT INTO reward_history (user_id, action_type, points_change, description) VALUES (?, 'order_point', 5, ?)");
    $histStmt->execute([$user_id, 'Points earned from order: ' . $item_name]);

    echo json_encode(['success' => true, 'message' => 'Order placed successfully!']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
