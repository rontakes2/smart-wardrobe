<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_outfit'])) {
    $outfitId = (int)$_POST['outfit_id'];
    $scheduleDate = $_POST['schedule_date'];

    if ($outfitId > 0 && !empty($scheduleDate)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO calendar_schedule (user_id, outfit_id, schedule_date) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $outfitId, $scheduleDate]);
            $success = "Asset matrix slotted into closet.";
        } catch (PDOException $e) {
            $error = "A matrix is already slotted for that day.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_schedule'])) {
    $scheduleId = (int)$_POST['schedule_id'];
    $pdo->prepare("DELETE FROM calendar_schedule WHERE schedule_id = ? AND user_id = ?")->execute([$scheduleId, $userId]);
    $success = "Closet slot cleared.";
}

$outfits = $pdo->prepare("SELECT outfit_id, outfit_name FROM outfits WHERE user_id = ? ORDER BY outfit_name");
$outfits->execute([$userId]);
$userOutfits = $outfits->fetchAll();

$baseDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$day1 = date('Y-m-d', strtotime($baseDate . ' -1 day')); 
$day2 = $baseDate;                                       
$day3 = date('Y-m-d', strtotime($baseDate . ' +1 day')); 
$windowDates = [$day1, $day2, $day3];

// SQL Updated to fetch the category_name for the striking hover info
$stmt = $pdo->prepare("
    SELECT cs.schedule_id, cs.schedule_date, o.outfit_name, c.image_path, c.item_name, cat.category_name
    FROM calendar_schedule cs 
    JOIN outfits o ON cs.outfit_id = o.outfit_id 
    JOIN outfit_items oi ON o.outfit_id = oi.outfit_id
    JOIN clothing_items c ON oi.item_id = c.item_id
    JOIN categories cat ON c.category_id = cat.category_id
    WHERE cs.user_id = ? AND cs.schedule_date IN (?, ?, ?)
");
$stmt->execute([$userId, $day1, $day2, $day3]);

$schedules = [];
foreach ($stmt->fetchAll() as $row) {
    $date = $row['schedule_date'];
    $schedId = $row['schedule_id'];
    
    if (!isset($schedules[$date][$schedId])) {
        $schedules[$date][$schedId] = [
            'outfit_name' => $row['outfit_name'],
            'items' => []
        ];
    }
    $schedules[$date][$schedId]['items'][] = [
        'image_path' => $row['image_path'],
        'item_name' => $row['item_name'],
        'category_name' => $row['category_name']
    ];
}

function formatSlabDate($dateString) {
    return date('D, M d', strtotime($dateString));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>The Closet - Smart Wardrobe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background-color: #f8fafc; color: #0f172a; margin: 0; padding: 0; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        
        .topnav { background: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .brand { font-size: 1.25rem; font-weight: 700; color: #0f172a; letter-spacing: -0.02em; }
        .nav-links { display: flex; gap: 24px; align-items: center; }
        .nav-links a { color: #475569; text-decoration: none; font-weight: 500; font-size: 0.9rem; transition: color 0.2s; }
        .nav-links a:hover { color: #0f172a; }

        .control-panel { background: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 24px 32px; display: flex; justify-content: space-between; align-items: center; }
        .date-nav { display: flex; align-items: center; gap: 16px; }
        .date-nav a { background: #f1f5f9; color: #475569; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; border: 1px solid #e2e8f0; transition: all 0.2s; }
        .date-nav a:hover { background: #e2e8f0; color: #0f172a; }
        .date-nav h2 { margin: 0; font-size: 1.5rem; font-weight: 700; color: #0f172a; min-width: 250px; text-align: center; letter-spacing: -0.02em; }

        .slot-form { display: flex; gap: 12px; align-items: center; background: #f8fafc; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .clean-input, .clean-select { padding: 10px 16px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 0.9rem; color: #0f172a; outline: none; background: #ffffff; }
        .clean-input:focus, .clean-select:focus { border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
        .solid-btn { background: #0f172a; color: #ffffff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; font-size: 0.9rem; }
        .solid-btn:hover { background: #8b5cf6; }

        .msg-bar { text-align: center; padding: 12px; font-size: 0.9rem; font-weight: 500; }
        .msg-success { background: #d1fae5; color: #059669; border-bottom: 1px solid #34d399; }
        .msg-error { background: #fee2e2; color: #dc2626; border-bottom: 1px solid #f87171; }

        .closet-stage { max-width: 1600px; margin: 40px auto; padding: 0 32px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; }

        /* Removed overflow: hidden so tooltips can escape the slab bounds */
        .slab { 
            background: #ffffff; border: 1px solid #e2e8f0; border-radius: 24px; 
            min-height: 65vh; display: flex; flex-direction: column; 
            position: relative; box-shadow: 0 4px 20px -5px rgba(0,0,0,0.03);
            transition: transform 0.3s ease, border-color 0.3s ease;
        }
        
        .slab-today { border-color: #8b5cf6; box-shadow: 0 10px 30px -5px rgba(139, 92, 246, 0.15); transform: translateY(-5px); z-index: 10; }

        /* Added border-radius to header to match slab since overflow:hidden was removed */
        .slab-header { padding: 24px; text-align: center; border-bottom: 1px solid #e2e8f0; background: #f8fafc; z-index: 2; border-radius: 24px 24px 0 0; }
        .slab-today .slab-header { background: #f5f3ff; border-bottom-color: #ede9fe; }
        .slab-title { font-size: 0.85rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; margin: 0 0 8px 0; }
        .slab-today .slab-title { color: #8b5cf6; }
        .slab-date { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0; letter-spacing: -0.02em; }

        .slab-center-rack { flex-grow: 1; padding: 32px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 40px; position: relative; z-index: 2; }
        .outfit-cluster { width: 100%; display: flex; flex-direction: column; align-items: center; gap: 24px; }
        .rack-images { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 16px; }

        /* Striking Minimal Black Tooltip Engine */
        .rack-item-wrapper {
            position: relative;
            display: inline-block;
        }
        
        .rack-item-img {
            max-height: 160px; width: auto; object-fit: contain;
            filter: drop-shadow(0 10px 15px rgba(0,0,0,0.15));
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            z-index: 5;
        }

        .rack-item-tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%) translateY(10px);
            background: #000000;
            color: #ffffff;
            padding: 8px 16px;
            border-radius: 4px;
            text-align: center;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 50;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .rack-item-tooltip::after {
            content: ''; position: absolute; top: 100%; left: 50%;
            transform: translateX(-50%); border-width: 5px;
            border-style: solid; border-color: #000000 transparent transparent transparent;
        }

        .tooltip-name { display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 2px; }
        .tooltip-cat { display: block; color: #a1a1aa; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 700; }

        .rack-item-wrapper:hover .rack-item-img { transform: scale(1.15); z-index: 10; }
        .rack-item-wrapper:hover .rack-item-tooltip { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(-10px); }

        .outfit-label-bar { background: #0f172a; color: #ffffff; padding: 10px 20px; border-radius: 100px; display: flex; align-items: center; gap: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .outfit-name-text { font-size: 0.9rem; font-weight: 600; white-space: nowrap; }
        .event-del-btn { background: transparent; border: none; color: #f87171; cursor: pointer; font-size: 1rem; transition: color 0.2s; padding: 0; }
        .event-del-btn:hover { color: #ef4444; transform: scale(1.1); }

        .empty-rack { color: #94a3b8; font-size: 1rem; font-weight: 500; text-align: center; }
    </style>
</head>
<body>

<div class="topnav">
    <span class="brand">Smart Wardrobe</span>
    <div class="nav-links">
        <a href="index.php">Dashboard</a>
        <a href="kal.php" style="color: #8b5cf6; font-weight: 600;"><i class="fas fa-robot"></i> Kal AI</a>
        <a href="code123.php" style="color: #0f172a; font-weight: 600;">The Closet</a>
    </div>
</div>

<div class="control-panel">
    <div class="date-nav">
        <a href="?date=<?= date('Y-m-d', strtotime($baseDate . ' -1 day')) ?>"><i class="fas fa-chevron-left"></i> Shift Back</a>
        <h2><?= date('F d, Y', strtotime($baseDate)) ?></h2>
        <a href="?date=<?= date('Y-m-d', strtotime($baseDate . ' +1 day')) ?>">Shift Forward <i class="fas fa-chevron-right"></i></a>
        <?php if($baseDate !== date('Y-m-d')): ?>
            <a href="?date=<?= date('Y-m-d') ?>" style="background: #ffffff; border-color: #8b5cf6; color: #8b5cf6;">Jump to Today</a>
        <?php endif; ?>
    </div>

    <form method="POST" class="slot-form">
        <div style="font-size: 0.85rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Slot Look</div>
        <input type="date" name="schedule_date" value="<?= $baseDate ?>" class="clean-input" required>
        <select name="outfit_id" class="clean-select" required>
            <option value="" disabled selected>-- Select Matrix --</option>
            <?php foreach ($userOutfits as $outfit): ?>
                <option value="<?= $outfit['outfit_id'] ?>"><?= htmlspecialchars($outfit['outfit_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="schedule_outfit" class="solid-btn">Mount to Rack</button>
    </form>
</div>

<?php if ($success): ?><div class="msg-bar msg-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="msg-bar msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="closet-stage">
    
    <?php 
    $slabLabels = ["YESTERDAY", "TODAY", "TOMORROW"];
    
    foreach ($windowDates as $index => $targetDate): 
        $isToday = ($targetDate === date('Y-m-d')) ? 'slab-today' : '';
        $label = $slabLabels[$index];
        if ($baseDate !== date('Y-m-d')) {
            if ($index == 0) $label = "PREVIOUS";
            if ($index == 1) $label = "FOCUS";
            if ($index == 2) $label = "NEXT";
        }
    ?>
        <div class="slab <?= $isToday ?>">
            <div class="slab-header">
                <p class="slab-title"><?= $label ?></p>
                <h3 class="slab-date"><?= formatSlabDate($targetDate) ?></h3>
            </div>
            
            <div class="slab-center-rack">
                <?php if (isset($schedules[$targetDate])): ?>
                    <?php foreach ($schedules[$targetDate] as $schedId => $event): ?>
                        <div class="outfit-cluster">
                            <div class="rack-images">
                                <?php foreach ($event['items'] as $item): ?>
                                    <div class="rack-item-wrapper">
                                        <img src="<?= htmlspecialchars($item['image_path']) ?>" class="rack-item-img" alt="Asset">
                                        <div class="rack-item-tooltip">
                                            <span class="tooltip-name"><?= htmlspecialchars($item['item_name']) ?></span>
                                            <span class="tooltip-cat"><?= htmlspecialchars($item['category_name']) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="outfit-label-bar">
                                <span class="outfit-name-text"><?= htmlspecialchars($event['outfit_name']) ?></span>
                                <form method="POST" style="margin:0; display:flex; align-items:center;">
                                    <input type="hidden" name="schedule_id" value="<?= $schedId ?>">
                                    <button type="submit" name="remove_schedule" class="event-del-btn" title="Remove from rack"><i class="fas fa-times-circle"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-rack">
                        <i class="fas fa-ghost" style="font-size: 2rem; color: #cbd5e1; margin-bottom: 16px; display: block;"></i>
                        Rack is empty
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

</div>

</body>
</html>