<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$userId = $_SESSION['user_id'];
$success = ''; 
$error = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $confirmEmail = trim($_POST['confirm_email']);
    $newPass = $_POST['new_password'];
    $confirmPass = $_POST['confirm_new_password'];
    $profilePicPath = $user['profile_picture'];

    if ($email !== $confirmEmail) {
        $error = "Email addresses do not match.";
    } elseif (!empty($newPass) && $newPass !== $confirmPass) {
        $error = "New passwords do not match.";
    } elseif (!empty($newPass) && strlen($newPass) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $newPicName = $uploadDir . 'pfp_' . $userId . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $newPicName)) {
                    if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) { unlink($user['profile_picture']); }
                    $profilePicPath = $newPicName;
                }
            } else { $error = "Invalid image format. Please use JPG, PNG, or WEBP."; }
        }

        if (empty($error)) {
            try {
                if (!empty($newPass)) {
                    $hash = password_hash($newPass, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET username=?, email=?, password_hash=?, profile_picture=? WHERE user_id=?")->execute([$username, $email, $hash, $profilePicPath, $userId]);
                } else {
                    $pdo->prepare("UPDATE users SET username=?, email=?, profile_picture=? WHERE user_id=?")->execute([$username, $email, $profilePicPath, $userId]);
                }
                $_SESSION['username'] = $username;
                $success = "Settings updated successfully.";
                $user['username'] = $username;
                $user['email'] = $email;
                $user['profile_picture'] = $profilePicPath;
            } catch (Exception $e) { $error = "Update failed. Username or email may already be in use."; }
        }
    }
}

$displayPfp = $user['profile_picture'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($user['username']) . '&background=000&color=fff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings - Smart Wardrobe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { background-color: #fcfcfc; color: #000000; margin: 0; padding: 0; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; flex-direction: column; transition: background-color 0.3s, color 0.3s; }
        a { text-decoration: none; color: inherit; }
        button, input { font-family: inherit; }

        .topnav { background: #ffffff; border-bottom: 1px solid #e5e5e5; padding: 12px 32px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; transition: background-color 0.3s, border-color 0.3s; }
        .brand { font-size: 1.1rem; font-weight: 700; letter-spacing: -0.02em; text-transform: uppercase; color: #000; }
        .nav-links { display: flex; gap: 32px; align-items: center; }
        .nav-links a { font-weight: 500; font-size: 0.85rem; color: #666; transition: color 0.2s; text-transform: uppercase; letter-spacing: 0.05em; }
        .nav-links a:hover, .nav-links a.active { color: #000; font-weight: 700; }
        .nav-actions { display: flex; align-items: center; gap: 16px; margin-left: 16px; padding-left: 24px; border-left: 1px solid #e5e5e5; }
        .nav-pfp { width: 28px; height: 28px; object-fit: cover; border: 1px solid #000; border-radius: 50%; }
        .theme-btn { background: transparent; border: none; font-size: 0.85rem; font-weight: 700; color: #666; cursor: pointer; text-transform: uppercase; letter-spacing: 0.05em; transition: color 0.2s; }
        .theme-btn:hover { color: #000; }

        .control-panel { background: #ffffff; border-bottom: 1px solid #e5e5e5; padding: 24px 32px; display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 90; transition: background-color 0.3s, border-color 0.3s; }
        .engine-title { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #000; margin: 0; }
        
        .msg-bar { text-align: center; padding: 12px; font-size: 0.8rem; font-weight: 600; margin: 0; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e5e5e5; }
        .msg-success { background: #ffffff; color: #000; }
        .msg-error { background: #000000; color: #ffffff; }

        .stage { flex-grow: 1; width: 100%; max-width: 700px; margin: 0 auto; padding: 60px 32px; }
        .settings-form { background: #ffffff; border: 1px solid #e5e5e5; padding: 40px; display: flex; flex-direction: column; gap: 24px; transition: background-color 0.3s, border-color 0.3s; }
        .input-group { display: flex; flex-direction: column; gap: 8px; width: 100%; }
        .input-group label { font-size: 0.75rem; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.1em; }
        .clean-input { width: 100%; padding: 14px 16px; border: 1px solid #cccccc; font-size: 0.85rem; outline: none; background: #fcfcfc; font-weight: 500; transition: border-color 0.2s, background-color 0.3s; border-radius: 0; }
        .clean-input:focus { border-color: #000000; background: #ffffff; }
        .clean-file { font-size: 0.8rem; outline: none; background: #ffffff; cursor: pointer; color: #666; transition: background-color 0.3s; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        .solid-btn { background: #000000; color: #ffffff; border: 1px solid #000000; padding: 16px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; margin-top: 16px; border-radius: 0; }
        .solid-btn:hover { background: #ffffff; color: #000000; }

        /* STRICT DARK MODE */
        body.dark-mode { background-color: #0a0a0a; color: #ffffff; }
        body.dark-mode .topnav, body.dark-mode .control-panel, body.dark-mode .settings-form { background-color: #0a0a0a; border-color: #333333; color: #ffffff; }
        body.dark-mode .brand, body.dark-mode .engine-title, body.dark-mode .theme-btn:hover, body.dark-mode .topnav a:hover, body.dark-mode .topnav a.active { color: #ffffff; }
        body.dark-mode .topnav a, body.dark-mode .theme-btn { color: #888888; }
        body.dark-mode .nav-actions, body.dark-mode .nav-pfp { border-color: #333333; }
        body.dark-mode .clean-input, body.dark-mode .clean-file { background-color: #111111; border-color: #333333; color: #ffffff; }
        body.dark-mode .clean-input:focus { border-color: #ffffff; }
        body.dark-mode .solid-btn { background-color: #ffffff; color: #000000; border-color: #ffffff; }
        body.dark-mode .solid-btn:hover { background-color: #cccccc; }
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
            <a href="profile.php" class="active">Settings</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="control-panel">
    <div>
        <p class="engine-title">Account Settings</p>
    </div>
</div>

<?php if ($success): ?><div class="msg-bar msg-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="msg-bar msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="stage">
    <form method="POST" enctype="multipart/form-data" class="settings-form">
        <div class="input-group">
            <label>Profile Picture</label>
            <div style="display: flex; align-items: center; gap: 16px;">
                <img src="<?= htmlspecialchars($displayPfp) ?>" alt="Profile" style="width: 64px; height: 64px; object-fit: cover; border: 1px solid #e5e5e5; border-radius: 50%;">
                <input type="file" name="profile_picture" accept="image/*" class="clean-file">
            </div>
        </div>

        <div class="input-group" style="margin-top: 16px;">
            <label>Username</label>
            <input type="text" name="username" class="clean-input" value="<?= htmlspecialchars($user['username']) ?>" required>
        </div>

        <div class="form-row">
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" class="clean-input" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>
            <div class="input-group">
                <label>Confirm Email</label>
                <input type="email" name="confirm_email" class="clean-input" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>
        </div>

        <div class="form-row" style="margin-top: 24px; padding-top: 24px; border-top: 1px dashed #e5e5e5;">
            <div class="input-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="clean-input" placeholder="Leave blank to keep current">
            </div>
            <div class="input-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_new_password" class="clean-input" placeholder="Confirm new password">
            </div>
        </div>

        <button type="submit" name="update_profile" class="solid-btn">Save Changes</button>
    </form>
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