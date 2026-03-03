<?php
/**
 * Schedule Slots API Endpoint
 * Handles CRUD operations for doctor schedule slots
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = get_pdo();
    
    // Create schedule_slots table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS schedule_slots (
        id SERIAL PRIMARY KEY,
        doctor_id BIGINT NOT NULL,
        slot_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'available',
        notes TEXT,
        created_at TIMESTAMPTZ DEFAULT now(),
        updated_at TIMESTAMPTZ DEFAULT now()
    )");
    
    // Add columns if they don't exist (for existing tables)
    try {
        $pdo->exec("ALTER TABLE schedule_slots ADD COLUMN IF NOT EXISTS doctor_id BIGINT");
    } catch (Throwable $_) {}
    try {
        $pdo->exec("ALTER TABLE schedule_slots ADD COLUMN IF NOT EXISTS slot_date DATE");
    } catch (Throwable $_) {}
    try {
        $pdo->exec("ALTER TABLE schedule_slots ADD COLUMN IF NOT EXISTS start_time TIME");
    } catch (Throwable $_) {}
    try {
        $pdo->exec("ALTER TABLE schedule_slots ADD COLUMN IF NOT EXISTS end_time TIME");
    } catch (Throwable $_) {}
    try {
        $pdo->exec("ALTER TABLE schedule_slots ADD COLUMN IF NOT EXISTS status VARCHAR(20)");
    } catch (Throwable $_) {}
    try {
        $pdo->exec("ALTER TABLE schedule_slots ADD COLUMN IF NOT EXISTS notes TEXT");
    } catch (Throwable $_) {}
    try {
        $pdo->exec("ALTER TABLE schedule_slots ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now()");
    } catch (Throwable $_) {}
    try {
        $pdo->exec("ALTER TABLE schedule_slots ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT now()");
    } catch (Throwable $_) {}
    
    // Get doctor ID from session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $doctorId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
    
    if ($doctorId === 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Doctor not logged in']);
        exit;
    }
    
    switch ($method) {
        case 'POST':
            // Create new schedule slot
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!is_array($input)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON input']);
                exit;
            }
            
            $slotDate = trim((string)($input['date'] ?? ''));
            $startTime = trim((string)($input['start_time'] ?? ''));
            $endTime = trim((string)($input['end_time'] ?? ''));
            $status = trim((string)($input['status'] ?? 'available'));
            $notes = trim((string)($input['notes'] ?? ''));
            
            // Validation
            $errors = [];
            if ($slotDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $slotDate)) {
                $errors[] = 'Valid date is required (YYYY-MM-DD)';
            }
            if ($startTime === '' || !preg_match('/^\d{2}:\d{2}$/', $startTime)) {
                $errors[] = 'Valid start time is required (HH:MM)';
            }
            if ($endTime === '' || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
                $errors[] = 'Valid end time is required (HH:MM)';
            }
            if ($status === '' || !in_array($status, ['available', 'not_available'], true)) {
                $errors[] = 'Status must be available or not_available';
            }
            
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['error' => implode(', ', $errors)]);
                exit;
            }
            
            // Check for overlapping slots
            $overlapStmt = $pdo->prepare("SELECT id FROM schedule_slots 
                WHERE doctor_id = :doctor_id 
                AND slot_date = :slot_date 
                AND status = 'available'
                AND (
                    (start_time <= :start_time AND end_time > :start_time) OR
                    (start_time < :end_time AND end_time >= :end_time) OR
                    (start_time >= :start_time AND end_time <= :end_time)
                )");
            $overlapStmt->execute([
                ':doctor_id' => $doctorId,
                ':slot_date' => $slotDate,
                ':start_time' => $startTime,
                ':end_time' => $endTime
            ]);
            
            if ($overlapStmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Time slot overlaps with an existing slot']);
                exit;
            }
            
            // Insert new schedule slot
            $insertStmt = $pdo->prepare("INSERT INTO schedule_slots 
                (doctor_id, slot_date, start_time, end_time, status, notes, created_at, updated_at) 
                VALUES (:doctor_id, :slot_date, :start_time, :end_time, :status, :notes, now(), now()) 
                RETURNING id");
            
            $insertStmt->execute([
                ':doctor_id' => $doctorId,
                ':slot_date' => $slotDate,
                ':start_time' => $startTime,
                ':end_time' => $endTime,
                ':status' => $status,
                ':notes' => $notes
            ]);
            
            $newId = $insertStmt->fetchColumn();
            
            echo json_encode([
                'ok' => true,
                'data' => [
                    'id' => $newId,
                    'doctor_id' => $doctorId,
                    'date' => $slotDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => $status,
                    'notes' => $notes
                ]
            ]);
            break;
            
        case 'GET':
            // Get schedule slots for the doctor
            $dateFilter = isset($_GET['date']) ? trim((string)$_GET['date']) : null;
            
            if ($dateFilter && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
                $stmt = $pdo->prepare("SELECT * FROM schedule_slots 
                    WHERE doctor_id = :doctor_id AND slot_date = :slot_date 
                    ORDER BY start_time ASC");
                $stmt->execute([':doctor_id' => $doctorId, ':slot_date' => $dateFilter]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM schedule_slots 
                    WHERE doctor_id = :doctor_id 
                    ORDER BY slot_date DESC, start_time ASC");
                $stmt->execute([':doctor_id' => $doctorId]);
            }
            
            $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'data' => $slots]);
            break;
            
        case 'PUT':
            // Update schedule slot
            $input = json_decode(file_get_contents('php://input'), true);
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            if ($id === 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Schedule slot ID is required']);
                exit;
            }
            
            // Verify ownership
            $checkStmt = $pdo->prepare("SELECT id FROM schedule_slots WHERE id = :id AND doctor_id = :doctor_id");
            $checkStmt->execute([':id' => $id, ':doctor_id' => $doctorId]);
            if (!$checkStmt->fetch()) {
                http_response_code(403);
                echo json_encode(['error' => 'Not authorized to update this slot']);
                exit;
            }
            
            $updates = [];
            $params = [':id' => $id];
            
            if (isset($input['date'])) {
                $updates[] = "slot_date = :slot_date";
                $params[':slot_date'] = trim((string)$input['date']);
            }
            if (isset($input['start_time'])) {
                $updates[] = "start_time = :start_time";
                $params[':start_time'] = trim((string)$input['start_time']);
            }
            if (isset($input['end_time'])) {
                $updates[] = "end_time = :end_time";
                $params[':end_time'] = trim((string)$input['end_time']);
            }
            if (isset($input['status'])) {
                $updates[] = "status = :status";
                $params[':status'] = trim((string)$input['status']);
            }
            if (isset($input['notes'])) {
                $updates[] = "notes = :notes";
                $params[':notes'] = trim((string)$input['notes']);
            }
            
            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['error' => 'No fields to update']);
                exit;
            }
            
            $updates[] = "updated_at = now()";
            $sql = "UPDATE schedule_slots SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['ok' => true, 'message' => 'Schedule slot updated']);
            break;
            
        case 'DELETE':
            // Delete schedule slot
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            if ($id === 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Schedule slot ID is required']);
                exit;
            }
            
            // Verify ownership
            $checkStmt = $pdo->prepare("SELECT id FROM schedule_slots WHERE id = :id AND doctor_id = :doctor_id");
            $checkStmt->execute([':id' => $id, ':doctor_id' => $doctorId]);
            if (!$checkStmt->fetch()) {
                http_response_code(403);
                echo json_encode(['error' => 'Not authorized to delete this slot']);
                exit;
            }
            
            $deleteStmt = $pdo->prepare("DELETE FROM schedule_slots WHERE id = :id");
            $deleteStmt->execute([':id' => $id]);
            
            http_response_code(204);
            echo json_encode(['ok' => true, 'message' => 'Schedule slot deleted']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
