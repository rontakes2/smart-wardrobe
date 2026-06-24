<?php
include 'includes/session.php';
include 'includes/db.php';

$error = '';
$success = '';
$userId = $_SESSION['user_id'];

// --- 1. Handle Marking Items as Clean/Returned ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_clean'])) {
    $itemId = (int)$_POST['item_id'];
    
    // Verify ownership before updating
    $verify = $pdo->prepare("SELECT item_name FROM clothing_items WHERE item_id = ? AND user_id = ?");
    $verify->execute([$itemId, $userId]);
    $item = $verify->fetch();

    if ($item) {
        $stmt = $pdo->prepare("UPDATE clothing_items SET status = 'clean', expected_return_date = NULL WHERE item_id = ?");
        if ($stmt->execute([$itemId])) {
            $success = htmlspecialchars($item['item_name']) . " is back in your wardrobe!";
            $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, activity_description) VALUES (?, 'Status', ?)");
            $logStmt->execute([$userId, "Marked item as clean: " . $item['item_name']]);
        }
    }
}

// --- 2. Handle Sending Items/Outfits to Laundry ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_away'])) {
    $statusType = $_POST['status_type'] ?? 'Machine Wash';
    $duration = $_POST['duration'] ?? '+1 day';
    $outfitId = (int)($_POST['outfit_id'] ?? 0);
    $selectedItems = $_POST['items'] ?? [];

    $itemsToUpdate = $selectedItems;

    // If an outfit was selected, fetch all its items and add them to our update list
    if ($outfitId > 0) {
        $outfitStmt = $pdo->prepare("
            SELECT oi.item_id, o.outfit_name 
            FROM outfit_items oi 
            JOIN outfits o ON oi.outfit_id = o.outfit_id 
            WHERE o.outfit_id = ? AND o.user_id = ?
        ");
        $outfitStmt->execute([$outfitId, $userId]);
        while ($row = $outfitStmt->fetch()) {
            $itemsToUpdate[] = $row['item_id'];
            $outfitName = $row['outfit_name']; // Capture name for logging
        }
    }

    $itemsToUpdate = array_unique($itemsToUpdate); // Prevent duplicates

    if (empty($itemsToUpdate)) {
        $error = "Please select at least one item or an outfit to send away.";
    } else {
        $returnDate = date('Y-m-d', strtotime($duration));
        $updateStmt = $pdo->prepare("UPDATE clothing_items SET status = ?, expected_return_date = ? WHERE item_id = ? AND user_id = ?");
        
        foreach ($itemsToUpdate as $itemId) {
            $updateStmt->execute([$statusType, $returnDate, (int)$itemId, $userId]);
        }

        $success = count($itemsToUpdate) . " item(s) sent to " . htmlspecialchars($statusType) . ".";
        
        // Log the activity
        $logDesc = $outfitId > 0 ? "Sent outfit '{$outfitName}' to {$statusType}." : "Sent " . count($itemsToUpdate) . " items to {$statusType}.";
        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, activity_description) VALUES (?, 'Status', ?)");
        $logStmt->execute([$userId, $logDesc]);
    }
}

// --- 3. Fetch Data for UI ---
// Items currently away
$awayStmt = $pdo->prepare("
    SELECT c.*, cat.category_name 
    FROM clothing_items c 
    JOIN categories cat ON c.category_id = cat.category_id 
    WHERE c.user_id = ? AND c.status != 'clean' AND c.status IS NOT NULL
    ORDER BY c.expected_return_date ASC
");
$awayStmt->execute([$userId]);
$awayItems = $awayStmt->fetchAll();

// Available Outfits (for the dropdown)
$outfitsStmt = $pdo->prepare("SELECT outfit_id, outfit_name FROM outfits WHERE user_id = ? ORDER BY outfit_name");
$outfitsStmt->execute([$userId]);
$outfits = $outfitsStmt->fetchAll();

// Available Items (grouped for checkboxes)
$availStmt = $pdo->prepare("
    SELECT c.*, cat.category_name 
    FROM clothing_items c 
    JOIN categories cat ON c.category_id = cat.category_id 
    WHERE c.user_id = ? AND (c.status = 'clean' OR c.status IS NULL)
    ORDER BY cat.category_name, c.created_at DESC
");
$availStmt->execute([$userId]);
$groupedAvailItems = [];
foreach ($availStmt->fetchAll() as $item) {
    $groupedAvailItems[$item['category_name']][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Laundry & Status - Smart Wardrobe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 500; margin-top: 8px; }
        .status-machine { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
        .status-dryclean { background: rgba(168, 85, 247, 0.1); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.3); }
        .status-mending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
        .return-date { font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; display: block; }
        .overdue { color: #ef4444; font-weight: 500; }
    </style>
</head>
<body class="has-topnav">
<script>
    const savedTheme = localStorage.getItem('wardrobe_theme') || 'light';
    if (savedTheme === 'dark') {
        document.documentElement.classList.add('dark-mode');
        document.body.classList.add('dark-mode');
    } else if (savedTheme === 'glass') {
        document.documentElement.classList.add('glass-mode');
        document.body.classList.add('glass-mode');
    }

    function toggleTheme() {
        const html = document.documentElement;
        const body = document.body;
        
        if (html.classList.contains('glass-mode')) {
            // Switch: Glass -> Light
            html.classList.remove('glass-mode');
            body.classList.remove('glass-mode');
            localStorage.setItem('wardrobe_theme', 'light');
        } else if (html.classList.contains('dark-mode')) {
            // Switch: Dark -> Glass
            html.classList.remove('dark-mode');
            body.classList.remove('dark-mode');
            html.classList.add('glass-mode');
            body.classList.add('glass-mode');
            localStorage.setItem('wardrobe_theme', 'glass');
        } else {
            // Switch: Light -> Dark
            html.classList.add('dark-mode');
            body.classList.add('dark-mode');
            localStorage.setItem('wardrobe_theme', 'dark');
        }
    }
</script>
<div class="topnav">
    <span class="brand">Smart Wardrobe</span>
    <div class="nav-links">
        <a href="index.php">Dashboard</a>
        <a href="wardrobe.php">Wardrobe</a>
        <a href="my_outfits.php">Outfits</a>
        <a href="laundry.php" style="color: var(--accent); font-weight: 500;">Status</a>
        <a class="logout-btn" href="logout.php">Logout</a>
    </div>
</div>

<div class="dashboard-container">
    <h2>Clothing Status & Laundry</h2>
    <p class="dashboard-intro">Track items at the dry cleaners, in the wash, or out for mending.</p>

    <?php if ($success): ?><div class="message-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="message-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div style="margin-bottom: 48px;">
        <h3 style="margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">Currently Away</h3>
        
        <div class="wardrobe-grid">
            <?php if (empty($awayItems)): ?>
                <p style="grid-column: 1 / -1; color: var(--text-muted);">All your clothes are currently clean and in your closet!</p>
            <?php else: ?>
                <?php foreach ($awayItems as $item): 
                    $badgeClass = 'status-machine';
                    if ($item['status'] === 'Dry Cleaners') $badgeClass = 'status-dryclean';
                    if ($item['status'] === 'Mending') $badgeClass = 'status-mending';
                    
                    $isOverdue = (!empty($item['expected_return_date']) && strtotime($item['expected_return_date']) < strtotime('today')) ? 'overdue' : '';
                ?>
                    <div class="wardrobe-card">
                        <div class="wardrobe-image" style="background-image: url('<?= htmlspecialchars($item['image_path']) ?>'); opacity: 0.7; filter: grayscale(50%);"></div>
                        <div class="wardrobe-info">
                            <h4><?= htmlspecialchars($item['item_name']) ?></h4>
                            <span class="status-badge <?= $badgeClass ?>"><i class="fas fa-tshirt"></i> <?= htmlspecialchars($item['status']) ?></span>
                            <?php if(!empty($item['expected_return_date'])): ?>
                                <span class="return-date <?= $isOverdue ?>">Expected: <?= date('M d', strtotime($item['expected_return_date'])) ?></span>
                            <?php endif; ?>
                            
                            <form method="POST" style="margin-top: 16px;">
                                <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                <button type="submit" name="mark_clean" style="padding: 6px 12px; font-size: 0.85rem; width: 100%; background: var(--surface); color: var(--text-main); border: 1px solid var(--border);">
                                    Mark as Returned
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <h3 style="margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">Update Item Status</h3>
        
        <form method="POST" class="outfit-builder-form">
            <div class="form-container" style="padding: 24px; margin-bottom: 32px; background: var(--surface);">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <div>
                        <label style="display:block; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-muted);">Action / Type</label>
                        <select name="status_type" required class="minimal-select" style="margin-bottom: 0;">
                            <option value="Machine Wash">Machine Wash</option>
                            <option value="Dry Cleaners">Dry Cleaners</option>
                            <option value="Mending">Tailor / Mending</option>
                            <option value="Borrowed">Borrowed by Friend</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-muted);">Expected Duration</label>
                        <select name="duration" required class="minimal-select" style="margin-bottom: 0;">
                            <option value="+1 day">1 Day</option>
                            <option value="+3 days">3 Days</option>
                            <option value="+7 days">1 Week</option>
                            <option value="+14 days">2 Weeks</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-muted);">Quick Select Outfit (Optional)</label>
                        <select name="outfit_id" class="minimal-select" style="margin-bottom: 0;">
                            <option value="">-- Choose an Outfit --</option>
                            <?php foreach ($outfits as $o): ?>
                                <option value="<?= $o['outfit_id'] ?>"><?= htmlspecialchars($o['outfit_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="outfit-selection-area">
                <p style="margin-bottom: 16px; color: var(--text-muted);">Or select individual items below:</p>
                <?php if (empty($groupedAvailItems)): ?>
                    <p style="text-align: center; color: var(--text-muted);">No available items to send away.</p>
                <?php else: ?>
                    <?php foreach ($groupedAvailItems as $category => $categoryItems): ?>
                        <div class="category-section">
                            <h3 class="category-title"><?= htmlspecialchars($category) ?></h3>
                            <div class="selectable-grid">
                                <?php foreach ($categoryItems as $item): ?>
                                    <label class="selectable-item">
                                        <input type="checkbox" name="items[]" value="<?= $item['item_id'] ?>">
                                        <div class="img-wrapper">
                                            <div class="img-bg" style="background-image: url('<?= htmlspecialchars($item['image_path']) ?>');"></div>
                                            <div class="selection-overlay"><span class="checkmark">✓</span></div>
                                        </div>
                                        <span class="item-label"><?= htmlspecialchars($item['item_name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="max-width: 400px; margin: 40px auto 0;">
                        <button type="submit" name="send_away" style="font-size: 1.1rem; padding: 16px;">Update Status</button>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

</div>

<script>
    if (localStorage.getItem('wardrobe_theme') === 'dark') {
        document.body.classList.add('dark-mode');
    }
</script>
</body>
</html>