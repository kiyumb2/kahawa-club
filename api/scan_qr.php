<?php
session_start();

// Redirect to login page if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

// Include central database connection (Supabase PostgreSQL)
require_once 'db.php';

// Handle POST request sent by JS scanner on successful QR code scan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $scanned_data = trim($_POST['scanned_data'] ?? '');

    try {
        // Begin database transaction
        $pdo->beginTransaction();

        // 1. Add 10 points to user balance
        $stmt = $pdo->prepare("UPDATE users SET points = points + 10 WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);

        // 2. Log activity into reward_history table
        $historyStmt = $pdo->prepare("INSERT INTO reward_history (user_id, description, points_change) VALUES (?, ?, ?)");
        $historyStmt->execute([
            $_SESSION['user_id'],
            'Store QR Check-in Scan ☕',
            10
        ]);

        // Commit transaction
        $pdo->commit();

        echo json_encode([
            'success' => true, 
            'message' => 'Check-in successful! +10 points added to your account! ☕'
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kahawa Coffee Club - Scan QR</title>
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
  body { background: #1e1e1e; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
  .app-container { 
    width: 100%; max-width: 414px; height: 100vh; max-height: 896px; 
    background: #F9F6F0; display: flex; flex-direction: column; justify-content: space-between; 
    padding: 20px; position: relative; box-shadow: 0 20px 40px rgba(0,0,0,0.5); overflow: hidden; 
  }
  @media (min-width: 450px) {
    .app-container { border-radius: 40px; border: 10px solid #2c2c2c; height: 85vh; }
  }
  .header { text-align: center; padding-top: 10px; }
  .header h2 { font-size: 18px; font-weight: 900; color: #111; }
  .header p { font-size: 12px; color: #666; margin-top: 4px; }
  
  #reader { width: 100%; border-radius: 16px; overflow: hidden; border: 2px solid #c49a6c !important; background: #000; }
  
  .back-btn { 
    display: block; width: 100%; padding: 12px; background: #111; color: white; 
    text-align: center; text-decoration: none; border-radius: 12px; font-weight: bold; font-size: 13px; 
  }
</style>
</head>
<body>
<div class="app-container">
  <div class="header">
    <h2>Scan Shop QR Code</h2>
    <p>Point your camera at the table or counter code to get +10 points</p>
  </div>

  <div style="width: 100%; max-width: 300px; margin: 0 auto;">
    <div id="reader"></div>
  </div>

  <a href="dashboard.php" class="back-btn">&larr; Back to Dashboard</a>
</div>

<script>
  function onScanSuccess(decodedText, decodedResult) {
    // Stop scanner once read
    html5QrcodeScanner.clear().then(_ => {
      // Send request to server to credit points
      fetch('scan_qr.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'scanned_data=' + encodeURIComponent(decodedText)
      })
      .then(res => res.json())
      .then(data => {
          alert(data.message);
          window.location.href = 'dashboard.php';
      })
      .catch(err => {
          console.error("Error submitting scan:", err);
          alert("An error occurred while processing your check-in.");
          window.location.href = 'dashboard.php';
      });
    }).catch(error => {
      console.error("Failed to clear scanner. ", error);
    });
  }

  let html5QrcodeScanner = new Html5QrcodeScanner(
    "reader", { fps: 10, qrbox: { width: 220, height: 220 } }, false);
  html5QrcodeScanner.render(onScanSuccess);
</script>
</body>
</html>