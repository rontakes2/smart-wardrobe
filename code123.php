<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'USER';
$success = '';
$error = '';

$stmtUser = $pdo->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
$stmtUser->execute([$userId]);
$userRow = $stmtUser->fetch();
$pfpPath = !empty($userRow['profile_picture']) ? $userRow['profile_picture'] : 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=000000&color=ffffff';

$viewMode = $_GET['view'] ?? 'slab';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_outfit'])) {
    $outfitId = (int)$_POST['outfit_id'];
    $scheduleDate = $_POST['schedule_date'];
    if ($outfitId > 0 && !empty($scheduleDate)) {
        try {
            $pdo->prepare("INSERT INTO calendar_schedule (user_id, outfit_id, schedule_date) VALUES (?, ?, ?)")->execute([$userId, $outfitId, $scheduleDate]);
            $success = "Outfit scheduled.";
        } catch (PDOException $e) {
            $error = "Date slot already occupied.";
        }
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
        * { box-sizing: border-box; }
        body { background-color: #fcfcfc; color: #000000; margin: 0; padding: 0; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; flex-direction: column; overflow-x: hidden; transition: background-color 0.3s, color 0.3s; }
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

        .control-panel { background: #ffffff; border-bottom: 1px solid #e5e5e5; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; position: relative; z-index: 90; transition: background-color 0.3s, border-color 0.3s; }
        .date-nav { display: flex; align-items: center; gap: 24px; }
        .date-nav a { color: #666; font-size: 1rem; transition: color 0.2s; }
        .date-nav a:hover { color: #000; }
        .date-nav h2 { margin: 0; font-size: 1.1rem; font-weight: 600; min-width: 180px; text-align: center; letter-spacing: 0.05em; text-transform: uppercase; }

        .view-toggles { display: flex; gap: 8px; border: 1px solid #e5e5e5; padding: 4px; background: #fcfcfc; border-radius: 4px; }
        .view-btn { padding: 6px 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; color: #666; transition: all 0.2s; }
        .view-btn.active, .view-btn:hover { background: #000; color: #fff; }

        .slot-form { display: flex; gap: 12px; align-items: center; margin: 0; }
        .clean-input, .clean-select { padding: 8px 12px; border: 1px solid #ccc; font-size: 0.8rem; outline: none; background: #fff; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; transition: border-color 0.2s, background-color 0.3s; border-radius: 0; }
        .clean-input:focus, .clean-select:focus { border-color: #000; }
        .solid-btn { background: #000; color: #fff; border: 1px solid #000; padding: 8px 16px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; border-radius: 0; }
        .solid-btn:hover { background: #fff; color: #000; }

        .msg-bar { text-align: center; padding: 8px; font-size: 0.8rem; font-weight: 500; flex-shrink: 0; margin: 0; text-transform: uppercase; letter-spacing: 0.05em; }
        .msg-success { background: #fff; color: #000; border-bottom: 1px solid #000; }
        .msg-error { background: #000; color: #fff; }

        /* SLAB VIEW */
        .slab-stage { flex-grow: 1; width: 100%; max-width: 1100px; margin: 24px auto; padding: 0 24px 24px 24px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; position: relative; z-index: 10; min-height: 0; <?php if($viewMode === 'grid') echo 'display: none;'; ?> }
        .slab { background: #fff; border: 1px solid #e5e5e5; height: 100%; display: flex; flex-direction: column; position: relative; transition: border-color 0.3s ease, background-color 0.3s ease; }
        .slab-today { border-color: #000; box-shadow: 0 4px 20px rgba(0,0,0,0.04); z-index: 20; }
        .slab-header { padding: 20px; text-align: center; border-bottom: 1px solid #e5e5e5; flex-shrink: 0; height: 90px; display: flex; flex-direction: column; justify-content: center; transition: border-color 0.3s; }
        .slab-date-default { transition: opacity 0.2s; }
        .slab-title { font-size: 0.65rem; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.15em; margin: 0 0 4px 0; }
        .slab-today .slab-title { color: #000; }
        .slab-day { font-size: 1rem; font-weight: 600; color: #000; margin: 0; letter-spacing: 0.02em; text-transform: uppercase; }
        .dynamic-category { display: none; font-size: 0.8rem; font-weight: 700; color: #000; text-transform: uppercase; letter-spacing: 0.15em; }

        .slab-center-rack { flex-grow: 1; padding: 24px; display: flex; flex-direction: column; position: relative; overflow: hidden; }
        .outfit-cluster { width: 100%; height: 100%; display: flex; flex-direction: column; }
        .rack-images { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; width: 100%; align-content: start; flex-grow: 1; }
        .rack-item-wrapper { position: relative; display: flex; justify-content: center; align-items: center; width: 100%; aspect-ratio: 3/4; cursor: crosshair; }
        .rack-item-img { width: 100%; height: 100%; max-height: 24vh; object-fit: contain; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.08)); transition: transform 0.3s ease, filter 0.3s ease, opacity 0.3s ease; }
        .outfit-cluster:hover .rack-item-img { opacity: 0.4; filter: grayscale(100%); }
        .outfit-cluster .rack-item-wrapper:hover .rack-item-img { opacity: 1; filter: grayscale(0%) drop-shadow(0 10px 20px rgba(0,0,0,0.15)); transform: scale(1.05); z-index: 10; }

        .slab-footer { padding: 16px 20px; border-top: 1px solid #e5e5e5; flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; height: 60px; background: #fff; transition: background-color 0.3s, border-color 0.3s; }
        .footer-text-container { flex-grow: 1; overflow: hidden; }
        .default-outfit-name { font-size: 0.75rem; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
        .dynamic-item-name { display: none; font-size: 0.85rem; font-weight: 700; color: #000; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .event-del-btn { background: transparent; border: none; color: #ccc; cursor: pointer; font-size: 1rem; transition: color 0.2s; padding: 0 0 0 16px; }
        .event-del-btn:hover { color: #000; }
        .empty-rack { color: #ccc; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; gap: 12px; }

        .is-focused .slab-date-default { display: none; }
        .is-focused .dynamic-category { display: block; }
        .is-focused .default-outfit-name { display: none; }
        .is-focused .dynamic-item-name { display: block; }

        /* GRID VIEW */
        .grid-stage { width: 100%; max-width: 1200px; margin: 24px auto; padding: 0 24px 60px 24px; position: relative; z-index: 10; overflow-y: auto; <?php if($viewMode !== 'grid') echo 'display: none;'; ?> }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #e5e5e5; border: 1px solid #e5e5e5; width: 100%; transition: border-color 0.3s; }
        .grid-header-cell { background: #fcfcfc; text-align: center; padding: 12px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #666; transition: background-color 0.3s; }
        .grid-cell { background: #fff; min-height: 140px; padding: 12px; display: flex; flex-direction: column; transition: background 0.2s; }
        .grid-cell:hover { background: #f9f9f9; }
        .grid-cell.today { background: #f5f5f5; box-shadow: inset 0 0 0 2px #000; }
        .grid-date { font-size: 0.85rem; font-weight: 600; color: #000; margin-bottom: 8px; text-align: right; }
        .grid-items { display: flex; flex-wrap: wrap; gap: 4px; justify-content: center; align-items: center; flex-grow: 1; }
        .grid-item-img { max-height: 45px; width: auto; object-fit: contain; filter: grayscale(100%) drop-shadow(0 2px 4px rgba(0,0,0,0.1)); transition: filter 0.2s, transform 0.2s; }
        .grid-cell:hover .grid-item-img { filter: grayscale(0%); transform: scale(1.1); }
        .grid-outfit-name { font-size: 0.6rem; font-weight: 700; text-align: center; margin-top: auto; text-transform: uppercase; background: #000; color: #fff; padding: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0.05em; }

        /* STRICT DARK MODE INVERSION */
        body.dark-mode { background-color: #0a0a0a; color: #ffffff; }
        body.dark-mode .topnav, body.dark-mode .control-panel, body.dark-mode .slab, 
        body.dark-mode .slab-footer, body.dark-mode .grid-header-cell, body.dark-mode .grid-cell { background-color: #0a0a0a; border-color: #333333; color: #ffffff; }
        body.dark-mode .brand, body.dark-mode .theme-btn:hover, body.dark-mode .topnav a:hover, body.dark-mode .topnav a.active, body.dark-mode .slab-day, body.dark-mode .dynamic-category, body.dark-mode .dynamic-item-name, body.dark-mode .grid-date { color: #ffffff; }
        body.dark-mode .topnav a, body.dark-mode .theme-btn, body.dark-mode .slab-title, body.dark-mode .default-outfit-name, body.dark-mode .grid-header-cell { color: #888888; }
        body.dark-mode .nav-actions, body.dark-mode .nav-pfp, body.dark-mode .slab-header, body.dark-mode .calendar-grid { border-color: #333333; }
        body.dark-mode .clean-input, body.dark-mode .clean-select { background-color: #111111; border-color: #333333; color: #ffffff; }
        body.dark-mode .clean-input:focus, body.dark-mode .clean-select:focus { border-color: #ffffff; }
        body.dark-mode .solid-btn { background-color: #ffffff; color: #000000; border-color: #ffffff; }
        body.dark-mode .solid-btn:hover { background-color: #cccccc; }
        body.dark-mode .view-toggles { background: #111; border-color: #333; }
        body.dark-mode .view-btn:hover, body.dark-mode .view-btn.active { background: #fff; color: #000; }
        body.dark-mode .grid-cell:hover { background: #1a1a1a; }
        body.dark-mode .grid-cell.today { background: #111; box-shadow: inset 0 0 0 2px #fff; }
        body.dark-mode .grid-outfit-name { background: #ffffff; color: #000000; }
    </style>
</head>
<body class="<?= $viewMode === 'grid' ? 'scrollable' : '' ?>">

<div class="topnav">
    <span class="brand">Smart Wardrobe</span>
    <div class="nav-links">
        <a href="index.php">Overview</a>
        <a href="kal.php">Kal AI</a>
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

<?php if ($success): ?><div class="msg-bar msg-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="msg-bar msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

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
                    <div class="empty-rack">No outfit scheduled</div>
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
                        <span class="default-outfit-name" style="color:#ccc;">EMPTY</span>
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

    // GLOBAL THEME ENGINE
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