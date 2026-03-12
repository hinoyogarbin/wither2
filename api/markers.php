<?php
// api/markers.php  – CRUD for sensor markers
require_once '../includes/config.php';
startSession();

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: public list ────────────────────────────────────────
if ($method === 'GET') {
    $pdo  = getDB();
    $rows = $pdo->query(
        'SELECT id, name, latitude, longitude, description, is_active, created_at
         FROM markers WHERE is_active=1 ORDER BY id'
    )->fetchAll();
    jsonResponse($rows);
}

// ── All write operations require admin ─────────────────────
requireAdmin();

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── POST: create ────────────────────────────────────────────
if ($method === 'POST') {
    $name = trim($body['name'] ?? '');
    $lat  = (float)($body['latitude']  ?? 0);
    $lng  = (float)($body['longitude'] ?? 0);
    $desc = trim($body['description']  ?? '');

    if (!$name || !$lat || !$lng) {
        jsonResponse(['error' => 'name, latitude and longitude are required'], 422);
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'INSERT INTO markers (name, latitude, longitude, description, created_by) VALUES (?,?,?,?,?)'
    );
    $stmt->execute([$name, $lat, $lng, $desc, $_SESSION['user_id']]);
    $id = (int)$pdo->lastInsertId();
    logActivity($_SESSION['user_id'], 'marker_create', "Created marker id=$id name=$name");
    jsonResponse(['id' => $id, 'message' => 'Marker created'], 201);
}

// ── PUT: update ─────────────────────────────────────────────
if ($method === 'PUT') {
    $id   = (int)($body['id']          ?? 0);
    $name = trim($body['name']         ?? '');
    $lat  = (float)($body['latitude']  ?? 0);
    $lng  = (float)($body['longitude'] ?? 0);
    $desc = trim($body['description']  ?? '');

    if (!$id || !$name || !$lat || !$lng) {
        jsonResponse(['error' => 'id, name, latitude and longitude are required'], 422);
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'UPDATE markers SET name=?, latitude=?, longitude=?, description=? WHERE id=?'
    );
    $stmt->execute([$name, $lat, $lng, $desc, $id]);
    logActivity($_SESSION['user_id'], 'marker_update', "Updated marker id=$id");
    jsonResponse(['message' => 'Marker updated']);
}

// ── DELETE ──────────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'id required'], 422);

    $pdo  = getDB();
    $pdo->prepare('UPDATE markers SET is_active=0 WHERE id=?')->execute([$id]);
    logActivity($_SESSION['user_id'], 'marker_delete', "Soft-deleted marker id=$id");
    jsonResponse(['message' => 'Marker removed']);
}

jsonResponse(['error' => 'Method not allowed'], 405);