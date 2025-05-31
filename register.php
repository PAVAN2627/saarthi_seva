<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';
require 'db.php';  // Your DB connection file

function sendOTPEmail($toEmail, $otp) {
    $mail = new PHPMailer(true);
    try {
        // SMTP server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'pavanmalith3@gmail.com'; // ✅ Replace with your Gmail
        $mail->Password = 'aiyq ydhn bltc wfqq';   // ✅ Use App Password, not Gmail password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Email content
        $mail->setFrom('pavanmalith3@gmail.com', 'Saarthi Seva');
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP for Saarthi Seva Registration';
        $mail->Body    = "<h3>Your OTP is: <b>$otp</b></h3>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Check if email already exists in DB
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Email exists
            $error = "Email already exists. Please login or use a different email.";
        } else {
            $_SESSION['email'] = $email;

            // Generate OTP
            $otp = rand(100000, 999999);
            $_SESSION['otp'] = $otp;

            // Send OTP using PHPMailer
            if (sendOTPEmail($email, $otp)) {
                header("Location: verify_otp.php");
                exit();
            } else {
                $error = "Failed to send OTP email. Please check your SMTP settings.";
            }
        }
        $stmt->close();
    } else {
        $error = "Please enter a valid email.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Register - Saarthi Seva</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;600&display=swap');

  body, html {
    margin: 0; padding: 0; height: 100%;
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #4a90e2, #50e3c2);
    display: flex;
    justify-content: center;
    align-items: center;
  }

  .container {
    display: flex;
    background: white;
    border-radius: 20px;
    box-shadow: 0 25px 50px rgba(0,0,0,0.15);
    width: 1000px;
    max-width: 95vw;
    overflow: hidden;
    animation: fadeInUp 1s ease forwards;
    opacity: 0;
  }

  @keyframes fadeInUp {
    to {
      opacity: 1;
      transform: translateY(0);
    }
    from {
      opacity: 0;
      transform: translateY(30px);
    }
  }

  .left-side {
    width: 45%;
    background: url('uploads/saarthi_seva.jpg') center center/cover no-repeat;
    position: relative;
    animation: slideInLeft 1s ease forwards;
    min-height: 500px;
  }

  @keyframes slideInLeft {
    from {
      opacity: 0;
      transform: translateX(-40px);
    }
    to {
      opacity: 1;
      transform: translateX(0);
    }
  }

  .overlay-text {
    position: absolute;
    bottom: 20px;
    left: 20px;
    color: white;
    font-weight: 700;
    font-size: 2rem;
    text-shadow: 0 3px 10px rgba(0,0,0,0.5);
  }

  .right-side {
    width: 55%;
    padding: 60px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  h2 {
    margin: 0 0 30px 0;
    font-weight: 700;
    color: #333;
    text-align: center;
  }

  form input[type="email"] {
    width: 100%;
    padding: 14px 18px;
    margin-bottom: 24px;
    border: 2px solid #ddd;
    border-radius: 12px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
    outline: none;
    box-sizing: border-box;
  }

  form input[type="email"]:focus {
    border-color: #4a90e2;
  }

  form button {
    width: 100%;
    padding: 16px 0;
    background: #4a90e2;
    border: none;
    border-radius: 12px;
    color: white;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 10px 20px rgba(74,144,226,0.4);
    transition: background 0.3s ease;
  }
  form button:hover {
    background: #357abd;
  }

  .error-msg {
    background: #ff6b6b;
    color: white;
    padding: 10px 15px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-weight: 700;
    text-align: center;
    animation: shake 0.3s ease;
  }

  @keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    50% { transform: translateX(5px); }
    75% { transform: translateX(-5px); }
  }

  .login-text {
    margin-top: 30px;
    text-align: center;
    font-weight: 600;
    color: #555;
  }
  .login-text a {
    color: #4a90e2;
    text-decoration: none;
    font-weight: 700;
    transition: color 0.3s ease;
  }
  .login-text a:hover {
    color: #357abd;
  }

  /* Responsive */
  @media(max-width: 768px) {
    .container {
      flex-direction: column;
      width: 90vw;
    }
    .left-side {
      width: 100%;
      height: 250px;
      min-height: unset;
    }
    .right-side {
      width: 100%;
      padding: 40px 20px;
    }
  }
</style>
</head>
<body>

<div class="container">
  <div class="left-side">
    <div class="overlay-text">Saarthi Seva</div>
  </div>
  <div class="right-side">
    <h2>Register with your Email</h2>
    <?php if (!empty($error)): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="">
      <input type="email" name="email" placeholder="Enter your email" required>
      <button type="submit">Send OTP</button>
    </form>
    <p class="login-text">Already have an account? <a href="login.php">Login here</a></p>
  </div>
</div>

</body>
</html>
