<?php
// api/readings.php
require_once '../includes/config.php';
startSession();

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: fetch latest or history ────────────────────────────
if ($method === 'GET') {
    requireLogin();
    $pdo = getDB();

    $markerId = isset($_GET['marker_id']) ? (int)$_GET['marker_id'] : null;
    $limit    = min((int)($_GET['limit'] ?? 20), 200);

    if ($markerId) {
        // History for one sensor
        $stmt = $pdo->prepare(
            'SELECT sr.id, sr.marker_id, m.name AS marker_name,
                    sr.temperature, sr.humidity, sr.recorded_at
             FROM sensor_readings sr
             JOIN markers m ON m.id = sr.marker_id
             WHERE sr.marker_id = ?
             ORDER BY sr.recorded_at DESC
             LIMIT ?'
        );
        $stmt->execute([$markerId, $limit]);
    } else {
        // Latest reading per marker
        $stmt = $pdo->prepare(
            'SELECT sr.id, sr.marker_id, m.name AS marker_name,
                    sr.temperature, sr.humidity, sr.recorded_at
             FROM sensor_readings sr
             JOIN markers m ON m.id = sr.marker_id
             WHERE sr.id IN (
                 SELECT MAX(id) FROM sensor_readings GROUP BY marker_id
             )
             ORDER BY sr.marker_id'
        );
        $stmt->execute();
    }

    jsonResponse($stmt->fetchAll());
}

// ── POST: simulate / ingest a reading ──────────────────────
if ($method === 'POST') {
    // Allow internal simulation call without auth
    // but validate a shared secret if desired
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $markerId    = (int)($body['marker_id']   ?? 0);
    $temperature = $body['temperature'] ?? null;
    $humidity    = $body['humidity']    ?? null;

    if (!$markerId) jsonResponse(['error' => 'marker_id required'], 422);

    // Auto-generate if not provided (IoT simulation)
    if ($temperature === null) {
        // Realistic Cagayan de Oro range: 24–36 °C
        $temperature = round(mt_rand(2400, 3600) / 100, 2);
    }
    if ($humidity === null) {
        // Tropical humidity: 55–90 %
        $humidity = round(mt_rand(5500, 9000) / 100, 2);
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'INSERT INTO sensor_readings (marker_id, temperature, humidity) VALUES (?,?,?)'
    );
    $stmt->execute([$markerId, $temperature, $humidity]);

    jsonResponse([
        'id'          => (int)$pdo->lastInsertId(),
        'marker_id'   => $markerId,
        'temperature' => $temperature,
        'humidity'    => $humidity,
        'recorded_at' => date('Y-m-d H:i:s'),
    ], 201);
}

jsonResponse(['error' => 'Method not allowed'], 405);