<?php
// API endpoint for updating prescription status in PostgreSQL
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

function json_body(): array {
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

$body = json_body();
$id = isset($body['id']) ? (int)$body['id'] : 0;
$status = isset($body['status']) ? strtolower(trim((string)$body['status'])) : '';

if ($id <= 0 || $status === '') {
  http_response_code(400);
  echo json_encode(['error' => 'id and status are required']);
  exit;
}

$allowed = ['new','accepted','acknowledged','done','rejected','dispensed','pending'];
if (!in_array($status, $allowed, true)) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid status']);
  exit;
}

try {
  $pdo = get_pdo();
  $stmt = $pdo->prepare('UPDATE prescription SET status = :status WHERE id = :id RETURNING id, doctor_name, patient_name, medicine, quantity, dosage_strength, description, status, created_at');
  $stmt->execute([
    ':status' => $status,
    ':id' => $id,
  ]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Prescription not found']);
    exit;
  }
  echo json_encode(['success' => true, 'data' => $row]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Failed to update prescription: ' . $e->getMessage()]);
}
