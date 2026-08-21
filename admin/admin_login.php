<?php
header('Content-Type: application/json');

define('ENCRYPTION_KEY', 'kahawa_secret_key_change_this_123456!');

function encryptCookie($data) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt(json_encode($data), 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please enter username and password']);
    exit;
}

try {
    // Check if user exists and has admin privileges (is_admin = true or role = 'admin')
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND (is_admin = TRUE OR role = 'admin') LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $cookiePayload = [
            'admin_id' => $admin['id'],
            'email' => $admin['email'],
            'role' => 'admin',
            'time' => time()
        ];
        
        $encryptedCookie = encryptCookie($cookiePayload);
        
        // Set encrypted cookie valid for 8 hours
        setcookie('admin_session', $encryptedCookie, [
            'expires' => time() + (8 * 3600),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid admin credentials']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
