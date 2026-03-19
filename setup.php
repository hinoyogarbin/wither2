<?php
// setup.php — one-time installer + password reset. DELETE after use.
require_once 'includes/config.php';

$pdo = getDB();

// ── Create tables ─────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(60)  NOT NULL UNIQUE,
    email       VARCHAR(120) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin','manager','user') NOT NULL DEFAULT 'user',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS markers (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    latitude      DECIMAL(10,8) NOT NULL,
    longitude     DECIMAL(11,8) NOT NULL,
    description   TEXT,
    sensor_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS sensor_readings (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    marker_id    INT UNSIGNED NOT NULL,
    temperature  DECIMAL(5,2) NOT NULL,
    humidity     DECIMAL(5,2) NOT NULL,
    recorded_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sr_marker   (marker_id),
    INDEX idx_sr_recorded (recorded_at)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_activity_logs (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    action     VARCHAR(80)  NOT NULL,
    detail     VARCHAR(255),
    ip_address VARCHAR(45),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ual_user    (user_id),
    INDEX idx_ual_created (created_at)
)");

echo "<h3>Wither Setup</h3>";

// ── Admin ─────────────────────────────────────────────────────
$adminHash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);

$existing = $pdo->query("SELECT id FROM users WHERE username='admin'")->fetch();
if (!$existing) {
    $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?,?,?,?)")
        ->execute(['admin', 'admin@example.com', $adminHash, 'admin']);
    echo "✅ Admin created &nbsp;<code>admin / admin123</code><br>";
} else {
    // Force-update the hash in case it was a placeholder
    $pdo->prepare("UPDATE users SET password=?, email=? WHERE username='admin'")
        ->execute([$adminHash, 'admin@example.com']);
    echo "✅ Admin password reset &nbsp;<code>admin / admin123</code><br>";
}

// Verify the hash works
$savedHash = $pdo->query("SELECT password FROM users WHERE username='admin'")->fetchColumn();
if (password_verify('admin123', $savedHash)) {
    echo "✅ Admin password verified OK<br>";
} else {
    echo "❌ Admin password verification FAILED — check PHP version<br>";
}

// ── Manager ───────────────────────────────────────────────────
$managerHash = password_hash('manager123', PASSWORD_BCRYPT, ['cost' => 12]);
$adminId     = (int)$pdo->query("SELECT id FROM users WHERE username='admin'")->fetchColumn();

$existingMgr = $pdo->query("SELECT id FROM users WHERE username='manager'")->fetch();
if (!$existingMgr) {
    $pdo->prepare("INSERT INTO users (username, email, password, role, created_by) VALUES (?,?,?,?,?)")
        ->execute(['manager', 'manager@example.com', $managerHash, 'manager', $adminId]);
    echo "✅ Manager created &nbsp;<code>manager / manager123</code><br>";
} else {
    $pdo->prepare("UPDATE users SET password=?, email=? WHERE username='manager'")
        ->execute([$managerHash, 'manager@example.com']);
    echo "✅ Manager password reset &nbsp;<code>manager / manager123</code><br>";
}

// Verify manager hash
$savedMgrHash = $pdo->query("SELECT password FROM users WHERE username='manager'")->fetchColumn();
if (password_verify('manager123', $savedMgrHash)) {
    echo "✅ Manager password verified OK<br>";
} else {
    echo "❌ Manager password verification FAILED<br>";
}

// ── Markers ───────────────────────────────────────────────────
$count = (int)$pdo->query("SELECT COUNT(*) FROM markers")->fetchColumn();
if ($count === 0) {
    $seeds = [
        ['NBSC Canteen',       8.35940476, 124.86847807],
        ['NBSC Spot 3',        8.36079200, 124.86919500],
        ['NBSC Spot 2',        8.35952068, 124.86766785],
        ['NBSC Spot 1',        8.35940869, 124.86880759],
        ['NBSC Covered Court', 8.35996846, 124.86889845],
        ['Lab 1',              8.35917491, 124.86905432],
        ['SWDC',               8.36024500, 124.86747400],
    ];
    $s = $pdo->prepare("INSERT INTO markers (name, latitude, longitude, created_by) VALUES (?,?,?,?)");
    foreach ($seeds as $m) $s->execute([$m[0], $m[1], $m[2], $adminId]);
    echo "✅ " . count($seeds) . " markers seeded<br>";
} else {
    echo "ℹ️ Markers already exist (" . $count . " found)<br>";
}

echo "<br><strong>✅ Setup complete. Delete this file now!</strong>";