<?php
// api/markers.php — CRUD + sensor ON/OFF toggle
require_once '../includes/config.php';
startSession();

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: public — all markers with status ────────────────────
if ($method === 'GET') {
    $rows = getDB()->query(
        'SELECT id, name, latitude, longitude, description, sensor_status, created_at
         FROM markers ORDER BY id'
    )->fetchAll();
    jsonResponse($rows);
}

// ── All writes require manager or admin ──────────────────────
requireManager();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── POST: create marker ──────────────────────────────────────
if ($method === 'POST') {
    $name = trim($body['name'] ?? '');
    $lat  = (float)($body['latitude']  ?? 0);
    $lng  = (float)($body['longitude'] ?? 0);
    $desc = trim($body['description']  ?? '');
    if (!$name || !$lat || !$lng) jsonResponse(['error' => 'name, latitude, longitude required'], 422);

    $pdo = getDB();
    $pdo->prepare('INSERT INTO markers (name, latitude, longitude, description, created_by) VALUES (?,?,?,?,?)')
        ->execute([$name, $lat, $lng, $desc, $_SESSION['user_id']]);
    $id = (int)$pdo->lastInsertId();
    logActivity($_SESSION['user_id'], 'marker_create', "Marker id=$id name=$name");
    jsonResponse(['id' => $id, 'message' => 'Marker created'], 201);
}

// ── PUT: update marker OR toggle sensor status ───────────────
if ($method === 'PUT') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'id required'], 422);

    $pdo = getDB();

    // Toggle-only payload
    if (isset($body['sensor_status'])) {
        $status = $body['sensor_status'] === 'active' ? 'active' : 'inactive';
        $pdo->prepare('UPDATE markers SET sensor_status=? WHERE id=?')->execute([$status, $id]);
        logActivity($_SESSION['user_id'], 'sensor_toggle', "Marker id=$id status=$status");
        jsonResponse(['message' => "Sensor $status"]);
    }

    // Full update
    $name = trim($body['name'] ?? '');
    $lat  = (float)($body['latitude']  ?? 0);
    $lng  = (float)($body['longitude'] ?? 0);
    $desc = trim($body['description']  ?? '');
    if (!$name || !$lat || !$lng) jsonResponse(['error' => 'name, latitude, longitude required'], 422);

    $pdo->prepare('UPDATE markers SET name=?, latitude=?, longitude=?, description=? WHERE id=?')
        ->execute([$name, $lat, $lng, $desc, $id]);
    logActivity($_SESSION['user_id'], 'marker_update', "Updated marker id=$id");
    jsonResponse(['message' => 'Marker updated']);
}

// ── DELETE: admin only ───────────────────────────────────────
if ($method === 'DELETE') {
    requireAdmin();
    $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'id required'], 422);
    getDB()->prepare('DELETE FROM markers WHERE id=?')->execute([$id]);
    logActivity($_SESSION['user_id'], 'marker_delete', "Deleted marker id=$id");
    jsonResponse(['message' => 'Marker deleted']);
}

jsonResponse(['error' => 'Method not allowed'], 405);