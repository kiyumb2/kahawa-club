<?php
// Include central database connection (Supabase PostgreSQL)
require_once __DIR__ . '/db.php';

// Define secret key for cookie decryption
define('ENCRYPTION_KEY', 'kahawa_secret_key_change_this_123456!');

/**
 * Decrypt cookie session payload
 */
function decryptCookie($cookie) {
    try {
        $decoded = base64_decode($cookie);
        $parts = explode('::', $decoded);
        if (count($parts) !== 2) return null;
        list($encrypted_data, $iv) = $parts;
        $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
        return json_decode($decrypted, true);
    } catch (Exception $e) {
        return null;
    }
}

// Check session / authentication
$session = isset($_COOKIE['user_session']) ? decryptCookie($_COOKIE['user_session']) : null;

if (!$session || empty($session['user_id'])) {
    header("Location: /index.html");
    exit;
}

// Fetch logged-in user details to verify role
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$session['user_id']]);
$currentUser = $userStmt->fetch();

// Check if user is admin using the is_admin boolean column
$isAdmin = ($currentUser && !empty($currentUser['is_admin']) && ($currentUser['is_admin'] === true || $currentUser['is_admin'] === 'TRUE' || $currentUser['is_admin'] == 1));

if (!$isAdmin) {
    die("Access Denied: You do not have permission to access the Admin Panel.");
}

$message = "";
$messageType = "";

try {
    // Handle Direct Point Request Actions (Approve / Reject)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_action'])) {
        $requestId = intval($_POST['request_id'] ?? 0);
        $action = $_POST['request_action'];

        if ($requestId > 0) {
            $reqStmt = $pdo->prepare("SELECT pr.*, u.first_name, u.last_name FROM point_requests pr JOIN users u ON pr.user_id = u.id WHERE pr.id = ? AND pr.status = 'pending'");
            $reqStmt->execute([$requestId]);
            $pointReq = $reqStmt->fetch(PDO::FETCH_ASSOC);

            if ($pointReq) {
                if ($action === 'approve') {
                    $pdo->beginTransaction();

                    // Update request status
                    $upReq = $pdo->prepare("UPDATE point_requests SET status = 'approved' WHERE id = ?");
                    $upReq->execute([$requestId]);

                    // Add 10 points to user
                    $upUser = $pdo->prepare("UPDATE users SET points = points + 10 WHERE id = ?");
                    $upUser->execute([$pointReq['user_id']]);

                    // Log in reward history
                    $hist = $pdo->prepare("INSERT INTO reward_history (user_id, action_type, points_change, description) VALUES (?, 'visit_point', 10, ?)");
                    $hist->execute([$pointReq['user_id'], 'Counter Visit Request Approved by Admin']);

                    $pdo->commit();
                    $message = "Approved +10 points request for " . htmlspecialchars($pointReq['first_name'] . ' ' . $pointReq['last_name']) . "!";
                    $messageType = "success";
                } elseif ($action === 'reject') {
                    $upReq = $pdo->prepare("UPDATE point_requests SET status = 'rejected' WHERE id = ?");
                    $upReq->execute([$requestId]);

                    $message = "Request rejected successfully.";
                    $messageType = "success";
                }
            } else {
                $message = "Error: Pending request not found or already processed.";
                $messageType = "error";
            }
        }
    }

    // Handle Member Code Actions (Manual Points / Redemptions)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_code'])) {
        $member_code = strtoupper(trim($_POST['member_code']));
        $action = $_POST['action'] ?? '';

        if (!empty($member_code)) {
            // Flexible matching: check exact member_code column or dynamic fallback code
            $stmt = $pdo->prepare("
                SELECT * FROM users 
                WHERE UPPER(TRIM(member_code)) = ? 
                   OR UPPER(CONCAT('KH-', SUBSTRING(MD5(id::text), 1, 6))) = ?
            ");
            $stmt->execute([$member_code, $member_code]);
            $customer = $stmt->fetch();

            if ($customer) {
                if ($action === 'add_points') {
                    $update = $pdo->prepare("UPDATE users SET points = points + 10 WHERE id = ?");
                    $update->execute([$customer['id']]);

                    try {
                        $hist = $pdo->prepare("INSERT INTO reward_history (user_id, action_type, points_change, description) VALUES (?, 'visit_point', 10, ?)");
                        $hist->execute([$customer['id'], 'Visit reward added by cashier']);
                    } catch (Exception $e) {}

                    $message = "Successfully added +10 points to " . $customer['first_name'] . " " . $customer['last_name'] . "!";
                    $messageType = "success";
                } elseif ($action === 'redeem_coffee') {
                    if ($customer['points'] >= 100) {
                        $update = $pdo->prepare("UPDATE users SET points = points - 100 WHERE id = ?");
                        $update->execute([$customer['id']]);

                        try {
                            $hist = $pdo->prepare("INSERT INTO reward_history (user_id, action_type, points_change, description) VALUES (?, 'redeem_coffee', -100, ?)");
                            $hist->execute([$customer['id'], 'Redeemed Free Coffee reward']);
                        } catch (Exception $e) {}

                        $message = "Successfully redeemed 100 points for a Free Coffee for " . $customer['first_name'] . "!";
                        $messageType = "success";
                    } else {
                        $message = "Error: Customer only has " . $customer['points'] . " points. Needs 100.";
                        $messageType = "error";
                    }
                }
            } else {
                $message = "Error: Member code not found.";
                $messageType = "error";
            }
        }
    }

    // Handle New Financial Record Submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finance_action'])) {
        $desc = trim($_POST['description'] ?? '');
        $type = $_POST['record_type'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $recDate = $_POST['record_date'] ?? date('Y-m-d');
        if (!empty($desc) && in_array($type, ['income', 'fee', 'debt']) && $amount > 0) {
            $stmt = $pdo->prepare("INSERT INTO finances (record_date, description, type, amount) VALUES (?, ?, ?, ?)");
            $stmt->execute([$recDate, $desc, $type, $amount]);
            $message = "Financial record added successfully!";
            $messageType = "success";
        }
    }

    // Fetch Daily Analytics (Today's metrics)
    $today = date('Y-m-d');
    $ordersTodayStmt = $pdo->prepare("SELECT COUNT(*) as total_orders, SUM(price) as total_revenue FROM orders WHERE DATE(order_date) = ?");
    $ordersTodayStmt->execute([$today]);
    $analytics = $ordersTodayStmt->fetch();
    
    $totalOrders = $analytics['total_orders'] ?? 0;
    $totalRevenue = $analytics['total_revenue'] ?? 0.00;

    // Fetch Pending Point Requests
    $pendingRequestsStmt = $pdo->query("
        SELECT pr.*, u.first_name, u.last_name, u.member_code 
        FROM point_requests pr 
        JOIN users u ON pr.user_id = u.id 
        WHERE pr.status = 'pending' 
        ORDER BY pr.created_at DESC
    ");
    $pendingRequests = $pendingRequestsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Recent App Orders
    $ordersStmt = $pdo->query("SELECT o.*, u.first_name, u.last_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.order_date DESC LIMIT 10");
    $recentOrders = $ordersStmt->fetchAll();

    // Fetch Financial Summary & Records
    $financesStmt = $pdo->query("SELECT * FROM finances ORDER BY record_date DESC, id DESC LIMIT 15");
    $financesList = $financesStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalsStmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
            SUM(CASE WHEN type = 'fee' THEN amount ELSE 0 END) as total_fees,
            SUM(CASE WHEN type = 'debt' THEN amount ELSE 0 END) as total_debt
        FROM finances
    ");
    $financesTotals = $totalsStmt->fetch(PDO::FETCH_ASSOC);
    
    $manualIncome = $financesTotals['total_income'] ?? 0;
    $grandFees = $financesTotals['total_fees'] ?? 0;
    $grandDebt = $financesTotals['total_debt'] ?? 0;
    $grandIncome = $totalRevenue + $manualIncome;
    $netProfit = $grandIncome - $grandFees;

    // Fetch last 5 days sales for chart
    $chartStmt = $pdo->query("
        SELECT DATE(order_date) as sale_date, SUM(price) as daily_total 
        FROM orders 
        GROUP BY DATE(order_date) 
        ORDER BY sale_date DESC 
        LIMIT 5
    ");
    $chartRawData = $chartStmt->fetchAll(PDO::FETCH_ASSOC);
    $chartData = array_reverse($chartRawData);
    $maxSale = 1;
    foreach ($chartData as $d) {
        if ($d['daily_total'] > $maxSale) {
            $maxSale = $d['daily_total'];
        }
    }
} catch (PDOException $e) {
    $message = "Database Error: " . $e->getMessage();
    $messageType = "error";
    $totalOrders = 0; $totalRevenue = 0.00; $recentOrders = []; $pendingRequests = [];
    $financesList = []; $grandIncome = 0; $grandFees = 0; $grandDebt = 0; $netProfit = 0;
    $chartData = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kahawa Club - Admin Panel</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
  body { background: #1e1e1e; display: flex; justify-content: center; align-items: center; min-height: 100vh; color: #333; padding: 20px 0; }
  .app-container { 
    width: 100%; max-width: 480px; background: #F9F6F0; border-radius: 30px; 
    padding: 24px 24px 80px 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); border: 8px solid #2c2c2c; position: relative;
  }
  .header { text-align: center; margin-bottom: 20px; }
  .header h1 { font-size: 18px; font-weight: 900; color: #111; letter-spacing: 1px; }
  .header span { font-size: 10px; color: #c49a6c; text-transform: uppercase; letter-spacing: 2px; display: block; }
  
  .analytics-grid { display: flex; gap: 10px; margin-bottom: 16px; }
  .analytic-card { flex: 1; background: #fff; padding: 12px; border-radius: 16px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.03); }
  .analytic-card h3 { font-size: 16px; font-weight: 900; color: #6f4e37; }
  .analytic-card p { font-size: 10px; color: #777; text-transform: uppercase; margin-top: 2px; font-weight: bold; }
  .card { background: #fff; padding: 16px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 16px; }
  .card h2 { font-size: 14px; font-weight: 900; color: #111; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
  
  .form-group { margin-bottom: 12px; }
  label { display: block; font-size: 11px; font-weight: bold; color: #555; margin-bottom: 4px; }
  input[type="text"] { 
    width: 100%; padding: 10px; border: 1.5px solid #ddd; border-radius: 10px; font-size: 13px; text-align: center; text-transform: uppercase; font-weight: bold; 
  }
  input[type="text"]:focus { border-color: #c49a6c; outline: none; }
  
  .btn-group { display: flex; gap: 8px; }
  .btn { 
    flex: 1; padding: 10px; border: none; border-radius: 10px; font-weight: bold; font-size: 12px; cursor: pointer; color: white; text-align: center;
  }
  .btn-add { background: #2d6a4f; }
  .btn-redeem { background: #6f4e37; }
  .btn-danger { background: #b7094c; }
  .alert { padding: 10px; border-radius: 10px; font-size: 11px; font-weight: bold; text-align: center; margin-bottom: 14px; }
  .alert.success { background: #d8f3dc; color: #1b4332; border: 1px solid #95d5b2; }
  .alert.error { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
  
  .requests-list { max-height: 200px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; }
  .request-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #fdfbf7; border: 1px solid #eee; border-radius: 12px; font-size: 12px; }
  .request-info strong { display: block; color: #111; }
  .request-info span { font-size: 10px; color: #777; }
  .request-actions { display: flex; gap: 6px; }
  .btn-sm { padding: 6px 10px; font-size: 10px; border-radius: 6px; border: none; font-weight: bold; cursor: pointer; color: white; }

  .orders-list { max-height: 160px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; }
  .order-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; background: #f9f9f9; border-radius: 10px; font-size: 12px; }
  .order-info strong { display: block; color: #111; }
  .order-info span { font-size: 10px; color: #777; }
  .order-price { font-weight: 900; color: #6f4e37; }
  
  .chart-card { background: #fff; padding: 16px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 16px; }
  .chart-card h2 { font-size: 14px; font-weight: 900; color: #111; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; }
  .chart-container { display: flex; align-items: flex-end; justify-content: space-between; height: 120px; padding-top: 20px; border-bottom: 2px solid #eee; gap: 8px; }
  .chart-bar-wrapper { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; }
  .chart-bar { width: 100%; max-width: 32px; background: #6f4e37; border-radius: 6px 6px 0 0; transition: height 0.4s ease; position: relative; }
  .chart-bar:hover { background: #5a3d2b; }
  .chart-bar span { position: absolute; top: -18px; left: 50%; transform: translateX(-50%); font-size: 9px; font-weight: bold; color: #555; white-space: nowrap; }
  .chart-label { font-size: 9px; color: #777; margin-top: 6px; font-weight: bold; text-align: center; }
  
  .finance-grid { display: flex; gap: 8px; margin-bottom: 12px; }
  .finance-badge { flex: 1; background: #f9f9f9; padding: 10px; border-radius: 12px; text-align: center; border: 1px solid #eee; }
  .finance-badge h4 { font-size: 12px; font-weight: 900; }
  .finance-badge.income h4 { color: #2d6a4f; }
  .finance-badge.fee h4 { color: #b7094c; }
  .finance-badge.profit h4 { color: #111; }
  .finance-badge.debt h4 { color: #e85d04; }
  .finance-badge span { font-size: 9px; color: #777; text-transform: uppercase; font-weight: bold; }
  .finance-form { display: flex; flex-direction: column; gap: 8px; margin-bottom: 14px; }
  .finance-row { display: flex; gap: 8px; }
  .finance-row input, .finance-row select { width: 100%; padding: 8px; border: 1.5px solid #ddd; border-radius: 8px; font-size: 11px; font-weight: bold; }
  
  .finances-list { max-height: 140px; overflow-y: auto; display: flex; flex-direction: column; gap: 6px; }
  .finance-item { display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; background: #f9f9f9; border-radius: 8px; font-size: 11px; }
  .finance-item.income { border-left: 4px solid #2d6a4f; }
  .finance-item.fee { border-left: 4px solid #b7094c; }
  .finance-item.debt { border-left: 4px solid #e85d04; }
  .finance-amount { font-weight: 900; }

  .bottom-nav {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 65px;
    background: #ffffff;
    border-bottom-left-radius: 22px;
    border-bottom-right-radius: 22px;
    display: flex;
    justify-content: space-around;
    align-items: center;
    border-top: 1px solid #eee;
    box-shadow: 0 -4px 15px rgba(0,0,0,0.03);
  }
  .nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: #888;
    font-size: 10px;
    font-weight: bold;
    gap: 4px;
  }
  .nav-item.active {
    color: #6f4e37;
  }
  .nav-item .icon {
    font-size: 18px;
  }
  .nav-item.logout {
    color: #d9534f;
  }
</style>
</head>
<body>

<div class="app-container">

  <div class="header">
    <span>Management & Cashier Console</span>
    <h1>KAHAWA ADMIN</h1>
  </div>

  <div class="analytics-grid">
    <div class="analytic-card">
      <h3><?php echo $totalOrders; ?></h3>
      <p>Orders Today</p>
    </div>
    <div class="analytic-card">
      <h3>ETB <?php echo number_format($totalRevenue, 2); ?></h3>
      <p>Revenue Today</p>
    </div>
  </div>

  <?php if (!empty($message)): ?>
    <div class="alert <?php echo $messageType; ?>">
      <?php echo htmlspecialchars($message); ?>
    </div>
  <?php endif; ?>

  <!-- NEW: Pending Point Requests Card -->
  <div class="card">
    <h2>Pending Counter Point Requests <span>📍</span></h2>
    <div class="requests-list">
      <?php if (count($pendingRequests) > 0): ?>
        <?php foreach ($pendingRequests as $req): ?>
          <div class="request-item">
            <div class="request-info">
              <strong><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></strong>
              <span>Code: <?php echo htmlspecialchars($req['member_code'] ?? 'N/A'); ?> &bull; <?php echo date('H:i', strtotime($req['created_at'])); ?></span>
            </div>
            <div class="request-actions">
              <form method="POST" style="display:inline;">
                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                <button type="submit" name="request_action" value="approve" class="btn-sm btn-add">Approve</button>
              </form>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                <button type="submit" name="request_action" value="reject" class="btn-sm btn-danger">Reject</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="text-align: center; font-size: 11px; color: #888; padding: 10px;">No pending point requests.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h2>Manual Customer Terminal</h2>
    <form method="POST">
      <div class="form-group">
        <label>Member Code</label>
        <input type="text" name="member_code" placeholder="e.g. KH-301454" required autocomplete="off">
      </div>
      <div class="btn-group">
        <button type="submit" name="action" value="add_points" class="btn btn-add">+10 Visit Points</button>
        <button type="submit" name="action" value="redeem_coffee" class="btn btn-redeem">Claim Coffee</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>Live App Orders <span>🛒</span></h2>
    <div class="orders-list">
      <?php if (count($recentOrders) > 0): ?>
        <?php foreach ($recentOrders as $order): ?>
          <div class="order-item">
            <div class="order-info">
              <strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong>
              <span><?php echo htmlspecialchars($order['item_name']); ?> &bull; <?php echo date('H:i', strtotime($order['order_date'])); ?></span>
            </div>
            <div class="order-price">ETB <?php echo number_format($order['price'], 2); ?></div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="text-align: center; font-size: 11px; color: #888; padding: 10px;">No app orders recorded yet today.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="chart-card">
    <h2>Sales Trend (Last 5 Days) <span>📊</span></h2>
    <div class="chart-container">
      <?php if (count($chartData) > 0): ?>
        <?php foreach ($chartData as $data): ?>
          <?php 
             $heightPercent = ($data['daily_total'] / $maxSale) * 100;
             if ($heightPercent < 5 && $data['daily_total'] > 0) { $heightPercent = 5; }
             $formattedDate = date('M j', strtotime($data['sale_date']));
          ?>
          <div class="chart-bar-wrapper">
            <div class="chart-bar" style="height: <?php echo $heightPercent; ?>%;">
              <span>ETB <?php echo number_format($data['daily_total'], 0); ?></span>
            </div>
            <div class="chart-label"><?php echo $formattedDate; ?></div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="text-align: center; font-size: 11px; color: #888; width: 100%; margin: auto;">No historical sales data available yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h2>Financial Overview <span>💰</span></h2>
    
    <div class="finance-grid">
      <div class="finance-badge income">
        <h4>+ETB <?php echo number_format($grandIncome, 2); ?></h4>
        <span>Income</span>
      </div>
      <div class="finance-badge fee">
        <h4>-ETB <?php echo number_format($grandFees, 2); ?></h4>
        <span>Fees/Exp</span>
      </div>
      <div class="finance-badge profit">
        <h4>ETB <?php echo number_format($netProfit, 2); ?></h4>
        <span>Net Profit</span>
      </div>
      <div class="finance-badge debt">
        <h4>ETB <?php echo number_format($grandDebt, 2); ?></h4>
        <span>Total Debt</span>
      </div>
    </div>

    <form method="POST" class="finance-form">
      <input type="hidden" name="finance_action" value="1">
      <div class="finance-row">
        <input type="text" name="description" placeholder="Description (e.g., Coffee Beans, Rent)" required autocomplete="off">
        <select name="record_type" required>
          <option value="income">Income</option>
          <option value="fee">Fee / Expense</option>
          <option value="debt">Debt / Owed</option>
        </select>
      </div>
      <div class="finance-row">
        <input type="number" step="0.01" name="amount" placeholder="Amount (ETB)" required>
        <input type="date" name="record_date" value="<?php echo date('Y-m-d'); ?>" required>
      </div>
      <button type="submit" class="btn btn-add" style="width: 100%; padding: 8px; font-size: 11px;">Add Financial Record</button>
    </form>

    <div class="finances-list">
      <?php if (count($financesList) > 0): ?>
        <?php foreach ($financesList as $rec): ?>
          <div class="finance-item <?php echo $rec['type']; ?>">
            <div>
              <strong><?php echo htmlspecialchars($rec['description']); ?></strong>
              <span style="font-size: 9px; color: #777;"><?php echo $rec['record_date']; ?> &bull; <?php echo strtoupper($rec['type']); ?></span>
            </div>
            <div class="finance-amount">
              <?php echo ($rec['type'] === 'fee') ? '-' : '+'; ?>ETB <?php echo number_format($rec['amount'], 2); ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="text-align: center; font-size: 11px; color: #888; padding: 6px;">No financial entries logged yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <nav class="bottom-nav">
    <a href="/api/admin_dashboard" class="nav-item active">
      <span class="icon">⚡</span>
      <span>Admin</span>
    </a>
    <a href="/api/dashboard" class="nav-item">
      <span class="icon">📱</span>
      <span>App View</span>
    </a>
    <a href="/api/logout" class="nav-item logout">
      <span class="icon">🚪</span>
      <span>Logout</span>
    </a>
  </nav>

</div>
</body>
</html>
