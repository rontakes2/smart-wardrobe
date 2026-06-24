<?php
include 'includes/db.php';
$error = '';

if (isset($_POST['register'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $confirm_email = trim($_POST['confirm_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $gender = $_POST['gender'] ?? 'Unspecified';

    if (!in_array($gender, ['Male', 'Female', 'Unspecified'])) $gender = 'Unspecified';

    if ($username === '' || $email === '' || $confirm_email === '' || $password === '' || $confirm_password === '') {
        $error = 'All fields are required.';
    } elseif ($email !== $confirm_email) {
        $error = 'Email addresses do not match.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, email, password_hash, gender) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        try {
            $stmt->execute([$username, $email, $passwordHash, $gender]);
            header("Location: login.php?registered=true");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'That username or email is already taken.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Smart Wardrobe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { background-color: #fcfcfc; color: #000000; margin: 0; padding: 0; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; position: relative; }
        
        .auth-container { width: 100%; max-width: 480px; padding: 48px 40px; background: #ffffff; border: 1px solid #e5e5e5; box-shadow: 0 10px 30px rgba(0,0,0,0.02); text-align: center; position: relative; z-index: 10; }
        
        .brand-logo { font-size: 1.5rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; margin: 0 0 8px 0; }
        .brand-sub { font-size: 0.75rem; color: #999999; letter-spacing: 0.1em; text-transform: uppercase; margin: 0 0 40px 0; }
        
        .clean-input { width: 100%; padding: 14px 16px; border: 1px solid #cccccc; font-size: 0.85rem; margin-bottom: 16px; outline: none; transition: border-color 0.2s; font-family: inherit; background: #fcfcfc; font-weight: 500; border-radius: 0; }
        .clean-input:focus { border-color: #000000; background: #ffffff; }
        .clean-input::placeholder { color: #999; }
        
        .input-group { display: flex; gap: 16px; }
        
        .solid-btn { width: 100%; background: #000000; color: #ffffff; border: 1px solid #000000; padding: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; margin-top: 8px; margin-bottom: 24px; border-radius: 0; }
        .solid-btn:hover { background: #ffffff; color: #000000; }
        
        .auth-links { font-size: 0.75rem; border-top: 1px solid #e5e5e5; padding-top: 24px; }
        .auth-links a { color: #666666; text-decoration: none; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; transition: color 0.2s; }
        .auth-links a:hover { color: #000000; }

        .msg-bar { padding: 12px; font-size: 0.75rem; font-weight: 600; margin-bottom: 24px; text-transform: uppercase; letter-spacing: 0.05em; border-radius: 4px; }
        .msg-error { background: #000000; color: #ffffff; }

        .admin-footer { position: absolute; bottom: 24px; width: 100%; text-align: center; z-index: 5; }
        .admin-link { color: #991b1b; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.15em; text-decoration: none; font-weight: 700; transition: color 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 6px; opacity: 0.8; }
        .admin-link:hover { color: #dc2626; opacity: 1; }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1 class="brand-logo">Smart Wardrobe</h1>
        <p class="brand-sub">Create Account</p>

        <?php if ($error): ?><div class="msg-bar msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST">
            <input class="clean-input" type="text" name="username" placeholder="Username" required>
            
            <div class="input-group">
                <input class="clean-input" type="email" name="email" placeholder="Email Address" required>
                <input class="clean-input" type="email" name="confirm_email" placeholder="Confirm Email" required>
            </div>

            <div class="input-group">
                <input class="clean-input" type="password" name="password" placeholder="Password" required>
                <input class="clean-input" type="password" name="confirm_password" placeholder="Confirm Password" required>
            </div>

            <select name="gender" class="clean-input" required style="cursor: pointer; color: #666;">
                <option value="" disabled selected>Sex</option>
                <option value="Female">F</option>
                <option value="Male">M</option>
                <option value="Unspecified">Prefer Not to Say (Monochrome)</option>
            </select>

            <button class="solid-btn" type="submit" name="register">Create Account</button>
            
            <div class="auth-links">
                <a href="login.php">Back to Login</a>
            </div>
        </form>
    </div>

    <div class="admin-footer">
        <a href="admin/login.php" class="admin-link">Secure Admin Access <i class="fas fa-lock"></i></a>
    </div>
</body>
</html>