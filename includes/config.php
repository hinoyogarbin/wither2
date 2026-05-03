<?php
// ============================================================
// includes/config.php — Database, session, role helpers
// ============================================================

// ── Database credentials ──────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_USER',    'root');        // ← change for production
define('DB_PASS',    '');            // ← change for production
define('DB_NAME',    'wither_db');
define('DB_CHARSET', 'utf8mb4');


define('ESP32_API_KEY', 'wither-nbsc-k7x2m9p4q1r8');

// ── Database connection ───────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn  = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    }
    return $pdo;
}

// ── Session helpers ───────────────────────────────────────────
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

function isLoggedIn(): bool {
    startSession();
    return isset($_SESSION['user_id']);
}

function getRole(): string {
    startSession();
    return $_SESSION['role'] ?? 'guest';
}

function isAdmin(): bool   { return getRole() === 'admin'; }
function isManager(): bool { return getRole() === 'manager'; }
function canManage(): bool { return in_array(getRole(), ['admin', 'manager']); }

// ── Route guards ──────────────────────────────────────────────
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /auth/login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
}

function requireManager(): void {
    requireLogin();
    if (!canManage()) jsonResponse(['error' => 'Forbidden'], 403);
}

// ── Activity logging ──────────────────────────────────────────
function logActivity(?int $userId, string $action, string $detail = ''): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        getDB()->prepare(
            'INSERT INTO user_activity_logs (user_id, action, detail, ip_address) VALUES (?,?,?,?)'
        )->execute([$userId, $action, $detail, $ip]);
    } catch (Throwable) { /* non-critical, never crash on logging */ }
}

// ── JSON response helper ──────────────────────────────────────
function jsonResponse(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}