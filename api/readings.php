<?php
// api/readings.php — public GET, open POST
require_once '../includes/config.php';
startSession();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $pdo = getDB();
    $markerId = isset($_GET['marker_id']) ? (int) $_GET['marker_id'] : null;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : null;

    if ($markerId) {
        // All users can view detailed sensor data
        // (Data requests/approval only needed for CSV export or other special features)
        $sql = 'SELECT sr.id, sr.marker_id, m.name AS marker_name,
                       sr.temperature, sr.humidity, sr.recorded_at
                FROM sensor_readings sr
                JOIN markers m ON m.id = sr.marker_id
                WHERE sr.marker_id = ?
                ORDER BY sr.recorded_at DESC';

        $params = [$markerId];

        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int)$limit;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        // Latest readings from all markers (public view)
        $stmt = $pdo->query(
            'SELECT sr.id, sr.marker_id, m.name AS marker_name,
                    sr.temperature, sr.humidity, sr.recorded_at
             FROM sensor_readings sr JOIN markers m ON m.id=sr.marker_id
             WHERE sr.id IN (SELECT MAX(id) FROM sensor_readings GROUP BY marker_id)
             ORDER BY sr.marker_id'
        );
    }
    jsonResponse($stmt->fetchAll());
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $markerId = (int) ($body['marker_id'] ?? 0);
    $temperature = $body['temperature'] ?? round(mt_rand(2400, 3600) / 100, 2);
    $humidity = $body['humidity'] ?? round(mt_rand(5500, 9000) / 100, 2);
    if (!$markerId)
        jsonResponse(['error' => 'marker_id required'], 422);

    $pdo = getDB();
    $pdo->prepare('INSERT INTO sensor_readings (marker_id, temperature, humidity) VALUES (?,?,?)')
        ->execute([$markerId, $temperature, $humidity]);
    jsonResponse([
        'id' => (int) $pdo->lastInsertId(),
        'marker_id' => $markerId,
        'temperature' => $temperature,
        'humidity' => $humidity,
        'recorded_at' => date('Y-m-d H:i:s')
    ], 201);
}

jsonResponse(['error' => 'Method not allowed'], 405);