<?php
session_start();
include 'includes/db.php';
include 'includes/mailer.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$userId = $_SESSION['user_id'];
$userGender = $_SESSION['user_gender'] ?? 'Unspecified';
$themeClass = ($userGender === 'Female') ? 'theme-female' : 'theme-monochrome';

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// --- General Info Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_general'])) {
    $username = trim($_POST['username']);
    $profilePicPath = $user['profile_picture'];

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
        } else { $error = "Invalid image format."; }
    }

    if (empty($error)) {
        try {
            $pdo->prepare("UPDATE users SET username=?, profile_picture=? WHERE user_id=?")->execute([$username, $profilePicPath, $userId]);
            $_SESSION['username'] = $username;
            $user['username'] = $username;
            $user['profile_picture'] = $profilePicPath;
            $success = "Profile updated successfully.";
        } catch (Exception $e) { $error = "Username may already be taken."; }
    }
}

// --- Password Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $newPass = $_POST['new_password'];
    $confirmPass = $_POST['confirm_new_password'];
    if (strlen($newPass) < 8) { $error = "Password must be at least 8 characters long."; } 
    elseif ($newPass !== $confirmPass) { $error = "New passwords do not match."; } 
    else {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash=? WHERE user_id=?")->execute([$hash, $userId]);
        $success = "Password updated securely.";
    }
}

// --- OTP Handlers (Unchanged logic) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_email_change'])) {
    $newEmail = filter_var($_POST['new_email'], FILTER_VALIDATE_EMAIL);
    if (!$newEmail) { $_SESSION['error'] = "Please provide a valid email address."; header("Location: profile.php"); exit; }
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->execute([$newEmail, $userId]);
    if ($stmt->fetch()) { $_SESSION['error'] = "That email is already registered to another account."; header("Location: profile.php"); exit; }
    $otp = sprintf("%06d", random_int(100000, 999999));
    $hashedOtp = password_hash($otp, PASSWORD_DEFAULT);
    $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ? AND type = 'email_change'")->execute([$userId]);
    $stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, token_hash, type, new_data, expires_at) VALUES (?, ?, 'email_change', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
    if ($stmt->execute([$userId, $hashedOtp, $newEmail])) {
        $subject = "Smart Wardrobe - Email Verification Code";
        $message = "<h3>Email Verification</h3><p>Your verification code to change your email to <strong>{$newEmail}</strong> is:</p><h2 style='letter-spacing: 5px; color: #8b5cf6;'>{$otp}</h2><p>This code will expire in 15 minutes.</p>";
        if (sendRealEmail($newEmail, $subject, $message)) { $_SESSION['success'] = "A 6-digit verification code has been sent to {$newEmail}."; } 
        else { $_SESSION['error'] = "Failed to send email. Check SMTP settings."; $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ? AND type = 'email_change'")->execute([$userId]); }
    } else { $_SESSION['error'] = "Database error generating token."; }
    header("Location: profile.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $submittedCode = preg_replace('/[^0-9]/', '', $_POST['otp_code']);
    $stmt = $pdo->prepare("SELECT id, token_hash, new_data FROM auth_tokens WHERE user_id = ? AND type = 'email_change' AND expires_at > NOW()");
    $stmt->execute([$userId]);
    $tokenRecord = $stmt->fetch();
    if ($tokenRecord && password_verify($submittedCode, $tokenRecord['token_hash'])) {
        $newEmail = $tokenRecord['new_data'];
        $updateStmt = $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?");
        if ($updateStmt->execute([$newEmail, $userId])) {
            $pdo->prepare("DELETE FROM auth_tokens WHERE id = ?")->execute([$tokenRecord['id']]);
            $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, activity_description) VALUES (?, 'Security', ?)");
            $logStmt->execute([$userId, "Changed primary email address to {$newEmail}"]);
            $_SESSION['success'] = "Email address successfully updated to {$newEmail}!";
        } else { $_SESSION['error'] = "Error updating database with new email."; }
    } else { $_SESSION['error'] = "Invalid or expired verification code."; }
    header("Location: profile.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_otp'])) {
    $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ? AND type = 'email_change'")->execute([$userId]);
    header("Location: profile.php"); exit;
}

$stmtToken = $pdo->prepare("SELECT new_data FROM auth_tokens WHERE user_id = ? AND type = 'email_change' AND expires_at > NOW()");
$stmtToken->execute([$userId]);
$pendingEmailChange = $stmtToken->fetch();

$displayPfp = $user['profile_picture'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($user['username']) . '&background=4f46e5&color=ffffff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings - Smart Wardrobe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        body { background-color: var(--bg-main); color: var(--text-main); margin: 0; padding: 0; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; flex-direction: column; transition: background-color 0.3s, color 0.3s; }
        a { text-decoration: none; color: inherit; }
        button, input { font-family: inherit; }

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

        .control-panel { background: var(--bg-panel); border-bottom: 1px solid var(--border-main); padding: 24px 32px; display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 90; transition: background-color 0.3s, border-color 0.3s; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .engine-title { font-size: 0.9rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.15em; color: var(--text-main); margin: 0; }
        
        .msg-bar { text-align: center; padding: 12px 40px; font-size: 0.85rem; font-weight: 700; margin: 0; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border-main); position: relative; }
        .msg-success { background: #10b981; color: #fff; }
        .msg-error { background: var(--danger); color: #fff; }
        .close-msg { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #fff; font-size: 1.2rem; line-height: 1; transition: opacity 0.2s; font-weight: bold; opacity: 0.7; }
        .close-msg:hover { opacity: 1; }

        .stage { flex-grow: 1; width: 100%; max-width: 700px; margin: 0 auto; padding: 60px 32px; }
        .settings-form { background: var(--bg-panel); border: 1px solid var(--border-main); padding: 40px; display: flex; flex-direction: column; gap: 24px; transition: background-color 0.3s, border-color 0.3s; margin-bottom: 32px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .form-section-title { font-size: 0.85rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.1em; margin: 0 0 16px 0; border-bottom: 2px dashed var(--border-main); padding-bottom: 12px; }

        .input-group { display: flex; flex-direction: column; gap: 8px; width: 100%; }
        .input-group label { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; }
        
        .clean-input { width: 100%; padding: 14px 16px; border: 1px solid var(--border-main); font-size: 0.85rem; outline: none; background: var(--bg-main); color: var(--text-main); font-weight: 600; transition: border-color 0.2s, background-color 0.3s; border-radius: 6px; }
        .clean-input:focus { border-color: var(--accent-solid); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        
        .clean-file { font-size: 0.8rem; outline: none; background: transparent; color: var(--text-muted); cursor: pointer; transition: background-color 0.3s; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        .solid-btn { background: var(--accent-solid); color: var(--accent-text); border: none; padding: 14px 24px; font-weight: 800; cursor: pointer; transition: all 0.2s; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; border-radius: 6px; }
        .solid-btn:hover { background: var(--accent-hover); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }
        
        .btn-text { background: transparent; border: none; font-size: 0.85rem; font-weight: 700; cursor: pointer; text-transform: uppercase; letter-spacing: 0.1em; }

        /* STRICT DARK MODE */
        body.dark-mode { background-color: #0a0a0a; color: #ffffff; }
        body.dark-mode .topnav, body.dark-mode .control-panel, body.dark-mode .settings-form { background-color: #0a0a0a; border-color: #333333; color: #ffffff; }
        body.dark-mode .brand, body.dark-mode .engine-title, body.dark-mode .theme-btn:hover, body.dark-mode .topnav a:hover, body.dark-mode .topnav a.active, body.dark-mode .form-section-title { color: #ffffff; }
        body.dark-mode .topnav a, body.dark-mode .theme-btn { color: #888888; }
        body.dark-mode .nav-actions, body.dark-mode .nav-pfp, body.dark-mode .form-section-title { border-color: #333333; }
        body.dark-mode .clean-input, body.dark-mode .clean-file { background-color: #111111; border-color: #333333; color: #ffffff; }
        body.dark-mode .clean-input:focus { border-color: #ffffff; }
        body.dark-mode .solid-btn { background-color: #ffffff; color: #000000; border-color: #ffffff; }
        body.dark-mode .solid-btn:hover { background-color: #cccccc; }
        body.dark-mode .msg-success { background: #111; border-color: #333; color: #fff; }
        body.dark-mode .close-msg { color: #f87171; }
        body.dark-mode .close-msg:hover { color: #ef4444; }
    </style>
</head>
<body class="<?= $themeClass ?>">

<div class="topnav">
    <span class="brand">Smart Wardrobe <?php if($themeClass === 'theme-female'): ?><sub style="font-size: 0.6rem; color: var(--accent-solid);">Women's</sub><?php endif; ?></span>
    <div class="nav-links">
        <a href="index.php">Overview</a>
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
    <div><p class="engine-title">Account Configurations</p></div>
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

<div class="stage">
    <form method="POST" enctype="multipart/form-data" class="settings-form">
        <h3 class="form-section-title">General Identity</h3>
        <div class="input-group">
            <label>Profile Picture</label>
            <div style="display: flex; align-items: center; gap: 16px;">
                <img src="<?= htmlspecialchars($displayPfp) ?>" alt="Profile" style="width: 64px; height: 64px; object-fit: cover; border: 1px solid var(--border-main); border-radius: 50%;">
                <input type="file" name="profile_picture" accept="image/*" class="clean-file">
            </div>
        </div>
        <div class="input-group">
            <label>Username</label>
            <input type="text" name="username" class="clean-input" value="<?= htmlspecialchars($user['username']) ?>" required>
        </div>
        <button type="submit" name="update_general" class="solid-btn" style="margin-top: 8px;">Save Profile</button>
    </form>

    <div class="settings-form">
        <h3 class="form-section-title">Security & Communication</h3>
        
        <?php if ($pendingEmailChange): ?>
            <form method="POST" style="margin-bottom: 32px; padding: 24px; background: var(--bg-main); border: 1px solid var(--border-main); border-radius: 8px;">
                <p style="font-size: 0.85rem; font-weight: 600; margin: 0 0 16px 0;">Enter the 6-digit code sent to <strong style="color: var(--accent-solid);"><?= htmlspecialchars($pendingEmailChange['new_data']) ?></strong>.</p>
                <div style="display: flex; gap: 16px; align-items: stretch;">
                    <input type="text" name="otp_code" class="clean-input" placeholder="000000" maxlength="6" required style="margin: 0; max-width: 150px; text-align: center; font-size: 1.2rem; letter-spacing: 0.2em;">
                    <button type="submit" name="verify_otp" class="solid-btn" style="margin: 0;">Verify</button>
                    <button type="submit" name="cancel_otp" class="btn-text" style="color: var(--danger);">Cancel</button>
                </div>
            </form>
        <?php else: ?>
            <form method="POST" style="margin-bottom: 32px;">
                <div class="input-group">
                    <label>Current Email: <span style="text-transform: none; font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($user['email']) ?></span></label>
                    <div style="display: flex; gap: 16px; align-items: stretch;">
                        <input type="email" name="new_email" class="clean-input" placeholder="Request New Email Address" required style="margin: 0;">
                        <button type="submit" name="request_email_change" class="solid-btn" style="margin: 0; white-space: nowrap;">Send OTP Code</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <div class="form-row" style="margin-top: 24px; padding-top: 24px; border-top: 1px dashed #e5e5e5;">
            <div class="input-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="clean-input" placeholder="Leave blank to keep current">
            </div>
            <div class="input-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_new_password" class="clean-input" placeholder="Confirm new password">
            </div>
            <button type="submit" name="update_password" class="solid-btn" style="margin-top: 8px;">Update Password</button>
        </form>
    </div>
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