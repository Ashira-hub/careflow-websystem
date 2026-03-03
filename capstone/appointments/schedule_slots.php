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

    // Check actual table structure and get column names
    $colStmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'schedule_slots'");
    $colStmt->execute();
    $existingCols = array_map('strtolower', array_map('strval', array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));

    // Determine if we use 'date' or 'slot_date'
    $hasDate = in_array('date', $existingCols, true);
    $hasSlotDate = in_array('slot_date', $existingCols, true);

    // Use appropriate column name
    $dateCol = ($hasSlotDate || !$hasDate) ? 'slot_date' : 'date';

    // If table doesn't exist, create it with slot_date
    if (empty($existingCols)) {
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
        $dateCol = 'slot_date';
    } else {
        // Add missing columns to existing table
        $requiredCols = [
            'doctor_id' => 'BIGINT',
            'start_time' => 'TIME',
            'end_time' => 'TIME',
            'status' => 'VARCHAR(20)',
            'notes' => 'TEXT',
            'created_at' => 'TIMESTAMPTZ DEFAULT now()',
            'updated_at' => 'TIMESTAMPTZ DEFAULT now()'
        ];

        // Add slot_date if neither date column exists
        if (!$hasDate && !$hasSlotDate) {
            $requiredCols['slot_date'] = 'DATE';
        }

        foreach ($requiredCols as $colName => $colType) {
            if (!in_array($colName, $existingCols, true)) {
                try {
                    $pdo->exec("ALTER TABLE schedule_slots ADD COLUMN IF NOT EXISTS {$colName} {$colType}");
                } catch (Throwable $_) {
                }
            }
        }
    }

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

            // Check for overlapping slots using correct column name
            $overlapSql = "SELECT id FROM schedule_slots 
                WHERE doctor_id = :doctor_id 
                AND " . ($dateCol === 'slot_date' ? 'slot_date' : '"date"') . " = :slot_date 
                AND status = 'available'
                AND (
                    (start_time <= :start_time AND end_time > :start_time) OR
                    (start_time < :end_time AND end_time >= :end_time) OR
                    (start_time >= :start_time AND end_time <= :end_time)
                )";
            $overlapStmt = $pdo->prepare($overlapSql);
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

            // Insert new schedule slot using correct column name
            $insertSql = "INSERT INTO schedule_slots 
                (doctor_id, " . ($dateCol === 'slot_date' ? 'slot_date' : '"date"') . ", start_time, end_time, status, notes, created_at, updated_at) 
                VALUES (:doctor_id, :slot_date, :start_time, :end_time, :status, :notes, now(), now()) 
                RETURNING id";
            $insertStmt = $pdo->prepare($insertSql);

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

            $dateColRef = ($dateCol === 'slot_date' ? 'slot_date' : '"date"');

            if ($dateFilter && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
                $stmt = $pdo->prepare("SELECT * FROM schedule_slots 
                    WHERE doctor_id = :doctor_id AND " . $dateColRef . " = :slot_date 
                    ORDER BY start_time ASC");
                $stmt->execute([':doctor_id' => $doctorId, ':slot_date' => $dateFilter]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM schedule_slots 
                    WHERE doctor_id = :doctor_id 
                    ORDER BY " . $dateColRef . " DESC, start_time ASC");
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
                $updates[] = ($dateCol === 'slot_date' ? 'slot_date' : '"date"') . " = :slot_date";
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
