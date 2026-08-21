<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$item = $_POST['item'] ?? '';
$price = $_POST['price'] ?? '';

$host = 'localhost';
$db   = 'kahawa_club';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Force strict update and get affected rows
    $pointStmt = $pdo->prepare("UPDATE users SET points = points + 10 WHERE id = ?");
    $pointStmt->execute([$userId]);
    $affectedRows = $pointStmt->rowCount();

    // Fetch back what the database actually has right now for this user
    Query:
    $fetchStmt = $pdo->prepare("SELECT id, points FROM users WHERE id = ?");
    $fetchStmt->execute([$userId]);
    $userData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true, 
        'session_user_id' => $userId,
        'rows_updated' => $affectedRows,
        'db_points_now' => $userData['points'] ?? 'User not found'
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>