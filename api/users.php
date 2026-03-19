<?php
// api/users.php — user management
require_once '../includes/config.php';
startSession();
requireManager();

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// ── GET: list users ──────────────────────────────────────────
if ($method === 'GET') {
    $rows = getDB()->query(
        'SELECT id, username, email, role, is_active, created_at FROM users ORDER BY created_at DESC'
    )->fetchAll();
    jsonResponse($rows);
}

// ── POST: create user ────────────────────────────────────────
if ($method === 'POST') {
    $username = trim($body['username'] ?? '');
    $email    = trim($body['email']    ?? '');
    $password = $body['password']      ?? '';
    $role     = $body['role']          ?? 'user';

    // Role restriction: managers can only create users
    if (!isAdmin() && $role !== 'user') {
        jsonResponse(['error' => 'Managers can only create regular users'], 403);
    }
    // Validate role value
    if (!in_array($role, ['admin','manager','user'])) {
        jsonResponse(['error' => 'Invalid role'], 422);
    }
    if (!$username || !$email || strlen($password) < 6) {
        jsonResponse(['error' => 'username, email and password (min 6 chars) required'], 422);
    }

    $pdo = getDB();
    $dup = $pdo->prepare('SELECT id FROM users WHERE username=? OR email=?');
    $dup->execute([$username, $email]);
    if ($dup->fetch()) jsonResponse(['error' => 'Username or email already taken'], 409);

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare('INSERT INTO users (username, email, password, role, created_by) VALUES (?,?,?,?,?)')
        ->execute([$username, $email, $hash, $role, $_SESSION['user_id']]);
    $id = (int)$pdo->lastInsertId();
    logActivity($_SESSION['user_id'], 'user_create', "Created user id=$id role=$role");
    jsonResponse(['id' => $id, 'message' => 'User created'], 201);
}

// ── PUT: toggle active / change role (admin only for role) ───
if ($method === 'PUT') {
    requireAdmin();
    $id = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'id required'], 422);

    $pdo = getDB();
    if (isset($body['is_active'])) {
        $active = $body['is_active'] ? 1 : 0;
        $pdo->prepare('UPDATE users SET is_active=? WHERE id=?')->execute([$active, $id]);
        logActivity($_SESSION['user_id'], 'user_toggle', "User id=$id active=$active");
        jsonResponse(['message' => 'User updated']);
    }
    if (isset($body['role'])) {
        $role = $body['role'];
        if (!in_array($role, ['admin','manager','user'])) jsonResponse(['error' => 'Invalid role'], 422);
        $pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role, $id]);
        logActivity($_SESSION['user_id'], 'user_role_change', "User id=$id role=$role");
        jsonResponse(['message' => 'Role updated']);
    }
    jsonResponse(['error' => 'Nothing to update'], 422);
}

// ── DELETE: admin only ───────────────────────────────────────
if ($method === 'DELETE') {
    requireAdmin();
    $id = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'id required'], 422);
    if ($id === ($_SESSION['user_id'] ?? 0)) jsonResponse(['error' => 'Cannot delete yourself'], 403);
    getDB()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    logActivity($_SESSION['user_id'], 'user_delete', "Deleted user id=$id");
    jsonResponse(['message' => 'User deleted']);
}

jsonResponse(['error' => 'Method not allowed'], 405);