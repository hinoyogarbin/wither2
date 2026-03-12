<?php
// auth/register.php
require_once '../includes/config.php';
startSession();

if (isLoggedIn()) { header('Location: ../index.php'); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    if (!$username || !$email || !$password) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $pdo  = getDB();
        $chk  = $pdo->prepare('SELECT id FROM users WHERE username=? OR email=?');
        $chk->execute([$username, $email]);
        if ($chk->fetch()) {
            $error = 'Username or email already taken.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $ins  = $pdo->prepare('INSERT INTO users (username, email, password, role) VALUES (?,?,?,?)');
            $ins->execute([$username, $email, $hash, 'user']);
            logActivity((int)$pdo->lastInsertId(), 'register', 'New account created');
            $success = 'Account created! You can now <a href="login.php">sign in</a>.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Wither – Register</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;800&family=DM+Mono:wght@400;500&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
<div class="card">
  <div class="logo">Wither</div>
  <p class="subtitle">Create your monitoring account</p>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>
  <form method="POST">
    <label>Username</label>
    <input type="text" name="username" required placeholder="Choose a username">
    <label>Email</label>
    <input type="email" name="email" required placeholder="your@email.com">
    <label>Password</label>
    <input type="password" name="password" required placeholder="At least 6 characters">
    <label>Confirm Password</label>
    <input type="password" name="confirm" required placeholder="Repeat password">
    <button class="btn" type="submit">Create Account</button>
  </form>
  <div class="footer-link">Already have an account? <a href="login.php">Sign in</a></div>
</div>
</body>
</html>