<?php
// includes/config.php  – centralised DB + session config
define('DB_HOST', 'localhost');
define('DB_USER', 'root');           // ← change for production
define('DB_PASS', '');               // ← change for production
define('DB_NAME', 'wither_db');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    }
    return $pdo;
}

// Session helpers
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn(): bool {
    startSession();
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    startSession();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /auth/login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        exit(json_encode(['error' => 'Forbidden']));
    }
}

function logActivity(?int $userId = null, string $action = '', string $detail = ''): void {
    try {
        $pdo = getDB();
        $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $pdo->prepare(
            'INSERT INTO user_activity_logs (user_id, action, detail, ip_address) VALUES (?,?,?,?)'
        );
        $stmt->execute([$userId, $action, $detail, $ip]);
    } catch (Throwable) { /* non-critical, swallow */ }
}

function jsonResponse(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}