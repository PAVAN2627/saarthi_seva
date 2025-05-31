<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';
require 'vendor/autoload.php'; // adjust path if needed (e.g., '../vendor/autoload.php')

function sendMail($to, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // or your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'pavanmalith3@gmail.com'; // replace with your email
        $mail->Password   = 'aiyq ydhn bltc wfqq';    // Gmail App Password (not your Gmail password!)
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('pavanmalith3@gmail.com', 'Saarthi Seva');
        $mail->addAddress($to);     // recipient
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mail error: ' . $mail->ErrorInfo);
        return false;
    }
}
