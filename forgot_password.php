<?php
session_start();
include 'includes/db.php';
include 'includes/mailer.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_request'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

    if ($email) {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $hashedToken = password_hash($token, PASSWORD_DEFAULT);
            $userId = $user['user_id'];

            $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ? AND type = 'password_reset'")->execute([$userId]);

            $stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, token_hash, type, expires_at) VALUES (?, ?, 'password_reset', DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
            $stmt->execute([$userId, $hashedToken]);

            $resetLink = "http://localhost:3000/reset_password.php?token=" . $token . "&id=" . $userId;

            $emailBody = "<h3>Password Reset Request</h3>
                          <p>We received a request to reset your password. Click the link below to set a new one. This link expires in 15 minutes.</p>
                          <a href='{$resetLink}' style='display:inline-block;padding:12px 24px;background:#000;color:#fff;text-decoration:none;font-weight:bold;letter-spacing:1px;text-transform:uppercase;'>Reset Password</a>
                          <p>If you didn't request this, ignore this email.</p>";

            if (sendRealEmail($email, "Password Reset - Smart Wardrobe", $emailBody)) {
                $success = "If the email exists, a reset link has been sent.";
            } else {
                $error = "Failed to send email. Check SMTP settings.";
            }
        } else {
            $success = "If the email exists, a reset link has been sent.";
        }
    } else {
        $error = "Invalid email format.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Smart Wardrobe</title>
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

        .msg-bar { padding: 12px; font-size: 0.75rem; font-weight: 600; margin-bottom: 24px; text-transform: uppercase; letter-spacing: 0.05em; }
        .msg-success { background: #ffffff; color: #000000; border: 1px solid #000000; }
        .msg-error { background: #000000; color: #ffffff; }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1 class="brand-logo">Smart Wardrobe</h1>
        <p class="brand-sub">Reset Password</p>

        <?php if ($success): ?><div class="msg-bar msg-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg-bar msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST">
            <input class="clean-input" type="email" name="email" placeholder="Email Address" required>
            <button class="solid-btn" type="submit" name="reset_request">Send Reset Link</button>
            <div class="auth-links">
                <a href="login.php">Back to Login</a>
            </div>
        </form>
    </div>
</body>
</html>