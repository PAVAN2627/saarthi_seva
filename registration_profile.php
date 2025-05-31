<?php
session_start();
require 'db.php';

// Check if email is set from registration flow
if (!isset($_SESSION['email'])) {
    header('Location: register.php');
    exit();
}

$email = $_SESSION['email'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$name || !$mobile || !$password || !$confirm_password) {
        $error = "Please fill all fields.";
    } elseif (!preg_match('/^\d{10}$/', $mobile)) {
        $error = "Please enter a valid 10-digit mobile number.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Handle profile image upload if any
        $profile_image_path = null;
        if (!empty($_FILES['profile_image']['name'])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($_FILES['profile_image']['type'], $allowed_types)) {
                $upload_dir = 'Uploads/profile_images/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '.' . $ext;
                $target_file = $upload_dir . $filename;

                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                    $profile_image_path = $target_file;
                } else {
                    $error = "Failed to upload profile image.";
                }
            } else {
                $error = "Invalid image type. Only JPG, PNG, GIF allowed.";
            }
        } else {
            // No image uploaded — assign default image path here
            $profile_image_path = 'Uploads/profile_images/default.jpg';
        }

        if (!$error) {
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Check if user already exists (by email)
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                // User exists, update the record
                $row = $res->fetch_assoc();
                $user_id = $row['id'];

                // Update query
                if ($profile_image_path === null) {
                    // If no new image uploaded, don't update profile_image field
                    $stmt = $conn->prepare("UPDATE users SET name = ?, mobile = ?, password_hash = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $name, $mobile, $password_hash, $user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET name = ?, mobile = ?, password_hash = ?, profile_image = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $name, $mobile, $password_hash, $profile_image_path, $user_id);
                }

                if ($stmt->execute()) {
                    $success = "Complete Your Profile. Profile updated successfully. You can now <a href='login.php'>login</a>.";
                    unset($_SESSION['email']);
                } else {
                    $error = "Database error: " . $conn->error;
                }
            } else {
                // Insert new user
                $stmt = $conn->prepare("INSERT INTO users (email, name, mobile, password_hash, profile_image, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("sssss", $email, $name, $mobile, $password_hash, $profile_image_path);

                if ($stmt->execute()) {
                    $success = "Complete Your Profile. Profile updated successfully. You can now <a href='login.php'>login</a>.";
                    unset($_SESSION['email']);
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Complete Profile - Saarthi Seva</title>
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
    max-width: 500px;
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
  .success-message {
    text-align: center;
    color: #22c55e;
    font-size: 0.9rem;
    margin-bottom: 16px;
    background: #f0fdf4;
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
  input[type="text"],
  input[type="password"],
  input[type="file"] {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: #f9fafb;
  }
  input[type="text"]:focus,
  input[type="password"]:focus,
  input[type="file"]:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
    background: white;
  }
  input[type="file"] {
    padding: 8px;
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
  <h2 class="animate__animated animate__fadeInDown">Complete Your Profile</h2>

  <?php if ($error): ?>
    <div class="error-message animate__animated animate__shakeX"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="success-message animate__animated animate__fadeIn"><?= $success ?></div>
  <?php else: ?>
    <form action="registration_profile.php" method="POST" enctype="multipart/form-data" class="space-y-4">
      <div class="form-group animate__animated animate__fadeInUp">
        <label for="name">Name</label>
        <input type="text" name="name" id="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" class="transition-all duration-300" />
      </div>

      <div class="form-group animate__animated animate__fadeInUp animate__delay-1s">
        <label for="mobile">Mobile Number</label>
        <input type="text" name="mobile" id="mobile" required maxlength="10" value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>" class="transition-all duration-300" />
      </div>

      <div class="form-group animate__animated animate__fadeInUp animate__delay-2s">
        <label for="password">Password</label>
        <input type="password" name="password" id="password" required class="transition-all duration-300" />
      </div>

      <div class="form-group animate__animated animate__fadeInUp animate__delay-3s">
        <label for="confirm_password">Confirm Password</label>
        <input type="password" name="confirm_password" id="confirm_password" required class="transition-all duration-300" />
      </div>

      <div class="form-group animate__animated animate__fadeInUp animate__delay-4s">
        <label for="profile_image">Profile Image (optional)</label>
        <input type="file" name="profile_image" id="profile_image" accept="image/*" class="transition-all duration-300" />
      </div>

      <div class="btn-container animate__animated animate__fadeInUp animate__delay-5s">
        <button type="submit" class="btn">Save Profile</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php if ($success): ?>
<script>
  setTimeout(() => {
    window.location.href = 'login.php';
  }, 1000);
</script>
<?php endif; ?>
</body>
</html>