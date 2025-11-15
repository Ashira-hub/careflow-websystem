<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: /capstone/login.php');
  exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
  $_SESSION['flash_error'] = 'Email and password are required';
  header('Location: /capstone/login.php');
  exit;
}

// Hardcoded admin login shortcut (persisted)
if ($email === 'admin@gmail.com' && $password === 'Admin123') {
  try {
    $pdo = get_pdo();
    // Ensure a user row exists for this admin
    $stmt = $pdo->prepare('SELECT id, full_name, email, role FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();
    $userId = 0;
    $fullName = 'Administrator';
    if ($row) {
      $userId = (int)$row['id'];
      $fullName = (string)($row['full_name'] ?: 'Administrator');
    } else {
      // Insert a minimal admin account
      $hash = password_hash('Admin123', PASSWORD_DEFAULT);
      $ins = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (:full_name, :email, :hash, 'admin') RETURNING id");
      $ins->execute([':full_name' => $fullName, ':email' => $email, ':hash' => $hash]);
      $userId = (int)$ins->fetchColumn();
    }
    // Preload avatar if present
    $avatar = '';
    try {
      $a = $pdo->prepare('SELECT avatar_uri FROM profile WHERE id = :id LIMIT 1');
      $a->execute([':id' => $userId]);
      $prow = $a->fetch();
      if ($prow && isset($prow['avatar_uri'])) { $avatar = (string)$prow['avatar_uri']; }
    } catch (Throwable $_) { /* ignore */ }

    $_SESSION['user'] = [
      'id' => $userId,
      'full_name' => $fullName,
      'email' => $email,
      'role' => 'admin',
      'avatar_uri' => $avatar,
    ];
    header('Location: /capstone/templates/admin/admin_dashboard.php');
    exit;
  } catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Login failed: ' . $e->getMessage();
    header('Location: /capstone/login.php');
    exit;
  }
}

try {
  $pdo = get_pdo();
  $stmt = $pdo->prepare('SELECT id, full_name, email, password_hash, role FROM users WHERE email = :email LIMIT 1');
  $stmt->execute([':email' => $email]);
  $user = $stmt->fetch();

  if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['flash_error'] = 'Invalid credentials';
    header('Location: /capstone/login.php');
    exit;
  }

  $_SESSION['user'] = [
    'id' => $user['id'],
    'full_name' => $user['full_name'],
    'email' => $user['email'],
    'role' => $user['role'],
  ];
  $_SESSION['flash_welcome'] = 'Welcome back, ' . ($user['full_name'] ?? '');

  switch ($user['role']) {
    case 'doctor':
      $dest = '/capstone/templates/doctor/doctor_dashboard.php'; break;
    case 'nurse':
      $dest = '/capstone/templates/nurse/nurse_dashboard.php'; break;
    case 'supervisor':
      $dest = '/capstone/templates/supervisor/supervisor_dashboard.php'; break;
    case 'pharmacist':
      $dest = '/capstone/templates/pharmacy/pharmacy_dashboard.php'; break;
    case 'labstaff':
      $dest = '/capstone/templates/laboratory/lab_dashboard.php'; break;
    case 'admin':
      $dest = '/capstone/templates/admin/admin_dashboard.php'; break;
    default:
      $dest = '/capstone/index.php';
  }

  header('Location: ' . $dest);
  exit;
} catch (Throwable $e) {
  $_SESSION['flash_error'] = 'Login failed: ' . $e->getMessage();
  header('Location: /capstone/login.php');
  exit;
}
