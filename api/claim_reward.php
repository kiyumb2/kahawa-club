<?php
header('Content-Type: application/json');

// 1. Central database connection
require_once __DIR__ . '/db.php';

// Encryption key matching your session system
define('ENCRYPTION_KEY', 'kahawa_secret_key_change_this_123456!');

/**
 * Decrypt cookie session payload
 */
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

// 2. Resolve User ID from Cookie Session or PHP Session
$userId = null;

if (isset($_COOKIE['user_session'])) {
    $session = decryptCookie($_COOKIE['user_session']);
    if (!empty($session['user_id'])) {
        $userId = $session['user_id'];
    }
}

// Fallback to PHP native session if cookies aren't used
if (!$userId) {
    session_start();
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
}

// Check authorization
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized user session.']);
    exit;
}

try {
    // 3. Fetch current user points
    $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $current_user = $stmt->fetch();

    if (!$current_user) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    $current_points = (int)($current_user['points'] ?? 0);

    // 4. Validate point threshold
    if ($current_points < 100) {
        echo json_encode([
            'success' => false, 
            'message' => 'You need at least 100 points to claim a free coffee.'
        ]);
        exit;
    }

    // Begin database transaction
    $pdo->beginTransaction();

    // 5. Subtract 100 points
    $update = $pdo->prepare("UPDATE users SET points = points - 100 WHERE id = ?");
    $update->execute([$userId]);

    // 6. Log in reward_history safely
    try {
        $historyStmt = $pdo->prepare("INSERT INTO reward_history (user_id, description, points_change) VALUES (?, ?, ?)");
        $historyStmt->execute([
            $userId, 
            'Claimed Free Coffee Reward ☕', 
            -100
        ]);
    } catch (Exception $histErr) {
        // Silently skip if reward_history schema varies so it doesn't break point deduction
    }

    // Commit transaction
    $pdo->commit();

    // 7. Send success response
    echo json_encode([
        'success' => true,
        'message' => 'Success! Your free coffee has been claimed.',
        'new_points' => $current_points - 100
    ]);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
}
?>
