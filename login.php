<?php
session_start();
include 'includes/db.php';

$error = '';
$success = '';

if (isset($_GET['registered']) && $_GET['registered'] == 'true') {
    $success = 'Account created successfully. Please log in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Please enter your credentials.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'] ?? 'user';
            
            // Critical Theme Separation Engine
            $_SESSION['user_gender'] = $user['gender'] ?? 'Unspecified';
            
            header("Location: index.php");
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Smart Wardrobe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { background-color: #fcfcfc; color: #000000; margin: 0; padding: 0; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; }
        
        .auth-container { width: 100%; max-width: 420px; padding: 48px 40px; background: #ffffff; border: 1px solid #e5e5e5; box-shadow: 0 10px 30px rgba(0,0,0,0.02); text-align: center; position: relative; z-index: 10; }
        
        .brand-logo { font-size: 1.5rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; margin: 0 0 8px 0; }
        .brand-sub { font-size: 0.75rem; color: #999999; letter-spacing: 0.1em; text-transform: uppercase; margin: 0 0 40px 0; }
        
        .clean-input { width: 100%; padding: 14px 16px; border: 1px solid #cccccc; font-size: 0.85rem; margin-bottom: 16px; outline: none; transition: border-color 0.2s; font-family: inherit; background: #fcfcfc; font-weight: 500; border-radius: 0; }
        .clean-input:focus { border-color: #000000; background: #ffffff; }
        .clean-input::placeholder { color: #999; }
        
        .solid-btn { width: 100%; background: #000000; color: #ffffff; border: 1px solid #000000; padding: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 24px; border-radius: 0; }
        .solid-btn:hover { background: #ffffff; color: #000000; }
        
        .auth-links { display: flex; justify-content: space-between; font-size: 0.75rem; border-top: 1px solid #e5e5e5; padding-top: 24px; }
        .auth-links a { color: #666666; text-decoration: none; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; transition: color 0.2s; }
        .auth-links a:hover { color: #000000; }

        .msg-bar { padding: 12px; font-size: 0.75rem; font-weight: 600; margin-bottom: 24px; text-transform: uppercase; letter-spacing: 0.05em; border-radius: 4px; }
        .msg-success { background: #fcfcfc; color: #000000; border: 1px solid #000000; }
        .msg-error { background: #000000; color: #ffffff; }

        .admin-footer { position: absolute; bottom: 24px; width: 100%; text-align: center; z-index: 5; }
        .admin-link { color: #991b1b; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.15em; text-decoration: none; font-weight: 700; transition: color 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 6px; opacity: 0.8; }
        .admin-link:hover { color: #dc2626; opacity: 1; }
    </style>
</head>
<body>

    <div class="auth-container">
        <h1 class="brand-logo">Smart Wardrobe</h1>
        <p class="brand-sub">Account Login</p>

        <?php if ($success): ?><div class="msg-bar msg-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg-bar msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST">
            <input class="clean-input" type="text" name="identifier" placeholder="Email or Username" required>
            <input class="clean-input" type="password" name="password" placeholder="Password" required>
            
            <button class="solid-btn" type="submit" name="login">Log In</button>
            
            <div class="auth-links">
                <a href="forgot_password.php">Forgot Password?</a>
                <a href="register.php">Create Account</a>
            </div>
        </form>
    </div>

    <div class="admin-footer">
        <a href="admin/login.php" class="admin-link">Secure Admin Access <i class="fas fa-lock"></i></a>
    </div>

</body>
</html>