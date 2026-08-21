<?php
define('ENCRYPTION_KEY', 'kahawa_secret_key_change_this_123456!');

/**
 * Decrypt cookie data to restore stateless sessions on Vercel
 */
function decryptCookie($cookieValue) {
    $parts = explode('::', base64_decode($cookieValue), 2);
    if (count($parts) !== 2) return null;
    list($encrypted_data, $iv) = $parts;
    $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
    return json_decode($decrypted, true);
}

// Read encrypted cookie session
$sessionData = isset($_COOKIE['user_session']) ? decryptCookie($_COOKIE['user_session']) : null;

// Redirect to login if user cookie is missing or invalid
if (!$sessionData || empty($sessionData['user_id'])) {
    header("Location: /index.html");
    exit;
}

$user_id = $sessionData['user_id'];

// Central Supabase PostgreSQL PDO Connection
require_once __DIR__ . '/db.php';

try {
    // Fetch live user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch();

    if (!$current_user) {
        setcookie('user_session', '', time() - 3600, '/');
        header("Location: /index.html");
        exit;
    }

    $points = $current_user['points'] ?? 0;

    $member_code = !empty($current_user['member_code']) 
        ? $current_user['member_code'] 
        : 'KH-' . strtoupper(substr(md5($current_user['id']), 0, 6));

    $full_name = trim(($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? ''));

    // Fetch reward history
    $historyStmt = $pdo->prepare("SELECT * FROM reward_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $historyStmt->execute([$user_id]);
    $rewardHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Connection failed: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kahawa Coffee Club - Dashboard</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
  
  body { 
    background: #1e1e1e; 
    display: flex; 
    justify-content: center; 
    align-items: center; 
    min-height: 100vh; 
  }
  /* Mobile App Container Frame */
  .app-container { 
    width: 100%; 
    max-width: 414px; 
    height: 100vh; 
    max-height: 896px; 
    background: #F9F6F0; 
    display: flex; 
    flex-direction: column; 
    position: relative; 
    box-shadow: 0 20px 40px rgba(0,0,0,0.5); 
    overflow: hidden; 
  }
  @media (min-width: 450px) {
    .app-container { border-radius: 40px; border: 10px solid #2c2c2c; height: 85vh; }
  }
  /* Top App Header */
  .app-header {
    background: #111;
    color: white;
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .brand-logo {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .logo-badge {
    width: 32px;
    height: 32px;
    background: #1b4332;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #c49a6c;
    font-weight: bold;
    font-size: 14px;
    border: 1px solid #c49a6c;
  }
  .brand-text h1 { font-size: 14px; font-weight: 900; letter-spacing: 1px; color: #fff; }
  .brand-text span { font-size: 9px; color: #c49a6c; letter-spacing: 2px; display: block; }
  
  .points-pill {
    background: #1a1a1a;
    border: 1px solid #333;
    padding: 6px 12px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 6px;
    color: #c49a6c;
    font-weight: bold;
    font-size: 13px;
  }
  /* Main Scrollable Content Area */
  .app-content {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    padding-bottom: 90px;
    scrollbar-width: none;
  }
  .app-content::-webkit-scrollbar { display: none; }
  /* Loyalty Card */
  .loyalty-card {
    background: linear-gradient(135deg, #2c221e 0%, #171210 100%);
    border-radius: 20px;
    padding: 20px;
    color: white;
    margin-bottom: 20px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    border: 1px solid rgba(196, 154, 108, 0.2);
  }
  .loyalty-points-title {
    font-size: 28px;
    font-weight: 900;
    color: #c49a6c;
    line-height: 1;
  }
  .loyalty-points-title span { font-size: 12px; font-weight: normal; color: #aaa; display: block; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px; }
  
  .progress-section {
    margin: 18px 0;
  }
  .progress-info {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #aaa;
    margin-bottom: 6px;
  }
  .progress-bar-bg {
    width: 100%;
    height: 6px;
    background: rgba(255,255,255,0.1);
    border-radius: 3px;
    overflow: hidden;
  }
  .progress-bar-fill {
    width: 5%;
    height: 100%;
    background: #c49a6c;
    border-radius: 3px;
  }
  .card-footer-info {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-top: 10px;
  }
  .user-fullname {
    font-size: 15px;
    font-weight: bold;
    color: #fff;
    text-transform: lowercase;
  }
  .member-id {
    font-size: 11px;
    color: #888;
  }
  .tap-qr {
    font-size: 12px;
    color: #c49a6c;
    text-decoration: none;
    font-weight: bold;
  }
  /* Section Headings */
  .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
  }
  .section-title { font-size: 16px; font-weight: 900; color: #111; }
  .section-action { font-size: 12px; color: #6f4e37; font-weight: bold; text-decoration: none; }
  /* Offers Horizontal Scroll */
  .offers-scroll {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding-bottom: 8px;
    margin-bottom: 24px;
    scrollbar-width: none;
  }
  .offers-scroll::-webkit-scrollbar { display: none; }
  
  .offer-card {
    min-width: 240px;
    border-radius: 16px;
    padding: 16px;
    color: white;
    flex-shrink: 0;
  }
  .offer-card.brown {
    background: linear-gradient(135deg, #3d2c23 0%, #1f1510 100%);
    border: 1px solid rgba(196,154,108,0.3);
  }
  .offer-card.purple {
    background: linear-gradient(135deg, #42275a 0%, #734b6d 100%);
  }
  .offer-tag { font-size: 9px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; opacity: 0.8; margin-bottom: 6px; display: block; }
  .offer-heading { font-size: 15px; font-weight: 900; margin-bottom: 4px; }
  .offer-desc { font-size: 11px; opacity: 0.9; line-height: 1.4; }
  /* Clickable Coffee Slide Panel */
  .coffee-scroll {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding-bottom: 10px;
    margin-bottom: 20px;
    scrollbar-width: none;
  }
  .coffee-scroll::-webkit-scrollbar { display: none; }
  .coffee-card {
    min-width: 135px;
    background: #fff;
    border-radius: 16px;
    padding: 12px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.03);
    text-align: center;
    flex-shrink: 0;
    border: 1.5px solid rgba(0,0,0,0.04);
    cursor: pointer;
    transition: all 0.2s ease;
  }
  .coffee-card:hover {
    border-color: #c49a6c;
    transform: translateY(-3px);
  }
  .coffee-card:active {
    transform: scale(0.96);
  }
  .coffee-img {
    height: 80px;
    background: #f0e4d8;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    margin-bottom: 8px;
  }
  .coffee-name { font-size: 13px; font-weight: bold; color: #111; margin-bottom: 2px; }
  .coffee-price { font-size: 11px; color: #6f4e37; font-weight: bold; margin-bottom: 6px; }
  .buy-tag {
    display: inline-block;
    background: #6f4e37;
    color: white;
    font-size: 9px;
    font-weight: bold;
    padding: 3px 8px;
    border-radius: 10px;
    text-transform: uppercase;
  }
  /* Modal Popup for Buying */
  .modal-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
  }
  .modal-overlay.active {
    opacity: 1;
    pointer-events: auto;
  }
  .modal-card {
    background: #fff;
    width: 85%;
    max-width: 320px;
    border-radius: 24px;
    padding: 24px;
    text-align: center;
    transform: scale(0.8);
    transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 15px 35px rgba(0,0,0,0.3);
  }
  .modal-overlay.active .modal-card {
    transform: scale(1);
  }
  .modal-icon { font-size: 40px; margin-bottom: 10px; }
  .modal-title { font-size: 18px; font-weight: 900; color: #111; margin-bottom: 6px; }
  .modal-price { font-size: 14px; color: #6f4e37; font-weight: bold; margin-bottom: 16px; }
  .modal-btn {
    width: 100%;
    padding: 12px;
    background: #6f4e37;
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    margin-bottom: 8px;
  }
  .modal-btn:hover { background: #5a3d2c; }
  .modal-cancel {
    background: none;
    border: none;
    color: #888;
    font-size: 13px;
    font-weight: bold;
    cursor: pointer;
    padding: 6px;
  }
  /* Bottom App Navigation Bar */
  .bottom-nav {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 75px;
    background: #111;
    display: flex;
    justify-content: space-around;
    align-items: center;
    padding-bottom: 10px;
    border-top: 1px solid #222;
    z-index: 100;
  }
  .nav-item {
    text-decoration: none;
    text-align: center;
    color: #777;
    flex: 1;
    transition: color 0.2s;
    background: none;
    border: none;
    cursor: pointer;
  }
  .nav-item.active { color: #c49a6c; }
  .nav-icon { font-size: 20px; display: block; margin-bottom: 3px; }
  .nav-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: block; }
  
  /* Modern Custom Popup Styles */
  #custom-popup {
    display: none;
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    justify-content: center;
    align-items: center;
    z-index: 9999;
    animation: fadeIn 0.2s ease-out;
  }
  .popup-box {
    background: #fff;
    padding: 30px 24px;
    border-radius: 20px;
    width: 90%; max-width: 360px;
    text-align: center;
    box-shadow: 0 15px 35px rgba(0,0,0,0.3);
    animation: popUp 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  }
  .popup-icon {
    width: 60px; height: 60px;
    background: #f5ede3;
    color: #6f4e37;
    font-size: 28px;
    border-radius: 50%;
    display: flex; justify-content: center; align-items: center;
    margin: 0 auto 16px;
  }
  .popup-box h3 { font-size: 18px; color: #111; margin-bottom: 8px; font-weight: 900; }
  .popup-box p { font-size: 13px; color: #666; margin-bottom: 20px; line-height: 1.4; }
  .popup-btn {
    background: #6f4e37; color: white; border: none; width: 100%;
    padding: 12px; border-radius: 12px; font-weight: bold; font-size: 14px;
    cursor: pointer; transition: background 0.2s;
  }
  .popup-btn:hover { background: #5a3d2b; }
  @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
  @keyframes popUp { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
</style>
</head>
<body>
<!-- Custom Popup Modal HTML -->
<div id="custom-popup">
    <div class="popup-box">
        <div class="popup-icon" id="popup-icon-symbol">✓</div>
        <h3 id="popup-title">Success</h3>
        <p id="popup-message">Your message goes here.</p>
        <button class="popup-btn" onclick="closeCustomPopup()">Continue</button>
    </div>
</div>

<div class="app-container">
  <!-- Top App Header -->
  <div class="app-header">
    <div class="brand-logo">
      <div class="logo-badge">☕</div>
      <div class="brand-text">
        <h1>KAHAWA</h1>
        <span>COFFEE CLUB</span>
      </div>
    </div>
    <div class="points-pill">
      ⭐ <?php echo $points; ?>
    </div>
  </div>

  <!-- Main Scrollable Screen Content -->
  <div class="app-content">
    <!-- Automated Reward Banner -->
    <?php if ($points >= 100): ?>
    <div style="background: linear-gradient(135deg, #2d6a4f 0%, #1b4332 100%); border-radius: 16px; padding: 16px; color: white; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(27,67,50,0.3); display: flex; justify-content: space-between; align-items: center;">
        <div>
            <span style="font-size: 10px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; color: #95d5b2;">Milestone Reached!</span>
            <div style="font-size: 16px; font-weight: 900; margin-top: 2px;">You have a Free Coffee! ☕</div>
            <div style="font-size: 11px; color: #d8f3dc; margin-top: 2px;">You have <?php echo $points; ?> points available.</div>
        </div>
        <button onclick="claimFreeCoffee()" style="background: #c49a6c; color: #111; border: none; padding: 10px 14px; border-radius: 12px; font-weight: bold; font-size: 12px; cursor: pointer;">Claim</button>
    </div>
    <?php endif; ?>
    
    <!-- Loyalty Status Card -->
    <div class="loyalty-card">
      <div class="loyalty-points-title">
        <?php echo $points; ?>
        <span>loyalty points</span>
      </div>
      
      <div class="progress-section">
        <div class="progress-info">
          <span>Progress to Silver</span>
          <span>0%</span>
        </div>
        <div class="progress-bar-bg">
          <div class="progress-bar-fill"></div>
        </div>
      </div>
      <div class="card-footer-info">
        <div>
          <div class="user-fullname"><?php echo htmlspecialchars($full_name); ?></div>
          <div class="member-id"><?php echo htmlspecialchars($member_code); ?></div>
        </div>
        <div>
          <a href="#" class="tap-qr">Tap for QR &rarr;</a>
        </div>
      </div>
    </div>

    <!-- Offers for You Section -->
    <div class="section-header">
      <div class="section-title">Offers for You</div>
    </div>
    
    <div class="offers-scroll">
      <div class="offer-card brown">
        <span class="offer-tag">Every Friday</span>
        <div class="offer-heading">Double Points! ⭐⭐</div>
        <div class="offer-desc">ለታማኝ ደንበኞች ስጦታዎች ይኖሩናል</div>
      </div>
      <div class="offer-card purple">
        <span class="offer-tag">Birthday Reward</span>
        <div class="offer-heading">Free Coffee! 🎂</div>
        <div class="offer-desc">አንድ ሲኒ ቡና ሲጠጡ 10 ነፃ ኮይን ያገኛሉ</div>
      </div>
    </div>

    <!-- Popular Today Slide Panel (Clickable to Buy) -->
    <div class="section-header">
      <div class="section-title">Our menu</div>
      <a href="#" class="section-action">See all &rarr;</a>
    </div>
    <div class="coffee-scroll">
      <div class="coffee-card" onclick="openModal('White Coffee', 'ETB 60.00', '☕')">
        <div class="coffee-img">
          <img src="/resource/tt.png" alt="White Coffee" width="90" height="90">
        </div>
        <div class="coffee-name">White Coffee</div>
        <div class="coffee-price">ETB 60.00</div>
        <span class="buy-tag">Buy Now</span>
      </div>
      <div class="coffee-card" onclick="openModal('Special Coffee', 'ETB 50.00', '🧋')">
        <div class="coffee-img">
          <img src="/resource/special.png" alt="Special Coffee" width="90" height="90">
        </div>
        <div class="coffee-name">Special Coffee</div>
        <div class="coffee-price">ETB 50.00</div>
        <span class="buy-tag">Buy Now</span>
      </div>
      <div class="coffee-card" onclick="openModal('Jebena Tower 8 cup', 'ETB 300.00', '☕')">
        <div class="coffee-img">
          <img src="/resource/jebena3.png" alt="Jebena Tower" width="120" height="80">
        </div>
        <div class="coffee-name">Jebena Tower 8 cup</div>
        <div class="coffee-price">ETB 300.00</div>
        <span class="buy-tag">Buy Now</span>
      </div>
      <div class="coffee-card" onclick="openModal('Siphon Coffee', 'ETB 50.00', '☕')">
        <div class="coffee-img">
          <img src="/resource/siphon.png" alt="Siphon Coffee" width="90" height="80">
        </div>
        <div class="coffee-name">Siphon Coffee</div>
        <div class="coffee-price">ETB 50.00</div>
        <span class="buy-tag">Buy Now</span>
      </div>
      <div class="coffee-card" onclick="openModal('Jebena Coffee', 'ETB 50.00', '🍫')">
        <div class="coffee-img">
          <img src="/resource/jebenaa.png" alt="Jebena Coffee" width="120" height="80">
        </div>
        <div class="coffee-name">Jebena Coffee</div>
        <div class="coffee-price">ETB 50.00</div>
        <span class="buy-tag">Buy Now</span>
      </div>
      <div class="coffee-card" onclick="openModal('Coffee With Butter', 'ETB 75.00', '☕')">
        <div class="coffee-img">
          <img src="/resource/cofb.png" alt="Coffee With Butter" width="120" height="80">
        </div>
        <div class="coffee-name">Coffee With Butter</div>
        <div class="coffee-price">ETB 75.00</div>
        <span class="buy-tag">Buy Now</span>
      </div>
    </div>
  </div>

  <!-- Buy Confirmation Modal Popup -->
  <div class="modal-overlay" id="buyModal">
    <div class="modal-card">
      <div class="modal-icon" id="modalIcon">☕</div>
      <div class="modal-title" id="modalTitle">Coffee Name</div>
      <div class="modal-price" id="modalPrice">ETB 0.00</div>
      <button class="modal-btn" onclick="confirmPurchase()">Confirm Order</button>
      <button class="modal-cancel" onclick="closeModal()">Cancel</button>
    </div>
  </div>

  <!-- Custom App Message Popup Modal -->
  <div class="modal-overlay" id="appMessageModal">
    <div class="modal-card">
      <div class="modal-icon" id="msgModalIcon">☕</div>
      <div class="modal-title" id="msgModalTitle">Notification</div>
      <div class="modal-price" id="msgModalText" style="font-size: 13px; color: #555; margin-bottom: 16px;">Message goes here...</div>
      <button class="modal-btn" onclick="closeMessageModal()">Got it!</button>
    </div>
  </div>

  <!-- Reward History Modal Overlay -->
  <div id="rewardsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:1000; justify-content:center; align-items:center;">
    <div style="background:#F9F6F0; width:90%; max-width:400px; border-radius:20px; padding:20px; max-height:80vh; overflow-y:auto; box-shadow:0 10px 25px rgba(0,0,0,0.3);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="font-size:15px; font-weight:900; color:#111;">🎁 Your Reward History</h3>
            <button onclick="closeRewardsModal()" style="background:none; border:none; font-size:16px; font-weight:bold; cursor:pointer;">✕</button>
        </div>
        
        <div style="display:flex; flex-direction:column; gap:8px;">
            <?php if (count($rewardHistory) > 0): ?>
                <?php foreach ($rewardHistory as $row): ?>
                    <div style="background:#fff; padding:10px 12px; border-radius:12px; display:flex; justify-content:space-between; align-items:center; border-left:4px solid <?php echo ($row['points_change'] > 0) ? '#2d6a4f' : '#b7094c'; ?>;">
                        <div>
                            <strong style="font-size:12px; color:#111; display:block;"><?php echo htmlspecialchars($row['description']); ?></strong>
                            <span style="font-size:9px; color:#777;"><?php echo date('M j, Y - H:i', strtotime($row['created_at'])); ?></span>
                        </div>
                        <div style="font-size:13px; font-weight:900; color:<?php echo ($row['points_change'] > 0) ? '#2d6a4f' : '#b7094c'; ?>;">
                            <?php echo ($row['points_change'] > 0) ? '+' . $row['points_change'] : $row['points_change']; ?> pts
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align:center; font-size:11px; color:#888; padding:20px;">No reward activity yet. Visit the coffee house to earn points!</p>
            <?php endif; ?>
        </div>
    </div>
  </div>

  <!-- Bottom App Navigation Bar (Fixed with Rewards Click Trigger) -->
  <div class="bottom-nav">
    <a href="/api/dashboard.php" class="nav-item active">
      <span class="nav-icon">🏠</span>
      <span class="nav-label">Home</span>
    </a>

    <button onclick="openRewardsModal()" class="nav-item">
      <span class="nav-icon">🎁</span>
      <span class="nav-label">Rewards</span>
    </button>

    <a href="/api/logout.php" class="nav-item">
      <span class="nav-icon">🚪</span>
      <span class="nav-label">Logout</span>
    </a>
  </div>
</div>

<script>
    function openRewardsModal() {
        document.getElementById('rewardsModal').style.display = 'flex';
    }
    function closeRewardsModal() {
        document.getElementById('rewardsModal').style.display = 'none';
    }

    function openModal(name, price, emoji) {
        document.getElementById('modalTitle').innerText = name;
        document.getElementById('modalPrice').innerText = price;
        document.getElementById('modalIcon').innerText = emoji;
        document.getElementById('buyModal').classList.add('active');
    }
    function closeModal() {
        document.getElementById('buyModal').classList.remove('active');
    }
    function showPopup(title, message, isSuccess = true, reloadOnClose = false) {
        document.getElementById('popup-title').innerText = title;
        document.getElementById('popup-message').innerText = message;
        document.getElementById('popup-icon-symbol').innerHTML = isSuccess ? '✓' : '✕';
        
        window.shouldReload = reloadOnClose;
        document.getElementById('custom-popup').style.display = 'flex';
    }
    function closeCustomPopup() {
        document.getElementById('custom-popup').style.display = 'none';
        if (window.shouldReload) {
            location.reload();
        }
    }
    function confirmPurchase() {
        const itemNameEl = document.querySelector('.modal-title');
        const itemPriceEl = document.querySelector('.modal-price');
        if (!itemNameEl || !itemPriceEl) {
            showPopup('Error', 'Could not find item details in the modal.', false);
            return;
        }
        const itemName = itemNameEl.innerText.trim();
        const rawPriceText = itemPriceEl.innerText;
        const itemPrice = parseFloat(rawPriceText.replace(/[^0-9.]/g, '')) || 0;
        if (!itemName || itemPrice <= 0) {
            showPopup('Error', 'Invalid item name or price detected.', false);
            return;
        }
        fetch('/api/place_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'item_name=' + encodeURIComponent(itemName) + '&price=' + encodeURIComponent(itemPrice)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showPopup('Order Confirmed!', 'Your ' + itemName + ' has been successfully ordered and logged.', true, true);
            } else {
                showPopup('Order Failed', data.message, false);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showPopup('Error', 'An error occurred while processing your order.', false);
        });
    }

    function showAppMessage(title, text, icon = '☕') {
        document.getElementById('msgModalTitle').innerText = title;
        document.getElementById('msgModalText').innerText = text;
        document.getElementById('msgModalIcon').innerText = icon;
        document.getElementById('appMessageModal').classList.add('active');
    }
    function closeMessageModal() {
        document.getElementById('appMessageModal').classList.remove('active');
    }

    function claimFreeCoffee() {
        fetch('/api/claim_reward.php', {
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
            showAppMessage("Error", "An unexpected error occurred.", "❌");
        });
    }
</script>
</body>
</html>
