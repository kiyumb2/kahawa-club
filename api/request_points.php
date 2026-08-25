<?php
header('Content-Type: application/json');
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

$session = isset($_COOKIE['user_session']) ? decryptCookie($_COOKIE['user_session']) : null;
if (!$session || empty($session['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $session['user_id'];

try {
    // Check if there's already an unhandled request from this user
    $checkStmt = $pdo->prepare("SELECT id FROM point_requests WHERE user_id = ? AND status = 'pending'");
    $checkStmt->execute([$userId]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You already have a pending visit point request.']);
        exit;
    }

    // Insert new request
    $stmt = $pdo->prepare("INSERT INTO point_requests (user_id, points_requested, status, created_at) VALUES (?, 10, 'pending', NOW())");
    $stmt->execute([$userId]);

    echo json_encode(['success' => true, 'message' => 'Visit points requested! Please ask the cashier to approve.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
