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

try {
    // 3. Fetch current points from Supabase database
    $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();

    $current_points = $current_user['points'] ?? 0;

    // 4. Validate point threshold (minimum 100 points required)
    if ($current_points < 100) {
        echo json_encode([
            'success' => false, 
            'message' => 'You need at least 100 points to claim a free coffee.'
        ]);
        exit;
    }

    // Begin database transaction
    $pdo->beginTransaction();

    // 5. Subtract 100 points from user balance
    $update = $pdo->prepare("UPDATE users SET points = points - 100 WHERE id = ?");
    $update->execute([$_SESSION['user_id']]);

    // 6. Log redemption in reward_history for dashboard activity tracking
    $historyStmt = $pdo->prepare("INSERT INTO reward_history (user_id, description, points_change) VALUES (?, ?, ?)");
    $historyStmt->execute([
        $_SESSION['user_id'], 
        'Claimed Free Coffee Reward ☕', 
        -100
    ]);

    // Commit transaction
    $pdo->commit();

    // 7. Send success response back to dashboard
    echo json_encode([
        'success' => true,
        'message' => 'Success! Your free coffee has been claimed.'
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