<?php
// api/stats.php  – admin analytics
require_once '../includes/config.php';
startSession();
requireAdmin();

$pdo = getDB();

$users    = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$sensors  = (int)$pdo->query('SELECT COUNT(*) FROM markers WHERE is_active=1')->fetchColumn();
$readings = (int)$pdo->query('SELECT COUNT(*) FROM sensor_readings')->fetchColumn();
$logins   = (int)$pdo->query("SELECT COUNT(*) FROM user_activity_logs WHERE action='login'")->fetchColumn();

// Readings per hour for the last 24 h
$trend = $pdo->query(
    "SELECT DATE_FORMAT(recorded_at,'%H:00') AS hour, COUNT(*) AS total
     FROM sensor_readings
     WHERE recorded_at >= NOW() - INTERVAL 24 HOUR
     GROUP BY hour ORDER BY hour"
)->fetchAll();

// User logins per day for last 7 days
$loginTrend = $pdo->query(
    "SELECT DATE(created_at) AS day, COUNT(*) AS total
     FROM user_activity_logs
     WHERE action='login' AND created_at >= NOW() - INTERVAL 7 DAY
     GROUP BY day ORDER BY day"
)->fetchAll();

// Latest activity
$activity = $pdo->query(
    "SELECT u.username, l.action, l.detail, l.ip_address, l.created_at
     FROM user_activity_logs l
     LEFT JOIN users u ON u.id = l.user_id
     ORDER BY l.created_at DESC LIMIT 20"
)->fetchAll();

jsonResponse([
    'summary'     => compact('users','sensors','readings','logins'),
    'readingTrend'=> $trend,
    'loginTrend'  => $loginTrend,
    'recentActivity' => $activity,
]);
