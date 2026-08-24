<?php
header('Content-Type: application/json');

define('ENCRYPTION_KEY', 'kahawa_secret_key_change_this_123456!');

/**
 * Decrypt cookie data to restore stateless sessions on Vercel
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

// Return Unauthorized if session cookie is missing or invalid
if (!$sessionData || empty($sessionData['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $sessionData['user_id'];

// Read JSON input sent from dashboard.php fetch()
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$itemName = $data['item_name'] ?? 'Coffee Order';
$price = $data['price'] ?? 0;

// Central Supabase PostgreSQL PDO Connection
require_once __DIR__ . '/db.php';

try {
    // 1. Award 10 loyalty points for ordering
    $pointStmt = $pdo->prepare("UPDATE users SET points = points + 10 WHERE id = ?");
    $pointStmt->execute([$userId]);

    // 2. Fetch updated point balance
    $fetchStmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $fetchStmt->execute([$userId]);
    $user = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    // 3. Log the purchase in reward_history (if table exists)
    try {
        $logStmt = $pdo->prepare("INSERT INTO reward_history (user_id, description, points_change, created_at) VALUES (?, ?, ?, NOW())");
        $logStmt->execute([$userId, "Purchased " . $itemName, 10]);
    } catch (Exception $e) {
        // Continue even if reward_history logging fails
    }

    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully!',
        'item' => $itemName,
        'points_earned' => 10,
        'total_points' => $user['points'] ?? 0
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
