<?php
require_once '../includes/config.php';
startSession();
if (isLoggedIn()) { header('Location: ../index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $stmt = getDB()->prepare('SELECT id, username, password, role FROM users WHERE (username=? OR email=?) AND is_active=1');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            logActivity($user['id'], 'login', 'Successful login');
            header('Location: ../index.php');
            exit;
        }
    }
    $error = 'Invalid credentials. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Wither – Sign In</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;800&family=DM+Mono:wght@400;500&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
<div class="card">
  <div class="logo">Wither</div>
  <p class="subtitle">Micro-Climate Monitoring System</p>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <label>Username or Email</label>
    <input type="text" name="username" required autocomplete="username" placeholder="Enter username">
    <label>Password</label>
    <input type="password" name="password" required placeholder="••••••••">
    <button class="btn" type="submit">Sign In</button>
  </form>
  <div class="footer-link">
    No account? <a href="register.php">Create one</a> &nbsp;·&nbsp;
    <a href="../index.php">Back to Dashboard</a>
  </div>
</div>
</body>
</html>