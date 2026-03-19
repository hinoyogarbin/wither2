<?php
// api/simulate.php — generate readings only for ACTIVE sensors
require_once '../includes/config.php';

$pdo     = getDB();
$markers = $pdo->query(
    "SELECT id FROM markers WHERE sensor_status='active'"
)->fetchAll();

$stmt     = $pdo->prepare('INSERT INTO sensor_readings (marker_id, temperature, humidity) VALUES (?,?,?)');
$inserted = [];

foreach ($markers as $m) {
    $temp = round(mt_rand(2400, 3600) / 100, 2);  // 24–36 °C
    $hum  = round(mt_rand(5500, 9000) / 100, 2);  // 55–90 %
    $stmt->execute([$m['id'], $temp, $hum]);
    $inserted[] = ['marker_id' => (int)$m['id'], 'temperature' => $temp, 'humidity' => $hum];
}

jsonResponse(['generated' => count($inserted), 'readings' => $inserted]);