<?php
include '../includes/auth.php';
include '../includes/db.php';

$error = '';
$success = '';  //.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $deleteUserId = (int) $_POST['delete_user'];

    if ($deleteUserId === $_SESSION['user_id']) {
        $error = 'You cannot delete your own account while logged in.';
    } else {
        $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = ?');
        $stmt->execute([$deleteUserId]);

        if ($stmt->rowCount() > 0) {
            $success = 'User deleted successfully.';
        } else {
            $error = 'User not found or already deleted.';
        }
    }
}

$users = $pdo->query('SELECT user_id, username, email, role, created_at FROM users ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - Smart Wardrobe Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        .admin-container { max-width: 1400px; margin: 40px auto; padding: 0 32px; }
        
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0; letter-spacing: -0.02em; }
        
        .msg-bar { padding: 12px 20px; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 24px; border-radius: 4px; }
        .msg-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .msg-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .table-wrapper { background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .admin-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; text-align: left; }
        
        .admin-table th { padding: 16px 24px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.75rem; border-bottom: 2px solid #e2e8f0; background: #f8fafc; }
        .admin-table td { padding: 16px 24px; border-bottom: 1px solid #e2e8f0; color: #475569; vertical-align: middle; }
        .admin-table tr:hover td { background-color: #f5f3ff; }
        
        .badge { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; padding: 4px 10px; border-radius: 100px; background: #f1f5f9; color: #475569; }
        .badge-admin { background: #ede9fe; color: #7c3aed; border: 1px solid #ddd6fe; }

        .delete-btn { background: transparent; color: #ef4444; border: 1px solid #ef4444; padding: 6px 12px; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: all 0.2s; border-radius: 4px; }
        .delete-btn:hover { background: #ef4444; color: #ffffff; }
    </style>
</head>
<body>

<div class="topnav">
    <span class="brand">Smart Wardrobe <span>Admin</span></span>
    <div class="nav-links">
        <a href="index.php">Overview</a>
        <a href="users.php" class="active">Manage Users</a>
        
        <div class="nav-actions">
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</div>

<div class="admin-container">
    <div class="header-flex">
        <h2 class="page-title">User Directory</h2>
    </div>

    <?php if ($success): ?><div class="msg-bar msg-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg-bar msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if (count($users) === 0): ?>
        <p style="color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.85rem;">No users found.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Access Level</th>
                        <th>Join Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td style="font-weight: 600; color: #0f172a;">#<?= htmlspecialchars($user['user_id']) ?></td>
                            <td style="font-weight: 600; color: #8b5cf6;"><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <span class="badge <?= $user['role'] === 'admin' ? 'badge-admin' : '' ?>">
                                    <?= htmlspecialchars(ucfirst($user['role'])) ?>
                                </span>
                            </td>
                            <td style="color: #94a3b8; font-size: 0.8rem;"><?= htmlspecialchars(date('M d, Y', strtotime($user['created_at']))) ?></td>
                            <td>
                                <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Purge this user permanently? This cannot be undone.');">
                                        <input type="hidden" name="delete_user" value="<?= (int) $user['user_id'] ?>">
                                        <button type="submit" class="delete-btn">Delete Account</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Current Session</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>