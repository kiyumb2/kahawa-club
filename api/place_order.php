<?php
// Clear buffer to guarantee clean JSON output
if (ob_get_length()) ob_clean();

header('Content-Type: application/json');

// Retrieve secret key securely from server environment or fallback for development
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: 'kahawa_secret_key_change_this_123456!');

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

// Return 401 Unauthorized if missing user session
if (!$sessionData || empty($sessionData['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $sessionData['user_id'];

// Support both JSON body payloads and standard form POST requests
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?? $_POST;

$item_name = trim($data['item_name'] ?? '');
$quantity  = intval($data['quantity'] ?? 1);
$price     = floatval($data['price'] ?? 0);

if (empty($item_name) || $quantity < 1 || $price <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order item details or quantity.']);
    exit;
}

// Include Supabase PostgreSQL PDO connection
require_once __DIR__ . '/db.php';

try {
    // Append quantity identifier to item name if quantity > 1
    $formatted_item = $quantity > 1 ? "{$item_name} (x{$quantity})" : $item_name;

    // Insert order as 'pending' so it requires admin approval before revenue/points are applied
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, item_name, price, status, order_date) VALUES (?, ?, ?, 'pending', NOW())");
    $stmt->execute([$userId, $formatted_item, $price]);

    echo json_encode([
        'success' => true, 
        'message' => 'Order request submitted! Awaiting cashier/admin approval.'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to process order. Please try again later.'
    ]);
}
?>
