<?php
session_start();
require 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (!$email || !$password) {
        $error = "Please enter both email and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $email;

                $stmt2 = $conn->prepare("SELECT name FROM users WHERE id = ?");
                $stmt2->bind_param("i", $user['id']);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                $user_data = $res2->fetch_assoc();

                if (empty($user_data['name'])) {
                    header("Location: profile.php");
                    exit();
                } else {
                    header("Location: dashboard.php");
                    exit();
                }
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "Email not registered.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Login - Saarthi Seva</title>
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

  /* Left Image Section with fixed width */
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

  /* Overlay with platform name/logo */
  .overlay-text {
    position: absolute;
    bottom: 20px;
    left: 20px;
    color: white;
    font-weight: 700;
    font-size: 2rem;
    text-shadow: 0 3px 10px rgba(0,0,0,0.5);
  }

  /* Right Form Section */
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
  }

  form label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #555;
  }

  input[type="email"],
  input[type="password"] {
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

  input[type="email"]:focus,
  input[type="password"]:focus {
    border-color: #4a90e2;
  }

  button {
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
  button:hover {
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

  .register-text {
    margin-top: 30px;
    text-align: center;
    font-weight: 600;
    color: #555;
  }
  .register-text a {
    color: #4a90e2;
    text-decoration: none;
    font-weight: 700;
    transition: color 0.3s ease;
  }
  .register-text a:hover {
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
    <h2>Welcome Back</h2>

    <?php if ($error): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <label for="email">Email Address</label>
      <input type="email" id="email" name="email" placeholder="you@example.com" required />

      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="••••••••" required />

      <button type="submit">Login</button>
    </form>

    <p class="register-text">
      Don't have an account? <a href="register.php">Register here</a>
    </p>
  </div>
</div>

</body>
</html>
