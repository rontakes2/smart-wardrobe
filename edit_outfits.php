<?php
include 'includes/session.php';
include 'includes/db.php';

$error = '';
$success = '';
$userId = $_SESSION['user_id'];

if (!isset($_GET['id']) || empty($_GET['id'])) { header("Location: my_outfits.php"); exit; }
$outfitId = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM outfits WHERE outfit_id = ? AND user_id = ?");
$stmt->execute([$outfitId, $userId]);
$outfit = $stmt->fetch();
if (!$outfit) { die("Matrix not found."); }

// --- BATCH CV APPEND ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cv_append_matrix'])) {
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        
        $stmtCats = $pdo->query("SELECT category_id, category_name FROM categories");
        $dbCats = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
        $adjectives = ['Structured', 'Minimalist', 'Oversized', 'Tailored', 'Essential'];
        $appendedCount = 0;

        $pdo->beginTransaction();
        try {
            $insertItemStmt = $pdo->prepare("INSERT INTO outfit_items (outfit_id, item_id) VALUES (?, ?)");
            foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $fileExt = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
                    if (in_array($fileExt, $allowedExts)) {
                        $newFileName = uniqid('cv_', true) . '.' . $fileExt;
                        $destination = $uploadDir . $newFileName;
                        
                        if (move_uploaded_file($tmpName, $destination)) {
                            $assignedCat = $dbCats[array_rand($dbCats)];
                            $syntheticName = strtoupper($adjectives[array_rand($adjectives)] . ' ' . $assignedCat['category_name']);
                            
                            $stmt = $pdo->prepare("INSERT INTO clothing_items (user_id, category_id, item_name, image_path, image_source, status) VALUES (?, ?, ?, ?, 'cv_batch', 'clean')");
                            $stmt->execute([$userId, $assignedCat['category_id'], $syntheticName, $destination]);
                            
                            // Append to outfit
                            $insertItemStmt->execute([$outfitId, $pdo->lastInsertId()]);
                            $appendedCount++;
                        }
                    }
                }
            }
            $pdo->commit();
            $success = "$appendedCount assets ingested via CV and appended to matrix.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'CV Engine failure during append.';
        }
    }
}

// --- MANUAL OUTFIT RECONFIGURATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_outfit'])) {
    $outfitName = trim($_POST['outfit_name'] ?? '');
    $selectedItems = $_POST['items'] ?? [];

    if ($outfitName === '' || empty($selectedItems)) {
        $error = 'Provide a designation and select assets.';
    } else {
        try {
            $pdo->beginTransaction();
            $updateStmt = $pdo->prepare("UPDATE outfits SET outfit_name = ? WHERE outfit_id = ?");
            $updateStmt->execute([strtoupper($outfitName), $outfitId]);

            $delStmt = $pdo->prepare("DELETE FROM outfit_items WHERE outfit_id = ?");
            $delStmt->execute([$outfitId]);

            $insertItemStmt = $pdo->prepare("INSERT INTO outfit_items (outfit_id, item_id) VALUES (?, ?)");
            foreach ($selectedItems as $itemId) { $insertItemStmt->execute([$outfitId, (int)$itemId]); }
            
            $pdo->commit();
            $success = 'Matrix reconfigured.';
            $outfit['outfit_name'] = strtoupper($outfitName);
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to reconfigure matrix.';
        }
    }
}

$itemStmt = $pdo->prepare("SELECT item_id FROM outfit_items WHERE outfit_id = ?");
$itemStmt->execute([$outfitId]);
$currentOutfitItems = $itemStmt->fetchAll(PDO::FETCH_COLUMN);

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
    <title>Reconfigure Matrix - Smart Wardrobe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { background-color: #fcfcfc; color: #000000; margin: 0; padding: 0; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
        a { text-decoration: none; color: inherit; }
        button, input, select { font-family: inherit; }

        .topnav { background: #ffffff; border-bottom: 1px solid #e5e5e5; padding: 12px 32px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
        .brand { font-size: 1.1rem; font-weight: 700; letter-spacing: -0.02em; text-transform: uppercase; color: #000; }
        .nav-links { display: flex; gap: 32px; align-items: center; }
        .nav-links a { font-weight: 500; font-size: 0.85rem; color: #666666; transition: color 0.2s; text-transform: uppercase; letter-spacing: 0.05em; }
        .nav-links a:hover, .nav-links a.active { color: #000000; font-weight: 700; }
        .nav-actions { display: flex; align-items: center; gap: 16px; margin-left: 16px; padding-left: 24px; border-left: 1px solid #e5e5e5; }

        .control-panel { background: #ffffff; border-bottom: 1px solid #e5e5e5; padding: 24px 32px; display: flex; flex-direction: column; align-items: center; gap: 16px; }
        .engine-title { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #000; margin: 0; }
        .engine-sub { font-size: 0.75rem; color: #666; text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }

        .cv-form { display: flex; gap: 12px; align-items: center; margin: 0; background: #fcfcfc; padding: 12px; border: 1px solid #e5e5e5; }
        .clean-file { font-size: 0.8rem; outline: none; background: #ffffff; cursor: pointer; color: #666; }
        .clean-input { padding: 10px 16px; border: 1px solid #cccccc; font-size: 0.8rem; outline: none; background: #ffffff; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; width: 250px; border-radius: 0; }
        .clean-input:focus { border-color: #000000; }
        .solid-btn { background: #000000; color: #ffffff; border: 1px solid #000000; padding: 10px 24px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.1em; border-radius: 0; }
        .solid-btn:hover { background: #ffffff; color: #000000; }

        .msg-bar { text-align: center; padding: 12px; font-size: 0.8rem; font-weight: 600; margin: 0; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e5e5e5; }
        .msg-success { background: #ffffff; color: #000; }
        .msg-error { background: #000000; color: #ffffff; }

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
        
        .selection-overlay { position: absolute; inset: 0; border: 4px solid #000000; opacity: 0; transition: opacity 0.2s; pointer-events: none; }
        .checkmark { position: absolute; top: 0; right: 0; background: #000000; color: #ffffff; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; }
        
        .item-label { position: absolute; bottom: 0; left: 0; right: 0; background: #ffffff; padding: 12px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; border-top: 1px solid #e5e5e5; text-align: center; letter-spacing: 0.05em; }
        .selectable-item input:checked ~ .item-label { background: #000000; color: #ffffff; border-top-color: #000000; }
        
        .manual-compile-bar { display: flex; justify-content: center; gap: 16px; padding: 24px; background: #ffffff; border-top: 1px solid #e5e5e5; position: sticky; bottom: 0; z-index: 90; }
    </style>
</head>
<body>

<div class="topnav">
    <span class="brand">Smart Wardrobe</span>
    <div class="nav-links">
        <a href="index.php">Overview</a>
        <a href="kal.php">Kal AI</a>
        <a href="wardrobe.php">Archive</a>
        <a href="create_outfit.php">Outfits</a>
        <a href="code123.php">The Closet</a>
        
        <div class="nav-actions">
            <button id="theme-toggle" class="theme-btn">Dark</button>
            <img src="<?= htmlspecialchars($displayPfp) ?>" alt="Profile" class="nav-pfp">
            <a href="profile.php">Settings</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="control-panel">
    <p class="engine-title"><i class="fas fa-microchip"></i> CV Append Engine</p>
    <p class="engine-sub">Select multiple images to auto-categorize and append to <strong style="color:#000;"><?= htmlspecialchars($outfit['outfit_name']) ?></strong>.</p>
    
    <form method="POST" enctype="multipart/form-data" class="cv-form">
        <input type="file" name="images[]" accept="image/*" multiple required class="clean-file">
        <button type="submit" name="cv_append_matrix" class="solid-btn">Append via CV</button>
    </form>
</div>

<?php if ($success): ?><div class="msg-bar msg-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="msg-bar msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST">
    <div class="stage">
        <?php if (empty($groupedItems)): ?>
            <p style="text-align: center; color: #999; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; margin-top: 40px;">Archive is empty. Utilize CV Engine above.</p>
        <?php else: ?>
            <p class="engine-title" style="margin-bottom: 24px;">OR MANUAL RECONFIGURATION</p>
            <?php foreach ($groupedItems as $category => $categoryItems): ?>
                <div class="category-section">
                    <h3 class="category-title"><?= htmlspecialchars($category) ?></h3>
                    <div class="selectable-grid">
                        <?php foreach ($categoryItems as $item): 
                            $isChecked = in_array($item['item_id'], $currentOutfitItems) ? 'checked' : '';
                        ?>
                            <label class="selectable-item">
                                <input type="checkbox" name="items[]" value="<?= $item['item_id'] ?>" <?= $isChecked ?>>
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

    <div class="manual-compile-bar">
        <a href="my_outfits.php" style="font-size: 0.8rem; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; margin-right: 16px;">Cancel</a>
        <input type="text" name="outfit_name" value="<?= htmlspecialchars($outfit['outfit_name']) ?>" class="clean-input" required>
        <button type="submit" name="update_outfit" class="solid-btn">Recompile Matrix</button>
    </div>
</form>

</body>
</html>