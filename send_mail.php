<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

function sendOTPEmail($toEmail, $otp) {
    $mail = new PHPMailer(true);

    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';  // or your mail host
        $mail->SMTPAuth   = true;
        $mail->Username   = 'pavanmalith3@gmail.com'; // your Gmail address
        $mail->Password   = 'PAVAN0281';    // Gmail App Password
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        // Sender and recipient
        $mail->setFrom('pavanmalith3@gmail.com', 'Saarthi Seva');
        $mail->addAddress($toEmail);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP for Saarthi Seva';
        $mail->Body    = "Your OTP is: <strong>$otp</strong>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
