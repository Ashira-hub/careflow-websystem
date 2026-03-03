<?php
// API endpoint for creating prescriptions in PostgreSQL
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$uid = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

function json_body(): array
{
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

try {
  $pdo = get_pdo();
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Database connection failed']);
  exit;
}

// Get JSON body data
$b = json_body();

// Extract and validate required fields
$doctor_name = isset($b['doctor_name']) ? trim((string)$b['doctor_name']) : '';
$patient_name = isset($b['patient_name']) ? trim((string)$b['patient_name']) : '';
$medicine = isset($b['medicine']) ? trim((string)$b['medicine']) : '';
$quantity = isset($b['quantity']) ? trim((string)$b['quantity']) : '';
$dosage_strength = isset($b['dosage_strength']) ? trim((string)$b['dosage_strength']) : '';
$instruction = isset($b['instruction']) ? trim((string)$b['instruction']) : '';
$description = isset($b['description']) ? trim((string)$b['description']) : '';

// Validate required fields
$errors = [];
if (empty($doctor_name)) {
  $errors[] = 'Doctor name is required';
}
if (empty($patient_name)) {
  $errors[] = 'Patient name is required';
}
if (empty($medicine)) {
  $errors[] = 'Medicine name is required';
}
if (empty($quantity)) {
  $errors[] = 'Quantity prescribed is required';
}
if (empty($dosage_strength)) {
  $errors[] = 'Dosage strength is required';
}

if (!empty($errors)) {
  http_response_code(400);
  echo json_encode(['error' => implode(', ', $errors)]);
  exit;
}

try {
  try {
    $pdo->exec('ALTER TABLE prescription ADD COLUMN IF NOT EXISTS instruction TEXT');
  } catch (Throwable $e) {
  }

  // Prefer inserting ownership if the column exists; if not, fall back
  $tryWithOwner = true;
  try {
    $stmt = $pdo->prepare('INSERT INTO prescription 
      (doctor_name, patient_name, medicine, quantity, dosage_strength, instruction, description, created_at, created_by_user_id) 
      VALUES (:doctor_name, :patient_name, :medicine, :quantity, :dosage_strength, :instruction, :description, NOW(), :uid) 
      RETURNING id, doctor_name, patient_name, medicine, quantity, dosage_strength, instruction, description, created_at');
    $stmt->execute([
      ':doctor_name' => $doctor_name,
      ':patient_name' => $patient_name,
      ':medicine' => $medicine,
      ':quantity' => $quantity,
      ':dosage_strength' => $dosage_strength,
      ':instruction' => ($instruction === '' ? null : $instruction),
      ':description' => $description ?: null,
      ':uid' => $uid,
    ]);
  } catch (Throwable $e) {
    $tryWithOwner = false;
  }
  if (!$tryWithOwner) {
    $tryWithInstruction = true;
    try {
      $stmt = $pdo->prepare('INSERT INTO prescription 
        (doctor_name, patient_name, medicine, quantity, dosage_strength, instruction, description, created_at) 
        VALUES (:doctor_name, :patient_name, :medicine, :quantity, :dosage_strength, :instruction, :description, NOW()) 
        RETURNING id, doctor_name, patient_name, medicine, quantity, dosage_strength, instruction, description, created_at');
      $stmt->execute([
        ':doctor_name' => $doctor_name,
        ':patient_name' => $patient_name,
        ':medicine' => $medicine,
        ':quantity' => $quantity,
        ':dosage_strength' => $dosage_strength,
        ':instruction' => ($instruction === '' ? null : $instruction),
        ':description' => $description ?: null,
      ]);
    } catch (Throwable $e) {
      $tryWithInstruction = false;
    }
    if (!$tryWithInstruction) {
      $stmt = $pdo->prepare('INSERT INTO prescription 
        (doctor_name, patient_name, medicine, quantity, dosage_strength, description, created_at) 
        VALUES (:doctor_name, :patient_name, :medicine, :quantity, :dosage_strength, :description, NOW()) 
        RETURNING id, doctor_name, patient_name, medicine, quantity, dosage_strength, description, created_at');
      $stmt->execute([
        ':doctor_name' => $doctor_name,
        ':patient_name' => $patient_name,
        ':medicine' => $medicine,
        ':quantity' => $quantity,
        ':dosage_strength' => $dosage_strength,
        ':description' => $description ?: null,
      ]);
    }
  }

  $prescription = $stmt->fetch(PDO::FETCH_ASSOC);

  // Also store into patient_records for unified records history
  try {
    $createdAt = isset($prescription['created_at']) ? (string)$prescription['created_at'] : date('Y-m-d H:i:s');
    $datePart = substr($createdAt, 0, 10);
    $timePart = substr($createdAt, 11, 8);
    $prColStmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'patient_records'");
    $prColStmt->execute();
    $prCols = array_map('strtolower', array_map('strval', array_column($prColStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
    $hasInstruction = in_array('instruction', $prCols, true);

    if ($hasInstruction) {
      $pr = $pdo->prepare('INSERT INTO patient_records (patient, "date", "time", notes, created_at, doctor, medicine, dosage, instruction, created_by_user_id)
        VALUES (:patient, :date, :time, :notes, :created_at, :doctor, :medicine, :dosage, :instruction, :uid)');
      $pr->execute([
        ':patient' => $patient_name,
        ':date' => $datePart,
        ':time' => $timePart,
        ':notes' => ($description === '' ? null : $description),
        ':created_at' => $createdAt,
        ':doctor' => $doctor_name,
        ':medicine' => $medicine,
        ':dosage' => $dosage_strength,
        ':instruction' => ($instruction === '' ? null : $instruction),
        ':uid' => $uid,
      ]);
    } else {
      $pr = $pdo->prepare('INSERT INTO patient_records (patient, "date", "time", notes, created_at, doctor, medicine, dosage, created_by_user_id)
        VALUES (:patient, :date, :time, :notes, :created_at, :doctor, :medicine, :dosage, :uid)');
      $pr->execute([
        ':patient' => $patient_name,
        ':date' => $datePart,
        ':time' => $timePart,
        ':notes' => ($description === '' ? null : $description),
        ':created_at' => $createdAt,
        ':doctor' => $doctor_name,
        ':medicine' => $medicine,
        ':dosage' => $dosage_strength,
        ':uid' => $uid,
      ]);
    }
  } catch (Throwable $e) { /* ignore patient_records errors */
  }

  // Session activity: log prescription submission
  try {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    if (!isset($_SESSION['doctor_activity']) || !is_array($_SESSION['doctor_activity'])) {
      $_SESSION['doctor_activity'] = [];
    }
    $ts = date('Y-m-d H:i:s');
    $meta = substr($ts, 0, 16);
    $title = 'Prescription submitted';
    $body = ($patient_name !== '' ? ('Patient: ' . $patient_name . ' • ') : '') . 'Medicine: ' . $medicine;
    $_SESSION['doctor_activity'][] = ['title' => $title, 'meta' => $meta, 'body' => $body, 'ts' => $ts];
    if (count($_SESSION['doctor_activity']) > 50) {
      $_SESSION['doctor_activity'] = array_slice($_SESSION['doctor_activity'], -50);
    }
  } catch (Throwable $e) {
  }

  http_response_code(201);
  echo json_encode(['success' => true, 'message' => 'Prescription created successfully', 'data' => $prescription]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Failed to create prescription: ' . $e->getMessage()]);
}
