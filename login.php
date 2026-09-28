<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

if (is_admin()) redirect('dashboard.php');
if (is_student()) redirect('../student/dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'inactive') {
                $error = 'This account has been deactivated.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                redirect('dashboard.php');
            }
        } else {
            $error = 'Invalid admin username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LibroFlow | Admin Login</title>
<link rel="stylesheet" href="../css/style.css?v=2">
<link rel="stylesheet" href="../css/admin-theme.css?v=2">
</head>
<body>
<div class="landing-wrap">
  <div class="landing-card">
    <div class="brand">
      <img src="../assets/logo.svg" alt="LibroFlow" class="brand-logo">
      
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= clean($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" required autofocus>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Log In</button>
    </form>

    <p style="text-align:center; margin-top:16px; font-size:13px;" class="text-muted">
    </p>
  </div>
</div>
</body>
</html>
