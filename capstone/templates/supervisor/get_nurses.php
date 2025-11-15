<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';

try {
    $pdo = get_pdo();
    $stmt = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'nurse' ORDER BY full_name ASC");
    $nurses = [];
    
    while ($row = $stmt->fetch()) {
        $nurses[] = [
            'id' => (int)$row['id'],
            'name' => trim($row['full_name'] ?: 'Unknown Nurse'),
            'email' => trim($row['email'] ?: '')
        ];
    }
    
    echo json_encode($nurses);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch nurses: ' . $e->getMessage()]);
}
?>
