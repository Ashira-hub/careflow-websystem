<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: /capstone/register.php');
  exit;
}

$full_name = trim($_POST['full_name'] ?? '');
$role = trim($_POST['role'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

$errors = [];
if ($full_name === '') $errors[] = 'Full name is required';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
$valid_roles = ['doctor','nurse','supervisor','pharmacist','labstaff','admin'];
if (!in_array($role, $valid_roles, true)) $errors[] = 'Invalid role';
if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
if ($password !== $confirm) $errors[] = 'Passwords do not match';

if ($errors) {
  $_SESSION['flash_error'] = implode('\n', $errors);
  header('Location: /capstone/register.php');
  exit;
}

try {
  $pdo = get_pdo();
  $pdo->beginTransaction();
  $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
  $stmt->execute([':email' => $email]);
  if ($stmt->fetch()) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = 'Email already registered';
    header('Location: /capstone/register.php');
    exit;
  }

  $hash = password_hash($password, PASSWORD_DEFAULT);
  $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (:full_name, :email, :hash, :role) RETURNING id');
  $stmt->execute([':full_name' => $full_name, ':email' => $email, ':hash' => $hash, ':role' => $role]);
  $user_id = $stmt->fetchColumn();

  // If registering an admin, ensure admin profile is stored (after user exists)
  if ($role === 'admin') {
    // Ensure admin table exists
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS admin (
         id SERIAL PRIMARY KEY,
         user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
         full_name TEXT NOT NULL,
         email TEXT NOT NULL,
         created_at TIMESTAMPTZ DEFAULT now()
       )"
    );
    // Upsert admin record
    $upA = $pdo->prepare(
      "INSERT INTO admin (user_id, full_name, email)
       VALUES (:uid, :full_name, :email)
       ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name, email = EXCLUDED.email"
    );
    $upA->execute([':uid' => $user_id, ':full_name' => $full_name, ':email' => $email]);
  }

  // If registering a labstaff, ensure labstaff profile is stored (after user exists)
  if ($role === 'labstaff') {
    // Ensure labstaff table exists
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS labstaff (
         id SERIAL PRIMARY KEY,
         user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
         full_name TEXT NOT NULL,
         email TEXT NOT NULL,
         created_at TIMESTAMPTZ DEFAULT now()
       )"
    );
    // Upsert labstaff record
    $upL = $pdo->prepare(
      "INSERT INTO labstaff (user_id, full_name, email)
       VALUES (:uid, :full_name, :email)
       ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name, email = EXCLUDED.email"
    );
    $upL->execute([':uid' => $user_id, ':full_name' => $full_name, ':email' => $email]);
  }

  if ($role === 'supervisor') {
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS supervisor (
         id SERIAL PRIMARY KEY,
         user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
         full_name TEXT NOT NULL,
         email TEXT NOT NULL,
         created_at TIMESTAMPTZ DEFAULT now()
       )"
    );
    $upS = $pdo->prepare(
      "INSERT INTO supervisor (user_id, full_name, email)
       VALUES (:uid, :full_name, :email)
       ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name, email = EXCLUDED.email"
    );
    $upS->execute([':uid' => $user_id, ':full_name' => $full_name, ':email' => $email]);
  }

  // If registering a pharmacist, ensure pharmacist profile is stored (after user exists)
  if ($role === 'pharmacist') {
    // Ensure pharmacist table exists
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS pharmacist (
         id SERIAL PRIMARY KEY,
         user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
         full_name TEXT NOT NULL,
         email TEXT NOT NULL,
         created_at TIMESTAMPTZ DEFAULT now()
       )"
    );
    // Upsert pharmacist record
    $upP = $pdo->prepare(
      "INSERT INTO pharmacist (user_id, full_name, email)
       VALUES (:uid, :full_name, :email)
       ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name, email = EXCLUDED.email"
    );
    $upP->execute([':uid' => $user_id, ':full_name' => $full_name, ':email' => $email]);
  }

  // If registering a nurse, ensure nurse profile is stored (after user exists)
  if ($role === 'nurse') {
    // Ensure nurse table exists
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS nurse (
         id SERIAL PRIMARY KEY,
         user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
         full_name TEXT NOT NULL,
         email TEXT NOT NULL,
         created_at TIMESTAMPTZ DEFAULT now()
       )"
    );
    // Upsert nurse record
    $upN = $pdo->prepare(
      "INSERT INTO nurse (user_id, full_name, email)
       VALUES (:uid, :full_name, :email)
       ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name, email = EXCLUDED.email"
    );
    $upN->execute([':uid' => $user_id, ':full_name' => $full_name, ':email' => $email]);
  }

  // If registering a doctor, ensure doctor profile is stored
  if ($role === 'doctor') {
    // Create a minimal doctor table if completely missing (won't override existing columns)
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS doctor (
         id SERIAL PRIMARY KEY,
         full_name TEXT NOT NULL,
         email TEXT NOT NULL,
         created_at TIMESTAMPTZ DEFAULT now()
       )"
    );
    // Discover current columns in doctor table
    $colStmt = $pdo->prepare(
      "SELECT column_name FROM information_schema.columns
        WHERE table_schema = current_schema() AND table_name = 'doctor'"
    );
    $colStmt->execute();
    $docCols = array_map('strtolower', array_map('strval', array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
    $hasUserId = in_array('user_id', $docCols, true);
    $hasRole   = in_array('role', $docCols, true);
    $hasPwd    = in_array('password_hash', $docCols, true);
    $hasActive = in_array('active', $docCols, true);
    $hasCreated= in_array('created_at', $docCols, true);

    if ($hasUserId) {
      // Build dynamic column/value lists for upsert on user_id
      $insCols = ['user_id','full_name','email'];
      $insVals = [':uid', ':full_name', ':email'];
      $updates = ['full_name = EXCLUDED.full_name', 'email = EXCLUDED.email'];
      if ($hasRole)   { $insCols[]='role';          $insVals[]="'doctor'";           $updates[]='role = EXCLUDED.role'; }
      if ($hasPwd)    { $insCols[]='password_hash'; $insVals[]=':pwd';                $updates[]='password_hash = EXCLUDED.password_hash'; }
      if ($hasActive) { $insCols[]='active';        $insVals[]='TRUE';                $updates[]='active = COALESCE(EXCLUDED.active, TRUE)'; }
      if ($hasCreated){ $insCols[]='created_at';    $insVals[]='now()'; /* no update */ }
      $sql = 'INSERT INTO doctor (' . implode(',', $insCols) . ') VALUES (' . implode(',', $insVals) . ') '
           . 'ON CONFLICT (user_id) DO UPDATE SET ' . implode(', ', $updates);
      $stmt = $pdo->prepare($sql);
      $params = [ ':uid'=>$user_id, ':full_name'=>$full_name, ':email'=>$email ];
      if ($hasPwd) { $params[':pwd'] = $hash; }
      $stmt->execute($params);
    } else {
      // No user_id column. Insert available columns and avoid duplicates by email
      $insCols = [];
      $selCols = [];
      if (in_array('full_name', $docCols, true)) { $insCols[]='full_name';  $selCols[]=':full_name::text'; }
      if (in_array('email', $docCols, true))     { $insCols[]='email';      $selCols[]=':email::text'; }
      if ($hasRole)   { $insCols[]='role';          $selCols[]="'doctor'"; }
      if ($hasPwd)    { $insCols[]='password_hash'; $selCols[]=':pwd::text'; }
      if ($hasActive) { $insCols[]='active';        $selCols[]='TRUE'; }
      if ($hasCreated){ $insCols[]='created_at';    $selCols[]='now()'; }
      if (!empty($insCols)) {
        $sql = 'INSERT INTO doctor (' . implode(',', $insCols) . ') '
             . 'SELECT ' . implode(',', $selCols) . ' '
             . 'WHERE NOT EXISTS (SELECT 1 FROM doctor d WHERE LOWER(TRIM(d.email)) = LOWER(TRIM(:email::text)))';
        $stmt = $pdo->prepare($sql);
        $params = [ ':full_name'=>$full_name, ':email'=>$email ];
        if ($hasPwd) { $params[':pwd'] = $hash; }
        $stmt->execute($params);
      }
    }
  }
  $pdo->commit();

  header('Location: /capstone/login.php');
  exit;
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
  $_SESSION['flash_error'] = 'Registration failed: ' . $e->getMessage();
  header('Location: /capstone/register.php');
  exit;
}
