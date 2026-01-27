<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

try {
  $pdo = get_pdo();
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'DB unavailable']);
  exit;
}

try {
  // Ensure table exists (best-effort; different pages already do this)
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS lab_tests (
      id SERIAL PRIMARY KEY,
      patient TEXT NOT NULL,
      test_name TEXT NOT NULL,
      category TEXT,
      status TEXT,
      test_date DATE,
      description TEXT,
      notes TEXT,
      created_at TIMESTAMPTZ DEFAULT now(),
      updated_at TIMESTAMPTZ DEFAULT now()
    )"
  );
} catch (Throwable $e) {
  // Ignore: table may exist with different schema
}

// Discover current columns to safely select
try {
  $colStmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'lab_tests'");
  $colStmt->execute();
  $cols = array_map('strtolower', array_map('strval', array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
} catch (Throwable $e) {
  $cols = [];
}

$hasLegacyDate = in_array('date', $cols, true);
$hasTestDate = in_array('test_date', $cols, true);
$hasCreatedAt = in_array('created_at', $cols, true);
$hasNotes = in_array('notes', $cols, true);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

try {
  $dateExpr = 'NULL';
  if ($hasTestDate) {
    $dateExpr = 'test_date::text';
  } elseif ($hasLegacyDate) {
    $dateExpr = 'date::text';
  } elseif ($hasCreatedAt) {
    $dateExpr = 'created_at::text';
  }

  $notesExpr = $hasNotes ? 'notes' : "''";

  $sql = "SELECT id,
            patient,
            test_name,
            category,
            status,
            {$dateExpr} AS date,
            {$notesExpr} AS notes
          FROM lab_tests
          ORDER BY id DESC";

  $stmt = $pdo->query($sql);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  echo json_encode($rows);
  exit;
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Failed to load lab records']);
  exit;
}
