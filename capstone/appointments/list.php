<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

try {
  $pdo = get_pdo();
  $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;
  $stmt = $pdo->prepare("SELECT id, patient, \"date\", \"time\", notes, COALESCE(done,false) AS done
                         FROM appointments
                         ORDER BY \"date\" DESC, \"time\" DESC
                         LIMIT :limit");
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode([ 'items' => $rows ]);
  exit;
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([ 'error' => 'Failed to load appointments' ]);
  exit;
}
