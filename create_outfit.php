<?php
include 'includes/session.php';
include 'includes/db.php';

if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
    try { $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/config'); $dotenv->load(); } catch (Exception $e) {}
}

function optimizeAssetBG($sourcePath) {
    $apiKey = $_ENV['REMOVE_BG_API_KEY'] ?? ''; 
    if (empty($apiKey) || !file_exists($sourcePath)) return false;
    $cFile = curl_file_create($sourcePath);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.remove.bg/v1.0/removebg");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['image_file' => $cFile, 'size' => 'auto']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Api-Key: $apiKey"]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode == 200) {
        $newFileName = 'uploads/opt_' . uniqid() . '.png';
        file_put_contents($newFileName, $response);
        return $newFileName;
    }
    return false;
}

$error = '';
$success = '';
$userId = $_SESSION['user_id'];
$userGender = $_SESSION['user_gender'] ?? 'Unspecified';
$themeClass = ($userGender === 'Female') ? 'theme-female' : 'theme-monochrome';

$stmtUser = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
$stmtUser->execute([$userId]);
$userRow = $stmtUser->fetch();
$username = !empty($userRow['username']) ? $userRow['username'] : 'USER';
$pfpPath = !empty($userRow['profile_picture']) ? $userRow['profile_picture'] : 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=4f46e5&color=ffffff';

// --- UNDO ENGINE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['undo_bulk'])) {
    if (isset($_SESSION['last_bulk_upload'])) {
        $undoData = $_SESSION['last_bulk_upload'];
        $pdo->beginTransaction();
        try {
            if (!empty($undoData['item_ids'])) {
                $placeholders = implode(',', array_fill(0, count($undoData['item_ids']), '?'));
                $params = $undoData['item_ids'];
                $params[] = $userId;
                
                $stmtImgs = $pdo->prepare("SELECT image_path FROM clothing_items WHERE item_id IN ($placeholders) AND user_id = ?");
                $stmtImgs->execute($params);
                while ($row = $stmtImgs->fetch()) { if (file_exists($row['image_path'])) unlink($row['image_path']); }
                $pdo->prepare("DELETE FROM clothing_items WHERE item_id IN ($placeholders) AND user_id = ?")->execute($params);
            }
            if (!empty($undoData['outfit_id'])) {
                $pdo->prepare("DELETE FROM outfits WHERE outfit_id = ? AND user_id = ?")->execute([$undoData['outfit_id'], $userId]);
            }
            $pdo->commit();
            unset($_SESSION['last_bulk_upload']);
            $success = "Action reversed. Uploaded images and outfit have been deleted.";
        } catch (Exception $e) { $pdo->rollBack(); $error = "Failed to reverse the action."; }
    }
}

// --- Bulk Upload & Create ---
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
                        $newFileName = uniqid('raw_', true) . '.' . $fileExt;
                        $destination = $uploadDir . $newFileName;
                        
                        if (move_uploaded_file($tmpName, $destination)) {
                            $optimizedPath = optimizeAssetBG($destination);
                            $finalPath = $optimizedPath ? $optimizedPath : $destination;
                            $isGlassified = $optimizedPath ? 1 : 0;
                            if ($optimizedPath && file_exists($destination)) unlink($destination);

                            $assignedCat = $dbCats[array_rand($dbCats)];
                            $syntheticName = strtoupper($adjectives[array_rand($adjectives)] . ' ' . $assignedCat['category_name']);
                            
                            $stmt = $pdo->prepare("INSERT INTO clothing_items (user_id, category_id, item_name, image_path, image_source, status, is_glassified) VALUES (?, ?, ?, ?, 'local', 'clean', ?)");
                            $stmt->execute([$userId, $assignedCat['category_id'], $syntheticName, $finalPath, $isGlassified]);
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
                
                $_SESSION['last_bulk_upload'] = ['outfit_id' => $newOutfitId, 'item_ids' => $uploadedItemIds];
                $success = count($uploadedItemIds) . " items uploaded, optimized, and saved as outfit: " . strtoupper($outfitName);
            } else { $error = "No valid images processed."; }
            $pdo->commit();
        } catch (Exception $e) { $pdo->rollBack(); $error = 'Error occurred during upload and creation.'; }
    } else { $error = 'Please select images to upload.'; }
}

// --- Manual Outfit Creation ---
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
            unset($_SESSION['last_bulk_upload']);
            $success = 'Outfit saved successfully.';
        } catch (Exception $e) { $pdo->rollBack(); $error = 'Failed to save outfit.'; }
    }
}

// --- Delete Outfit ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_outfit'])) {
    $outfitId = (int)$_POST['outfit_id'];
    $pdo->prepare("DELETE FROM outfits WHERE outfit_id = ? AND user_id = ?")->execute([$outfitId, $userId]);
    $success = "Outfit deleted successfully.";
}

$stmt = $pdo->prepare("SELECT c.*, cat.category_name FROM clothing_items c JOIN categories cat ON c.category_id = cat.category_id WHERE c.user_id = ? ORDER BY cat.category_name, c.created_at DESC");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();
$groupedItems = [];
foreach ($items as $item) { $groupedItems[$item['category_name']][] = $item; }

$outfitsStmt = $pdo->prepare("SELECT * FROM outfits WHERE user_id = ? ORDER BY created_at DESC");
$outfitsStmt->execute([$userId]);
$outfitsList = $outfitsStmt->fetchAll();

$outfitItems = [];
if (!empty($outfitsList)) {
    $itemsStmt = $pdo->prepare("SELECT oi.outfit_id, c.item_name, c.image_path FROM outfit_items oi JOIN clothing_items c ON oi.item_id = c.item_id WHERE c.user_id = ?");
    $itemsStmt->execute([$userId]);
    foreach ($itemsStmt->fetchAll() as $oItem) { $outfitItems[$oItem['outfit_id']][] = $oItem; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Outfits - Smart Wardrobe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        <a href="index.php">Overview</a>
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

<?php if (isset($_SESSION['last_bulk_upload'])): ?>
<div class="undo-bar">
    <span>Mistake with your last upload?</span>
    <form method="POST" style="margin:0;">
        <button type="submit" name="undo_bulk" class="undo-btn">Undo Last Upload</button>
    </form>
</div>
<?php endif; ?>

<div class="control-panel">
    <p class="engine-title">Bulk Upload & Auto-Create Outfit</p>
    <p class="engine-sub">Select multiple images. The system will optimize the images and group them into a new outfit instantly.</p>
    
    <form method="POST" enctype="multipart/form-data" class="cv-form">
        <input type="text" name="cv_outfit_name" placeholder="Outfit Name (Optional)" class="clean-input">
        <input type="file" name="images[]" accept="image/*" multiple required class="clean-file">
        <button type="submit" name="cv_compile_matrix" class="solid-btn">Upload & Optimize</button>
    </form>
</div>

<?php if ($success): ?>
    <div class="msg-bar msg-success">
        <?= htmlspecialchars($success) ?>
        <span class="close-msg" onclick="this.parentElement.style.display='none';">&times;</span>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="msg-bar msg-error">
        <?= htmlspecialchars($error) ?>
        <span class="close-msg" onclick="this.parentElement.style.display='none';">&times;</span>
    </div>
<?php endif; ?>

<form method="POST">
    <div class="stage">
        <?php if (!empty($groupedItems)): ?>
            <p class="engine-title" style="margin-bottom: 32px; text-align: center; color: var(--text-muted);">OR CREATE OUTFIT MANUALLY</p>
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
            <div class="manual-compile-bar">
                <input type="text" name="outfit_name" placeholder="Outfit Name" class="clean-input" required>
                <button type="submit" name="manual_create_outfit" class="solid-btn">Save Outfit</button>
            </div>
        <?php endif; ?>
    </div>
</form>

<div class="stage" style="padding-top: 0; margin-top: 48px; border-top: 2px solid var(--border-main);">
    <h2 class="engine-title" style="margin: 48px 0 32px 0; font-size: 1.5rem; text-align: center;">Your Outfits Library</h2>
    
    <?php if (empty($outfitsList)): ?>
        <p style="text-align: center; color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em;">No outfits created yet.</p>
    <?php else: ?>
        <?php foreach ($outfitsList as $outfit): ?>
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
                        <p style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">No items assigned.</p>
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