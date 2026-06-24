<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

function sendRealEmail($toAddress, $subject, $bodyHTML) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'itegi.njuguna@gmail.com';
        $mail->Password   = 'zhsazzrcblqpvphg'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('itegi.njuguna@gmail.com', 'Smart Wardrobe Admin');
        $mail->addAddress($toAddress);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHTML;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $bodyHTML));

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error if needed: $mail->ErrorInfo
        return false;
    }
}