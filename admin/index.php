<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
require_once __DIR__ . '/../includes/db.php';

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
$username = $_SESSION['username'] ?? 'Admin';

if ($role !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$hour = date('H');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';

$stmtUser = $pdo->prepare("SELECT profile_picture, username FROM users WHERE user_id = ?");
$stmtUser->execute([$userId]);
$userRow = $stmtUser->fetch();
$pfpPath = !empty($userRow['profile_picture']) ? '../' . $userRow['profile_picture'] : 'https://ui-avatars.com/api/?name=' . urlencode($userRow['username']) . '&background=8b5cf6&color=fff';

$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalClothes = $pdo->query("SELECT COUNT(*) FROM clothing_items")->fetchColumn();
$totalSystemOutfits = $pdo->query("SELECT COUNT(*) FROM outfits")->fetchColumn();

$stmtCats = $pdo->query("SELECT cat.category_name, COUNT(c.item_id) as item_count FROM categories cat LEFT JOIN clothing_items c ON cat.category_id = c.category_id GROUP BY cat.category_id, cat.category_name");
$categoryStats = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

$stmtActivity = $pdo->query("SELECT DATE(created_at) as log_date, COUNT(log_id) as activity_count FROM activity_logs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY log_date ASC");
$activityStats = $stmtActivity->fetchAll(PDO::FETCH_ASSOC);

$chartDates = []; $chartCounts = []; $activityMap = [];
foreach ($activityStats as $stat) { $activityMap[$stat['log_date']] = $stat['activity_count']; }
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartDates[] = date('M d', strtotime($date));
    $chartCounts[] = $activityMap[$date] ?? 0;
}

$stmtLogs = $pdo->query("SELECT a.log_id, a.activity_type, a.activity_description, a.created_at, u.username FROM activity_logs a LEFT JOIN users u ON a.user_id = u.user_id ORDER BY a.created_at DESC LIMIT 100");
$logs = $stmtLogs->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Overview - Smart Wardrobe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        * { box-sizing: border-box; }
        body { background-color: #f8fafc; color: #0f172a; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        a { text-decoration: none; color: inherit; }

        /* Purple Admin Topnav */
        .topnav { background: #ffffff; border-bottom: 2px solid #8b5cf6; padding: 12px 32px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
        .brand { font-size: 1.1rem; font-weight: 700; letter-spacing: -0.02em; text-transform: uppercase; color: #000; }
        .brand span { color: #8b5cf6; font-weight: 800; }
        .nav-links { display: flex; align-items: center; gap: 32px; }
        .nav-links a { font-weight: 600; font-size: 0.85rem; color: #64748b; transition: color 0.2s; text-transform: uppercase; letter-spacing: 0.05em; }
        .nav-links a:hover, .nav-links a.active { color: #8b5cf6; }
        .nav-actions { display: flex; align-items: center; gap: 16px; margin-left: 16px; padding-left: 24px; border-left: 1px solid #e2e8f0; }
        .nav-pfp { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid #8b5cf6; }

        .admin-container { max-width: 1400px; margin: 40px auto; padding: 0 32px; }
        .admin-header { margin-bottom: 32px; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; }
        .admin-greeting { font-size: 1.8rem; font-weight: 700; color: #0f172a; margin: 0 0 8px 0; letter-spacing: -0.02em; }
        .admin-subtitle { font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; margin: 0; }

        .admin-stats-ribbon { display: flex; gap: 24px; margin-bottom: 32px; }
        .stat-badge { background: #ffffff; border: 1px solid #e2e8f0; border-left: 4px solid #8b5cf6; padding: 24px; flex: 1; display: flex; flex-direction: column; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .stat-badge-title { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: #64748b; font-weight: 700; margin-bottom: 8px; }
        .stat-badge-value { font-size: 2.5rem; font-weight: 700; color: #0f172a; line-height: 1; }

        .chart-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 40px; }
        .chart-card { background: #ffffff; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .chart-card h3 { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; color: #0f172a; font-weight: 700; margin: 0 0 24px 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; }
        .canvas-container { position: relative; height: 300px; width: 100%; }

        .activity-section { background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .activity-header { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #0f172a; padding: 20px 24px; margin: 0; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .log-scroller { max-height: 500px; overflow-y: auto; }
        
        .compact-log-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left; }
        .compact-log-table th { padding: 12px 24px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.75rem; border-bottom: 1px solid #e2e8f0; background: #ffffff; position: sticky; top: 0; }
        .compact-log-table td { padding: 14px 24px; border-bottom: 1px solid #e2e8f0; color: #0f172a; }
        .compact-log-table tr:hover td { background-color: #f5f3ff; } /* Purple tint on hover */
        
        .log-col-time { width: 15%; color: #64748b; }
        .log-col-user { width: 20%; font-weight: 600; color: #8b5cf6; }
        .log-col-type { width: 15%; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
        .log-col-desc { width: 50%; }

        /* Type Colors */
        .type-security { color: #ef4444; } 
        .type-delete { color: #f97316; } 
        .type-upload { color: #10b981; } 
        .type-update { color: #3b82f6; } 
        .type-create { color: #8b5cf6; }
    </style>
</head>
<body>

<div class="topnav">
    <span class="brand">Smart Wardrobe <span>Admin</span></span>
    <div class="nav-links">
        <a href="index.php" class="active">Overview</a>
        <a href="users.php">Manage Users</a>
        
        <div class="nav-actions">
            <img src="<?= htmlspecialchars($pfpPath) ?>" alt="Profile" class="nav-pfp">
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</div>

<div class="admin-container">
    <header class="admin-header">
        <h1 class="admin-greeting"><?= $greeting ?>, <?= htmlspecialchars($username) ?>.</h1>
        <p class="admin-subtitle">System Telemetry & Activity Metrics</p>
    </header>

    <div class="admin-stats-ribbon">
        <div class="stat-badge"><span class="stat-badge-title">Registered Users</span><span class="stat-badge-value"><?= number_format($totalUsers) ?></span></div>
        <div class="stat-badge"><span class="stat-badge-title">Total Wardrobe Assets</span><span class="stat-badge-value"><?= number_format($totalClothes) ?></span></div>
        <div class="stat-badge"><span class="stat-badge-title">Compiled Matrices</span><span class="stat-badge-value"><?= number_format($totalSystemOutfits) ?></span></div>
    </div>

    <div class="chart-grid">
        <div class="chart-card">
            <h3>System Activity (Last 7 Days)</h3>
            <div class="canvas-container"><canvas id="activityChart"></canvas></div>
        </div>
        <div class="chart-card">
            <h3>Asset Category Distribution</h3>
            <div class="canvas-container"><canvas id="categoryChart"></canvas></div>
        </div>
    </div>

    <div class="activity-section">
        <h3 class="activity-header">System Operations Log</h3>
        <div class="log-scroller">
            <table class="compact-log-table">
                <thead><tr><th>Timestamp</th><th>User</th><th>Action Type</th><th>Description</th></tr></thead>
                <tbody>
                    <?php foreach ($logs as $log): 
                        $typeClass = 'type-' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', $log['activity_type'])[0])); 
                    ?>
                        <tr>
                            <td class="log-col-time"><?= date('M d, H:i', strtotime($log['created_at'])) ?></td>
                            <td class="log-col-user"><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                            <td class="log-col-type <?= $typeClass ?>"><?= htmlspecialchars($log['activity_type']) ?></td>
                            <td class="log-col-desc"><?= htmlspecialchars($log['activity_description']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const chartDates = <?= json_encode($chartDates) ?>;
    const chartCounts = <?= json_encode($chartCounts) ?>;
    const catLabels = <?= json_encode(array_column($categoryStats, 'category_name')) ?>;
    const catData = <?= json_encode(array_column($categoryStats, 'item_count')) ?>;
    
    // Hardcoded Purple Theme Chart Settings
    const textColor = '#64748b';
    const gridColor = 'rgba(139, 92, 246, 0.1)';

    Chart.defaults.color = textColor; 
    Chart.defaults.font.family = "'Inter', sans-serif";

    new Chart(document.getElementById('activityChart'), {
        type: 'bar',
        data: { labels: chartDates, datasets: [{ data: chartCounts, backgroundColor: '#8b5cf6', borderRadius: 4 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: gridColor }, ticks: { stepSize: 1 } }, x: { grid: { display: false } } } }
    });

    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: { labels: catLabels.length > 0 ? catLabels : ['Empty'], datasets: [{ data: catData.length > 0 ? catData : [1], backgroundColor: catData.length > 0 ? ['#8b5cf6', '#a855f7', '#c084fc', '#e879f9', '#f472b6', '#fb7185'] : [gridColor], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { position: 'bottom' } } }
    });
</script>
</body>
</html>