<?php
session_start();
include '../includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginUser = trim((string) ($_POST['user'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($loginUser === '' || $password === '') {
        $error = 'Credentials required.';
    } else {
        $sql = "SELECT * FROM users WHERE email = ? OR username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$loginUser, $loginUser]);
        $user = $stmt->fetch();

        if ($user && !empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
            if ($user['role'] !== 'admin') {
                $error = 'Unauthorized access. Standard users must use the public portal.';
            } else {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                header("Location: index.php");
                exit;
            }
        } else {
            $error = 'Invalid credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Portal - Smart Wardrobe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0; padding: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: 'Inter', sans-serif;
            background: #4c1d95; /* Deep Purple */
        }
        
        .admin-card {
            background: #ffffff;
            border-radius: 0;
            padding: 48px 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            text-align: center;
        }

        .brand-header { font-size: 1.5rem; font-weight: 700; color: #000; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; }
        .brand-sub { font-size: 0.75rem; color: #8b5cf6; text-transform: uppercase; letter-spacing: 0.15em; font-weight: 700; margin-bottom: 40px; }

        .admin-input { width: 100%; padding: 14px 16px; margin-bottom: 16px; background: #fcfcfc; border: 1px solid #e5e5e5; font-family: inherit; font-size: 0.85rem; outline: none; transition: border-color 0.2s; font-weight: 500; }
        .admin-input:focus { border-color: #8b5cf6; background: #ffffff; }

        .admin-btn { width: 100%; padding: 14px; margin-bottom: 24px; background: #8b5cf6; color: #ffffff; border: none; font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; transition: background 0.2s; }
        .admin-btn:hover { background: #7c3aed; }

        .back-link { display: inline-flex; align-items: center; gap: 8px; color: #999; text-decoration: none; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; transition: color 0.2s; }
        .back-link:hover { color: #000; }

        .msg-error { background: #000; color: #fff; padding: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 24px; }
    </style>
</head>
<body>

    <div class="admin-card">
        <div class="brand-header">Smart Wardrobe</div>
        <div class="brand-sub">Admin Gateway</div>
        
        <?php if ($error): ?>
            <div class="msg-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input class="admin-input" type="text" name="user" placeholder="Admin Identity" required>
            <input class="admin-input" type="password" name="password" placeholder="Passcode" required>
            
            <button class="admin-btn" type="submit">Authenticate</button>

            <a href="../login.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Public Portal
            </a>
        </form>
    </div>

</body>
</html>