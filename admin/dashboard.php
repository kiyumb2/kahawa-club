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

// Redirect if not logged in as admin
if (!$adminSession || empty($adminSession['admin_id']) || ($adminSession['role'] ?? '') !== 'admin') {
    header("Location: /admin/index.html");
    exit;
}

require_once __DIR__ . '/../api/db.php';

try {
    // Fetch stats
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalOrders = $pdo->query("SELECT COUNT(*) FROM reward_history")->fetchColumn();
    
    // Fetch recent transaction logs
    $stmt = $pdo->prepare("
        SELECT r.*, u.first_name, u.last_name, u.email 
        FROM reward_history r 
        LEFT JOIN users u ON r.user_id = u.id 
        ORDER BY r.created_at DESC LIMIT 15
    ");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
  body { background: #141414; color: #fff; padding: 20px; }
  .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #222; padding-bottom: 15px; margin-bottom: 20px; }
  .brand { font-weight: 900; font-size: 18px; color: #c49a6c; }
  .logout-btn { background: #b7094c; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: bold; cursor: pointer; text-decoration: none; }
  .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
  .stat-card { background: #1f1f1f; border: 1px solid #333; padding: 20px; border-radius: 12px; }
  .stat-card h3 { font-size: 12px; color: #888; text-transform: uppercase; margin-bottom: 5px; }
  .stat-card div { font-size: 24px; font-weight: 900; color: #fff; }
  .table-container { background: #1f1f1f; border: 1px solid #333; border-radius: 12px; padding: 15px; overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
  th, td { padding: 12px; border-bottom: 1px solid #2d2d2d; }
  th { color: #888; text-transform: uppercase; font-size: 11px; }
  .pts-plus { color: #2d6a4f; font-weight: bold; }
  .pts-minus { color: #b7094c; font-weight: bold; }
</style>
</head>
<body>

<div class="header">
  <div class="brand">☕ KAHAWA ADMIN</div>
  <a href="/admin/logout.php" class="logout-btn">Logout</a>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <h3>Total Members</h3>
    <div><?php echo htmlspecialchars($totalUsers); ?></div>
  </div>
  <div class="stat-card">
    <h3>Total Activity Logs</h3>
    <div><?php echo htmlspecialchars($totalOrders); ?></div>
  </div>
</div>

<div class="table-container">
  <h3 style="margin-bottom: 15px; font-size: 15px;">Recent Member Transactions</h3>
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>User</th>
        <th>Description</th>
        <th>Points</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($logs)): ?>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></td>
            <td><?php echo htmlspecialchars(($log['first_name'] ?? 'User') . ' (' . $log['email'] . ')'); ?></td>
            <td><?php echo htmlspecialchars($log['description']); ?></td>
            <td class="<?php echo ($log['points_change'] >= 0) ? 'pts-plus' : 'pts-minus'; ?>">
              <?php echo ($log['points_change'] >= 0 ? '+' : '') . $log['points_change']; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="4" style="text-align:center; color:#666;">No activity logged yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

</body>
</html>
