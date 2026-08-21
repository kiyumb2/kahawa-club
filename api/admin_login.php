<?php
define('ENCRYPTION_KEY', 'kahawa_secret_key_change_this_123456!');

function decryptCookie($cookieValue) {
    $parts = explode('::', base64_decode($cookieValue), 2);
    if (count($parts) !== 2) return null;
    list($encrypted_data, $iv) = $parts;
    $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
    return json_decode($decrypted, true);
}

$adminSession = isset($_COOKIE['admin_session']) ? decryptCookie($_COOKIE['admin_session']) : null;

if (!$adminSession || empty($adminSession['admin_id']) || ($adminSession['role'] ?? '') !== 'admin') {
    header("Location: /admin/index.html");
    exit;
}

require_once __DIR__ . '/../api/db.php';

try {
    // Stats Overview
    $totalMembers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalPoints  = $pdo->query("SELECT SUM(points) FROM users")->fetchColumn() ?: 0;
    
    // Fetch all members
    $membersStmt = $pdo->query("SELECT id, first_name, last_name, phone_number, member_code, points, created_at FROM users ORDER BY created_at DESC");
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kahawa Coffee - Admin Dashboard</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
  body { background: #141414; color: #fff; padding: 24px; }
  .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #222; padding-bottom: 16px; margin-bottom: 24px; }
  .brand { font-weight: 900; font-size: 20px; color: #c49a6c; letter-spacing: 1px; }
  .logout-btn { background: #b7094c; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-size: 12px; font-weight: bold; cursor: pointer; text-decoration: none; }
  .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 28px; }
  .stat-card { background: #1f1f1f; border: 1px solid #333; padding: 20px; border-radius: 12px; }
  .stat-card h3 { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
  .stat-card div { font-size: 28px; font-weight: 900; color: #fff; }
  .table-container { background: #1f1f1f; border: 1px solid #333; border-radius: 12px; padding: 20px; }
  .table-title { font-size: 16px; margin-bottom: 16px; color: #c49a6c; font-weight: 700; }
  table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
  th, td { padding: 12px; border-bottom: 1px solid #2d2d2d; }
  th { color: #888; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
  .badge-code { background: #2a2a2a; border: 1px solid #444; color: #c49a6c; padding: 4px 8px; border-radius: 6px; font-family: monospace; font-size: 12px; }
</style>
</head>
<body>

<div class="header">
  <div class="brand">☕ KAHAWA ADMIN CONTROL</div>
  <a href="/admin/logout.php" class="logout-btn">Logout</a>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <h3>Total Members</h3>
    <div><?php echo htmlspecialchars($totalMembers); ?></div>
  </div>
  <div class="stat-card">
    <h3>Total Points Issued</h3>
    <div><?php echo htmlspecialchars($totalPoints); ?> pts</div>
  </div>
</div>

<div class="table-container">
  <div class="table-title">Registered Members Directory</div>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Member Name</th>
        <th>Phone Number</th>
        <th>Member Code</th>
        <th>Points</th>
        <th>Joined Date</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($members as $user): ?>
        <tr>
          <td><?php echo $user['id']; ?></td>
          <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
          <td><?php echo htmlspecialchars($user['phone_number']); ?></td>
          <td><span class="badge-code"><?php echo htmlspecialchars($user['member_code']); ?></span></td>
          <td style="color: #2d6a4f; font-weight: bold;"><?php echo $user['points']; ?> pts</td>
          <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

</body>
</html>
