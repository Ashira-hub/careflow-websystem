<?php
// Unified PHP endpoint for appointments CRUD
// Methods supported:
// - GET    /appointments/create.php              -> list appointments
// - POST   /appointments/create.php              -> create appointment
// - PUT    /appointments/create.php?id=123       -> update appointment
// - DELETE /appointments/create.php?id=123       -> delete appointment

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$uid = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;

function json_body(): array {
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function ok($data) {
  echo json_encode($data);
  exit;
}

function fail($code, $msg) {
  http_response_code($code);
  echo json_encode(['message' => $msg]);
  exit;
}

try {
  $pdo = get_pdo();
} catch (Throwable $e) {
  fail(500, 'DB unavailable');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

// ----------------------------------------------------------------------
// GET - List all appointments
// ----------------------------------------------------------------------
if ($method === 'GET') {
  try {
    $stmt = $pdo->prepare('SELECT id, patient, "date", "time", notes, done, created_by_name, created_at
                           FROM appointments
                           WHERE created_by_user_id = :uid
                           ORDER BY id DESC');
    $stmt->execute([':uid'=>$uid]);
    $rows = $stmt->fetchAll();
    ok($rows);
  } catch (Throwable $e) {
    fail(500, 'Server error: ' . $e->getMessage());
  }
}

// ----------------------------------------------------------------------
// POST - Create new appointment
// ----------------------------------------------------------------------
if ($method === 'POST') {
  $b = array_replace([], $_POST, json_body());

  $patient = isset($b['patient']) ? trim((string)$b['patient']) : '';
  // Accept both 'date' and 'appt_date'
  $date = isset($b['date']) ? trim((string)$b['date']) : (isset($b['appt_date']) ? trim((string)$b['appt_date']) : '');
  // Accept both 'time' and 'appt_time'
  $time = isset($b['time']) ? trim((string)$b['time']) : (isset($b['appt_time']) ? trim((string)$b['appt_time']) : '');
  $notes = $b['notes'] ?? null;
  $done = isset($b['done']) ? (bool)$b['done'] : false;
  $created = isset($b['createdByName']) ? trim((string)$b['createdByName']) : (isset($_SESSION['user']['full_name']) ? (string)$_SESSION['user']['full_name'] : null);

  // Normalize date (accept DD/MM/YYYY or YYYY-MM-DD)
  if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
    [$d, $m, $y] = explode('/', $date);
    $date = sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
  }

  // Normalize time (accept 'hh:mm am/pm' or HH:MM)
  if ($time !== '') {
    $t = strtolower($time);
    if (strpos($t, 'am') !== false || strpos($t, 'pm') !== false) {
      $ts = strtotime($t);
      if ($ts !== false) $time = date('H:i:s', $ts);
    } elseif (preg_match('/^\d{1,2}:\d{2}$/', $t)) {
      $time .= ':00';
    }
  }

  if ($patient === '' || $date === '' || $time === '') {
    fail(400, 'Missing required fields: patient, date, or time');
  }

  try {
    $stmt = $pdo->prepare('INSERT INTO appointments (patient, "date", "time", notes, done, created_by_name, created_by_user_id, created_at)
      VALUES (:patient, :date, :time, :notes, :done, :created_by_name, :created_by_user_id, :created_at)
      RETURNING id, patient, "date", "time", notes, done, created_by_name, created_at');

    $done_pg = $done ? 'true' : 'false';
    $stmt->execute([
      ':patient' => $patient,
      ':date' => $date,
      ':time' => $time,
      ':notes' => ($notes === '' ? null : $notes),
      ':done' => $done_pg,
      ':created_by_name' => $created,
      ':created_at' => date('Y-m-d H:i:s'),
      ':created_by_user_id' => $uid,
    ]);

    $row = $stmt->fetch();

    // Session activity: log new appointment
    try {
      if (session_status() === PHP_SESSION_NONE) { session_start(); }
      if (!isset($_SESSION['doctor_activity']) || !is_array($_SESSION['doctor_activity'])) { $_SESSION['doctor_activity'] = []; }
      $ts = date('Y-m-d H:i:s');
      $meta = substr($ts, 0, 16);
      $title = 'Appointment created';
      $body = (string)$patient;
      $_SESSION['doctor_activity'][] = ['title'=>$title,'meta'=>$meta,'body'=>$body,'ts'=>$ts];
      if (count($_SESSION['doctor_activity']) > 50) { $_SESSION['doctor_activity'] = array_slice($_SESSION['doctor_activity'], -50); }
    } catch (Throwable $e) { }

    // Mirror to simplified table: appointment
    try {
      $status = $done ? 'done' : 'pending';
      $m = $pdo->prepare('INSERT INTO appointment (full_name, "date", "time", status, appointment_id)
        VALUES (:full_name, :date, :time, :status, :appointment_id)');
      $m->execute([
        ':full_name' => $patient,
        ':date' => $row['date'],
        ':time' => $row['time'],
        ':status' => $status,
        ':appointment_id' => $row['id'],
      ]);
    } catch (Throwable $e) { /* ignore mirror errors */ }

    // Also store into patient_records for unified records history
    try {
      $pr = $pdo->prepare('INSERT INTO patient_records (patient, "date", "time", notes, created_at, doctor, medicine, dosage, created_by_user_id)
        VALUES (:patient, :date, :time, :notes, :created_at, :doctor, :medicine, :dosage, :uid)');
      $pr->execute([
        ':patient' => $patient,
        ':date' => $row['date'],
        ':time' => $row['time'],
        ':notes' => ($notes === '' ? null : $notes),
        ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
        ':doctor' => $created,
        ':medicine' => null,
        ':dosage' => null,
        ':uid' => $uid,
      ]);
    } catch (Throwable $e) { /* ignore patient_records errors */ }

    http_response_code(201);
    ok($row ?: ['id' => null]);
  } catch (Throwable $e) {
    fail(500, 'Server error: ' . $e->getMessage());
  }
}

// ----------------------------------------------------------------------
// PUT - Update appointment
// ----------------------------------------------------------------------
if ($method === 'PUT') {
  if (!$id) fail(400, 'Missing id');
  $b = json_body();

  $patient = array_key_exists('patient', $b) ? $b['patient'] : null;
  $date = array_key_exists('date', $b) ? $b['date'] : (array_key_exists('appt_date', $b) ? $b['appt_date'] : null);
  $time = array_key_exists('time', $b) ? $b['time'] : (array_key_exists('appt_time', $b) ? $b['appt_time'] : null);
  $notes = array_key_exists('notes', $b) ? $b['notes'] : null;
  $done = array_key_exists('done', $b) ? (bool)$b['done'] : null;
  $created = array_key_exists('createdByName', $b) ? $b['createdByName'] : null;

  // Normalize date/time formats if provided
  if (is_string($date) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
    [$d, $m, $y] = explode('/', $date);
    $date = sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
  }
  if (is_string($time)) {
    $t = strtolower($time);
    if (strpos($t, 'am') !== false || strpos($t, 'pm') !== false) {
      $ts = strtotime($t);
      if ($ts !== false) $time = date('H:i:s', $ts);
    } elseif (preg_match('/^\d{1,2}:\d{2}$/', $t)) {
      $time .= ':00';
    }
  }

  try {
    $stmt = $pdo->prepare('UPDATE appointments
      SET patient = COALESCE(:patient, patient),
          "date" = COALESCE(:date, "date"),
          "time" = COALESCE(:time, "time"),
          notes = COALESCE(:notes, notes),
          done = COALESCE(:done, done),
          created_by_name = COALESCE(:created, created_by_name)
      WHERE id = :id AND created_by_user_id = :uid
      RETURNING id, patient, "date", "time", notes, done, created_by_name, created_at');

    $done_pg = is_null($done) ? null : ($done ? 'true' : 'false');
    $stmt->execute([
      ':patient' => $patient,
      ':date' => $date,
      ':time' => $time,
      ':notes' => $notes,
      ':done' => $done_pg,
      ':created' => $created,
      ':id' => $id,
      ':uid' => $uid,
    ]);

    if ($stmt->rowCount() === 0) fail(404, 'Appointment not found');
    $updated = $stmt->fetch();

    // Mirror update
    try {
      $status = $updated['done'] ? 'done' : 'pending';
      $m = $pdo->prepare('UPDATE appointment
        SET full_name = COALESCE(:full_name, full_name),
            "date" = COALESCE(:date, "date"),
            "time" = COALESCE(:time, "time"),
            status = :status
        WHERE appointment_id = :appointment_id');
      $m->execute([
        ':full_name' => $patient,
        ':date' => $date,
        ':time' => $time,
        ':status' => $status,
        ':appointment_id' => $updated['id'],
      ]);
    } catch (Throwable $e) { /* ignore mirror errors */ }

    // Session activity: log appointment update
    try {
      if (session_status() === PHP_SESSION_NONE) { session_start(); }
      if (!isset($_SESSION['doctor_activity']) || !is_array($_SESSION['doctor_activity'])) { $_SESSION['doctor_activity'] = []; }
      $ts = date('Y-m-d H:i:s');
      $meta = substr($ts, 0, 16);
      $title = 'Appointment updated';
      $body = (string)($updated['patient'] ?? '');
      $_SESSION['doctor_activity'][] = ['title'=>$title,'meta'=>$meta,'body'=>$body,'ts'=>$ts];
      if (count($_SESSION['doctor_activity']) > 50) { $_SESSION['doctor_activity'] = array_slice($_SESSION['doctor_activity'], -50); }
    } catch (Throwable $e) { }

    ok($updated);
  } catch (Throwable $e) {
    fail(500, 'Server error: ' . $e->getMessage());
  }
}

// ----------------------------------------------------------------------
// DELETE - Delete appointment
// ----------------------------------------------------------------------
if ($method === 'DELETE') {
  if (!$id) fail(400, 'Missing id');
  try {
    $stmt = $pdo->prepare('DELETE FROM appointments WHERE id = :id AND created_by_user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $uid]);
    if ($stmt->rowCount() === 0) fail(404, 'Appointment not found');

    // Mirror delete
    try {
      $m = $pdo->prepare('DELETE FROM appointment WHERE appointment_id = :id');
      $m->execute([':id' => $id]);
    } catch (Throwable $e) { }

    http_response_code(204);
    exit;
  } catch (Throwable $e) {
    fail(500, 'Server error: ' . $e->getMessage());
  }
}

fail(405, 'Method not allowed');
?>
