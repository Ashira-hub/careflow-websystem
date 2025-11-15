<?php
header('Content-Type: application/json');

// Get the JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['nurse_id']) || !isset($input['date']) || !isset($input['time']) || !isset($input['shift'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$nurseId = $input['nurse_id'];
$date = $input['date'];
$time = $input['time'];
$shift = $input['shift'];
$ward = $input['ward'] ?? '';
$notes = $input['notes'] ?? '';
$status = $input['status'] ?? 'accepted';

// Validate required fields
if (empty($nurseId) || empty($date) || empty($time) || empty($shift)) {
    http_response_code(400);
    echo json_encode(['error' => 'All required fields must be filled']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

// Validate time format
if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid time format']);
    exit;
}

// Validate that date is not in the past
$selectedDate = new DateTime($date);
$today = new DateTime();
$today->setTime(0, 0, 0); // Reset time to start of day

if ($selectedDate < $today) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot schedule for past dates']);
    exit;
}

// If date is today, validate that time is not in the past
if ($selectedDate->format('Y-m-d') === $today->format('Y-m-d')) {
    $selectedDateTime = new DateTime($date . ' ' . $time);
    $now = new DateTime();
    
    if ($selectedDateTime < $now) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot schedule for past time']);
        exit;
    }
}

// Path to the JSON file
$requestFile = __DIR__ . '/../../data/nurse_shift_requests.json';

// Read existing data
$data = [];
if (file_exists($requestFile)) {
    $raw = file_get_contents($requestFile);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = [];
    }
}

// Get nurse information from database
require_once __DIR__ . '/../../config/db.php';
$nurseName = 'Unknown Nurse';
$nurseEmail = '';

try {
    $pdo = get_pdo();
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ? AND role = 'nurse'");
    $stmt->execute([$nurseId]);
    $nurse = $stmt->fetch();
    if ($nurse) {
        $nurseName = $nurse['full_name'] ?? 'Unknown Nurse';
        $nurseEmail = $nurse['email'] ?? '';
    }
} catch (Throwable $e) {
    // Continue with default values if database query fails
}

// Generate new ID
$newId = 1;
if (!empty($data)) {
    $maxId = max(array_column($data, 'id'));
    $newId = $maxId + 1;
}

// Create new schedule entry
$newSchedule = [
    'id' => $newId,
    'nurse_id' => (int)$nurseId,
    'nurse' => $nurseName,
    'nurse_email' => $nurseEmail,
    'date' => $date,
    'time' => $time,
    'shift' => $shift,
    'ward' => $ward,
    'notes' => $notes,
    'status' => $status,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
];

// Add to data array
$data[] = $newSchedule;

// Write back to file
$result = file_put_contents($requestFile, json_encode($data, JSON_PRETTY_PRINT));

if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save schedule']);
    exit;
}

// Return success
echo json_encode([
    'success' => true,
    'message' => 'Schedule created successfully',
    'schedule' => $newSchedule
]);
?>
