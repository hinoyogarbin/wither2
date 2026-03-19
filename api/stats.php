<?php
// api/stats.php — admin & manager analytics
require_once '../includes/config.php';
startSession();
requireManager();

$pdo  = getDB();
$role = getRole();

// Summary counts
$users    = isAdmin() ? (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() : null;
$sensors  = (int)$pdo->query("SELECT COUNT(*) FROM markers WHERE sensor_status='active'")->fetchColumn();
$inactive = (int)$pdo->query("SELECT COUNT(*) FROM markers WHERE sensor_status='inactive'")->fetchColumn();
$readings = (int)$pdo->query('SELECT COUNT(*) FROM sensor_readings')->fetchColumn();
$logins   = (int)$pdo->query("SELECT COUNT(*) FROM user_activity_logs WHERE action='login'")->fetchColumn();

// Readings per hour (last 24h)
$readingTrend = $pdo->query(
    "SELECT DATE_FORMAT(recorded_at,'%H:00') AS hour, COUNT(*) AS total
     FROM sensor_readings WHERE recorded_at >= NOW() - INTERVAL 24 HOUR
     GROUP BY hour ORDER BY hour"
)->fetchAll();

// Logins per day (last 7 days)
$loginTrend = $pdo->query(
    "SELECT DATE(created_at) AS day, COUNT(*) AS total
     FROM user_activity_logs WHERE action='login' AND created_at >= NOW() - INTERVAL 7 DAY
     GROUP BY day ORDER BY day"
)->fetchAll();

// Activity log (admin sees all, manager sees all too per spec)
$activity = $pdo->query(
    "SELECT u.username, l.action, l.detail, l.ip_address, l.created_at
     FROM user_activity_logs l LEFT JOIN users u ON u.id=l.user_id
     ORDER BY l.created_at DESC LIMIT 50"
)->fetchAll();

// User list (admin only)
$userList = [];
if (isAdmin()) {
    $userList = $pdo->query(
        'SELECT id, username, email, role, is_active, created_at FROM users ORDER BY created_at DESC'
    )->fetchAll();
}

jsonResponse([
    'summary'        => compact('users','sensors','inactive','readings','logins'),
    'readingTrend'   => $readingTrend,
    'loginTrend'     => $loginTrend,
    'recentActivity' => $activity,
    'userList'       => $userList,
]);