<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

function json_body(): array
{
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function get_header_value(string $key): ?string
{
  $k = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
  return isset($_SERVER[$k]) ? (string)$_SERVER[$k] : null;
}

function normalize_name(string $s): string
{
  return strtolower(trim(preg_replace('/\s+/', ' ', $s)));
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
  $pdo = get_pdo();
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'DB unavailable']);
  exit;
}

try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS patient_notifications (
    id BIGSERIAL PRIMARY KEY,
    patient_name TEXT NOT NULL,
    user_id BIGINT,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
  )");
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Failed to initialize notifications']);
  exit;
}

$userIdHeader = get_header_value('X-User-Id');
$userId = $userIdHeader !== null && ctype_digit($userIdHeader) ? (int)$userIdHeader : 0;

$userName = '';
if ($userId > 0) {
  try {
    $u = $pdo->prepare('SELECT full_name FROM users WHERE id = :id LIMIT 1');
    $u->execute([':id' => $userId]);
    $userName = (string)($u->fetchColumn() ?: '');
  } catch (Throwable $e) {
    $userName = '';
  }
}

if ($method === 'GET') {
  $patientNameParam = isset($_GET['patient']) ? trim((string)$_GET['patient']) : '';
  $needle = '';
  if ($userName !== '') {
    $needle = normalize_name($userName);
  } elseif ($patientNameParam !== '') {
    $needle = normalize_name($patientNameParam);
  }

  try {
    if ($userId > 0 && $needle !== '') {
      $stmt = $pdo->prepare('SELECT id, title, message, created_at, is_read FROM patient_notifications WHERE user_id = :uid OR LOWER(TRIM(patient_name)) = :p ORDER BY created_at DESC, id DESC');
      $stmt->execute([':uid' => $userId, ':p' => $needle]);
    } elseif ($needle !== '') {
      $stmt = $pdo->prepare('SELECT id, title, message, created_at, is_read FROM patient_notifications WHERE LOWER(TRIM(patient_name)) = :p ORDER BY created_at DESC, id DESC');
      $stmt->execute([':p' => $needle]);
    } else {
      $stmt = $pdo->query('SELECT id, title, message, created_at, is_read FROM patient_notifications ORDER BY created_at DESC, id DESC');
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $out = array_map(function ($r) {
      return [
        'id' => (string)($r['id'] ?? ''),
        'title' => (string)($r['title'] ?? ''),
        'message' => (string)($r['message'] ?? ''),
        'created_at' => (string)($r['created_at'] ?? ''),
        'read' => (bool)($r['is_read'] ?? false),
      ];
    }, $rows);

    echo json_encode($out);
    exit;
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load notifications']);
    exit;
  }
}

if ($method === 'POST') {
  $b = json_body();
  $title = trim((string)($b['title'] ?? ''));
  $message = trim((string)($b['message'] ?? ''));
  $patientName = trim((string)($b['patient_name'] ?? $b['patient'] ?? ''));
  $userIdBody = isset($b['user_id']) && ctype_digit((string)$b['user_id']) ? (int)$b['user_id'] : 0;

  if ($title === '' || $message === '' || $patientName === '') {
    http_response_code(400);
    echo json_encode(['error' => 'title, message, and patient_name are required']);
    exit;
  }

  try {
    $stmt = $pdo->prepare('INSERT INTO patient_notifications (patient_name, user_id, title, message, is_read) VALUES (:patient_name, :user_id, :title, :message, false) RETURNING id, title, message, created_at, is_read');
    $stmt->execute([
      ':patient_name' => $patientName,
      ':user_id' => ($userIdBody > 0 ? $userIdBody : null),
      ':title' => $title,
      ':message' => $message,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
      'id' => (string)($row['id'] ?? ''),
      'title' => (string)($row['title'] ?? $title),
      'message' => (string)($row['message'] ?? $message),
      'created_at' => (string)($row['created_at'] ?? ''),
      'read' => (bool)($row['is_read'] ?? false),
    ]);
    exit;
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create notification']);
    exit;
  }
}

if ($method === 'PUT') {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'id required']);
    exit;
  }
  $b = json_body();
  $read = array_key_exists('read', $b) ? (bool)$b['read'] : null;
  if ($read === null) {
    http_response_code(400);
    echo json_encode(['error' => 'read required']);
    exit;
  }

  try {
    $stmt = $pdo->prepare('UPDATE patient_notifications SET is_read = :read WHERE id = :id RETURNING id, title, message, created_at, is_read');
    $stmt->execute([':read' => $read, ':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      http_response_code(404);
      echo json_encode(['error' => 'not found']);
      exit;
    }
    echo json_encode([
      'id' => (string)($row['id'] ?? ''),
      'title' => (string)($row['title'] ?? ''),
      'message' => (string)($row['message'] ?? ''),
      'created_at' => (string)($row['created_at'] ?? ''),
      'read' => (bool)($row['is_read'] ?? false),
    ]);
    exit;
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update notification']);
    exit;
  }
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
