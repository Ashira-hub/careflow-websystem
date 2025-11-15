<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$storeDir = __DIR__ . '/../data';
$storeFile = $storeDir . '/notifications_pharmacy.json';
if (!is_dir($storeDir)) { @mkdir($storeDir, 0777, true); }
if (!file_exists($storeFile)) { @file_put_contents($storeFile, json_encode([])); }

function load_store($file){
  $raw = @file_get_contents($file);
  if ($raw === false || $raw === '') { return []; }
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function save_store($file, $data){
  @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function role_store_file($role, $dir, $doctorId = null){
  $role = strtolower((string)$role);
  if ($role === 'doctor') {
    // If a specific doctor_id is provided, store per-doctor
    $doctorId = (int)($doctorId ?? 0);
    if ($doctorId > 0) {
      return $dir . '/notifications_doctor_' . $doctorId . '.json';
    }
    // Fallback legacy doctor file (role-wide)
    return $dir . '/notifications_doctor.json';
  }
  if ($role === 'nurse')       return $dir . '/notifications_nurse.json';
  if ($role === 'pharmacy')    return $dir . '/notifications_pharmacy.json';
  if ($role === 'laboratory')  return $dir . '/notifications_laboratory.json';
  return null;
}

function append_role_notification($role, $dir, $title, $body, $doctorId = null){
  $file = role_store_file($role, $dir, $doctorId);
  if(!$file) return;
  if (!file_exists($file)) { @file_put_contents($file, json_encode([])); }
  $items = load_store($file);
  $nextId = 1; if (!empty($items)) { $ids = array_column($items, 'id'); $nextId = max($ids) + 1; }
  $items[] = [
    'id' => $nextId,
    'title' => $title,
    'body' => $body,
    'time' => date('Y-m-d H:i'),
    'read' => false,
    'status' => 'new'
  ];
  save_store($file, $items);
}

function nurse_prescription_store($dir){
  return $dir . '/nurse_prescriptions.json';
}

function ensure_store_file($file){
  if (!file_exists($file)) { @file_put_contents($file, json_encode([])); }
}

function load_nurse_prescriptions($file){
  ensure_store_file($file);
  $raw = @file_get_contents($file);
  if ($raw === false || $raw === '') return [];
  $decoded = json_decode($raw, true);
  return is_array($decoded) ? $decoded : [];
}

function save_nurse_prescriptions($file, $items){
  @file_put_contents($file, json_encode(array_values($items), JSON_PRETTY_PRINT));
}

function parse_prescription_body($body){
  $fields = [
    'patient' => '',
    'medicine' => '',
    'dose' => '',
    'route' => '',
    'frequency' => '',
    'notes' => ''
  ];
  if(!$body) return $fields;
  $parts = preg_split('/\|/', $body);
  foreach ($parts as $part) {
    $part = trim((string)$part);
    if ($part === '') continue;
    $lower = strtolower($part);
    if (strpos($lower, 'patient:') === 0) { $fields['patient'] = trim(substr($part, strpos($part, ':')+1)); }
    elseif (strpos($lower, 'medicine:') === 0 || strpos($lower, 'medication:') === 0) { $fields['medicine'] = trim(substr($part, strpos($part, ':')+1)); }
    elseif (strpos($lower, 'dose:') === 0) { $fields['dose'] = trim(substr($part, strpos($part, ':')+1)); }
    elseif (strpos($lower, 'route:') === 0) { $fields['route'] = trim(substr($part, strpos($part, ':')+1)); }
    elseif (strpos($lower, 'frequency:') === 0) { $fields['frequency'] = trim(substr($part, strpos($part, ':')+1)); }
    else {
      $fields['notes'] = trim($fields['notes'].' '. $part);
    }
  }
  $fields['notes'] = trim($fields['notes']);
  return $fields;
}

function parse_prescription_id_from_body($body){
  if(!$body) return null;
  $parts = preg_split('/\|/', $body);
  foreach ($parts as $part) {
    $part = trim((string)$part);
    if ($part === '') continue;
    $lower = strtolower($part);
    if (strpos($lower, 'prescriptionid:') === 0 || strpos($lower, 'prescription_id:') === 0) {
      $val = trim(substr($part, strpos($part, ':')+1));
      if ($val !== '' && ctype_digit($val)) {
        return (int)$val;
      }
    }
  }
  return null;
}

function upsert_nurse_prescription($dir, $notification){
  $file = nurse_prescription_store($dir);
  $items = load_nurse_prescriptions($file);
  $id = (int)($notification['id'] ?? 0);
  $fields = parse_prescription_body($notification['body'] ?? '');
  $found = false;
  foreach ($items as &$entry) {
    if ((int)($entry['notification_id'] ?? 0) === $id) {
      $entry['status'] = $notification['status'] ?? 'accepted';
      $entry['updated_at'] = date('Y-m-d H:i');
      $entry['body'] = $notification['body'] ?? '';
      $entry['title'] = $notification['title'] ?? '';
      $entry['patient'] = $fields['patient'];
      $entry['medicine'] = $fields['medicine'];
      $entry['dose'] = $fields['dose'];
      $entry['route'] = $fields['route'];
      $entry['frequency'] = $fields['frequency'];
      $entry['notes'] = $fields['notes'];
      $found = true;
      break;
    }
  }
  unset($entry);
  if (!$found) {
    $items[] = [
      'notification_id' => $id,
      'title' => $notification['title'] ?? '',
      'body' => $notification['body'] ?? '',
      'patient' => $fields['patient'],
      'medicine' => $fields['medicine'],
      'dose' => $fields['dose'],
      'route' => $fields['route'],
      'frequency' => $fields['frequency'],
      'notes' => $fields['notes'],
      'status' => $notification['status'] ?? 'accepted',
      'time' => $notification['time'] ?? date('Y-m-d H:i'),
      'updated_at' => date('Y-m-d H:i')
    ];
  }
  save_nurse_prescriptions($file, $items);
}

function remove_nurse_prescription($dir, $notificationId){
  $file = nurse_prescription_store($dir);
  $items = load_nurse_prescriptions($file);
  $filtered = array_values(array_filter($items, function($entry) use ($notificationId){
    return (int)($entry['notification_id'] ?? 0) !== (int)$notificationId;
  }));
  save_nurse_prescriptions($file, $filtered);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  $role = isset($_GET['role']) ? strtolower((string)$_GET['role']) : '';
  $doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
  if ($role === 'doctor' || $role === 'nurse' || $role === 'pharmacy' || $role === 'laboratory') {
    $rf = role_store_file($role, $storeDir, $doctorId);
    if (!file_exists($rf)) { @file_put_contents($rf, json_encode([])); }
    $items = load_store($rf);
    echo json_encode(['items' => array_values($items)]);
  } else {
    $items = load_store($storeFile);
    echo json_encode(['items' => array_values($items)]);
  }
  exit;
}

if ($method === 'POST') {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!is_array($input)) { http_response_code(400); echo json_encode(['error'=>'Invalid JSON']); exit; }
  $title = trim($input['title'] ?? '');
  $body = trim($input['body'] ?? '');
  $time = $input['time'] ?? date('Y-m-d H:i');
  $prescriptionId = isset($input['prescription_id']) ? (int)$input['prescription_id'] : null;
  $role = isset($_GET['role']) ? strtolower((string)$_GET['role']) : '';
  // Prefer doctor_id from GET, else payload
  $doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : (int)($input['doctor_id'] ?? 0);
  if ($title === '' || $body === '') { http_response_code(400); echo json_encode(['error'=>'title and body required']); exit; }
  // Route to role-specific store if provided, otherwise default
  $rf = ($role === 'doctor' || $role === 'nurse' || $role === 'pharmacy' || $role === 'laboratory')
      ? role_store_file($role, $storeDir, $doctorId)
      : $storeFile;
  if (!file_exists($rf)) { @file_put_contents($rf, json_encode([])); }
  $items = load_store($rf);
  $nextId = 1;
  if (!empty($items)) { $ids = array_column($items, 'id'); $nextId = max($ids) + 1; }
  $new = [
    'id' => $nextId,
    'title' => $title,
    'body' => $body,
    'time' => $time,
    'read' => false,
    'status' => 'new',
    'prescription_id' => $prescriptionId,
    'doctor_id' => $doctorId
  ];
  $items[] = $new;
  save_store($rf, $items);

  // Also persist notification into PostgreSQL notifications table (best-effort, non-fatal on error)
  try {
    $pdo = get_pdo();
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
      id BIGSERIAL PRIMARY KEY,
      role TEXT NOT NULL,
      title TEXT NOT NULL,
      body TEXT NOT NULL,
      time TIMESTAMPTZ NOT NULL,
      status TEXT,
      is_read BOOLEAN NOT NULL DEFAULT false,
      doctor_id BIGINT,
      prescription_id BIGINT,
      created_at TIMESTAMPTZ NOT NULL DEFAULT now()
    )");

    $stmt = $pdo->prepare('INSERT INTO notifications (role, title, body, time, status, is_read, doctor_id, prescription_id)
      VALUES (:role, :title, :body, :time, :status, :is_read, :doctor_id, :prescription_id)');
    $stmt->execute([
      ':role' => ($role === '' ? 'pharmacy' : $role),
      ':title' => $title,
      ':body' => $body,
      ':time' => $time,
      ':status' => 'new',
      ':is_read' => false,
      ':doctor_id' => $doctorId ?: null,
      ':prescription_id' => $prescriptionId ?: null,
    ]);
  } catch (Throwable $e) {
    // Ignore DB persistence errors to keep file-based notifications working
  }

  echo json_encode($new);
  exit;
}

if ($method === 'PUT') {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'id required']); exit; }
  $input = json_decode(file_get_contents('php://input'), true);
  if (!is_array($input)) { http_response_code(400); echo json_encode(['error'=>'Invalid JSON']); exit; }

  $role = isset($_GET['role']) ? strtolower((string)$_GET['role']) : '';
  $doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
  if ($role === 'doctor' || $role === 'nurse' || $role === 'pharmacy' || $role === 'laboratory') {
    $rf = role_store_file($role, $storeDir, $doctorId);
    if (!file_exists($rf)) { @file_put_contents($rf, json_encode([])); }
    $items = load_store($rf);
    $idx = -1;
    foreach ($items as $k => $v) { if ((int)$v['id'] === $id) { $idx = $k; break; } }
    if ($idx === -1) { http_response_code(404); echo json_encode(['error'=>'not found']); exit; }
    if (array_key_exists('read', $input)) { $items[$idx]['read'] = (bool)$input['read']; }
    save_store($rf, $items);
    echo json_encode($items[$idx]);
    exit;
  }

  $items = load_store($storeFile);
  $idx = -1;
  foreach ($items as $k => $v) { if ((int)$v['id'] === $id) { $idx = $k; break; } }
  if ($idx === -1) { http_response_code(404); echo json_encode(['error'=>'not found']); exit; }
  // Allowed fields to update
  if (array_key_exists('read', $input)) { $items[$idx]['read'] = (bool)$input['read']; }
  if (array_key_exists('status', $input)) {
    $allowed = ['new','accepted','acknowledged','done','rejected','dispensed'];
    $val = strtolower((string)$input['status']);
    if (in_array($val, $allowed, true)) { $items[$idx]['status'] = $val; }
  }
  save_store($storeFile, $items);
  // If accepted, notify doctor and nurse as well
  $statusNow = $items[$idx]['status'] ?? '';

  // Attempt to sync status to prescription table if we can parse a prescription ID from the body
  try {
    $pdo = get_pdo();
    $pid = null;
    if (isset($items[$idx]['prescription_id'])) {
      $pid = (int)$items[$idx]['prescription_id'];
    }
    if (!$pid) {
      $body = $items[$idx]['body'] ?? '';
      $pid = parse_prescription_id_from_body($body);
    }
    if ($pid !== null && $pid > 0) {
      $stmt = $pdo->prepare('UPDATE prescription SET status = :status WHERE id = :id');
      $stmt->execute([
        ':status' => $statusNow,
        ':id' => $pid,
      ]);
    }
  } catch (Throwable $e) {
  }

  if ($statusNow === 'accepted') {
    $title = 'Prescription accepted by Pharmacy';
    $body  = $items[$idx]['body'] ?? ($items[$idx]['title'] ?? '');
    append_role_notification('doctor', $storeDir, $title, $body, $doctorId);
    append_role_notification('nurse', $storeDir, $title, $body);
    upsert_nurse_prescription($storeDir, $items[$idx]);
  } elseif ($statusNow === 'acknowledged') {
    $title = 'Prescription acknowledged by Nurse';
    $body  = $items[$idx]['body'] ?? ($items[$idx]['title'] ?? '');
    append_role_notification('doctor', $storeDir, $title, $body, $doctorId);
    append_role_notification('pharmacy', $storeDir, $title, $body);
    upsert_nurse_prescription($storeDir, $items[$idx]);
  } elseif ($statusNow === 'done') {
    $title = 'Prescription administered by Nurse';
    $body  = $items[$idx]['body'] ?? ($items[$idx]['title'] ?? '');
    append_role_notification('doctor', $storeDir, $title, $body, $doctorId);
    append_role_notification('pharmacy', $storeDir, $title, $body);
    upsert_nurse_prescription($storeDir, $items[$idx]);
  } elseif ($statusNow === 'rejected') {
    remove_nurse_prescription($storeDir, $items[$idx]['id']);
  } elseif ($statusNow === 'dispensed') {
    $title = 'Prescription dispensed by Pharmacy';
    $body  = $items[$idx]['body'] ?? ($items[$idx]['title'] ?? '');
    append_role_notification('doctor', $storeDir, $title, $body, $doctorId);
    append_role_notification('nurse', $storeDir, $title, $body);
    upsert_nurse_prescription($storeDir, $items[$idx]);
  }
  echo json_encode($items[$idx]);
  exit;
}

if ($method === 'DELETE') {
  $role = isset($_GET['role']) ? strtolower((string)$_GET['role']) : '';
  $doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
  if ($role === 'doctor' || $role === 'nurse' || $role === 'pharmacy' || $role === 'laboratory') {
    $rf = role_store_file($role, $storeDir, $doctorId);
    if (!file_exists($rf)) { @file_put_contents($rf, json_encode([])); }
    save_store($rf, []);
    echo json_encode(['ok'=>true]);
  } else {
    // Clear pharmacy notifications (legacy default)
    save_store($storeFile, []);
    echo json_encode(['ok'=>true]);
  }
  exit;
}

http_response_code(405);
echo json_encode(['error'=>'Method not allowed']);
