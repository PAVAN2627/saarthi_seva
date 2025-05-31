<?php
session_start();

if (!isset($_SESSION['email']) || !isset($_SESSION['otp'])) {
    header("Location: register.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_otp = trim($_POST['otp']);
    if ($user_otp == $_SESSION['otp']) {
        // OTP is correct — proceed with user registration or login
        require 'db.php';
        $email = $_SESSION['email'];

        // Check if user exists
        $stmt = $conn->prepare("SELECT id, is_verified FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // User exists
            $user = $result->fetch_assoc();
            if (!$user['is_verified']) {
                // Mark user as verified
                $stmt = $conn->prepare("UPDATE users SET is_verified=1 WHERE id=?");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
            }
            $_SESSION['user_id'] = $user['id'];
        } else {
            // New user, insert record
            $stmt = $conn->prepare("INSERT INTO users (email, is_verified) VALUES (?, 1)");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $_SESSION['user_id'] = $stmt->insert_id;
        }

        // Clear OTP from session
        unset($_SESSION['otp']);

        // Redirect to dashboard
        header("Location: registration_profile.php");
        exit();

    } else {
        $error = "Invalid OTP, please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Verify OTP - Saarthi Seva</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
<style>
  body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
  }
  .form-container {
    max-width: 400px;
    width: 100%;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    padding: 32px;
    margin: 0 auto;
  }
  h2 {
    text-align: center;
    font-size: 1.75rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 16px;
  }
  .email-info {
    text-align: center;
    font-size: 0.9rem;
    color: #4b5563;
    margin-bottom: 24px;
  }
  .error-message {
    text-align: center;
    color: #ef4444;
    font-size: 0.9rem;
    margin-bottom: 16px;
    background: #fef2f2;
    padding: 8px;
    border-radius: 8px;
  }
  .form-group {
    margin-bottom: 20px;
  }
  label {
    display: block;
    font-weight: 600;
    color: #4b5563;
    margin-bottom: 8px;
    font-size: 0.9rem;
  }
  input[type="text"] {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 1rem;
    text-align: center;
    letter-spacing: 4px;
    transition: all 0.3s ease;
    background: #f9fafb;
  }
  input[type="text"]:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
    background: white;
  }
  .btn-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 24px;
  }
  .btn {
    padding: 12px;
    background: linear-gradient(to right, #3b82f6, #2563eb);
    color: white;
    text-align: center;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
  }
  .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
  }
</style>
</head>
<body>
<div class="form-container animate__animated animate__fadeIn">
  <h2 class="animate__animated animate__fadeInDown">Verify OTP</h2>
  <p class="email-info animate__animated animate__fadeIn">Enter the OTP sent to <strong><?= htmlspecialchars($_SESSION['email']); ?></strong></p>

  <?php if (!empty($error)): ?>
    <div class="error-message animate__animated animate__shakeX"><?= htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <form action="verify_otp.php" method="POST" class="space-y-4">
    <div class="form-group animate__animated animate__fadeInUp">
      <label for="otp">OTP Code</label>
      <input type="text" name="otp" id="otp" placeholder="Enter 6-digit OTP" required maxlength="6" class="transition-all duration-300" />
    </div>

    <div class="btn-container animate__animated animate__fadeInUp animate__delay-1s">
      <button type="submit" class="btn">Verify OTP</button>
    </div>
  </form>
</div>
</body>
</html>