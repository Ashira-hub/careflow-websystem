<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

$notificationDir = __DIR__ . '/../data';
$storeFile = $notificationDir . '/notifications_supervisor.json';
if (!is_dir($notificationDir)) { @mkdir($notificationDir, 0777, true); }
if (!file_exists($storeFile)) { @file_put_contents($storeFile, json_encode([])); }

function load_store($file){
  $raw = @file_get_contents($file);
  if ($raw === false || $raw === '') { return []; }
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}
function save_store($file, $data){
  @file_put_contents($file, json_encode(array_values($data), JSON_PRETTY_PRINT));
}

// --- Helpers to notify other roles (e.g., nurse) ---
function role_store_file($role, $dir){
  $map = [
    'nurse' => $dir . '/notifications_nurse.json',
  ];
  return $map[$role] ?? null;
}
function append_role_notification($role, $dir, $title, $body){
  $file = role_store_file($role, $dir);
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

function update_request_status($requestId, $status){
  $storeDir = __DIR__ . '/../data';
  $storeFile = $storeDir . '/nurse_shift_requests.json';
  if (!file_exists($storeFile)) return;
  $raw = @file_get_contents($storeFile);
  if ($raw === false || $raw === '') return;
  $data = json_decode($raw, true);
  if (!is_array($data)) return;
  $updated = false;
  foreach ($data as &$item) {
    if ((int)($item['id'] ?? 0) === (int)$requestId) {
      $item['status'] = $status;
      $item['status_changed_at'] = date('Y-m-d H:i');
      $item['updated_at'] = date('Y-m-d H:i');
      $updated = true;
      break;
    }
  }
  unset($item);
  if ($updated) {
    @file_put_contents($storeFile, json_encode(array_values($data), JSON_PRETTY_PRINT));
  }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$allowedStatuses = ['pending','accepted','rejected'];

function normalize_status($value, $allowed){
  $val = strtolower((string)$value);
  return in_array($val, $allowed, true) ? $val : $allowed[0];
}

if ($method === 'GET') {
  $items = load_store($storeFile);
  echo json_encode(['items' => array_values($items)]);
  exit;
}

if ($method === 'POST') {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!is_array($input)) { http_response_code(400); echo json_encode(['error'=>'Invalid JSON']); exit; }
  $title = trim($input['title'] ?? '');
  $body = trim($input['body'] ?? '');
  $time = $input['time'] ?? date('Y-m-d H:i');
  $requestId = isset($input['request_id']) ? (int)$input['request_id'] : null;
  $status = normalize_status($input['status'] ?? 'pending', $allowedStatuses);
  if ($title === '' || $body === '') { http_response_code(400); echo json_encode(['error'=>'title and body required']); exit; }
  $items = load_store($storeFile);
  $nextId = 1;
  if (!empty($items)) { $ids = array_column($items, 'id'); $nextId = max($ids) + 1; }
  $new = [
    'id' => $nextId,
    'title' => $title,
    'body' => $body,
    'time' => $time,
    'read' => false,
    'status' => $status,
    'request_id' => $requestId,
    'status_changed_at' => null
  ];
  $items[] = $new;
  save_store($storeFile, $items);
  echo json_encode($new);
  exit;
}

if ($method === 'PUT') {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'id required']); exit; }
  $input = json_decode(file_get_contents('php://input'), true);
  if (!is_array($input)) { http_response_code(400); echo json_encode(['error'=>'Invalid JSON']); exit; }
  $items = load_store($storeFile);
  $idx = -1;
  foreach ($items as $k => $v) { if ((int)$v['id'] === $id) { $idx = $k; break; } }
  if ($idx === -1) { http_response_code(404); echo json_encode(['error'=>'not found']); exit; }
  if (array_key_exists('read', $input)) { $items[$idx]['read'] = (bool)$input['read']; }
  if (array_key_exists('status', $input)) {
    $items[$idx]['status'] = normalize_status($input['status'], $allowedStatuses);
    $items[$idx]['status_changed_at'] = date('Y-m-d H:i');
    if (!empty($items[$idx]['request_id'])) {
      update_request_status($items[$idx]['request_id'], $items[$idx]['status']);
    }
    // Notify nurse when supervisor accepts/rejects a nurse request
    $st = $items[$idx]['status'];
    if ($st === 'accepted' || $st === 'rejected'){
      $title = $st === 'accepted' ? 'Schedule request accepted by Supervisor' : 'Schedule request rejected by Supervisor';
      $body  = trim(($items[$idx]['title'] ?? '').' | '.($items[$idx]['body'] ?? ''));
      append_role_notification('nurse', dirname($storeFile), $title, $body);
    }
  }
  save_store($storeFile, $items);
  echo json_encode($items[$idx]);
  exit;
}

if ($method === 'DELETE') {
  save_store($storeFile, []);
  echo json_encode(['ok'=>true]);
  exit;
}

http_response_code(405);
echo json_encode(['error'=>'Method not allowed']);
