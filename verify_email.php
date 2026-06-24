<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/mailer.php'; // Pulls in our PHPMailer engine

$userId = $_SESSION['user_id'];

// --- 1. Handle Initial Request (Generate & Send OTP) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_email_change'])) {
    $newEmail = filter_var($_POST['new_email'], FILTER_VALIDATE_EMAIL);
    
    if (!$newEmail) {
        $_SESSION['error'] = "Please provide a valid email address.";
        header("Location: profile.php");
        exit;
    }

    // Check if email is already in use by someone else
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->execute([$newEmail, $userId]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "That email is already registered to another account.";
        header("Location: profile.php");
        exit;
    }

    // Generate secure 6-digit OTP
    $otp = sprintf("%06d", random_int(100000, 999999));
    $hashedOtp = password_hash($otp, PASSWORD_DEFAULT);
    
    // Clear any existing email change tokens for this user
    $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ? AND type = 'email_change'")->execute([$userId]);

    // Save hashed token with 15-minute expiration
    $stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, token_hash, type, new_data, expires_at) VALUES (?, ?, 'email_change', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
    
    if ($stmt->execute([$userId, $hashedOtp, $newEmail])) {
        
        // Use our new PHPMailer engine
        $subject = "Smart Wardrobe - Email Verification Code";
        $message = "<h3>Email Verification</h3>
                    <p>Your verification code to change your email to <strong>{$newEmail}</strong> is:</p>
                    <h2 style='letter-spacing: 5px; color: #8b5cf6;'>{$otp}</h2>
                    <p>This code will expire in 15 minutes. If you did not request this, please secure your account immediately.</p>";

        if (sendRealEmail($newEmail, $subject, $message)) {
            $_SESSION['success'] = "A 6-digit verification code has been sent to {$newEmail}.";
        } else {
            $_SESSION['error'] = "Failed to send email. Please check your SMTP configuration in mailer.php.";
            // Delete the useless token so they can try again immediately
            $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ? AND type = 'email_change'")->execute([$userId]);
        }
        
    } else {
        $_SESSION['error'] = "Database error generating token.";
    }
    
    header("Location: profile.php");
    exit;
}

// --- 2. Handle OTP Verification ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $submittedCode = preg_replace('/[^0-9]/', '', $_POST['otp_code']); // Sanitize to numbers only

    // Fetch the active token
    $stmt = $pdo->prepare("SELECT id, token_hash, new_data FROM auth_tokens WHERE user_id = ? AND type = 'email_change' AND expires_at > NOW()");
    $stmt->execute([$userId]);
    $tokenRecord = $stmt->fetch();

    if ($tokenRecord && password_verify($submittedCode, $tokenRecord['token_hash'])) {
        // Code is correct and valid! Update the email.
        $newEmail = $tokenRecord['new_data'];
        
        $updateStmt = $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?");
        if ($updateStmt->execute([$newEmail, $userId])) {
            
            // Delete the used token
            $pdo->prepare("DELETE FROM auth_tokens WHERE id = ?")->execute([$tokenRecord['id']]);
            
            // Log the change
            $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, activity_description) VALUES (?, 'Security', ?)");
            $logStmt->execute([$userId, "Changed primary email address to {$newEmail}"]);

            $_SESSION['success'] = "Email address successfully updated to {$newEmail}!";
        } else {
            $_SESSION['error'] = "Error updating database with new email.";
        }
    } else {
        $_SESSION['error'] = "Invalid or expired verification code.";
    }

    header("Location: profile.php");
    exit;
}

// --- 3. Cancel OTP Process ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_otp'])) {
    $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ? AND type = 'email_change'")->execute([$userId]);
    header("Location: profile.php");
    exit;
}

// Fallback if accessed directly via URL
header("Location: profile.php");
exit;
?>