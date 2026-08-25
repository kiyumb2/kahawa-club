<?php
header('Content-Type: application/json');

define('ENCRYPTION_KEY', 'kahawa_secret_key_change_this_123456!');

/**
 * Decrypt cookie data to verify admin/staff session
 */
function decryptCookie($cookieValue) {
    $parts = explode('::', base64_decode($cookieValue), 2);
    if (count($parts) !== 2) return null;
    list($encrypted_data, $iv) = $parts;
    $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
    return json_decode($decrypted, true);
}

// Read encrypted cookie session
$sessionData = isset($_COOKIE['user_session']) ? decryptCookie($_COOKIE['user_session']) : null;

// Ensure session exists (you can also add role checks here if you store role in session)
if (!$sessionData || empty($sessionData['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Read JSON input or POST data
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?? $_POST;

$order_id = intval($data['order_id'] ?? 0);

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID.']);
    exit;
}

// Include Supabase PostgreSQL PDO connection
require_once __DIR__ . '/db.php';

try {
    // Begin transaction to ensure data integrity
    $pdo->beginTransaction();

    // 1. Fetch the pending order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND status = 'pending' FOR UPDATE");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Pending order not found or already processed.']);
        exit;
    }

    $userId = $order['user_id'];
    $orderPrice = floatval($order['price']);
    
    // Calculate loyalty points earned (e.g., 10 points per order, adjust formula as needed)
    $earnedPoints = 10; 

    // 2. Update order status to 'approved'
    $updateOrder = $pdo->prepare("UPDATE orders SET status = 'approved' WHERE id = ?");
    $updateOrder->execute([$order_id]);

    // 3. Add loyalty points to the user
    $updateUser = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
    $updateUser->execute([$earnedPoints, $userId]);

    // 4. Record transaction in reward_history
    $historyDesc = "Earned " . $earnedPoints . " points for purchase: " . $order['item_name'];
    $insertHistory = $pdo->prepare("INSERT INTO reward_history (user_id, action_type, points_change, description, created_at) VALUES (?, 'order_approval', ?, ?, NOW())");
    $insertHistory->execute([$userId, $earnedPoints, $historyDesc]);

    // Commit all changes
    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Order approved successfully! Revenue updated and ' . $earnedPoints . ' points added to member account.'
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
