<?php
// ============================================================
// api/ingest.php — ESP32 / IoT sensor data ingestion endpoint
//
// The ESP32 sends a POST request here every N seconds with:
//   {
//     "api_key":    "your-secret-key",
//     "marker_id":  1,
//     "temperature": 28.5,
//     "humidity":    72.3
//   }
//
// Security: validated by API key (set in config.php)
// Method:   POST only
// Auth:     No session / no login required — key-based only
// ============================================================
require_once '../includes/config.php';

// ── Only accept POST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST only'], 405);
}

// ── Parse JSON body ──────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);

if (!$body || !is_array($body)) {
    jsonResponse(['error' => 'Invalid JSON body'], 400);
}

// ── Validate API key ─────────────────────────────────────────
$receivedKey = trim($body['api_key'] ?? '');
if ($receivedKey === '' || $receivedKey !== ESP32_API_KEY) {
    jsonResponse(['error' => 'Unauthorized — invalid API key'], 401);
}

// ── Validate fields ──────────────────────────────────────────
$markerId    = isset($body['marker_id'])    ? (int)$body['marker_id']       : 0;
$temperature = isset($body['temperature'])  ? (float)$body['temperature']   : null;
$humidity    = isset($body['humidity'])     ? (float)$body['humidity']      : null;

if ($markerId <= 0) {
    jsonResponse(['error' => 'marker_id is required and must be a positive integer'], 422);
}
if ($temperature === null || $humidity === null) {
    jsonResponse(['error' => 'temperature and humidity are required'], 422);
}

// ── Sanity-check sensor ranges ───────────────────────────────
// DHT11 range: 0–50 °C temperature, 20–90% humidity
if ($temperature < -10 || $temperature > 80) {
    jsonResponse(['error' => 'temperature out of plausible range (-10 to 80 °C)'], 422);
}
if ($humidity < 0 || $humidity > 100) {
    jsonResponse(['error' => 'humidity out of plausible range (0–100 %)'], 422);
}

// ── Check marker exists and is active ───────────────────────
$pdo  = getDB();
$stmt = $pdo->prepare("SELECT id, name FROM markers WHERE id=? AND sensor_status='active'");
$stmt->execute([$markerId]);
$marker = $stmt->fetch();

if (!$marker) {
    jsonResponse(['error' => "Marker id=$markerId not found or is inactive"], 404);
}

// ── Insert reading ───────────────────────────────────────────
$pdo->prepare(
    'INSERT INTO sensor_readings (marker_id, temperature, humidity) VALUES (?,?,?)'
)->execute([$markerId, round($temperature, 2), round($humidity, 2)]);

$insertedId = (int)$pdo->lastInsertId();

jsonResponse([
    'success'     => true,
    'id'          => $insertedId,
    'marker_id'   => $markerId,
    'marker_name' => $marker['name'],
    'temperature' => round($temperature, 2),
    'humidity'    => round($humidity, 2),
    'recorded_at' => date('Y-m-d H:i:s'),
]);