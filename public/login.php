<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Session.php';

Session::start();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $stmt = Database::pdo()->prepare('SELECT id, password_hash FROM admin_users WHERE email = :e');
    $stmt->execute(['e' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password_hash'])) {
        Session::login((int)$user['id']);
        header('Location: /public/index.php');
        exit;
    }
    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in — Short Circuit Meta Manager</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
  <div class="auth-card">
    <img src="https://shortcircuit.company/assets/img/logo.svg" alt="Short Circuit" class="auth-logo">
    <h1 class="small-heading">META MANAGER</h1>
    <?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="post" class="auth-form">
      <label>Email
        <input type="email" name="email" required autofocus>
      </label>
      <label>Password
        <input type="password" name="password" required>
      </label>
      <button type="submit" class="btn btn-primary">Sign In</button>
    </form>
  </div>
</body>
</html>
