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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $itemId = (int)$_POST['item_id'];
    $stmt = $pdo->prepare("SELECT item_name, image_path, image_source FROM clothing_items WHERE item_id = ? AND user_id = ?");
    $stmt->execute([$itemId, $userId]);
    $itemToDel = $stmt->fetch();
    if ($itemToDel) {
        if ($itemToDel['image_source'] === 'local' && file_exists($itemToDel['image_path'])) { unlink($itemToDel['image_path']); }
        $pdo->prepare("DELETE FROM clothing_items WHERE item_id = ?")->execute([$itemId]);
        $success = 'Item deleted.';
    } else { $error = 'Item not found.'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_cv_upload'])) {
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        $uploadedItemIds = [];
        $stmtCats = $pdo->query("SELECT category_id, category_name FROM categories");
        $dbCats = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
        $adjectives = ['Structured', 'Minimalist', 'Oversized', 'Tailored', 'Essential'];
        $pdo->beginTransaction();
        try {
            foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $fileExt = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
                    if (in_array($fileExt, $allowedExts)) {
                        $newFileName = uniqid('cv_', true) . '.' . $fileExt;
                        $destination = $uploadDir . $newFileName;
                        if (move_uploaded_file($tmpName, $destination)) {
                            $assignedCat = $dbCats[array_rand($dbCats)];
                            $syntheticName = strtoupper($adjectives[array_rand($adjectives)] . ' ' . $assignedCat['category_name']);
                            $stmt = $pdo->prepare("INSERT INTO clothing_items (user_id, category_id, item_name, image_path, image_source, status) VALUES (?, ?, ?, ?, 'local', 'clean')");
                            $stmt->execute([$userId, $assignedCat['category_id'], $syntheticName, $destination]);
                            $uploadedItemIds[] = $pdo->lastInsertId();
                        }
                    }
                }
            }
            if (count($uploadedItemIds) > 1) {
                $outfitName = "Auto Outfit [" . date('His') . "]";
                $outfitStmt = $pdo->prepare("INSERT INTO outfits (user_id, outfit_name, occasion) VALUES (?, ?, 'Auto-Group')");
                $outfitStmt->execute([$userId, $outfitName]);
                $newOutfitId = $pdo->lastInsertId();
                $insertItemStmt = $pdo->prepare("INSERT INTO outfit_items (outfit_id, item_id) VALUES (?, ?)");
                foreach ($uploadedItemIds as $rItemId) { $insertItemStmt->execute([$newOutfitId, $rItemId]); }
                $success = count($uploadedItemIds) . " items uploaded and grouped into outfit: " . $outfitName;
            } elseif (count($uploadedItemIds) === 1) { $success = "1 item uploaded."; } 
            else { $error = "No valid items were processed."; }
            $pdo->commit();
        } catch (Exception $e) { $pdo->rollBack(); $error = 'Error during upload processing.'; }
    } else { $error = 'No files provided.'; }
}

$stmt = $pdo->prepare("SELECT c.*, cat.category_name FROM clothing_items c JOIN categories cat ON c.category_id = cat.category_id WHERE c.user_id = ? ORDER BY c.created_at DESC");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Wardrobe - Smart Wardrobe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { background-color: #fcfcfc; color: #000000; margin: 0; padding: 0; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; flex-direction: column; transition: background-color 0.3s, color 0.3s; }
        a { text-decoration: none; color: inherit; }
        button, input, select { font-family: inherit; }

        .topnav { background: #ffffff; border-bottom: 1px solid #e5e5e5; padding: 12px 32px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; transition: background-color 0.3s, border-color 0.3s; }
        .brand { font-size: 1.1rem; font-weight: 700; letter-spacing: -0.02em; text-transform: uppercase; color: #000; }
        .nav-links { display: flex; gap: 32px; align-items: center; }
        .nav-links a { font-weight: 500; font-size: 0.85rem; color: #666; transition: color 0.2s; text-transform: uppercase; letter-spacing: 0.05em; }
        .nav-links a:hover, .nav-links a.active { color: #000; font-weight: 700; }
        .nav-actions { display: flex; align-items: center; gap: 16px; margin-left: 16px; padding-left: 24px; border-left: 1px solid #e5e5e5; }
        .nav-pfp { width: 28px; height: 28px; object-fit: cover; border: 1px solid #000; border-radius: 50%; }
        .theme-btn { background: transparent; border: none; font-size: 0.85rem; font-weight: 700; color: #666; cursor: pointer; text-transform: uppercase; letter-spacing: 0.05em; transition: color 0.2s; }
        .theme-btn:hover { color: #000; }

        .control-panel { background: #ffffff; border-bottom: 1px solid #e5e5e5; padding: 24px 32px; display: flex; flex-direction: column; align-items: center; gap: 16px; position: relative; z-index: 90; transition: background-color 0.3s, border-color 0.3s; }
        .engine-title { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #000; margin: 0; }
        .engine-sub { font-size: 0.75rem; color: #666; text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }
        
        .cv-form { display: flex; gap: 12px; align-items: center; margin: 0; background: #fcfcfc; padding: 12px; border: 1px solid #e5e5e5; }
        .clean-file { font-size: 0.8rem; outline: none; background: #fff; cursor: pointer; color: #666; }
        .solid-btn { background: #000; color: #fff; border: 1px solid #000; padding: 10px 24px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; border-radius: 0; }
        .solid-btn:hover { background: #fff; color: #000; }

        .msg-bar { text-align: center; padding: 12px; font-size: 0.8rem; font-weight: 600; margin: 0; text-transform: uppercase; letter-spacing: 0.05em; }
        .msg-success { background: #fff; color: #000; border-bottom: 1px solid #000; }
        .msg-error { background: #000; color: #fff; }

        .archive-stage { flex-grow: 1; width: 100%; max-width: 1400px; margin: 0 auto; padding: 40px 32px; }
        .wardrobe-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 24px; }
        .wardrobe-card { border: 1px solid #e5e5e5; background: #fff; display: flex; flex-direction: column; transition: border-color 0.2s, background-color 0.3s; }
        .wardrobe-card:hover { border-color: #000; }
        
        .wardrobe-img-wrapper { aspect-ratio: 3/4; overflow: hidden; border-bottom: 1px solid #e5e5e5; background: #fcfcfc; display: flex; justify-content: center; align-items: center; padding: 16px; }
        .wardrobe-img { width: 100%; height: 100%; object-fit: contain; filter: grayscale(100%); transition: filter 0.3s ease, transform 0.3s ease; }
        .wardrobe-card:hover .wardrobe-img { filter: grayscale(0%); transform: scale(1.05); }
        .unavailable-item .wardrobe-img { filter: grayscale(100%) opacity(0.3) !important; }
        
        .wardrobe-info { padding: 16px; display: flex; flex-direction: column; gap: 8px; }
        .item-cat { font-size: 0.65rem; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.15em; }
        .item-name { font-size: 0.85rem; font-weight: 600; color: #000; text-transform: uppercase; letter-spacing: 0.02em; line-height: 1.3; }
        
        .delete-form { margin-top: auto; padding-top: 16px; border-top: 1px dashed #e5e5e5; }
        .del-btn { background: transparent; border: none; color: #999; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; padding: 0; font-weight: 600; transition: color 0.2s; }
        .del-btn:hover { color: #000; }

        /* STRICT DARK MODE */
        body.dark-mode { background-color: #0a0a0a; color: #ffffff; }
        body.dark-mode .topnav, body.dark-mode .control-panel, body.dark-mode .wardrobe-card { background-color: #0a0a0a; border-color: #333333; color: #ffffff; }
        body.dark-mode .brand, body.dark-mode .engine-title, body.dark-mode .theme-btn:hover, body.dark-mode .topnav a:hover, body.dark-mode .topnav a.active, body.dark-mode .item-name { color: #ffffff; }
        body.dark-mode .topnav a, body.dark-mode .theme-btn, body.dark-mode .engine-sub, body.dark-mode .item-cat, body.dark-mode .del-btn { color: #888888; }
        body.dark-mode .nav-actions, body.dark-mode .nav-pfp, body.dark-mode .wardrobe-img-wrapper, body.dark-mode .delete-form { border-color: #333333; }
        body.dark-mode .solid-btn { background-color: #ffffff; color: #000000; border-color: #ffffff; }
        body.dark-mode .solid-btn:hover { background-color: #cccccc; }
    </style>
</head>
<body>

<div class="topnav">
    <span class="brand">Smart Wardrobe</span>
    <div class="nav-links">
        <a href="index.php">Overview</a>
        <a href="kal.php">Kal AI</a>
        <a href="wardrobe.php" class="active">Archive</a>
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

<div class="control-panel">
    <p class="engine-title">Bulk Upload Items</p>
    <p class="engine-sub">Select multiple images. They will be auto-categorized and grouped into an outfit.</p>
    
    <form method="POST" enctype="multipart/form-data" class="cv-form">
        <input type="file" name="images[]" accept="image/*" multiple required class="clean-file">
        <button type="submit" name="batch_cv_upload" class="solid-btn">Upload Items</button>
    </form>
</div>

<?php if ($success): ?><div class="msg-bar msg-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="msg-bar msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="archive-stage">
    <div class="wardrobe-grid">
        <?php if (empty($items)): ?>
            <p style="grid-column: 1 / -1; text-align: center; color: #999; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em;">Wardrobe is empty. Upload items above.</p>
        <?php else: ?>
            <?php foreach ($items as $item): 
                $isAway = ($item['status'] !== 'clean' && !empty($item['status']));
                $cardClass = $isAway ? 'unavailable-item' : '';
            ?>
                <div class="wardrobe-card <?= $cardClass ?>">
                    <div class="wardrobe-img-wrapper">
                        <img src="<?= htmlspecialchars($item['image_path']) ?>" class="wardrobe-img" alt="Item">
                    </div>
                    <div class="wardrobe-info">
                        <span class="item-cat"><?= htmlspecialchars($item['category_name']) ?></span>
                        <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                        
                        <form method="POST" onsubmit="return confirm('Delete this item?');" class="delete-form">
                            <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                            <button type="submit" name="delete_item" class="del-btn">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
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