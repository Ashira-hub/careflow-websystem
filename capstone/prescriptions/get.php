<?php
// API endpoint for fetching a single prescription by ID from PostgreSQL
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'id is required']);
  exit;
}

try {
  $pdo = get_pdo();

  // Prefer newer schema with status and created_by_user_id when available.
  try {
    $stmt = $pdo->prepare('SELECT id, doctor_name, patient_name, medicine, quantity, dosage_strength, description, status, created_at, created_by_user_id
      FROM prescription
      WHERE id = :id');
    $stmt->execute([':id' => $id]);
  } catch (Throwable $e) {
    $stmt = $pdo->prepare('SELECT id, doctor_name, patient_name, medicine, quantity, dosage_strength, description, created_at
      FROM prescription
      WHERE id = :id');
    $stmt->execute([':id' => $id]);
  }

  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Prescription not found']);
    exit;
  }

  echo json_encode(['success' => true, 'data' => $row]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Failed to fetch prescription: ' . $e->getMessage()]);
}
