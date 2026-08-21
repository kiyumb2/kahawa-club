<?php
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // 1. Capture and sanitize inputs
        $first_name   = trim($_POST['first_name'] ?? '');
        $last_name    = trim($_POST['last_name'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $birthday     = !empty($_POST['birthday']) ? $_POST['birthday'] : null;
        $password     = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);

        if (empty($first_name) || empty($last_name) || empty($phone_number)) {
            throw new Exception("Please fill in all required fields.");
        }

        // 2. Generate a unique member code (e.g., KH-A1B2C3)
        $member_code = 'KH-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

        // 3. Insert user into Supabase PostgreSQL
        $sql = "INSERT INTO users (first_name, last_name, phone_number, birthday, password, points, member_code) 
                VALUES (?, ?, ?, ?, ?, 50, ?) RETURNING id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$first_name, $last_name, $phone_number, $birthday, $password, $member_code]);
        
        $user_id = $stmt->fetchColumn();

        // 4. Log the initial 50 points into reward history
        if ($user_id) {
            $hist_sql = "INSERT INTO reward_history (user_id, action_type, points_change, description) 
                         VALUES (?, 'signup_bonus', 50, 'Welcome Bonus Points')";
            $hist_stmt = $pdo->prepare($hist_sql);
            $hist_stmt->execute([$user_id]);
        }

        // 5. Success UI Response
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Welcome to Kahawa Club</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: Arial, sans-serif; background: #111; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
                .popup-card { background: #fff; padding: 40px 30px; border-radius: 24px; text-align: center; box-shadow: 0 15px 35px rgba(0,0,0,0.4); max-width: 400px; width: 90%; }
                .icon-container { width: 70px; height: 70px; background: #e8f5e9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; }
                .icon { font-size: 32px; color: #2e7d32; }
                h2 { color: #111; font-size: 22px; margin-bottom: 8px; }
                p { color: #666; font-size: 14px; margin-bottom: 25px; line-height: 1.5; }
                .spinner { border: 3px solid #f3f3f3; border-top: 3px solid #6F4E37; border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; margin: 0 auto; }
                @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            </style>
        </head>
        <body>
            <div class="popup-card">
                <div class="icon-container">
                    <div class="icon">&#10004;</div>
                </div>
                <h2>You are in, <strong>' . htmlspecialchars($first_name) . '</strong>!</h2>
                <p>Welcome to Kahawa Club. Your account has been created with 50 bonus points. Redirecting to sign in...</p>
                <div class="spinner"></div>
            </div>
            <script>
                setTimeout(function() {
                    window.location.href = "/index.html";
                }, 2500);
            </script>
        </body>
        </html>';
        exit;

    } catch (PDOException $e) {
        $errorMessage = "An unexpected error occurred during registration.";
        
        // Handle unique constraint failure (e.g. phone number already registered)
        if ($e->getCode() == '23505') {
            $errorMessage = "That phone number is already registered. Please sign in instead.";
        }

        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Registration Error</title>
            <style>
                body { font-family: Arial, sans-serif; background: #111; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
                .popup-card { background: #fff; padding: 40px 30px; border-radius: 24px; text-align: center; max-width: 400px; width: 90%; }
                .icon { font-size: 40px; color: #d9534f; margin-bottom: 15px; }
                h2 { color: #111; margin-bottom: 10px; }
                p { color: #666; margin-bottom: 20px; font-size: 14px; }
                .btn { display: inline-block; padding: 12px 24px; background: #6F4E37; color: white; text-decoration: none; border-radius: 12px; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="popup-card">
                <div class="icon">&#9888;</div>
                <h2>Registration Failed</h2>
                <p>' . htmlspecialchars($errorMessage) . '</p>
                <a href="/signup.html" class="btn">Try Again</a>
            </div>
        </body>
        </html>';
    } catch (Exception $e) {
        echo '<p style="color:red; text-align:center; padding-top: 50px;">' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}
?>
