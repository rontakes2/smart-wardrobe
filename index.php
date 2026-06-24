<?php
include 'includes/session.php';
include 'includes/db.php';

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

if ($role === 'admin') { header("Location: admin/index.php"); exit; }

$userGender = $_SESSION['user_gender'] ?? 'Unspecified';
$themeClass = ($userGender === 'Female') ? 'theme-female' : 'theme-monochrome';

$stmtUser = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
$stmtUser->execute([$userId]);
$userRow = $stmtUser->fetch();

$username = !empty($userRow['username']) ? $userRow['username'] : 'USER';
$pfpPath = !empty($userRow['profile_picture']) ? $userRow['profile_picture'] : 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=000000&color=ffffff';

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* PURE MONOCHROME ROOT */
        :root {
            --bg-main: #fcfcfc;
            --bg-panel: #ffffff;
            --text-main: #000000;
            --text-muted: #666666;
            --border-main: #e5e5e5;
            --accent-solid: #000000;
            --accent-hover: #333333;
            --accent-text: #ffffff;
            --danger: #ef4444;
            --danger-hover: #dc2626;
        }

        /* WOMEN'S THEME (PINK/PURPLE LINES) */
        body.theme-female {
            --bg-main: #fcfcfc;
            --bg-panel: #ffffff;
            --text-main: #1a0011;
            --text-muted: #83526c;
            --border-main: #f3e8ff;
            --accent-solid: #d946ef;
            --accent-hover: #c026d3;
            --accent-text: #ffffff;
        }

        /* STRICT DARK MODE INVERSIONS */
        body.dark-mode {
            --bg-main: #0a0a0a;
            --bg-panel: #111111;
            --text-main: #ffffff;
            --text-muted: #888888;
            --border-main: #333333;
            --accent-solid: #ffffff;
            --accent-hover: #cccccc;
            --accent-text: #000000;
        }

        body.dark-mode.theme-female {
            --bg-main: #120510;
            --bg-panel: #1a0817;
            --text-main: #fce7f3;
            --text-muted: #a16287;
            --border-main: #4a1d3e;
            --accent-solid: #f472b6;
            --accent-hover: #fbcfe8;
            --accent-text: #000000;
        }

        * { box-sizing: border-box; }
        body { background-color: var(--bg-main); color: var(--text-main); margin: 0; padding: 0; font-family: 'Inter', sans-serif; transition: background-color 0.3s, color 0.3s; }
        a { text-decoration: none; color: inherit; }
        button, input, select { font-family: inherit; }
        
        .topnav { background: var(--bg-panel); border-bottom: 1px solid var(--border-main); padding: 12px 32px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; transition: background-color 0.3s, border-color 0.3s; }
        .brand { font-size: 1.1rem; font-weight: 800; letter-spacing: -0.02em; text-transform: uppercase; color: var(--text-main); }
        .nav-links { display: flex; gap: 32px; align-items: center; }
        .nav-links a { font-weight: 600; font-size: 0.85rem; color: var(--text-muted); transition: color 0.2s; text-transform: uppercase; letter-spacing: 0.05em; }
        .nav-links a:hover, .nav-links a.active { color: var(--accent-solid); }
        
        .nav-actions { display: flex; align-items: center; gap: 16px; margin-left: 16px; padding-left: 24px; border-left: 1px solid var(--border-main); }
        .nav-pfp { width: 28px; height: 28px; object-fit: cover; border: 2px solid var(--accent-solid); border-radius: 50%; transition: transform 0.2s; }
        .nav-pfp:hover { transform: scale(1.1); }
        
        .theme-btn { background: transparent; border: none; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); cursor: pointer; text-transform: uppercase; letter-spacing: 0.05em; transition: color 0.2s; }
        .theme-btn:hover { color: var(--accent-solid); }

        .dashboard-wrapper { display: grid; grid-template-columns: 280px 1fr; gap: 40px; max-width: 1400px; margin: 40px auto; padding: 0 32px; }
        .premium-greeting { font-size: 1.8rem; font-weight: 800; letter-spacing: -0.02em; line-height: 1.1; margin: 0 0 8px 0; text-transform: uppercase; color: var(--text-main); }
        .premium-subtitle { font-size: 0.75rem; color: var(--accent-solid); font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; margin: 0; }
        
        .metrics-vertical { display: flex; flex-direction: column; gap: 16px; margin-top: 32px; }
        .metric-card { background: var(--bg-panel); border: 1px solid var(--border-main); border-left: 4px solid var(--accent-solid); padding: 24px 20px; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); }
        .metric-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); border-color: var(--accent-solid); }
        .metric-num { font-size: 2.2rem; font-weight: 800; color: var(--text-main); line-height: 1; letter-spacing: -0.02em; margin-bottom: 8px; }
        .metric-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); font-weight: 700; margin: 0; }
        
        .panel-title { font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin: 0 0 16px 0; border-bottom: 2px solid var(--border-main); padding-bottom: 8px; text-transform: uppercase; letter-spacing: 0.1em; display: flex; justify-content: space-between; align-items: flex-end; }
        
        .import-module { background: var(--bg-panel); border: 1px solid var(--border-main); padding: 32px; margin-bottom: 40px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); border-radius: 0; }
        .import-input-wrapper { display: flex; flex-direction: column; gap: 16px; margin-top: 24px; }
        .import-input { width: 100%; padding: 14px 16px; background: var(--bg-main); border: 1px solid var(--border-main); color: var(--text-main); font-size: 0.85rem; outline: none; transition: border-color 0.2s; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; border-radius: 0; }
        .import-input:focus { border-color: var(--accent-solid); }
        
        .import-btn { background: var(--accent-solid); color: var(--accent-text); border: 1px solid var(--accent-solid); padding: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; border-radius: 0; }
        .import-btn:hover { background: var(--bg-main); color: var(--accent-solid); }
        
        .workflow-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 40px; }
        .workflow-btn { background: var(--bg-panel); border: 1px solid var(--border-main); padding: 24px; color: var(--text-main); font-weight: 700; font-size: 0.75rem; text-align: center; text-transform: uppercase; letter-spacing: 0.1em; transition: all 0.2s; border-radius: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .workflow-btn i { font-size: 1.8rem; margin-bottom: 16px; display: block; color: var(--accent-solid); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        .workflow-btn:hover { border-color: var(--accent-solid); transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); }
        .workflow-btn:hover i { transform: scale(1.15); }
        
        .recent-ribbon { display: flex; gap: 20px; overflow-x: auto; padding-bottom: 24px; }
        .recent-item-card { flex: 0 0 180px; border: 1px solid var(--border-main); background: var(--bg-panel); transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); cursor: pointer; border-radius: 0; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .recent-item-card:hover { border-color: var(--accent-solid); transform: translateY(-4px); box-shadow: 0 12px 20px rgba(0,0,0,0.08); }
        .recent-item-img { height: 200px; width: 100%; background-size: contain; background-position: center; background-repeat: no-repeat; filter: grayscale(100%) opacity(0.8); transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .recent-item-card:hover .recent-item-img { filter: grayscale(0%) opacity(1); transform: scale(1.12); }
        .recent-item-name { padding: 16px; font-size: 0.75rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: center; border-top: 1px solid var(--border-main); text-transform: uppercase; letter-spacing: 0.05em; background: var(--bg-panel); position: relative; z-index: 2; }

        .msg-inline { padding: 12px 40px; margin-top: 16px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; text-align: center; letter-spacing: 0.05em; position: relative; border-radius: 0; border: 1px solid var(--text-main); }
        .msg-inline-error { background: var(--text-main); color: var(--bg-main); }
        .msg-inline-success { background: var(--bg-panel); color: var(--text-main); }
        .close-msg { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--danger); font-size: 1.2rem; line-height: 1; transition: opacity 0.2s; font-weight: bold; opacity: 0.7; }
        .close-msg:hover { opacity: 1; color: var(--danger-hover); }
    </style>
</head>
<body class="<?= $themeClass ?>">

<div class="topnav">
    <span class="brand">Smart Wardrobe <?php if($themeClass === 'theme-female'): ?><sub style="font-size: 0.6rem; color: var(--accent-solid);">Women's</sub><?php endif; ?></span>
    <div class="nav-links">
        <a href="index.php" class="active">Overview</a>
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
            <h1 class="premium-greeting"><?= $greeting ?>,<br><span style="color: var(--accent-solid);"><?= htmlspecialchars(strtoupper($username)) ?>.</span></h1>
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
                <span style="color: var(--accent-solid); font-size: 0.85rem;"><i class="fas fa-bolt"></i> URL Import</span>
            </h2>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="msg-inline msg-inline-error">
                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    <span class="close-msg" onclick="this.parentElement.style.display='none';">&times;</span>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="msg-inline msg-inline-success">
                    <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                    <span class="close-msg" onclick="this.parentElement.style.display='none';">&times;</span>
                </div>
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
                
                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.75rem; color: var(--text-muted); cursor: pointer; margin: 8px 0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                    <input type="checkbox" name="auto_outfit" value="1" checked style="accent-color: var(--accent-solid); width: 16px; height: 16px;">
                    Auto-create outfit with AI
                </label>

                <button type="submit" class="import-btn">Execute Sync</button>
            </form>
        </div>

        <div>
            <h3 class="panel-title">Operations</h3>
            <div class="workflow-grid">
                <a href="wardrobe.php" class="workflow-btn"><i class="fas fa-upload"></i> Upload Items</a>
                <a href="create_outfit.php" class="workflow-btn"><i class="fas fa-layer-group"></i> Create Outfit</a>
                <a href="code123.php" class="workflow-btn"><i class="fas fa-door-open"></i> The Closet</a>
                <a href="laundry.php" class="workflow-btn"><i class="fas fa-clipboard-check"></i> Laundry</a>
            </div>
        </div>

        <div>
            <h3 class="panel-title">
                <span>Recent Uploads</span>
                <a href="wardrobe.php" style="font-size: 0.75rem; color: var(--accent-solid); transition: opacity 0.2s;">View Archive <i class="fas fa-arrow-right"></i></a>
            </h3>
            <div class="recent-ribbon">
                <?php if (empty($recentItems)): ?>
                    <p style="color: var(--text-muted); font-size: 0.85rem; width: 100%; text-align: center; padding: 24px; border: 1px solid var(--border-main); text-transform: uppercase; letter-spacing: 0.1em;">Archive Empty.</p>
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