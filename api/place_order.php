<?php
// Set content type to JSON first so error messages render properly
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

define('ENCRYPTION_KEY', 'kahawa_secret_key_change_this_123456!');

/**
 * Decrypt session cookie
 */
function decryptCookie($cookie) {
    try {
        $decoded = base64_decode($cookie);
        $parts = explode('::', $decoded, 2);
        if (count($parts) !== 2) return null;
        list($encrypted_data, $iv) = $parts;
        $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
        return json_decode($decrypted, true);
    } catch (Exception $e) {
        return null;
    }
}

// 1. Verify User Session
$session = isset($_COOKIE['user_session']) ? decryptCookie($_COOKIE['user_session']) : null;

if (!$session || empty($session['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}

$user_id = (int)$session['user_id'];

// 2. Read Request Payload
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

$item_name = trim($input['item_name'] ?? $_POST['item_name'] ?? '');
$price     = floatval($input['price'] ?? $_POST['price'] ?? 0);

if (empty($item_name) || $price <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid item name or price provided.']);
    exit;
}

try {
    // 3. Insert into `orders` matching your exact Supabase columns
    $orderStmt = $pdo->prepare("
        INSERT INTO public.orders (user_id, item_name, price, order_date) 
        VALUES (:user_id, :item_name, :price, NOW())
    ");
    
    $orderStmt->execute([
        ':user_id'   => $user_id,
        ':item_name' => $item_name,
        ':price'     => $price
    ]);

    // 4. Update Loyalty Points (+5 per purchase)
    try {
        $pointsStmt = $pdo->prepare("UPDATE public.users SET points = COALESCE(points, 0) + 5 WHERE id = ?");
        $pointsStmt->execute([$user_id]);
    } catch (Exception $e) {
        // Ignore if user points column is missing
    }

    // 5. Log Action in reward_history
    try {
        $rewardStmt = $pdo->prepare("
            INSERT INTO public.reward_history (user_id, action_type, points_change, description, created_at) 
            VALUES (?, 'order_point', 5, ?, NOW())
        ");
        $rewardStmt->execute([$user_id, 'Points earned from order: ' . $item_name]);
    } catch (Exception $e) {
        // Ignore if reward_history table does not exist
    }

    echo json_encode(['success' => true, 'message' => 'Order placed successfully!']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
