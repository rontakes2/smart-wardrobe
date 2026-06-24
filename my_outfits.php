<?php
include 'includes/session.php';
include 'includes/db.php';

$error = '';
$success = '';
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'USER';
$stmtUser = $pdo->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
$stmtUser->execute([$userId]);
$userRow = $stmtUser->fetch();
$pfpPath = !empty($userRow['profile_picture']) ? $userRow['profile_picture'] : 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=000000&color=ffffff';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_outfit'])) {
    $outfitId = (int)$_POST['outfit_id'];
    $verifyStmt = $pdo->prepare("SELECT outfit_name FROM outfits WHERE outfit_id = ? AND user_id = ?");
    $verifyStmt->execute([$outfitId, $userId]);
    $outfit = $verifyStmt->fetch();

    if ($outfit) {
        $deleteStmt = $pdo->prepare("DELETE FROM outfits WHERE outfit_id = ?");
        if ($deleteStmt->execute([$outfitId])) {
            $success = 'Outfit deleted successfully.';
        } else { $error = 'Failed to delete outfit.'; }
    } else { $error = 'Outfit not found.'; }
}

$outfitsStmt = $pdo->prepare("SELECT * FROM outfits WHERE user_id = ? ORDER BY created_at DESC");
$outfitsStmt->execute([$userId]);
$outfits = $outfitsStmt->fetchAll();

$outfitItems = [];
if (!empty($outfits)) {
    $itemsStmt = $pdo->prepare("SELECT oi.outfit_id, c.item_name, c.image_path, cat.category_name FROM outfit_items oi JOIN clothing_items c ON oi.item_id = c.item_id JOIN categories cat ON c.category_id = cat.category_id WHERE c.user_id = ?");
    $itemsStmt->execute([$userId]);
    $allLinkedItems = $itemsStmt->fetchAll();
    foreach ($allLinkedItems as $item) { $outfitItems[$item['outfit_id']][] = $item; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Outfits - Smart Wardrobe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { background-color: #fcfcfc; color: #000000; margin: 0; padding: 0; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; flex-direction: column; transition: background-color 0.3s, color 0.3s; }
        a { text-decoration: none; color: inherit; }
        button { font-family: inherit; }

        .topnav { background: #ffffff; border-bottom: 1px solid #e5e5e5; padding: 12px 32px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; transition: background-color 0.3s, border-color 0.3s; }
        .brand { font-size: 1.1rem; font-weight: 700; letter-spacing: -0.02em; text-transform: uppercase; color: #000; }
        .nav-links { display: flex; gap: 32px; align-items: center; }
        .nav-links a { font-weight: 500; font-size: 0.85rem; color: #666; transition: color 0.2s; text-transform: uppercase; letter-spacing: 0.05em; }
        .nav-links a:hover, .nav-links a.active { color: #000; font-weight: 700; }
        .nav-actions { display: flex; align-items: center; gap: 16px; margin-left: 16px; padding-left: 24px; border-left: 1px solid #e5e5e5; }
        .nav-pfp { width: 28px; height: 28px; object-fit: cover; border: 1px solid #000; border-radius: 50%; }
        .theme-btn { background: transparent; border: none; font-size: 0.85rem; font-weight: 700; color: #666; cursor: pointer; text-transform: uppercase; letter-spacing: 0.05em; transition: color 0.2s; }
        .theme-btn:hover { color: #000; }

        .control-panel { background: #ffffff; border-bottom: 1px solid #e5e5e5; padding: 24px 32px; display: flex; flex-direction: column; align-items: center; gap: 16px; transition: background-color 0.3s, border-color 0.3s; }
        .engine-title { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #000; margin: 0; }
        
        .msg-bar { text-align: center; padding: 12px; font-size: 0.8rem; font-weight: 600; margin: 0; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e5e5e5; }
        .msg-success { background: #fff; color: #000; }
        .msg-error { background: #000; color: #fff; }

        .stage { flex-grow: 1; width: 100%; max-width: 1400px; margin: 0 auto; padding: 40px 32px; }
        .outfit-card { border: 1px solid #e5e5e5; background: #fff; padding: 32px; margin-bottom: 32px; transition: border-color 0.2s, background-color 0.3s; }
        .outfit-card:hover { border-color: #000; }
        
        .outfit-header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid #000; padding-bottom: 16px; margin-bottom: 24px; transition: border-color 0.3s; }
        .outfit-title { font-size: 1.25rem; font-weight: 700; color: #000; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 8px 0; }
        .outfit-occ { font-size: 0.7rem; color: #666; text-transform: uppercase; letter-spacing: 0.15em; font-weight: 600; margin: 0; }
        
        .outfit-actions { display: flex; gap: 16px; align-items: center; }
        .action-link { font-size: 0.75rem; font-weight: 700; color: #000; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 1px solid transparent; transition: border-color 0.2s; }
        .action-link:hover { border-color: #000; }
        .del-btn { background: transparent; border: none; color: #999; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; padding: 0; transition: color 0.2s; }
        .del-btn:hover { color: #000; }

        .mini-grid { display: flex; gap: 24px; overflow-x: auto; padding-bottom: 16px; }
        .mini-item { flex: 0 0 140px; display: flex; flex-direction: column; align-items: center; }
        .mini-img { width: 100%; aspect-ratio: 3/4; background-size: contain; background-position: center; background-repeat: no-repeat; filter: grayscale(100%); transition: filter 0.3s, transform 0.3s; margin-bottom: 12px; }
        .outfit-card:hover .mini-img { filter: grayscale(0%); }
        .mini-img:hover { transform: scale(1.05); }
        .mini-label { font-size: 0.7rem; font-weight: 600; color: #000; text-transform: uppercase; letter-spacing: 0.05em; text-align: center; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border-top: 1px solid #e5e5e5; padding-top: 8px; transition: border-color 0.3s; }

        /* STRICT DARK MODE */
        body.dark-mode { background-color: #0a0a0a; color: #ffffff; }
        body.dark-mode .topnav, body.dark-mode .control-panel, body.dark-mode .outfit-card { background-color: #0a0a0a; border-color: #333333; color: #ffffff; }
        body.dark-mode .brand, body.dark-mode .engine-title, body.dark-mode .theme-btn:hover, body.dark-mode .topnav a:hover, body.dark-mode .topnav a.active, body.dark-mode .outfit-title, body.dark-mode .mini-label, body.dark-mode .action-link { color: #ffffff; }
        body.dark-mode .topnav a, body.dark-mode .theme-btn, body.dark-mode .outfit-occ, body.dark-mode .del-btn { color: #888888; }
        body.dark-mode .nav-actions, body.dark-mode .nav-pfp, body.dark-mode .outfit-header, body.dark-mode .mini-label { border-color: #333333; }
        body.dark-mode .action-link:hover { border-color: #ffffff; }
    </style>
</head>
<body>

<div class="topnav">
    <span class="brand">Smart Wardrobe</span>
    <div class="nav-links">
        <a href="index.php">Overview</a>
        <a href="kal.php">Kal AI</a>
        <a href="wardrobe.php">Archive</a>
        <a href="create_outfit.php" class="active">Outfits</a>
        <a href="code123.php">The Closet</a>
        
        <div class="nav-actions">
            <button id="theme-toggle" class="theme-btn">Dark</button>
            <img src="<?= htmlspecialchars($pfpPath) ?>" alt="Profile" class="nav-pfp">
            <a href="profile.php">Settings</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="control-panel">
    <p class="engine-title"><i class="fas fa-layer-group"></i> My Outfits</p>
</div>

<?php if ($success): ?><div class="msg-bar msg-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="msg-bar msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="stage">
    <?php if (empty($outfits)): ?>
        <p style="text-align: center; color: #999; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em;">No outfits created yet.</p>
    <?php else: ?>
        <?php foreach ($outfits as $outfit): ?>
            <div class="outfit-card">
                <div class="outfit-header">
                    <div>
                        <h3 class="outfit-title"><?= htmlspecialchars($outfit['outfit_name']) ?></h3>
                        <p class="outfit-occ">CATEGORY: <?= htmlspecialchars($outfit['occasion'] ?: 'STANDARD') ?></p>
                    </div>
                    <div class="outfit-actions">
                        <a href="edit_outfits.php?id=<?= $outfit['outfit_id'] ?>" class="action-link">Edit Outfit</a>
                        <form method="POST" onsubmit="return confirm('Delete this outfit?');" style="margin:0;">
                            <input type="hidden" name="outfit_id" value="<?= $outfit['outfit_id'] ?>">
                            <button type="submit" name="delete_outfit" class="del-btn">Delete</button>
                        </form>
                    </div>
                </div>
                
                <div class="mini-grid">
                    <?php if (isset($outfitItems[$outfit['outfit_id']])): ?>
                        <?php foreach ($outfitItems[$outfit['outfit_id']] as $item): ?>
                            <div class="mini-item">
                                <div class="mini-img" style="background-image: url('<?= htmlspecialchars($item['image_path']) ?>');"></div>
                                <div class="mini-label" title="<?= htmlspecialchars($item['item_name']) ?>"><?= htmlspecialchars($item['item_name']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="font-size: 0.75rem; color: #999; text-transform: uppercase;">No items assigned.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    const themeToggle = document.getElementById('theme-toggle');
    const currentTheme = localStorage.getItem('theme') || 'light';
    if (currentTheme === 'dark') {
        document.body.classList.add('dark-mode');
        if(themeToggle) themeToggle.textContent = 'LIGHT';
    }
    if(themeToggle) {
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
    }
</script>
</body>
</html>