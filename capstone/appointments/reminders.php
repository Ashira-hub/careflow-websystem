<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

date_default_timezone_set('Asia/Manila');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$storeFile = __DIR__.'/reminders.json';
$notificationDir = __DIR__.'/../data';
if(!is_dir($notificationDir)){
  @mkdir($notificationDir, 0777, true);
}

function ensure_file($file){
  if (!file_exists($file)) {
    file_put_contents($file, json_encode([]));
  }
}
function load_store($file){
  ensure_file($file);
  $raw = file_get_contents($file);
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}
function save_store($file, $data){
  file_put_contents($file, json_encode(array_values($data), JSON_PRETTY_PRINT));
}
function role_store_file($role, $dir){
  $role = strtolower((string)$role);
  $map = [
    'doctor' => $dir.'/notifications_doctor.json',
    'nurse' => $dir.'/notifications_nurse.json',
    'supervisor' => $dir.'/notifications_supervisor.json',
  ];
  return $map[$role] ?? null;
}
function append_role_notification($role, $dir, $title, $body){
  $file = role_store_file($role, $dir);
  if(!$file) return;
  $items = load_store($file);
  $nextId = 1;
  if(!empty($items)){
    $ids = array_column($items, 'id');
    $nextId = max($ids) + 1;
  }
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

if ($method === 'POST') {
  $payload = json_decode(file_get_contents('php://input'), true);
  if(!is_array($payload)){
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
  }
  $appointmentId = intval($payload['appointment_id'] ?? 0);
  $patient = trim((string)($payload['patient'] ?? ''));
  $date = trim((string)($payload['date'] ?? ''));
  $time = trim((string)($payload['time'] ?? ''));
  $offset = intval($payload['offset_minutes'] ?? 0);
  $role = strtolower((string)($payload['role'] ?? 'doctor'));
  if(!$appointmentId || !$date || !$time){
    http_response_code(422);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
  }
  if($offset < 0) $offset = 0;
  ensure_file($storeFile);
  $items = load_store($storeFile);
  $dtStr = $date.' '.$time;
  $ts = strtotime($dtStr);
  if($ts === false){
    http_response_code(422);
    echo json_encode(['error' => 'Invalid appointment time']);
    exit;
  }
  $remindTs = $ts - ($offset * 60);
  if($remindTs < time()){
    $remindTs = time();
  }
  $nextId = 1;
  if(!empty($items)){
    $ids = array_column($items, 'id');
    $nextId = max($ids) + 1;
  }
  $items[] = [
    'id' => $nextId,
    'appointment_id' => $appointmentId,
    'patient' => $patient,
    'date' => $date,
    'time' => $time,
    'role' => $role,
    'offset_minutes' => $offset,
    'remind_at' => date('Y-m-d H:i', $remindTs),
    'sent' => false
  ];
  save_store($storeFile, $items);
  echo json_encode(['ok' => true, 'id' => $nextId]);
  exit;
}

if ($method === 'GET') {
  $role = strtolower((string)($_GET['role'] ?? 'doctor'));
  $dueOnly = isset($_GET['due']) ? intval($_GET['due']) : 0;
  $items = load_store($storeFile);
  if($dueOnly) {
    $now = time();
    $updated = false;
    $triggered = [];
    foreach($items as &$item){
      if(!$item['sent'] && strtolower($item['role']) === $role){
        $remindTs = strtotime($item['remind_at'] ?? '');
        if($remindTs !== false && $remindTs <= $now){
          $item['sent'] = true;
          $updated = true;
          $triggered[] = $item;
        }
      }
    }
    unset($item);
    if($updated){
      save_store($storeFile, $items);
      foreach($triggered as $rem){
        $body = 'Reminder: '.$rem['patient'].' appointment on '.$rem['date'].' '.$rem['time'];
        append_role_notification($role, $notificationDir, 'Appointment reminder', $body);
      }
    }
    echo json_encode(['ok' => true, 'triggered' => array_map(function($r){ return ['id'=>$r['id']]; }, $triggered)]);
    exit;
  }
  echo json_encode(['items' => $items]);
  exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
