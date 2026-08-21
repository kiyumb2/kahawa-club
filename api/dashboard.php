<?php
session_start();

// Redirect to login if user session does not exist
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

// Central Supabase PostgreSQL PDO Connection
require_once 'db.php';

try {
    // 1. Fetch current user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();

    if (!$current_user) {
        session_destroy();
        header("Location: index.html");
        exit;
    }

    $points = $current_user['points'] ?? 0;
    
    // Member Code fallback if not set
    $member_code = !empty($current_user['member_code']) 
        ? $current_user['member_code'] 
        : 'KH-' . strtoupper(substr(md5($current_user['id']), 0, 6));
        
    $full_name = trim(($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? ''));

    // 2. Fetch recent reward/points transaction history
    $historyStmt = $pdo->prepare("SELECT * FROM reward_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $historyStmt->execute([$_SESSION['user_id']]);
    $rewardHistory = $historyStmt->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kahawa Club - Dashboard</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #000; color: #fff; min-height: 100vh; padding-bottom: 80px; }
  .app-container { max-width: 420px; margin: 0 auto; background: #111; min-height: 100vh; position: relative; padding: 20px 16px; }
  
  /* Header */
  .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  .user-welcome h3 { font-size: 14px; color: #888; font-weight: normal; }
  .user-welcome h1 { font-size: 20px; font-weight: bold; color: #fff; }
  .logout-btn { background: rgba(255,255,255,0.1); color: #fff; padding: 8px 14px; border-radius: 20px; text-decoration: none; font-size: 12px; font-weight: bold; border: 1px solid rgba(255,255,255,0.1); }

  /* Loyalty Card */
  .loyalty-card { background: linear-gradient(135deg, #2c1a0e 0%, #1a0f08 100%); border: 1px solid #6F4E37; border-radius: 20px; padding: 20px; margin-bottom: 24px; box-shadow: 0 10px 20px rgba(0,0,0,0.5); position: relative; overflow: hidden; }
  .loyalty-card::after { content: '☕'; position: absolute; right: -10px; bottom: -10px; font-size: 100px; opacity: 0.05; pointer-events: none; }
  .card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
  .card-brand { font-size: 16px; font-weight: 900; letter-spacing: 1px; color: #C49A6C; }
  .member-code { font-size: 11px; background: rgba(196, 154, 108, 0.2); color: #C49A6C; padding: 4px 8px; border-radius: 6px; font-family: monospace; }
  .points-label { font-size: 12px; color: #aaa; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
  .points-val { font-size: 38px; font-weight: 900; color: #fff; line-height: 1; }
  .points-val span { font-size: 18px; color: #C49A6C; font-weight: normal; margin-left: 4px; }

  /* Actions */
  .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; }
  .action-card { background: #1c1c1e; border-radius: 16px; padding: 16px; text-decoration: none; color: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; border: 1px solid #2c2c2e; transition: transform 0.2s; }
  .action-card:active { transform: scale(0.98); }
  .action-icon { font-size: 28px; margin-bottom: 8px; }
  .action-title { font-size: 13px; font-weight: bold; }

  /* Section Titles */
  .section-title { font-size: 16px; font-weight: bold; margin-bottom: 14px; color: #fff; display: flex; justify-content: space-between; align-items: center; }

  /* Menu Grid */
  .menu-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; }
  .menu-item { background: #1c1c1e; border-radius: 16px; padding: 14px; border: 1px solid #2c2c2e; text-align: center; }
  .menu-item-icon { font-size: 36px; margin-bottom: 8px; }
  .menu-item-name { font-size: 14px; font-weight: bold; margin-bottom: 4px; }
  .menu-item-price { font-size: 12px; color: #C49A6C; margin-bottom: 10px; }
  .order-btn { width: 100%; padding: 8px; background: #6F4E37; color: white; border: none; border-radius: 8px; font-size: 12px; font-weight: bold; cursor: pointer; }

  /* History Table */
  .history-list { background: #1c1c1e; border-radius: 16px; border: 1px solid #2c2c2e; overflow: hidden; margin-bottom: 24px; }
  .history-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid #2c2c2e; }
  .history-item:last-child { border-bottom: none; }
  .hist-desc { font-size: 13px; font-weight: 500; }
  .hist-date { font-size: 11px; color: #666; margin-top: 2px; }
  .hist-points { font-size: 13px; font-weight: bold; }
  .points-plus { color: #4CAF50; }
  .points-minus { color: #FF5252; }

  /* Modal Popup */
  .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: none; justify-content: center; align-items: center; z-index: 1000; padding: 20px; }
  .modal-overlay.active { display: flex; }
  .modal-card { background: #1c1c1e; border-radius: 20px; padding: 24px; text-align: center; max-width: 320px; width: 100%; border: 1px solid #333; }
  .modal-icon { font-size: 40px; margin-bottom: 12px; }
  .modal-title { font-size: 18px; font-weight: bold; margin-bottom: 8px; }
  .modal-text { font-size: 13px; color: #aaa; margin-bottom: 20px; line-height: 1.4; }
  .modal-btn { padding: 10px 20px; background: #6F4E37; color: white; border: none; border-radius: 10px; font-size: 14px; font-weight: bold; cursor: pointer; }
</style>
</head>
<body>

<div class="app-container">
  
  <div class="header">
    <div class="user-welcome">
      <h3>Welcome back,</h3>
      <h1><?php echo htmlspecialchars($full_name); ?></h1>
    </div>
    <a href="logout.php" class="logout-btn">Log Out</a>
  </div>

  <div class="loyalty-card">
    <div class="card-top">
      <div class="card-brand">KAHAWA CLUB</div>
      <div class="member-code"><?php echo htmlspecialchars($member_code); ?></div>
    </div>
    <div class="points-label">Rewards Balance</div>
    <div class="points-val"><?php echo number_format($points); ?> <span>PTS</span></div>
  </div>

  <div class="action-grid">
    <a href="scan_qr.php" class="action-card">
      <div class="action-icon">📷</div>
      <div class="action-title">Scan In-Store QR</div>
    </a>
    <div class="action-card" onclick="claimFreeCoffee()" style="cursor: pointer;">
      <div class="action-icon">🎁</div>
      <div class="action-title">Claim Free Coffee</div>
    </div>
  </div>

  <div class="section-title">Quick Order</div>
  <div class="menu-grid">
    <div class="menu-item">
      <div class="menu-item-icon">☕</div>
      <div class="menu-item-name">Espresso</div>
      <div class="menu-item-price">80 ETB</div>
      <button class="order-btn" onclick="placeOrder('Espresso', 80)">Order Now</button>
    </div>
    <div class="menu-item">
      <div class="menu-item-icon">🥛</div>
      <div class="menu-item-name">Macchiato</div>
      <div class="menu-item-price">100 ETB</div>
      <button class="order-btn" onclick="placeOrder('Macchiato', 100)">Order Now</button>
    </div>
    <div class="menu-item">
      <div class="menu-item-icon">🧊</div>
      <div class="menu-item-name">Iced Coffee</div>
      <div class="menu-item-price">120 ETB</div>
      <button class="order-btn" onclick="placeOrder('Iced Coffee', 120)">Order Now</button>
    </div>
    <div class="menu-item">
      <div class="menu-item-icon">🥐</div>
      <div class="menu-item-name">Croissant</div>
      <div class="menu-item-price">90 ETB</div>
      <button class="order-btn" onclick="placeOrder('Croissant', 90)">Order Now</button>
    </div>
  </div>

  <div class="section-title">Activity History</div>
  <div class="history-list">
    <?php if (empty($rewardHistory)): ?>
      <div class="history-item">
        <div class="hist-desc">No transaction history yet</div>
      </div>
    <?php else: ?>
      <?php foreach ($rewardHistory as $item): ?>
        <div class="history-item">
          <div>
            <div class="hist-desc"><?php echo htmlspecialchars($item['description']); ?></div>
            <div class="hist-date"><?php echo date('M d, Y - H:i', strtotime($item['created_at'])); ?></div>
          </div>
          <div class="hist-points <?php echo $item['points_change'] >= 0 ? 'points-plus' : 'points-minus'; ?>">
            <?php echo ($item['points_change'] >= 0 ? '+' : '') . intval($item['points_change']); ?> pts
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<div class="modal-overlay" id="appMessageModal">
  <div class="modal-card">
    <div class="modal-icon" id="msgModalIcon">☕</div>
    <div class="modal-title" id="msgModalTitle">Notice</div>
    <div class="modal-text" id="msgModalText">Message text goes here...</div>
    <button class="modal-btn" onclick="closeMessageModal()">OK</button>
  </div>
</div>

<script>
  function showAppMessage(title, text, icon = '☕') {
      document.getElementById('msgModalTitle').innerText = title;
      document.getElementById('msgModalText').innerText = text;
      document.getElementById('msgModalIcon').innerText = icon;
      document.getElementById('appMessageModal').classList.add('active');
  }

  function closeMessageModal() {
      document.getElementById('appMessageModal').classList.remove('active');
  }

  function placeOrder(itemName, price) {
      fetch('place_order.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'item_name=' + encodeURIComponent(itemName) + '&price=' + encodeURIComponent(price)
      })
      .then(res => res.json())
      .then(data => {
          if (data.success) {
              showAppMessage('Order Placed!', 'Your order for ' + itemName + ' has been placed successfully.', '✅');
          } else {
              showAppMessage('Order Failed', data.message || 'Could not place order.', '⚠️');
          }
      })
      .catch(error => {
          console.error('Error:', error);
          showAppMessage('Error', 'An error occurred while processing your order.', '⚠️');
      });
  }

  function claimFreeCoffee() {
      fetch('claim_reward.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
      })
      .then(res => res.json())
      .then(data => {
          if(data.success) {
              showAppMessage("Reward Claimed!", "Success! Your free coffee has been claimed. Enjoy!", "🎉");
              setTimeout(() => { location.reload(); }, 2500);
          } else {
              showAppMessage("Notice", data.message, "⚠️");
          }
      })
      .catch(error => {
          console.error('Fetch Error:', error);
          showAppMessage("Error", "An unexpected error occurred.", "⚠️");
      });
  }
</script>

</body>
</html>