<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Get form inputs
    $name = trim($_POST['name']);
    $mobile = trim($_POST['mobile']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== '' && $password !== $confirm_password) {
        echo "<script>alert('Passwords do not match!'); window.history.back();</script>";
        exit();
    }

    // 2. Handle profile image upload (optional)
    $profile_image = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = './Uploads/profile_images/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $tmp_name = $_FILES['profile_image']['tmp_name'];
        $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
        $destination = $upload_dir . $new_filename;

        if (move_uploaded_file($tmp_name, $destination)) {
            $profile_image = $new_filename;

            // Delete old profile image
            $stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
            if (!$stmt) {
                die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
            }
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $old_img = $stmt->get_result()->fetch_assoc()['profile_image'];
            if ($old_img && file_exists($upload_dir . $old_img)) {
                unlink($upload_dir . $old_img);
            }
        } else {
            echo "<script>alert('Failed to upload profile image'); window.history.back();</script>";
            exit();
        }
    }

    // 3. Prepare update query
    if ($password !== '') {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        if ($profile_image) {
            $stmt = $conn->prepare("UPDATE users SET name=?, mobile=?, password_hash=?, profile_image=? WHERE id=?");
            if (!$stmt) {
                die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
            }
            $stmt->bind_param("ssssi", $name, $mobile, $hashed_password, $profile_image, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name=?, mobile=?, password_hash=? WHERE id=?");
            if (!$stmt) {
                die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
            }
            $stmt->bind_param("sssi", $name, $mobile, $hashed_password, $user_id);
        }
    } else {
        if ($profile_image) {
            $stmt = $conn->prepare("UPDATE users SET name=?, mobile=?, profile_image=? WHERE id=?");
            if (!$stmt) {
                die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
            }
            $stmt->bind_param("sssi", $name, $mobile, $profile_image, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name=?, mobile=? WHERE id=?");
            if (!$stmt) {
                die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
            }
            $stmt->bind_param("ssi", $name, $mobile, $user_id);
        }
    }

    // 4. Execute update and respond
    if ($stmt->execute()) {
        echo "<script>alert('Profile updated successfully'); window.location.href = 'dashboard.php';</script>";
        exit();
    } else {
        echo "<script>alert('Update failed. Please try again.'); window.history.back();</script>";
        exit();
    }
}

// --- Fetch user data for displaying the form ---
$stmt = $conn->prepare("SELECT name, mobile, profile_image FROM users WHERE id=?");
if (!$stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found.");
}

$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Edit Profile - Saarthi Seva</title>
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
  input[type="tel"],
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
  input[type="tel"]:focus,
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
  .current-image {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 8px;
    font-style: italic;
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
  .btn-secondary {
    background: linear-gradient(to right, #6b7280, #4b5563);
  }
  .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
  }
  .btn-secondary:hover {
    background: linear-gradient(to right, #4b5563, #374151);
  }
</style>
</head>
<body>
<div class="form-container animate__animated animate__fadeIn">
  <h2 class="animate__animated animate__fadeInDown">Edit Profile</h2>

  <form action="update_profile.php" method="POST" enctype="multipart/form-data" class="space-y-4">
    <div class="form-group animate__animated animate__fadeInUp">
      <label for="profile_image">Change Profile Image</label>
      <input type="file" name="profile_image" id="profile_image" accept="image/*" class="transition-all duration-300" />
      <?php if (!empty($user['profile_image'])): ?>
        <div class="current-image">
          Current Image: <strong><?= htmlspecialchars($user['profile_image']); ?></strong>
        </div>
      <?php endif; ?>
    </div>

    <div class="form-group animate__animated animate__fadeInUp animate__delay-1s">
      <label for="name">Name</label>
      <input type="text" name="name" id="name" required value="<?= htmlspecialchars($user['name']); ?>" class="transition-all duration-300" />
    </div>

    <div class="form-group animate__animated animate__fadeInUp animate__delay-2s">
      <label for="mobile">Mobile</label>
      <input type="tel" name="mobile" id="mobile" required value="<?= htmlspecialchars($user['mobile']); ?>" class="transition-all duration-300" />
    </div>

    <div class="form-group animate__animated animate__fadeInUp animate__delay-3s">
      <label for="password">New Password (leave blank to keep current)</label>
      <input type="password" name="password" id="password" class="transition-all duration-300" />
    </div>

    <div class="form-group animate__animated animate__fadeInUp animate__delay-4s">
      <label for="confirm_password">Confirm New Password</label>
      <input type="password" name="confirm_password" id="confirm_password" class="transition-all duration-300" />
    </div>

    <div class="btn-container animate__animated animate__fadeInUp animate__delay-5s">
      <button type="submit" class="btn">Update Profile</button>
      <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
  </form>
</div>
</body>
</html>