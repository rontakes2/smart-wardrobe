<?php
session_start();
include 'includes/db.php';

$error = '';
$token = $_GET['token'] ?? '';
$userId = $_GET['id'] ?? '';

if (!$token || !$userId) {
    die("Invalid or missing reset token.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        // Validate Token
        $stmt = $pdo->prepare("SELECT id, token_hash FROM auth_tokens WHERE user_id = ? AND type = 'password_reset' AND expires_at > NOW()");
        $stmt->execute([$userId]);
        $record = $stmt->fetch();

        if ($record && password_verify($token, $record['token_hash'])) {
            // Update Password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")->execute([$newHash, $userId]);
            
            // Delete used token
            $pdo->prepare("DELETE FROM auth_tokens WHERE id = ?")->execute([$record['id']]);

            header("Location: login.php?reset=success");
            exit;
        } else {
            $error = "This reset link is invalid or has expired.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set New Password - Smart Wardrobe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { background-color: #fcfcfc; color: #000000; margin: 0; padding: 0; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        
        .auth-container { width: 100%; max-width: 420px; padding: 48px 40px; background: #ffffff; border: 1px solid #e5e5e5; box-shadow: 0 10px 30px rgba(0,0,0,0.02); text-align: center; }
        
        .brand-logo { font-size: 1.5rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; margin: 0 0 8px 0; }
        .brand-sub { font-size: 0.75rem; color: #999999; letter-spacing: 0.1em; text-transform: uppercase; margin: 0 0 40px 0; }
        
        .clean-input { width: 100%; padding: 14px 16px; border: 1px solid #cccccc; font-size: 0.85rem; margin-bottom: 16px; outline: none; transition: border-color 0.2s; font-family: inherit; background: #fcfcfc; font-weight: 500; }
        .clean-input:focus { border-color: #000000; background: #ffffff; }
        .clean-input::placeholder { color: #999; }
        
        .solid-btn { width: 100%; background: #000000; color: #ffffff; border: 1px solid #000000; padding: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 24px; }
        .solid-btn:hover { background: #ffffff; color: #000000; }
        
        .auth-links { display: flex; justify-content: center; font-size: 0.75rem; border-top: 1px solid #e5e5e5; padding-top: 24px; }
        .auth-links a { color: #666666; text-decoration: none; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; transition: color 0.2s; }
        .auth-links a:hover { color: #000000; }

        .msg-bar { padding: 12px; font-size: 0.75rem; font-weight: 600; margin-bottom: 24px; text-transform: uppercase; letter-spacing: 0.05em; border-radius: 4px; }
        .msg-error { background: #000000; color: #ffffff; }
    </style>
</head>
<body>

    <div class="auth-container">
        <h1 class="brand-logo">Smart Wardrobe</h1>
        <p class="brand-sub">Create New Password</p>

        <?php if ($error): ?><div class="msg-bar msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST">
            <input class="clean-input" type="password" name="password" placeholder="New Password (min 8 chars)" required>
            <input class="clean-input" type="password" name="confirm_password" placeholder="Confirm New Password" required>
            
            <button class="solid-btn" type="submit">Update Password</button>
            
            <div class="auth-links">
                <a href="login.php">Back to Login</a>
            </div>
        </form>
    </div>

</body>
</html>