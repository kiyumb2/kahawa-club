<?php
// Include database connection
require_once __DIR__ . '/db.php';

// Define secret key for cookie encryption
define('ENCRYPTION_KEY', 'kahawa_secret_key_change_this_123456!');

/**
 * Encrypt cookie session payload
 */
function encryptCookie($data) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt(json_encode($data), 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

/**
 * Helper to display error UI and stop script execution
 */
function showLoginError($message) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login Failed - Kahawa Club</title>
        <style>
            body { font-family: Arial, sans-serif; background: #111; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
            .popup-card { background: #fff; padding: 40px 30px; border-radius: 24px; text-align: center; box-shadow: 0 15px 35px rgba(0,0,0,0.4); max-width: 400px; width: 90%; }
            .icon { font-size: 32px; color: #d9534f; margin-bottom: 15px; }
            h2 { color: #111; font-size: 22px; margin-bottom: 8px; }
            p { color: #666; font-size: 14px; margin-bottom: 20px; line-height: 1.5; }
            .btn { display: inline-block; padding: 12px 24px; background: #6F4E37; color: white; text-decoration: none; border-radius: 12px; font-weight: bold; font-size: 14px; }
            .btn:hover { background: #5a3d2c; }
        </style>
    </head>
    <body>
        <div class="popup-card">
            <div class="icon">&#9888;</div>
            <h2>Login Failed</h2>
            <p>' . htmlspecialchars($message) . '</p>
            <a href="/index.html" class="btn">Try Again</a>
        </div>
    </body>
    </html>';
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $login_input = trim($_POST['phone_number'] ?? '');
        $password    = $_POST['password'] ?? '';

        if (empty($login_input) || empty($password)) {
            showLoginError("Please enter both your login credential and password.");
        }

        // Search by phone_number OR username if the column exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE phone_number = ? OR username = ?");
        $stmt->execute([$login_input, $login_input]);
        $user = $stmt->fetch();

        if ($user) {
            $is_valid = false;

            // Check using password_verify
            if (password_verify($password, $user['password'])) {
                $is_valid = true;
            } 
            // Fallback: Check plain-text password if created manually in Supabase
            elseif ($password === $user['password']) {
                $is_valid = true;
                // Auto-hash password in DB for future logins
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->execute([$newHash, $user['id']]);
            }

            if ($is_valid) {
                $sessionData = [
                    'user_id'    => $user['id'],
                    'first_name' => $user['first_name'] ?? 'User',
                    'last_name'  => $user['last_name'] ?? '',
                    'role'       => $user['role'] ?? 'customer',
                    'time'       => time()
                ];

                $encryptedCookie = encryptCookie($sessionData);

                // Set encrypted authentication cookie for 7 days
                setcookie('user_session', $encryptedCookie, [
                    'expires'  => time() + (86400 * 7),
                    'path'     => '/',
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);

                // Redirect cleanly to dashboard route
                header("Location: /api/dashboard");
                exit;
            }
        }

        showLoginError("Incorrect phone number/username or password. Please try again.");

    } catch (PDOException $e) {
        showLoginError("Database Connection Error: " . $e->getMessage());
    }
} else {
    header("Location: /index.html");
    exit;
}
?>
