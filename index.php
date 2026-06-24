<?php
include 'includes/session.php';
include 'includes/db.php';

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

if ($role === 'admin') { header("Location: admin/index.php"); exit; }

// Enforce strict DB fetch to prevent empty variables
$stmtUser = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
$stmtUser->execute([$userId]);
$userRow = $stmtUser->fetch();

$username = !empty($userRow['username']) ? $userRow['username'] : 'USER';
$pfpPath = !empty($userRow['profile_picture']) 
    ? $userRow['profile_picture'] 
    : 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=000000&color=ffffff';

$hour = date('H');
if ($hour < 12) $greeting = 'GOOD MORNING';
elseif ($hour < 17) $greeting = 'GOOD AFTERNOON';
else $greeting = 'GOOD EVENING';

$totalItems = $pdo->query("SELECT COUNT(*) FROM clothing_items WHERE user_id = $userId")->fetchColumn();
$totalOutfits = $pdo->query("SELECT COUNT(*) FROM outfits WHERE user_id = $userId")->fetchColumn();
$totalLaundry = $pdo->query("SELECT COUNT(*) FROM clothing_items WHERE user_id = $userId AND status != 'clean' AND status IS NOT NULL")->fetchColumn();

$stmtRecent = $pdo->prepare("SELECT item_name, image_path FROM clothing_items WHERE user_id = ? ORDER BY created_at DESC LIMIT 4");
$stmtRecent->execute([$userId]);
$recentItems = $stmtRecent->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Overview - Smart Wardrobe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { background-color: #fcfcfc; color: #000000; margin: 0; padding: 0; font-family: 'Inter', sans-serif; transition: background-color 0.3s, color 0.3s; }
        a { text-decoration: none; color: inherit; }
        button, input, select { font-family: inherit; }
        
        /* UNIFORM NAVBAR */
        .topnav { background: #ffffff; border-bottom: 1px solid #e5e5e5; padding: 12px 32px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; transition: background-color 0.3s, border-color 0.3s; }
        .brand { font-size: 1.1rem; font-weight: 700; letter-spacing: -0.02em; text-transform: uppercase; color: #000; }
        .nav-links { display: flex; gap: 32px; align-items: center; }
        .nav-links a { font-weight: 500; font-size: 0.85rem; color: #666666; transition: color 0.2s; text-transform: uppercase; letter-spacing: 0.05em; }
        .nav-links a:hover, .nav-links a.active { color: #000000; font-weight: 700; }
        
        .nav-actions { display: flex; align-items: center; gap: 16px; margin-left: 16px; padding-left: 24px; border-left: 1px solid #e5e5e5; }
        .nav-pfp { width: 28px; height: 28px; object-fit: cover; border: 1px solid #000; border-radius: 50%; }
        
        .theme-btn { background: transparent; border: none; font-size: 0.85rem; font-weight: 700; color: #666; cursor: pointer; text-transform: uppercase; letter-spacing: 0.05em; transition: color 0.2s; }
        .theme-btn:hover { color: #000; }

        .dashboard-wrapper { display: grid; grid-template-columns: 280px 1fr; gap: 40px; max-width: 1400px; margin: 40px auto; padding: 0 32px; }
        
        .premium-greeting { font-size: 1.8rem; font-weight: 700; letter-spacing: -0.02em; line-height: 1.1; margin: 0 0 8px 0; text-transform: uppercase; }
        .premium-subtitle { font-size: 0.75rem; color: #999; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; margin: 0; }
        
        .metrics-vertical { display: flex; flex-direction: column; gap: 16px; margin-top: 32px; }
        .metric-card { background: #ffffff; border: 1px solid #e5e5e5; padding: 24px 20px; display: flex; flex-direction: column; transition: border-color 0.2s, background-color 0.3s; }
        .metric-card:hover { border-color: #000000; }
        .metric-num { font-size: 2rem; font-weight: 700; color: #000; line-height: 1; letter-spacing: -0.02em; margin-bottom: 8px; }
        .metric-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: #666; font-weight: 700; margin: 0; }
        
        .panel-title { font-size: 0.85rem; font-weight: 700; color: #000; margin: 0 0 16px 0; border-bottom: 1px solid #000; padding-bottom: 8px; text-transform: uppercase; letter-spacing: 0.1em; display: flex; justify-content: space-between; align-items: flex-end; }
        
        .import-module { background: #fcfcfc; border: 1px solid #e5e5e5; padding: 32px; margin-bottom: 40px; transition: border-color 0.3s, background-color 0.3s; }
        .import-input-wrapper { display: flex; flex-direction: column; gap: 16px; margin-top: 24px; }
        .import-input { width: 100%; padding: 14px 16px; background: #ffffff; border: 1px solid #ccc; font-size: 0.85rem; outline: none; transition: border-color 0.2s, background-color 0.3s; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 500; }
        .import-input:focus { border-color: #000; }
        .import-btn { background: #000000; color: #ffffff; border: 1px solid #000; padding: 14px; font-weight: 700; cursor: pointer; transition: background 0.2s; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; }
        .import-btn:hover { background: #ffffff; color: #000000; }
        
        .workflow-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 40px; }
        .workflow-btn { background: #ffffff; border: 1px solid #e5e5e5; padding: 24px; color: #000; font-weight: 600; font-size: 0.75rem; text-align: center; text-transform: uppercase; letter-spacing: 0.1em; transition: border-color 0.2s, background-color 0.3s; }
        .workflow-btn:hover { border-color: #000; }
        
        .recent-ribbon { display: flex; gap: 16px; overflow-x: auto; padding-bottom: 16px; }
        .recent-item-card { flex: 0 0 160px; border: 1px solid #e5e5e5; background: #ffffff; transition: border-color 0.2s, background-color 0.3s; cursor: pointer; }
        .recent-item-card:hover { border-color: #000; }
        .recent-item-img { height: 180px; width: 100%; background-size: contain; background-position: center; background-repeat: no-repeat; filter: grayscale(100%); transition: filter 0.3s; }
        .recent-item-card:hover .recent-item-img { filter: grayscale(0%); }
        .recent-item-name { padding: 12px; font-size: 0.7rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: center; border-top: 1px solid #e5e5e5; text-transform: uppercase; letter-spacing: 0.05em; }

        /* =========================================
           STRICT DARK MODE INVERSION
           ========================================= */
        body.dark-mode { background-color: #0a0a0a; color: #ffffff; }
        body.dark-mode .topnav, body.dark-mode .metric-card, body.dark-mode .import-module, 
        body.dark-mode .workflow-btn, body.dark-mode .recent-item-card { background-color: #0a0a0a; border-color: #333333; }
        
        body.dark-mode .brand, body.dark-mode .premium-greeting, body.dark-mode .panel-title, 
        body.dark-mode .metric-num, body.dark-mode .workflow-btn, body.dark-mode .recent-item-name,
        body.dark-mode .theme-btn:hover, body.dark-mode .topnav a:hover, body.dark-mode .topnav a.active { color: #ffffff; }
        
        body.dark-mode .topnav a, body.dark-mode .premium-subtitle, body.dark-mode .metric-label, body.dark-mode .theme-btn { color: #888888; }
        body.dark-mode .panel-title, body.dark-mode .recent-item-name { border-color: #333333; }
        body.dark-mode .nav-actions { border-color: #333333; }
        body.dark-mode .nav-pfp { border-color: #333333; }
        
        body.dark-mode .import-input { background-color: #111111; border-color: #333333; color: #ffffff; }
        body.dark-mode .import-input:focus { border-color: #ffffff; }
        
        body.dark-mode .import-btn { background-color: #ffffff; color: #000000; border-color: #ffffff; }
        body.dark-mode .import-btn:hover { background-color: #cccccc; }
    </style>
</head>
<body>

<div class="topnav">
    <span class="brand">Smart Wardrobe</span>
    <div class="nav-links">
        <a href="index.php" class="active">Overview</a>
        <a href="kal.php">Kal AI</a>
        <a href="wardrobe.php">Archive</a>
        <a href="create_outfit.php">Outfits</a>
        <a href="code123.php">The Closet</a>
        
        <div class="nav-actions">
            <button id="theme-toggle" class="theme-btn">Dark</button>
            <img src="<?= htmlspecialchars($pfpPath) ?>" alt="Profile" class="nav-pfp">
            <a href="profile.php">Settings</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="dashboard-wrapper">
    <aside class="sidebar">
        <header>
            <h1 class="premium-greeting"><?= $greeting ?>,<br><?= htmlspecialchars(strtoupper($username)) ?>.</h1>
            <p class="premium-subtitle">System Overview</p>
        </header>

        <div class="metrics-vertical">
            <div class="metric-card">
                <span class="metric-num"><?= number_format($totalItems) ?></span>
                <p class="metric-label">Total Items</p>
            </div>
            <div class="metric-card">
                <span class="metric-num"><?= number_format($totalOutfits) ?></span>
                <p class="metric-label">Outfits</p>
            </div>
            <div class="metric-card">
                <span class="metric-num"><?= number_format($totalLaundry) ?></span>
                <p class="metric-label">Laundry</p>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="import-module">
            <h2 class="panel-title" style="border:none; padding:0;">
                <span>Cart Sync</span>
                <span style="color: #999; font-size: 0.75rem;">URL Import</span>
            </h2>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div style="background: #000; color: #fff; padding: 12px; margin-top: 16px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; text-align: center; letter-spacing: 0.05em;"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <div style="background: #fff; color: #000; padding: 12px; margin-top: 16px; font-size: 0.8rem; font-weight: 600; border: 1px solid #000; text-transform: uppercase; text-align: center; letter-spacing: 0.05em;"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <form action="import_url.php" method="POST" class="import-input-wrapper">
                <input type="url" name="url" class="import-input" placeholder="Product URL..." required>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <select name="department" class="import-input" style="cursor: pointer;">
                        <option value="Unisex">Auto-Detect</option>
                        <option value="Menswear">Menswear</option>
                        <option value="Womenswear">Womenswear</option>
                    </select>
                    <input type="text" name="size" class="import-input" placeholder="Size Parameter">
                </div>
                
                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.75rem; color: #666; cursor: pointer; margin: 8px 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">
                    <input type="checkbox" name="auto_outfit" value="1" checked style="accent-color: #000; width: 16px; height: 16px;">
                    Auto-create outfit with Kal
                </label>

                <button type="submit" class="import-btn">Execute Sync</button>
            </form>
        </div>

        <div>
            <h3 class="panel-title">Operations</h3>
            <div class="workflow-grid">
                <a href="wardrobe.php" class="workflow-btn">Upload Items</a>
                <a href="create_outfit.php" class="workflow-btn">Create Outfit</a>
                <a href="code123.php" class="workflow-btn">The Closet</a>
                <a href="laundry.php" class="workflow-btn">Laundry</a>
            </div>
        </div>

        <div>
            <h3 class="panel-title">
                <span>Recent Uploads</span>
                <a href="wardrobe.php" style="font-size: 0.75rem; color: #999;">View Archive -></a>
            </h3>
            <div class="recent-ribbon">
                <?php if (empty($recentItems)): ?>
                    <p style="color: #999; font-size: 0.85rem; width: 100%; text-align: center; padding: 24px; border: 1px solid #e5e5e5; text-transform: uppercase; letter-spacing: 0.1em;">Archive Empty.</p>
                <?php else: ?>
                    <?php foreach ($recentItems as $item): ?>
                        <div class="recent-item-card">
                            <div class="recent-item-img" style="background-image: url('<?= htmlspecialchars($item['image_path']) ?>');"></div>
                            <div class="recent-item-name" title="<?= htmlspecialchars($item['item_name']) ?>">
                                <?= htmlspecialchars($item['item_name']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
    // Strict Light/Dark Mode Engine
    const themeToggle = document.getElementById('theme-toggle');
    const currentTheme = localStorage.getItem('theme') || 'light';

    if (currentTheme === 'dark') {
        document.body.classList.add('dark-mode');
        themeToggle.textContent = 'LIGHT';
    }

    themeToggle.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        if (document.body.classList.contains('dark-mode')) {
            localStorage.setItem('theme', 'dark');
            themeToggle.textContent = 'LIGHT';
        } else {
            localStorage.setItem('theme', 'light');
            themeToggle.textContent = 'DARK';
        }
    });
</script>
</body>
</html>