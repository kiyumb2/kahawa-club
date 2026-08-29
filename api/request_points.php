<?php
// Clear buffer to guarantee clean JSON output
if (ob_get_length()) ob_clean();

header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

// Retrieve secret key securely from server environment or fallback for development
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: 'kahawa_secret_key_change_this_123456!');

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
    // 1. Check if there is already an active pending request
    $pendingStmt = $pdo->prepare("SELECT id FROM point_requests WHERE user_id = ? AND status = 'pending'");
    $pendingStmt->execute([$userId]);
    if ($pendingStmt->fetch()) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'You already have a visit point request awaiting cashier approval.'
        ]);
        exit;
    }

    // 2. Check time passed since the last requested visit points (8-hour restriction = 28,800 seconds)
    $lastReqStmt = $pdo->prepare("
        SELECT created_at, 
               EXTRACT(EPOCH FROM (NOW() - created_at)) AS seconds_passed 
        FROM point_requests 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $lastReqStmt->execute([$userId]);
    $lastRequest = $lastReqStmt->fetch(PDO::FETCH_ASSOC);

    if ($lastRequest && $lastRequest['seconds_passed'] < 28800) {
        $remainingSeconds = 28800 - (int)$lastRequest['seconds_passed'];
        $hoursLeft = floor($remainingSeconds / 3600);
        $minutesLeft = floor(($remainingSeconds % 3600) / 60);

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "You can only claim visit points once every 8 hours. Please wait {$hoursLeft}h {$minutesLeft}m.",
            'cooldown_seconds' => $remainingSeconds
        ]);
        exit;
    }

    // 3. Insert new request if 8 hours have passed
    $stmt = $pdo->prepare("INSERT INTO point_requests (user_id, points_requested, status, created_at) VALUES (?, 10, 'pending', NOW())");
    $stmt->execute([$userId]);

    echo json_encode([
        'success' => true, 
        'message' => 'Visit points requested! Please ask the cashier to approve.'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error. Please try again later.'
    ]);
}
?>
