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

$phoneNumber = trim($_POST['phone_number'] ?? '');
$password    = trim($_POST['password'] ?? '');

if (empty($phoneNumber) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please enter phone number and password']);
    exit;
}

try {
    // Authenticate user with matching phone_number and is_admin = TRUE
    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone_number = ? AND is_admin = TRUE LIMIT 1");
    $stmt->execute([$phoneNumber]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $cookiePayload = [
            'admin_id' => $admin['id'],
            'phone'    => $admin['phone_number'],
            'role'     => 'admin',
            'time'     => time()
        ];
        
        setcookie('admin_session', encryptCookie($cookiePayload), [
            'expires'  => time() + (8 * 3600),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials or account is not an admin']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
