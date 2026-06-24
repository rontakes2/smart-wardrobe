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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cv_compile_matrix'])) {
    $outfitName = trim($_POST['cv_outfit_name'] ?? '');
    if (empty($outfitName)) $outfitName = 'Auto Outfit [' . date('His') . ']';

    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
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
            if (count($uploadedItemIds) > 0) {
                $outfitStmt = $pdo->prepare("INSERT INTO outfits (user_id, outfit_name, occasion) VALUES (?, ?, 'Auto-Created')");
                $outfitStmt->execute([$userId, strtoupper($outfitName)]);
                $newOutfitId = $pdo->lastInsertId();
                $insertItemStmt = $pdo->prepare("INSERT INTO outfit_items (outfit_id, item_id) VALUES (?, ?)");
                foreach ($uploadedItemIds as $rItemId) { $insertItemStmt->execute([$newOutfitId, $rItemId]); }
                $success = count($uploadedItemIds) . " items uploaded and saved as outfit: " . strtoupper($outfitName);
            } else { $error = "No valid images processed."; }
            $pdo->commit();
        } catch (Exception $e) { $pdo->rollBack(); $error = 'Error occurred during upload and creation.'; }
    } else { $error = 'Please select images to upload.'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_create_outfit'])) {
    $outfitName = trim($_POST['outfit_name'] ?? '');
    $selectedItems = $_POST['items'] ?? [];
    if ($outfitName === '' || empty($selectedItems)) { $error = 'Please provide an outfit name and select items.'; } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO outfits (user_id, outfit_name, occasion) VALUES (?, ?, 'Custom Outfit')");
            $stmt->execute([$userId, strtoupper($outfitName)]);
            $outfitId = $pdo->lastInsertId();
            $insertItemStmt = $pdo->prepare("INSERT INTO outfit_items (outfit_id, item_id) VALUES (?, ?)");
            foreach ($selectedItems as $itemId) { $insertItemStmt->execute([$outfitId, (int)$itemId]); }
            $pdo->commit();
            $success = 'Outfit saved successfully.';
        } catch (Exception $e) { $pdo->rollBack(); $error = 'Failed to save outfit.'; }
    }
}

$stmt = $pdo->prepare("SELECT c.*, cat.category_name FROM clothing_items c JOIN categories cat ON c.category_id = cat.category_id WHERE c.user_id = ? ORDER BY cat.category_name, c.created_at DESC");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();
$groupedItems = [];
foreach ($items as $item) { $groupedItems[$item['category_name']][] = $item; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Outfit - Smart Wardrobe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .control-panel { background: #ffffff; border-bottom: 1px solid #e5e5e5; padding: 24px 32px; display: flex; flex-direction: column; align-items: center; gap: 16px; transition: background-color 0.3s, border-color 0.3s; }
        .engine-title { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #000; margin: 0; }
        .engine-sub { font-size: 0.75rem; color: #666; text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }

        .cv-form { display: flex; gap: 12px; align-items: center; margin: 0; background: #fcfcfc; padding: 12px; border: 1px solid #e5e5e5; }
        .clean-input { padding: 10px 16px; border: 1px solid #ccc; font-size: 0.8rem; outline: none; background: #fff; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; width: 250px; border-radius: 0; transition: border-color 0.2s, background-color 0.3s; }
        .clean-input:focus { border-color: #000; }
        .clean-file { font-size: 0.8rem; outline: none; background: #fff; cursor: pointer; color: #666; }
        .solid-btn { background: #000; color: #fff; border: 1px solid #000; padding: 10px 24px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.1em; border-radius: 0; }
        .solid-btn:hover { background: #fff; color: #000; }

        .msg-bar { text-align: center; padding: 12px; font-size: 0.8rem; font-weight: 600; margin: 0; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e5e5e5; }
        .msg-success { background: #fff; color: #000; }
        .msg-error { background: #000; color: #fff; }

        .stage { flex-grow: 1; width: 100%; max-width: 1400px; margin: 0 auto; padding: 40px 32px; }
        .category-section { margin-bottom: 48px; }
        .category-title { font-size: 0.75rem; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.15em; margin: 0 0 16px 0; border-bottom: 1px solid #e5e5e5; padding-bottom: 8px; }

        .selectable-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; }
        .selectable-item { display: block; position: relative; cursor: pointer; border: 1px solid #e5e5e5; background: #fff; transition: border-color 0.2s; aspect-ratio: 3/4; overflow: hidden; }
        .selectable-item input { display: none; }
        .img-bg { width: 100%; height: 100%; background-size: contain; background-position: center; background-repeat: no-repeat; filter: grayscale(100%); transition: all 0.2s ease; }
        .selectable-item:hover .img-bg { filter: grayscale(30%); transform: scale(1.02); }
        .selectable-item input:checked ~ .img-bg { filter: grayscale(0%); transform: scale(1.05); }
        .selectable-item input:checked ~ .selection-overlay { opacity: 1; }
        .selection-overlay { position: absolute; inset: 0; border: 4px solid #000; opacity: 0; transition: opacity 0.2s; pointer-events: none; }
        .checkmark { position: absolute; top: 0; right: 0; background: #000; color: #fff; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; }
        .item-label { position: absolute; bottom: 0; left: 0; right: 0; background: #fff; padding: 12px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; border-top: 1px solid #e5e5e5; text-align: center; letter-spacing: 0.05em; }
        .selectable-item input:checked ~ .item-label { background: #000; color: #fff; border-top-color: #000; }
        
        .manual-compile-bar { display: flex; justify-content: center; gap: 16px; padding: 24px; background: #fff; border-top: 1px solid #e5e5e5; position: sticky; bottom: 0; z-index: 90; transition: background-color 0.3s, border-color 0.3s; }

        /* STRICT DARK MODE */
        body.dark-mode { background-color: #0a0a0a; color: #ffffff; }
        body.dark-mode .topnav, body.dark-mode .control-panel, body.dark-mode .selectable-item, body.dark-mode .item-label, body.dark-mode .manual-compile-bar { background-color: #0a0a0a; border-color: #333333; color: #ffffff; }
        body.dark-mode .brand, body.dark-mode .engine-title, body.dark-mode .theme-btn:hover, body.dark-mode .topnav a:hover, body.dark-mode .topnav a.active { color: #ffffff; }
        body.dark-mode .topnav a, body.dark-mode .theme-btn, body.dark-mode .engine-sub { color: #888888; }
        body.dark-mode .nav-actions, body.dark-mode .nav-pfp, body.dark-mode .category-title { border-color: #333333; }
        body.dark-mode .clean-input { background-color: #111111; border-color: #333333; color: #ffffff; }
        body.dark-mode .clean-input:focus { border-color: #ffffff; }
        body.dark-mode .solid-btn { background-color: #ffffff; color: #000000; border-color: #ffffff; }
        body.dark-mode .solid-btn:hover { background-color: #cccccc; }
        body.dark-mode .selection-overlay { border-color: #ffffff; }
        body.dark-mode .checkmark { background: #ffffff; color: #000000; }
        body.dark-mode .selectable-item input:checked ~ .item-label { background: #ffffff; color: #000000; border-top-color: #ffffff; }
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
    <p class="engine-title"><i class="fas fa-upload"></i> Bulk Upload & Auto-Create Outfit</p>
    <p class="engine-sub">Select multiple images to automatically group them into a new outfit.</p>
    
    <form method="POST" enctype="multipart/form-data" class="cv-form">
        <input type="text" name="cv_outfit_name" placeholder="Outfit Name (Optional)" class="clean-input">
        <input type="file" name="images[]" accept="image/*" multiple required class="clean-file">
        <button type="submit" name="cv_compile_matrix" class="solid-btn">Upload & Create</button>
    </form>
</div>

<?php if ($success): ?><div class="msg-bar msg-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="msg-bar msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST">
    <div class="stage">
        <?php if (empty($groupedItems)): ?>
            <p style="text-align: center; color: #999; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; margin-top: 40px;">Wardrobe is empty. Please upload items first.</p>
        <?php else: ?>
            <p class="engine-title" style="margin-bottom: 24px;">OR CREATE OUTFIT MANUALLY</p>
            <?php foreach ($groupedItems as $category => $categoryItems): ?>
                <div class="category-section">
                    <h3 class="category-title"><?= htmlspecialchars($category) ?></h3>
                    <div class="selectable-grid">
                        <?php foreach ($categoryItems as $item): ?>
                            <label class="selectable-item">
                                <input type="checkbox" name="items[]" value="<?= $item['item_id'] ?>">
                                <div class="img-bg" style="background-image: url('<?= htmlspecialchars($item['image_path']) ?>');"></div>
                                <div class="selection-overlay"><span class="checkmark"><i class="fas fa-check"></i></span></div>
                                <span class="item-label"><?= htmlspecialchars($item['item_name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($groupedItems)): ?>
    <div class="manual-compile-bar">
        <input type="text" name="outfit_name" placeholder="Outfit Name" class="clean-input" required>
        <button type="submit" name="manual_create_outfit" class="solid-btn">Save Outfit</button>
    </div>
    <?php endif; ?>
</form>

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