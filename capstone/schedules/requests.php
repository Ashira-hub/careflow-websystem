<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../config/db.php';

function pdo_safe() {
  try {
    return get_pdo();
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
  }
}

function table_has_column($pdo, $table, $column){
  try{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = :t AND column_name = :c');
    $stmt->execute([':t'=>$table, ':c'=>$column]);
    return (bool)$stmt->fetchColumn();
  }catch(Throwable $e){ return false; }
}

function pick_col($pdo, $table, $candidates){
  foreach ($candidates as $c) {
    if (table_has_column($pdo, $table, $c)) return $c;
  }
  return null;
}

function map_row($row){
  if(!$row) return null;
  $start = isset($row['start_time']) ? (string)$row['start_time'] : '';
  $end = isset($row['end_time']) ? (string)$row['end_time'] : '';
  $time = trim(($start !== '' || $end !== '') ? ($start . ($end !== '' ? ' - ' . $end : '')) : ((string)($row['time'] ?? '')));
  return [
    'id' => (int)($row['id'] ?? 0),
    'nurse' => $row['nurse'] ?? '',
    'nurse_id' => isset($row['nurse_id']) ? (int)$row['nurse_id'] : null,
    'nurse_email' => $row['nurse_email'] ?? '',
    'created_by_user_id' => isset($row['created_by_user_id']) ? (int)$row['created_by_user_id'] : null,
    'date' => $row['date'] ?? ($row['date_str'] ?? ''),
    'time' => $time,
    'start_time' => $start,
    'end_time' => $end,
    'shift' => $row['shift'] ?? '',
    'ward' => $row['ward'] ?? ($row['station'] ?? ''),
    'notes' => $row['notes'] ?? '',
    'status' => strtolower($row['status'] ?? 'request'),
    'created_at' => $row['created_at'] ?? null,
    'updated_at' => $row['updated_at'] ?? null,
  ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  $pdo = pdo_safe();
  try {
    $role = isset($_GET['role']) ? strtolower((string)$_GET['role']) : '';
    $hasNurseId = table_has_column($pdo, 'schedules', 'nurse_id');
    $hasNurseEmail = table_has_column($pdo, 'schedules', 'nurse_email');
    $hasCreatedAt = table_has_column($pdo, 'schedules', 'created_at');
    $hasUpdatedAt = table_has_column($pdo, 'schedules', 'updated_at');
    $colNurse = pick_col($pdo, 'schedules', ['nurse','nurse_name','requested_by','requester','created_by_name']);
    $colDate  = pick_col($pdo, 'schedules', ['date','schedule_date']);
    $colShift = pick_col($pdo, 'schedules', ['shift','title','name']);
    $colWard  = pick_col($pdo, 'schedules', ['ward','station','unit','department']);
    $colNotes = pick_col($pdo, 'schedules', ['notes','note','remarks','description']);
    $colStatus= pick_col($pdo, 'schedules', ['status','state']);
    $colStart = pick_col($pdo, 'schedules', ['start_time','start','time_start','from_time']);
    $colEnd   = pick_col($pdo, 'schedules', ['end_time','end','time_end','to_time']);

    $hasCreatedBy = table_has_column($pdo, 'schedules', 'created_by_user_id');
    $cols = ''
      . 'id'
      . ', ' . ($colNurse ? $colNurse.' as nurse' : "''::text as nurse")
      . ', ' . ($hasNurseId? 'nurse_id' : 'NULL::int as nurse_id')
      . ', ' . ($hasNurseEmail? 'nurse_email' : "''::text as nurse_email")
      . ', ' . ($hasCreatedBy ? 'created_by_user_id' : 'NULL::int as created_by_user_id')
      . ', ' . ($colDate ? 'to_char("'.$colDate.'",\'YYYY-MM-DD\') as date' : "'' as date")
      . ', ' . ($colShift ? $colShift.' as shift' : "'' as shift")
      . ', ' . ($colWard ? $colWard.' as ward' : "'' as ward")
      . ', ' . ($colNotes ? $colNotes.' as notes' : "'' as notes")
      . ', ' . ($colStatus ? $colStatus.' as status' : "'request' as status")
      . ', ' . ($colStart ? 'to_char("'.$colStart.'",\'HH24:MI\') as start_time' : "'' as start_time")
      . ', ' . ($colEnd ? 'to_char("'.$colEnd.'",\'HH24:MI\') as end_time' : "'' as end_time")
      . ', ' . ($hasCreatedAt ? 'created_at' : 'now() as created_at')
      . ', ' . ($hasUpdatedAt ? 'updated_at' : 'now() as updated_at');
    $base = 'SELECT ' . $cols . ' FROM schedules';
    if ($role === 'nurse') {
      $fltCol = $colStatus ?: 'status';
      $sql = $base . " WHERE LOWER(COALESCE(".$fltCol.",'')) IN ('accepted','created') ORDER BY created_at DESC";
      $stmt = $pdo->query($sql);
    } else {
      $stmt = $pdo->query($base . ' ORDER BY created_at DESC');
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $items = array_map('map_row', $rows);
    echo json_encode(['items' => array_values($items)]);
    exit;
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load schedules']);
    exit;
  }
}

if ($method === 'POST') {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!is_array($input)) { http_response_code(400); echo json_encode(['error'=>'Invalid JSON']); exit; }
  $nurse = trim((string)($input['nurse'] ?? ''));
  $nurseId = (int)($input['nurse_id'] ?? 0);
  $nurseEmail = trim((string)($input['nurse_email'] ?? ''));
  $date = trim((string)($input['date'] ?? ''));
  $shift = trim((string)($input['shift'] ?? ''));
  // Frontend sends station; map to ward
  $ward = trim((string)($input['ward'] ?? ($input['station'] ?? '')));
  // Frontend sends start_time/end_time; compute display time for response
  $startTime = trim((string)($input['start_time'] ?? ''));
  $endTime = trim((string)($input['end_time'] ?? ''));
  $notes = trim((string)($input['notes'] ?? ($input['note'] ?? '')));
  $status = strtolower((string)($input['status'] ?? 'request'));
  $createdByUserId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
  if ($nurse === '' || $date === '' || $shift === '' || $ward === '' || $startTime === '' || $endTime === '') {
    http_response_code(422); echo json_encode(['error'=>'Missing required fields']); exit;
  }
  $pdo = pdo_safe();
  try {
    $hasNurseId = table_has_column($pdo, 'schedules', 'nurse_id');
    $hasNurseEmail = table_has_column($pdo, 'schedules', 'nurse_email');
    $hasCreatedAt = table_has_column($pdo, 'schedules', 'created_at');
    $hasUpdatedAt = table_has_column($pdo, 'schedules', 'updated_at');
    $hasCreatedBy = table_has_column($pdo, 'schedules', 'created_by_user_id');
    $colNurse = pick_col($pdo, 'schedules', ['nurse','nurse_name','requested_by','requester','created_by_name']);
    $colDate  = pick_col($pdo, 'schedules', ['date','schedule_date']);
    $colShift = pick_col($pdo, 'schedules', ['shift','title','name']);
    $colWard  = pick_col($pdo, 'schedules', ['ward','station','unit','department']);
    $colNotes = pick_col($pdo, 'schedules', ['notes','note','remarks','description']);
    $colStatus= pick_col($pdo, 'schedules', ['status','state']);
    $colStart = pick_col($pdo, 'schedules', ['start_time','start','time_start','from_time']);
    $colEnd   = pick_col($pdo, 'schedules', ['end_time','end','time_end','to_time']);

    $cols = [];
    $vals = [];
    $params = [];
    if($colNurse){ $cols[] = $colNurse; $vals[]=':nurse'; $params[':nurse']=$nurse; } else if($nurse !== '') { $notes = trim($notes.' | Nurse: '.$nurse); }
    if($hasNurseId){ $cols[] = 'nurse_id'; $vals[]=':nurse_id'; $params[':nurse_id'] = $nurseId; }
    if($hasNurseEmail){ $cols[] = 'nurse_email'; $vals[]=':nurse_email'; $params[':nurse_email'] = $nurseEmail; }
    if($colDate){ $cols[] = '"'.$colDate.'"'; $vals[]='CAST(:date AS date)'; $params[':date']=$date; }
    if($colShift){ $cols[] = $colShift; $vals[]=':shift'; $params[':shift']=$shift; } else { $notes = trim($notes.' | Shift: '.$shift); }
    if($colWard){ $cols[] = $colWard; $vals[]=':ward'; $params[':ward']=$ward; } else { $notes = trim($notes.' | Station: '.$ward); }
    if($colStart){ $cols[] = '"'.$colStart.'"'; $vals[]='CAST(:start_time AS time)'; $params[':start_time']=$startTime; } else { $notes = trim($notes.' | Start: '.$startTime); }
    if($colEnd){ $cols[] = '"'.$colEnd.'"'; $vals[]='CAST(:end_time AS time)'; $params[':end_time']=$endTime; } else { $notes = trim($notes.' | End: '.$endTime); }
    if($colNotes){ $cols[] = $colNotes; $vals[]=':notes'; $params[':notes']=$notes; }
    if($colStatus){ $cols[] = $colStatus; $vals[]=':status'; $params[':status']=$status; }
    if($hasCreatedBy && $createdByUserId){ $cols[] = 'created_by_user_id'; $vals[]=':created_by_user_id'; $params[':created_by_user_id'] = $createdByUserId; }
    // Include timestamps only if columns exist
    if($hasCreatedAt){ $cols[] = 'created_at'; $vals[] = 'now()'; }
    if($hasUpdatedAt){ $cols[] = 'updated_at'; $vals[] = 'now()'; }

    if (empty($cols)) { http_response_code(500); echo json_encode(['error'=>'Table schedules has no compatible columns']); exit; }
    $returning = 'id' . ($hasCreatedAt ? ', created_at' : '') . ($hasUpdatedAt ? ', updated_at' : '');
    $sql = 'INSERT INTO schedules ('.implode(',', $cols).') VALUES ('.implode(',', $vals).') RETURNING ' . $returning;
    $ins = $pdo->prepare($sql);
    $ins->execute($params);
    $ret = $ins->fetch(PDO::FETCH_ASSOC) ?: [];
    $id = (int)($ret['id'] ?? 0);
    $record = map_row([
      'id' => $id,
      'nurse' => $nurse,
      'nurse_id' => $hasNurseId ? $nurseId : null,
      'nurse_email' => $hasNurseEmail ? $nurseEmail : '',
      'created_by_user_id' => $hasCreatedBy ? $createdByUserId : null,
      'date' => $date,
      'shift' => $shift,
      'ward' => $ward,
      'notes' => $notes,
      'status' => $status,
      'start_time' => $startTime,
      'end_time' => $endTime,
      'created_at' => $ret['created_at'] ?? null,
      'updated_at' => $ret['updated_at'] ?? null,
    ]);
    echo json_encode(['id' => $id, 'item' => $record]);
    exit;
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save schedule', 'details' => $e->getMessage()]);
    exit;
  }
}

if ($method === 'PUT') {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'id required']); exit; }
  $input = json_decode(file_get_contents('php://input'), true);
  if (!is_array($input)) { http_response_code(400); echo json_encode(['error'=>'Invalid JSON']); exit; }
  $pdo = pdo_safe();
  try {
    // Detect columns
    $hasCreatedAt = table_has_column($pdo, 'schedules', 'created_at');
    $hasUpdatedAt = table_has_column($pdo, 'schedules', 'updated_at');
    $hasNurseId = table_has_column($pdo, 'schedules', 'nurse_id');
    $hasNurseEmail = table_has_column($pdo, 'schedules', 'nurse_email');
    $hasCreatedBy = table_has_column($pdo, 'schedules', 'created_by_user_id');
    $colNurse = pick_col($pdo, 'schedules', ['nurse','nurse_name','requested_by','requester','created_by_name']);
    $colDate  = pick_col($pdo, 'schedules', ['date','schedule_date']);
    $colShift = pick_col($pdo, 'schedules', ['shift','title','name']);
    $colWard  = pick_col($pdo, 'schedules', ['ward','station','unit','department']);
    $colNotes = pick_col($pdo, 'schedules', ['notes','note','remarks','description']);
    $colStatus= pick_col($pdo, 'schedules', ['status','state']);
    $colStart = pick_col($pdo, 'schedules', ['start_time','start','time_start','from_time']);
    $colEnd   = pick_col($pdo, 'schedules', ['end_time','end','time_end','to_time']);

    // Build SETs using detected columns
    $sets = [];
    $params = [':id' => $id];
    if (array_key_exists('status', $input) && $colStatus) { $sets[] = '"'.$colStatus.'" = :status'; $params[':status'] = strtolower((string)$input['status']); }
    if (array_key_exists('notes', $input) && $colNotes) { $sets[] = '"'.$colNotes.'" = :notes'; $params[':notes'] = (string)$input['notes']; }
    if ($hasUpdatedAt) { $sets[] = 'updated_at = now()'; }
    if (empty($sets)) { echo json_encode(['ok'=>true]); exit; }

    // Build RETURNING list conditionally
    $cols = ''
      . 'id'
      . ', ' . ($colNurse ? $colNurse.' as nurse' : "''::text as nurse")
      . ', ' . ($hasNurseId? 'nurse_id' : 'NULL::int as nurse_id')
      . ', ' . ($hasNurseEmail? 'nurse_email' : "''::text as nurse_email")
      . ', ' . ($hasCreatedBy ? 'created_by_user_id' : 'NULL::int as created_by_user_id')
      . ', ' . ($colDate ? 'to_char("'.$colDate.'",\'YYYY-MM-DD\') as date' : "'' as date")
      . ', ' . ($colShift ? $colShift.' as shift' : "'' as shift")
      . ', ' . ($colWard ? $colWard.' as ward' : "'' as ward")
      . ', ' . ($colNotes ? $colNotes.' as notes' : "'' as notes")
      . ', ' . ($colStatus ? $colStatus.' as status' : "'request' as status")
      . ', ' . ($colStart ? 'to_char("'.$colStart.'",\'HH24:MI\') as start_time' : "'' as start_time")
      . ', ' . ($colEnd ? 'to_char("'.$colEnd.'",\'HH24:MI\') as end_time' : "'' as end_time")
      . ( $hasCreatedAt ? ', created_at' : '' )
      . ( $hasUpdatedAt ? ', updated_at' : '' );

    $sql = 'UPDATE schedules SET ' . implode(', ', $sets) . ' WHERE id = :id RETURNING ' . $cols;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row){ http_response_code(404); echo json_encode(['error'=>'not found']); exit; }
    echo json_encode(map_row($row));
    exit;
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update schedule', 'details' => $e->getMessage()]);
    exit;
  }
}

if ($method === 'DELETE') {
  // Optional: clear all (admin use). Keep consistent with previous behavior.
  $pdo = pdo_safe();
  try {
    $pdo->exec('DELETE FROM schedules');
    echo json_encode(['ok'=>true]);
    exit;
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete schedules']);
    exit;
  }
}

http_response_code(405);
echo json_encode(['error'=>'Method not allowed']);

?>
