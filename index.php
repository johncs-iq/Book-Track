<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

if (is_admin()) redirect('admin/dashboard.php');
if (is_student()) redirect('student/dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'inactive') {
                $error = 'This account has been deactivated. Contact the librarian.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php');
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LibroFlow | Login</title>
<link rel="icon" href="assets/school-logo.png">
<link rel="stylesheet" href="css/style.css?v=4">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="landing-wrap">
  <div class="landing-card">
    <div class="brand">
      <img src="assets/school-logo.png" alt="Marcelo F. Cabrera Vocational High School" class="brand-logo">
      <h1>Libro<span>Flow</span></h1>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= clean($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label><i class="fa-solid fa-user"></i> Username</label>
        <input type="text" name="username" required autofocus>
      </div>
      <div class="form-group">
        <label><i class="fa-solid fa-lock"></i> Password</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-right-to-bracket"></i> Log In</button>
    </form>

    <p style="text-align:center; margin-top:16px; font-size:14px;">
      Don't have an account? <a href="student/register.php">Register here</a>
    </p>
  </div>
</div>
</body>
</html>
