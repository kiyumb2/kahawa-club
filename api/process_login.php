<?php
session_start();

// Include central database connection (Supabase PostgreSQL)
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Sanitize and check inputs
        $phone_number = trim($_POST['phone_number'] ?? '');
        $password     = $_POST['password'] ?? '';

        if (empty($phone_number) || empty($password)) {
            showLoginError("Please enter both your phone number and password.");
        }

        // Search for user in Supabase database by phone number
        $stmt = $pdo->prepare("SELECT * FROM users WHERE phone_number = ?");
        $stmt->execute([$phone_number]);
        $user = $stmt->fetch();

        // Verify password match against the hashed password in the database
        if ($user && password_verify($password, $user['password'])) {
            // Save session variables
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];

            // Redirect to dashboard page
            header("Location: dashboard.php");
            exit;
        } else {
            showLoginError("Incorrect phone number or password. Please try again.");
        }

    } catch (PDOException $e) {
        showLoginError("Database Connection Error: " . $e->getMessage());
    }
} else {
    header("Location: index.html");
    exit;
}

/**
 * Helper function to output the styled error modal
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
            <a href="index.html" class="btn">Try Again</a>
        </div>
    </body>
    </html>';
    exit;
}
?>