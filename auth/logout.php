<?php
// auth/logout.php
require_once '../includes/config.php';
startSession();
$uid = $_SESSION['user_id'] ?? null;
logActivity($uid, 'logout', 'User logged out');
session_destroy();
header('Location: ../auth/login.php');
exit;