<?php
session_start();
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/config');
$dotenv->load();

include 'includes/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

$currentMonthNum = (int)date('n');
$currentMonthName = strtoupper(date('F'));

if (in_array($currentMonthNum, [3, 4, 5])) {
    $season = 'SPRING';
    $seasonKeywords = ['shirt', 'jeans', 'sneaker', 'light', 'cotton', 'blouse', 'floral'];
    $kalMessage = "ENVIRONMENT: $currentMonthName. DIRECTIVE: LIGHT COTTONS & VERSATILE LAYERS.";
} elseif (in_array($currentMonthNum, [6, 7, 8])) {
    $season = 'SUMMER';
    $seasonKeywords = ['short', 'tee', 'tank', 'linen', 'sandal', 'cotton', 't-shirt', 'swim'];
    $kalMessage = "ENVIRONMENT: $currentMonthName. DIRECTIVE: BREATHABLE LINEN & LIGHTWEAR.";
} elseif (in_array($currentMonthNum, [9, 10, 11])) {
    $season = 'FALL';
    $seasonKeywords = ['sweater', 'jeans', 'jacket', 'boots', 'wool', 'denim', 'cardigan'];
    $kalMessage = "ENVIRONMENT: $currentMonthName. DIRECTIVE: DENIM, JACKETS, LAYERED WOOLS.";
} else {
    $season = 'WINTER';
    $seasonKeywords = ['coat', 'sweater', 'pants', 'boots', 'fleece', 'wool', 'jacket', 'beanie'];
    $kalMessage = "ENVIRONMENT: $currentMonthName. DIRECTIVE: HEAVY WOOLS, FLEECES, OUTERWEAR.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kal_suggest'])) {
    $keywordConditions = [];
    foreach ($seasonKeywords as $kw) {
        $keywordConditions[] = "LOWER(item_name) LIKE '%" . $kw . "%'";
        $keywordConditions[] = "LOWER(fabric_type) LIKE '%" . $kw . "%'";
        $keywordConditions[] = "LOWER(cat.category_name) LIKE '%" . $kw . "%'";
    }
    $keywordSql = implode(" OR ", $keywordConditions);

    $stmt = $pdo->prepare("SELECT c.item_id FROM clothing_items c JOIN categories cat ON c.category_id = cat.category_id WHERE c.user_id = ? AND (c.status = 'clean' OR c.status IS NULL) AND ($keywordSql) GROUP BY c.category_id ORDER BY RAND() LIMIT 3");
    $stmt->execute([$userId]);
    $suggestedItems = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($suggestedItems) > 0) {
        $outfitName = "KAL " . $season . " MATRIX [" . date('His') . "]";
        $pdo->beginTransaction();
        try {
            $outfitStmt = $pdo->prepare("INSERT INTO outfits (user_id, outfit_name, occasion) VALUES (?, ?, 'Seasonal Logic')");
            $outfitStmt->execute([$userId, $outfitName]);
            $newOutfitId = $pdo->lastInsertId();

            $insertItemStmt = $pdo->prepare("INSERT INTO outfit_items (outfit_id, item_id) VALUES (?, ?)");
            foreach ($suggestedItems as $itemId) { $insertItemStmt->execute([$newOutfitId, $itemId]); }

            $today = date('Y-m-d');
            $pdo->prepare("INSERT IGNORE INTO calendar_schedule (user_id, outfit_id, schedule_date) VALUES (?, ?, ?)")->execute([$userId, $newOutfitId, $today]);

            $pdo->commit();
            $success = "MATRIX COMPILED AND SLOTTED INTO THE CLOSET.";
            $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, activity_description) VALUES (?, 'Auto-Outfit', ?)")->execute([$userId, "Kal mapped seasonal matrix: " . $outfitName]);
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "ENGINE FAILURE DURING COMPILATION.";
        }
    } else {
        $error = "INSUFFICIENT $season ASSETS IN ARCHIVE TO COMPILE MATRIX.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kal_process'])) {
    $itemId = (int)$_POST['item_id'];
    $stmt = $pdo->prepare("SELECT item_name, image_path FROM clothing_items WHERE item_id = ? AND user_id = ?");
    $stmt->execute([$itemId, $userId]);
    $item = $stmt->fetch();

    if ($item && file_exists($item['image_path'])) {
        $apiKey = $_ENV['REMOVE_BG_API_KEY'] ?? ''; 
        if (!empty($apiKey)) {
            $cFile = curl_file_create($item['image_path']);
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
                $newFileName = 'uploads/kal_' . uniqid() . '.png';
                file_put_contents($newFileName, $response);
                $pdo->prepare("UPDATE clothing_items SET image_path = ?, is_glassified = 1 WHERE item_id = ?")->execute([$newFileName, $itemId]);
                unlink($item['image_path']);
                $success = "ASSET OPTIMIZED.";
            } else {
                $error = "API LIMIT OR FILE REJECTION.";
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM clothing_items WHERE user_id = ? AND is_glassified = 0 ORDER BY created_at DESC");
$stmt->execute([$userId]);
$rawItems = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Kal AI - Smart Wardrobe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { background-color: #fcfcfc; color: #000000; margin: 0; padding: 0; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
        a { text-decoration: none; color: inherit; }
        button, input, select { font-family: inherit; }

        .topnav { background: #ffffff; border-bottom: 1px solid #e5e5e5; padding: 12px 32px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
        .brand { font-size: 1.1rem; font-weight: 700; letter-spacing: -0.02em; text-transform: uppercase; }
        .nav-links { display: flex; gap: 32px; align-items: center; }
        .nav-links a { font-weight: 500; font-size: 0.85rem; color: #666666; transition: color 0.2s; text-transform: uppercase; letter-spacing: 0.05em; }
        .nav-links a:hover, .nav-links a.active { color: #000000; }

        .kal-container { max-width: 1000px; margin: 40px auto; padding: 0 24px; width: 100%; }
        
        .kal-header { text-align: center; margin-bottom: 48px; background: #ffffff; padding: 48px 32px; border: 1px solid #000; }
        .robot-icon { font-size: 2.5rem; color: #000; margin-bottom: 24px; }
        .kal-header h2 { font-size: 2rem; font-weight: 700; color: #000; margin: 0 0 16px 0; letter-spacing: 0.1em; text-transform: uppercase; }
        .kal-header p { color: #666; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; max-width: 600px; margin: 0 auto; line-height: 1.5; }
        
        .solid-btn { background: #000000; color: #ffffff; border: 1px solid #000000; padding: 14px 32px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; display: inline-flex; align-items: center; justify-content: center; gap: 8px; margin-top: 32px; }
        .solid-btn:hover { background: #ffffff; color: #000000; }

        .msg-bar { text-align: center; padding: 12px; font-size: 0.8rem; font-weight: 600; margin-top: 24px; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; width: auto; }
        .msg-success { background: #ffffff; color: #000; border: 1px solid #000; }
        .msg-error { background: #000000; color: #ffffff; }

        .panel-title { font-size: 0.85rem; font-weight: 700; color: #000; margin: 0 0 24px 0; border-bottom: 1px solid #000; padding-bottom: 8px; text-transform: uppercase; letter-spacing: 0.1em; }

        .kal-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 24px; }
        .kal-card { background: #ffffff; border: 1px solid #e5e5e5; display: flex; flex-direction: column; transition: border-color 0.2s; }
        .kal-card:hover { border-color: #000; }
        .kal-img-wrapper { aspect-ratio: 1; padding: 16px; background: #fcfcfc; border-bottom: 1px solid #e5e5e5; display: flex; align-items: center; justify-content: center; }
        .kal-img { width: 100%; height: 100%; object-fit: contain; filter: grayscale(100%); transition: transform 0.3s, filter 0.3s; }
        .kal-card:hover .kal-img { transform: scale(1.05); filter: grayscale(0%); }
        
        .kal-info { padding: 16px; text-align: center; }
        .kal-info h4 { margin: 0 0 16px 0; font-size: 0.75rem; font-weight: 700; color: #000; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .kal-process-btn { background: transparent; color: #000; border: 1px solid #000; padding: 10px; width: 100%; font-weight: 700; font-size: 0.75rem; cursor: pointer; transition: all 0.2s; text-transform: uppercase; letter-spacing: 0.1em; }
        .kal-process-btn:hover { background: #000; color: #fff; }
    </style>
</head>
<body>

<div class="topnav">
    <span class="brand">Smart Wardrobe</span>
    <div class="nav-links">
        <a href="index.php">Dashboard</a>
        <a href="kal.php" class="active" style="color: #000; font-weight: 700;">Kal AI</a>
        <a href="code123.php">The Closet</a>
    </div>
</div>

<div class="kal-container">
    <div class="kal-header">
        <i class="fas fa-barcode robot-icon"></i>
        <h2>Engine Kal</h2>
        <p><?= $kalMessage ?></p>
        
        <?php if ($success): ?><br><div class="msg-bar msg-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><br><div class="msg-bar msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST">
            <button type="submit" name="kal_suggest" class="solid-btn">Compile <?= $season ?> Matrix</button>
        </form>
    </div>

    <?php if (!empty($rawItems)): ?>
        <h3 class="panel-title">Pending Image Optimization</h3>
        
        <div class="kal-grid">
            <?php foreach ($rawItems as $item): ?>
                <div class="kal-card">
                    <div class="kal-img-wrapper">
                        <img src="<?= htmlspecialchars($item['image_path']) ?>" class="kal-img">
                    </div>
                    <div class="kal-info">
                        <h4 title="<?= htmlspecialchars($item['item_name']) ?>"><?= htmlspecialchars($item['item_name']) ?></h4>
                        <form method="POST">
                            <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                            <button type="submit" name="kal_process" class="kal-process-btn">Optimize Asset</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>