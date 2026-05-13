<?php
// ============================================================
// api/export.php — Export approved sensor data as CSV
//
// GET parameters:
//   request_id: Export data for an approved request
//   date_from: Optional start date (Y-m-d)
//   date_to: Optional end date (Y-m-d)
// ============================================================
require_once '../includes/config.php';

startSession();
requireLogin();

$requestId = (int)($_GET['request_id'] ?? 0);
$dateFrom  = $_GET['date_from'] ?? null;
$dateTo    = $_GET['date_to'] ?? null;

if (!$requestId) {
    jsonResponse(['error' => 'request_id is required'], 422);
}

$pdo = getDB();

// Get the request details
$reqStmt = $pdo->prepare(
    'SELECT r.*, m.name AS marker_name, u.username 
     FROM sensor_data_requests r
     JOIN markers m ON m.id = r.marker_id
     JOIN users u ON u.id = r.user_id
     WHERE r.id = ?'
);
$reqStmt->execute([$requestId]);
$request = $reqStmt->fetch();

if (!$request) {
    jsonResponse(['error' => 'Request not found'], 404);
}

// Verify permissions
if (!isAdmin() && !isManager() && $_SESSION['user_id'] != $request['user_id']) {
    jsonResponse(['error' => 'Access denied'], 403);
}

// Verify request is approved
if ($request['status'] !== 'approved') {
    jsonResponse(['error' => 'Request must be approved to export data'], 403);
}

// Verify request hasn't expired
if ($request['expires_at'] && strtotime($request['expires_at']) < time()) {
    jsonResponse(['error' => 'Request has expired'], 403);
}

// Build query for readings
$query = 'SELECT sr.id, sr.temperature, sr.humidity, sr.recorded_at 
          FROM sensor_readings sr 
          WHERE sr.marker_id = ?';
$params = [$request['marker_id']];

if ($dateFrom) {
    $query .= ' AND DATE(sr.recorded_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $query .= ' AND DATE(sr.recorded_at) <= ?';
    $params[] = $dateTo;
}

$query .= ' ORDER BY sr.recorded_at ASC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$readings = $stmt->fetchAll();

// Generate CSV
$csv = "Sensor Export Report\n";
$csv .= "Sensor: " . $request['marker_name'] . "\n";
$csv .= "User: " . $request['username'] . "\n";
$csv .= "Requested: " . date('Y-m-d H:i:s', strtotime($request['created_at'])) . "\n";
$csv .= "Approved: " . date('Y-m-d H:i:s', strtotime($request['approved_at'])) . "\n";
if ($dateFrom || $dateTo) {
    $csv .= "Period: " . ($dateFrom ?: 'Any') . " to " . ($dateTo ?: 'Any') . "\n";
}
$csv .= "Records: " . count($readings) . "\n\n";

$csv .= "ID,Date/Time,Temperature (°C),Humidity (%)\n";
foreach ($readings as $r) {
    $csv .= sprintf(
        "%d,%s,%.2f,%.2f\n",
        $r['id'],
        $r['recorded_at'],
        $r['temperature'],
        $r['humidity']
    );
}

// Output CSV file
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . 
       $request['marker_name'] . '_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

echo $csv;
exit;
