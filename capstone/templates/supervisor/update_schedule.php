<?php
header('Content-Type: application/json');

// Get the JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$scheduleId = $input['id'];
$newStatus = $input['status'];

// Validate status
$validStatuses = ['pending', 'accepted', 'rejected', 'request'];
if (!in_array($newStatus, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status']);
    exit;
}

// Path to the JSON file
$requestFile = __DIR__ . '/../../data/nurse_shift_requests.json';

// Check if file exists
if (!file_exists($requestFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Schedule requests file not found']);
    exit;
}

// Read the current data
$raw = file_get_contents($requestFile);
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid data format']);
    exit;
}

// Find and update the request
$updated = false;
foreach ($data as &$request) {
    if (isset($request['id']) && $request['id'] == $scheduleId) {
        $request['status'] = $newStatus;
        $request['updated_at'] = date('Y-m-d H:i:s');
        $updated = true;
        break;
    }
}

if (!$updated) {
    http_response_code(404);
    echo json_encode(['error' => 'Schedule request not found']);
    exit;
}

// Write back to file
$result = file_put_contents($requestFile, json_encode($data, JSON_PRETTY_PRINT));

if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save changes']);
    exit;
}

// Return success
echo json_encode([
    'success' => true,
    'message' => 'Schedule request updated successfully',
    'id' => $scheduleId,
    'status' => $newStatus
]);
?>
