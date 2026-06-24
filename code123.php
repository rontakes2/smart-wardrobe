<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$userId = $_SESSION['user_id'];
$userGender = $_SESSION['user_gender'] ?? 'Unspecified';
$themeClass = ($userGender === 'Female') ? 'theme-female' : 'theme-monochrome';

$stmtUser = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
$stmtUser->execute([$userId]);
$userRow = $stmtUser->fetch();
$username = !empty($userRow['username']) ? $userRow['username'] : 'USER';
$pfpPath = !empty($userRow['profile_picture']) ? $userRow['profile_picture'] : 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=4f46e5&color=ffffff';

$success = '';
$error = '';
$viewMode = $_GET['view'] ?? 'slab';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_outfit'])) {
    $outfitId = (int)$_POST['outfit_id'];
    $scheduleDate = $_POST['schedule_date'];
    
    if ($outfitId > 0 && !empty($scheduleDate)) {
        try {
            $pdo->prepare("INSERT INTO calendar_schedule (user_id, outfit_id, schedule_date) VALUES (?, ?, ?)")->execute([$userId, $outfitId, $scheduleDate]);
            $success = "Outfit scheduled.";
        } catch (PDOException $e) { $error = "Failed to schedule outfit."; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_schedule'])) {
    $scheduleId = (int)$_POST['schedule_id'];
    $pdo->prepare("DELETE FROM calendar_schedule WHERE schedule_id = ? AND user_id = ?")->execute([$scheduleId, $userId]);
    $success = "Schedule cleared.";
}

$outfits = $pdo->prepare("SELECT outfit_id, outfit_name FROM outfits WHERE user_id = ? ORDER BY outfit_name");
$outfits->execute([$userId]);
$userOutfits = $outfits->fetchAll();

$baseDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$year = date('Y', strtotime($baseDate));
$month = date('m', strtotime($baseDate));
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstDayOfMonth = date('N', strtotime("$year-$month-01")); 

if ($viewMode === 'grid') {
    $startDate = "$year-$month-01";
    $endDate = "$year-$month-$daysInMonth";
} else {
    $day1 = date('Y-m-d', strtotime($baseDate . ' -1 day')); 
    $day2 = $baseDate;                                       
    $day3 = date('Y-m-d', strtotime($baseDate . ' +1 day')); 
    $windowDates = [$day1, $day2, $day3];
    $startDate = $day1;
    $endDate = $day3;
}

$stmt = $pdo->prepare("SELECT cs.schedule_id, cs.schedule_date, o.outfit_name, c.image_path, c.item_name, cat.category_name FROM calendar_schedule cs JOIN outfits o ON cs.outfit_id = o.outfit_id JOIN outfit_items oi ON o.outfit_id = oi.outfit_id JOIN clothing_items c ON oi.item_id = c.item_id JOIN categories cat ON c.category_id = cat.category_id WHERE cs.user_id = ? AND cs.schedule_date BETWEEN ? AND ?");
$stmt->execute([$userId, $startDate, $endDate]);

$schedules = [];
foreach ($stmt->fetchAll() as $row) {
    $date = $row['schedule_date'];
    $schedId = $row['schedule_id'];
    if (!isset($schedules[$date][$schedId])) { $schedules[$date][$schedId] = ['outfit_name' => $row['outfit_name'], 'items' => []]; }
    $schedules[$date][$schedId]['items'][] = ['image_path' => $row['image_path'], 'item_name' => $row['item_name'], 'category_name' => $row['category_name']];
}

function formatSlabDate($dateString) { return date('M d', strtotime($dateString)); }
function formatSlabDay($dateString) { return date('l', strtotime($dateString)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>The Closet - Smart Wardrobe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-main: #f8fafc;
            --bg-panel: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-main: #e2e8f0;
            --accent-solid: #4f46e5;
            --accent-hover: #4338ca;
            --accent-text: #ffffff;
            --danger: #ef4444;
            --danger-hover: #dc2626;
        }
        body.theme-female {
            --bg-main: #fdf4ff;
            --text-main: #2e0219;
            --text-muted: #83526c;
            --border-main: #f3e8ff;
            --accent-solid: #d946ef;
            --accent-hover: #c026d3;
        }
        body.dark-mode {
            --bg-main: #0a0a0a;
            --bg-panel: #111111;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --border-main: #334155;
            --accent-solid: #6366f1;
            --accent-hover: #818cf8;
        }
        body.dark-mode.theme-female {
            --bg-main: #120510;
            --bg-panel: #1a0817;
            --text-main: #fce7f3;
            --text-muted: #f472b6;
            --border-main: #4a1d3e;
            --accent-solid: #e879f9;
            --accent-hover: #f0abfc;
            --accent-text: #000000;
        }

        * { box-sizing: border-box; }
        body { background-color: var(--bg-main); color: var(--text-main); margin: 0; padding: 0; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; flex-direction: column; overflow-x: hidden; transition: background-color 0.3s, color 0.3s; }
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

        .control-panel { background: var(--bg-panel); border-bottom: 1px solid var(--border-main); padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; position: relative; z-index: 90; transition: background-color 0.3s, border-color 0.3s; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .date-nav { display: flex; align-items: center; gap: 24px; }
        .date-nav a { color: var(--text-muted); font-size: 1.2rem; transition: color 0.2s; }
        .date-nav a:hover { color: var(--accent-solid); }
        .date-nav h2 { margin: 0; font-size: 1.2rem; font-weight: 800; min-width: 180px; text-align: center; letter-spacing: 0.05em; text-transform: uppercase; color: var(--text-main); }

        .view-toggles { display: flex; gap: 8px; border: 1px solid var(--border-main); padding: 4px; background: var(--bg-main); border-radius: 6px; }
        .view-btn { padding: 6px 16px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; color: var(--text-muted); transition: all 0.2s; border-radius: 4px; }
        .view-btn.active, .view-btn:hover { background: var(--accent-solid); color: var(--accent-text); }

        .slot-form { display: flex; gap: 12px; align-items: center; margin: 0; }
        .clean-input, .clean-select { padding: 10px 16px; border: 1px solid var(--border-main); font-size: 0.85rem; outline: none; background: var(--bg-main); color: var(--text-main); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; border-radius: 6px; transition: border-color 0.2s; }
        .clean-input:focus, .clean-select:focus { border-color: var(--accent-solid); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        .solid-btn { background: var(--accent-solid); color: var(--accent-text); border: none; padding: 10px 24px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.1em; border-radius: 6px; }
        .solid-btn:hover { background: var(--accent-hover); transform: translateY(-1px); }

        .msg-bar { text-align: center; padding: 12px 40px; font-size: 0.85rem; font-weight: 700; margin: 0; text-transform: uppercase; letter-spacing: 0.05em; position: relative; border-bottom: 1px solid var(--border-main); }
        .msg-success { background: #10b981; color: #fff; }
        .msg-error { background: var(--danger); color: #fff; }
        .close-msg { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #fff; font-size: 1.2rem; line-height: 1; transition: opacity 0.2s; font-weight: bold; opacity: 0.7; }
        .close-msg:hover { opacity: 1; }

        /* SLAB VIEW */
        .slab-stage { flex-grow: 1; width: 100%; max-width: 1200px; margin: 40px auto; padding: 0 32px 32px 32px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; position: relative; z-index: 10; min-height: 0; <?php if($viewMode === 'grid') echo 'display: none;'; ?> }
        .slab { background: var(--bg-panel); border: 1px solid var(--border-main); border-radius: 12px; height: 100%; display: flex; flex-direction: column; position: relative; transition: all 0.3s ease; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .slab-today { border-color: var(--accent-solid); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); z-index: 20; transform: scale(1.02); border-width: 2px; }
        .slab-header { padding: 24px; text-align: center; border-bottom: 2px solid var(--border-main); flex-shrink: 0; height: 100px; display: flex; flex-direction: column; justify-content: center; }
        .slab-date-default { transition: opacity 0.2s; }
        .slab-title { font-size: 0.75rem; font-weight: 800; color: var(--accent-solid); text-transform: uppercase; letter-spacing: 0.15em; margin: 0 0 6px 0; }
        .slab-day { font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0; letter-spacing: -0.02em; text-transform: uppercase; }
        .dynamic-category { display: none; font-size: 0.85rem; font-weight: 800; color: var(--accent-solid); text-transform: uppercase; letter-spacing: 0.15em; }

        .slab-center-rack { flex-grow: 1; padding: 32px; display: flex; flex-direction: column; position: relative; overflow: hidden; }
        .outfit-cluster { width: 100%; height: 100%; display: flex; flex-direction: column; }
        .rack-images { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; width: 100%; align-content: start; flex-grow: 1; }
        .rack-item-wrapper { position: relative; display: flex; justify-content: center; align-items: center; width: 100%; aspect-ratio: 3/4; cursor: crosshair; }
        .rack-item-img { width: 100%; height: 100%; max-height: 28vh; object-fit: contain; filter: drop-shadow(0 10px 15px rgba(0,0,0,0.1)); transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .outfit-cluster:hover .rack-item-img { opacity: 0.3; filter: grayscale(100%); }
        .outfit-cluster .rack-item-wrapper:hover .rack-item-img { opacity: 1; filter: grayscale(0%) drop-shadow(0 20px 25px rgba(0,0,0,0.2)); transform: scale(1.15); z-index: 10; }

        .slab-footer { padding: 20px 24px; border-top: 1px solid var(--border-main); flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; height: 70px; background: var(--bg-main); border-radius: 0 0 12px 12px; }
        .footer-text-container { flex-grow: 1; overflow: hidden; }
        .default-outfit-name { font-size: 0.85rem; font-weight: 700; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
        .dynamic-item-name { display: none; font-size: 0.9rem; font-weight: 800; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .event-del-btn { background: transparent; border: none; color: var(--danger); cursor: pointer; font-size: 1.2rem; transition: color 0.2s; padding: 0 0 0 16px; opacity: 0.6; }
        .event-del-btn:hover { color: var(--danger-hover); opacity: 1; }
        .empty-rack { color: var(--text-muted); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; opacity: 0.5; }

        .is-focused .slab-date-default { display: none; }
        .is-focused .dynamic-category { display: block; }
        .is-focused .default-outfit-name { display: none; }
        .is-focused .dynamic-item-name { display: block; }

        /* GRID VIEW */
        .grid-stage { width: 100%; max-width: 1200px; margin: 40px auto; padding: 0 32px 60px 32px; position: relative; z-index: 10; overflow-y: auto; <?php if($viewMode !== 'grid') echo 'display: none;'; ?> }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: var(--border-main); border: 1px solid var(--border-main); border-radius: 12px; overflow: hidden; width: 100%; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .grid-header-cell { background: var(--bg-main); text-align: center; padding: 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); }
        .grid-cell { background: var(--bg-panel); min-height: 160px; padding: 16px; display: flex; flex-direction: column; transition: background-color 0.2s; cursor: pointer; }
        .grid-cell:hover { background: var(--bg-main); }
        .grid-cell.today { background: var(--bg-main); box-shadow: inset 0 0 0 2px var(--accent-solid); }
        .grid-date { font-size: 1rem; font-weight: 800; color: var(--text-main); margin-bottom: 12px; text-align: right; }
        .grid-items { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; align-items: center; flex-grow: 1; padding-bottom: 8px; }
        .grid-item-img { max-height: 55px; width: auto; object-fit: contain; filter: grayscale(100%) drop-shadow(0 2px 4px rgba(0,0,0,0.1)); transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .grid-cell:hover .grid-item-img { filter: grayscale(0%); transform: scale(1.2); z-index: 5; position: relative; }
        .grid-outfit-name { font-size: 0.65rem; font-weight: 800; text-align: center; margin-top: auto; text-transform: uppercase; background: var(--accent-solid); color: var(--accent-text); padding: 6px; border-radius: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0.05em; }

        /* STRICT DARK MODE */
        body.dark-mode { background-color: #0a0a0a; color: #ffffff; }
        body.dark-mode .topnav, body.dark-mode .control-panel, body.dark-mode .slab, 
        body.dark-mode .slab-footer, body.dark-mode .grid-header-cell, body.dark-mode .grid-cell { background-color: #0a0a0a; border-color: #333333; color: #ffffff; }
        body.dark-mode .brand, body.dark-mode .theme-btn:hover, body.dark-mode .topnav a:hover, body.dark-mode .topnav a.active, body.dark-mode .slab-day, body.dark-mode .dynamic-item-name, body.dark-mode .grid-date { color: #ffffff; }
        body.dark-mode .topnav a, body.dark-mode .theme-btn, body.dark-mode .default-outfit-name, body.dark-mode .grid-header-cell { color: #888888; }
        body.dark-mode .nav-actions, body.dark-mode .nav-pfp, body.dark-mode .slab-header, body.dark-mode .calendar-grid { border-color: #333333; }
        body.dark-mode .clean-input, body.dark-mode .clean-select { background-color: #111111; border-color: #333333; color: #ffffff; }
        body.dark-mode .clean-input:focus, body.dark-mode .clean-select:focus { border-color: #ffffff; }
        body.dark-mode .view-toggles { background: #111; border-color: #333; }
        body.dark-mode .grid-cell:hover { background: #1a1a1a; }
        body.dark-mode .grid-cell.today { background: #111; }
        body.dark-mode .msg-success { background: #111; border-color: #333; color: #fff; }
    </style>
</head>
<body class="<?= $themeClass ?> <?= $viewMode === 'grid' ? 'scrollable' : '' ?>">

<div class="topnav">
    <span class="brand">Smart Wardrobe <?php if($themeClass === 'theme-female'): ?><sub style="font-size: 0.6rem; color: var(--accent-solid);">Women's</sub><?php endif; ?></span>
    <div class="nav-links">
        <a href="index.php">Overview</a>
        <a href="wardrobe.php">Archive</a>
        <a href="create_outfit.php">Outfits</a>
        <a href="code123.php" class="active">The Closet</a>
        
        <div class="nav-actions">
            <button id="theme-toggle" class="theme-btn">Dark</button>
            <img src="<?= htmlspecialchars($pfpPath) ?>" alt="Profile" class="nav-pfp">
            <a href="profile.php">Settings</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="control-panel">
    <div class="date-nav">
        <a href="?date=<?= date('Y-m-d', strtotime($baseDate . ' -1 month')) ?>&view=<?= $viewMode ?>"><i class="fas fa-angle-double-left"></i></a>
        <a href="?date=<?= date('Y-m-d', strtotime($baseDate . ' -1 day')) ?>&view=<?= $viewMode ?>"><i class="fas fa-arrow-left"></i></a>
        <h2><?= date('F d, Y', strtotime($baseDate)) ?></h2>
        <a href="?date=<?= date('Y-m-d', strtotime($baseDate . ' +1 day')) ?>&view=<?= $viewMode ?>"><i class="fas fa-arrow-right"></i></a>
        <a href="?date=<?= date('Y-m-d', strtotime($baseDate . ' +1 month')) ?>&view=<?= $viewMode ?>"><i class="fas fa-angle-double-right"></i></a>
        
        <div class="view-toggles">
            <a href="?date=<?= $baseDate ?>&view=slab" class="view-btn <?= $viewMode === 'slab' ? 'active' : '' ?>">Slabs</a>
            <a href="?date=<?= $baseDate ?>&view=grid" class="view-btn <?= $viewMode === 'grid' ? 'active' : '' ?>">Grid</a>
        </div>
    </div>

    <form method="POST" class="slot-form">
        <input type="date" name="schedule_date" value="<?= $baseDate ?>" class="clean-input" required>
        <select name="outfit_id" class="clean-select" required>
            <option value="" disabled selected>Select Outfit</option>
            <?php foreach ($userOutfits as $outfit): ?>
                <option value="<?= $outfit['outfit_id'] ?>"><?= htmlspecialchars($outfit['outfit_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="schedule_outfit" class="solid-btn">Save</button>
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

<div class="slab-stage">
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
        <div class="slab <?= $isToday ?>" id="slab-<?= $index ?>">
            <div class="slab-header">
                <div class="slab-date-default">
                    <p class="slab-title"><?= $label ?></p>
                    <h3 class="slab-day"><?= formatSlabDay($targetDate) ?> <?= formatSlabDate($targetDate) ?></h3>
                </div>
                <div class="dynamic-category"></div>
            </div>
            
            <div class="slab-center-rack">
                <?php if (isset($schedules[$targetDate])): ?>
                    <?php foreach ($schedules[$targetDate] as $schedId => $event): ?>
                        <div class="outfit-cluster">
                            <div class="rack-images">
                                <?php foreach ($event['items'] as $item): ?>
                                    <div class="rack-item-wrapper" data-cat="<?= htmlspecialchars($item['category_name']) ?>" data-name="<?= htmlspecialchars($item['item_name']) ?>">
                                        <img src="<?= htmlspecialchars($item['image_path']) ?>" class="rack-item-img" alt="Asset">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-rack"><i class="fas fa-hanger" style="font-size: 2rem; margin-bottom: 8px;"></i> No outfit scheduled</div>
                <?php endif; ?>
            </div>

            <div class="slab-footer">
                <div class="footer-text-container">
                    <?php if (isset($schedules[$targetDate])): ?>
                        <?php foreach ($schedules[$targetDate] as $schedId => $event): ?>
                            <span class="default-outfit-name"><?= htmlspecialchars($event['outfit_name']) ?></span>
                            <span class="dynamic-item-name"></span>
                        <?php break; endforeach; ?>
                    <?php else: ?>
                        <span class="default-outfit-name" style="color:var(--text-muted); opacity: 0.5;">EMPTY</span>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($schedules[$targetDate])): ?>
                    <?php foreach ($schedules[$targetDate] as $schedId => $event): ?>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="schedule_id" value="<?= $schedId ?>">
                            <button type="submit" name="remove_schedule" class="event-del-btn"><i class="fas fa-times"></i></button>
                        </form>
                    <?php break; endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid-stage">
    <div class="calendar-grid">
        <div class="grid-header-cell">Mon</div><div class="grid-header-cell">Tue</div><div class="grid-header-cell">Wed</div>
        <div class="grid-header-cell">Thu</div><div class="grid-header-cell">Fri</div><div class="grid-header-cell">Sat</div>
        <div class="grid-header-cell">Sun</div>
        <?php
        for ($i = 1; $i < $firstDayOfMonth; $i++) { echo '<div class="grid-cell" style="background: transparent;"></div>'; }
        $todayStr = date('Y-m-d');
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDateStr = sprintf("%04d-%02d-%02d", $year, $month, $day);
            $isToday = ($currentDateStr === $todayStr) ? 'today' : '';
            echo "<div class='grid-cell $isToday'><div class='grid-date'>$day</div>";
            if (isset($schedules[$currentDateStr])) {
                foreach ($schedules[$currentDateStr] as $schedId => $event) {
                    echo "<div class='grid-items'>";
                    foreach ($event['items'] as $item) { echo "<img src='" . htmlspecialchars($item['image_path']) . "' class='grid-item-img'>"; }
                    echo "</div><div class='grid-outfit-name'>" . htmlspecialchars($event['outfit_name']) . "</div>";
                    break; 
                }
            }
            echo "</div>";
        }
        ?>
    </div>
</div>

<script>
    const wrappers = document.querySelectorAll('.rack-item-wrapper');
    wrappers.forEach(wrapper => {
        wrapper.addEventListener('mouseenter', function() {
            const cat = this.getAttribute('data-cat'), name = this.getAttribute('data-name'), slab = this.closest('.slab');
            if(slab) {
                slab.classList.add('is-focused');
                const catEl = slab.querySelector('.dynamic-category'), nameEl = slab.querySelector('.dynamic-item-name');
                if(catEl) catEl.textContent = cat; if(nameEl) nameEl.textContent = name;
            }
        });
        wrapper.addEventListener('mouseleave', function() { const slab = this.closest('.slab'); if(slab) slab.classList.remove('is-focused'); });
    });
    if(document.body.classList.contains('scrollable')) document.body.style.overflow = 'auto';

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