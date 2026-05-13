<?php
// ============================================================
// api/requests.php — User data requests + admin approval
//
// GET:  List requests (admins see all, users see their own)
// POST: Create a new request
// PUT:  Approve/reject requests (admin/manager only)
// ============================================================
require_once '../includes/config.php';

startSession();
requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$pdo    = getDB();

// ── GET: List requests ─────────────────────────────────────
if ($method === 'GET') {
    if (isAdmin() || isManager()) {
        // Admins/managers see all requests
        $stmt = $pdo->query(
            'SELECT r.id, r.user_id, u.username, r.marker_id, m.name AS marker_name,
                    r.status, r.reason, r.approved_by, r.approved_at, r.rejected_at,
                    r.expires_at, r.created_at, approver.username AS approver_name
             FROM sensor_data_requests r
             JOIN users u ON u.id = r.user_id
             JOIN markers m ON m.id = r.marker_id
             LEFT JOIN users approver ON approver.id = r.approved_by
             ORDER BY r.created_at DESC'
        );
    } else {
        // Regular users see only their own requests
        $stmt = $pdo->prepare(
            'SELECT r.id, r.user_id, u.username, r.marker_id, m.name AS marker_name,
                    r.status, r.reason, r.approved_by, r.approved_at, r.rejected_at,
                    r.expires_at, r.created_at, approver.username AS approver_name
             FROM sensor_data_requests r
             JOIN users u ON u.id = r.user_id
             JOIN markers m ON m.id = r.marker_id
             LEFT JOIN users approver ON approver.id = r.approved_by
             WHERE r.user_id = ?
             ORDER BY r.created_at DESC'
        );
        $stmt->execute([$_SESSION['user_id']]);
    }
    jsonResponse($stmt->fetchAll());
}

// ── POST: Create a new request ─────────────────────────────
if ($method === 'POST') {
    $markerId = (int)($body['marker_id'] ?? 0);
    $reason   = trim($body['reason'] ?? '');
    $dateFrom = $body['date_from'] ?? null;
    $dateTo   = $body['date_to'] ?? null;

    if (!$markerId) {
        jsonResponse(['error' => 'marker_id is required'], 422);
    }

    // Check marker exists
    $markerStmt = $pdo->prepare('SELECT id, name FROM markers WHERE id=?');
    $markerStmt->execute([$markerId]);
    $marker = $markerStmt->fetch();
    if (!$marker) {
        jsonResponse(['error' => 'Marker not found'], 404);
    }

    // Check if user already has a pending request for this marker
    $existingStmt = $pdo->prepare(
        'SELECT id FROM sensor_data_requests WHERE user_id=? AND marker_id=? AND status=\'pending\''
    );
    $existingStmt->execute([$_SESSION['user_id'], $markerId]);
    if ($existingStmt->fetch()) {
        jsonResponse(['error' => 'You already have a pending request for this sensor'], 409);
    }

    // Check if user already has an approved request that hasn't expired
    $approvedStmt = $pdo->prepare(
        'SELECT id, expires_at FROM sensor_data_requests 
         WHERE user_id=? AND marker_id=? AND status=\'approved\' 
         AND (expires_at IS NULL OR expires_at > NOW())'
    );
    $approvedStmt->execute([$_SESSION['user_id'], $markerId]);
    if ($approvedStmt->fetch()) {
        jsonResponse(['error' => 'You already have an active approved request for this sensor'], 409);
    }

    // Create the request
    $insertStmt = $pdo->prepare(
        'INSERT INTO sensor_data_requests (user_id, marker_id, reason) VALUES (?,?,?)'
    );
    $insertStmt->execute([$_SESSION['user_id'], $markerId, $reason]);
    $requestId = (int)$pdo->lastInsertId();

    logActivity($_SESSION['user_id'], 'data_request_create', "Requested data from sensor id=$markerId");

    jsonResponse([
        'id'           => $requestId,
        'marker_id'    => $markerId,
        'marker_name'  => $marker['name'],
        'status'       => 'pending',
        'created_at'   => date('Y-m-d H:i:s'),
        'message'      => 'Data request submitted for admin approval'
    ], 201);
}

// ── PUT: Approve/reject requests (admin/manager only) ──────
if ($method === 'PUT') {
    requireManager();

    $requestId = (int)($body['id'] ?? 0);
    $action    = trim($body['action'] ?? ''); // 'approve' or 'reject'
    $notes     = trim($body['notes'] ?? '');

    if (!$requestId) {
        jsonResponse(['error' => 'id is required'], 422);
    }
    if (!in_array($action, ['approve', 'reject'])) {
        jsonResponse(['error' => 'action must be "approve" or "reject"'], 422);
    }

    // Get the request
    $reqStmt = $pdo->prepare('SELECT * FROM sensor_data_requests WHERE id=?');
    $reqStmt->execute([$requestId]);
    $request = $reqStmt->fetch();

    if (!$request) {
        jsonResponse(['error' => 'Request not found'], 404);
    }

    // Can't re-approve/reject already processed requests
    if ($request['status'] !== 'pending') {
        jsonResponse(['error' => 'Request has already been ' . $request['status']], 409);
    }

    if ($action === 'approve') {
        // Set expiration to 30 days from now
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        $updateStmt = $pdo->prepare(
            'UPDATE sensor_data_requests SET status=\'approved\', approved_by=?, approved_at=NOW(), expires_at=? WHERE id=?'
        );
        $updateStmt->execute([$_SESSION['user_id'], $expiresAt, $requestId]);
        logActivity($_SESSION['user_id'], 'data_request_approve', 
                   "Approved request id=$requestId from user id={$request['user_id']} for sensor id={$request['marker_id']}. Notes: $notes");
        $message = 'Request approved (expires in 30 days)';
    } else {
        // Reject
        $updateStmt = $pdo->prepare(
            'UPDATE sensor_data_requests SET status=\'rejected\', approved_by=?, rejected_at=NOW() WHERE id=?'
        );
        $updateStmt->execute([$_SESSION['user_id'], $requestId]);
        logActivity($_SESSION['user_id'], 'data_request_reject', 
                   "Rejected request id=$requestId from user id={$request['user_id']} for sensor id={$request['marker_id']}. Reason: $notes");
        $message = 'Request rejected';
    }

    jsonResponse(['message' => $message]);
}

jsonResponse(['error' => 'Method not allowed'], 405);
