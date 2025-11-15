<?php
 $page='Admin Users';
 require_once __DIR__.'/../../config/db.php';
 
 // Ensure doctor table exists and backfill existing doctor accounts from users
try {
  $pdo = get_pdo();
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

  // Build dynamic column and select lists
  $insCols = [];
  $selCols = [];
  if ($hasUserId) { $insCols[] = 'user_id';      $selCols[] = 'u.id'; }
  if (in_array('full_name', $docCols, true)) { $insCols[] = 'full_name';  $selCols[] = 'u.full_name'; }
  if (in_array('email', $docCols, true))     { $insCols[] = 'email';      $selCols[] = 'u.email'; }
  if ($hasRole)   { $insCols[] = 'role';        $selCols[] = "'doctor'"; }
  if ($hasPwd)    { $insCols[] = 'password_hash'; $selCols[] = 'u.password_hash'; }
  if ($hasActive) { $insCols[] = 'active';      $selCols[] = 'TRUE'; }
  if ($hasCreated){ $insCols[] = 'created_at';  $selCols[] = 'now()'; }

  if (!empty($insCols)) {
    if ($hasUserId) {
      // Upsert on user_id
      $sql = 'INSERT INTO doctor (' . implode(',', $insCols) . ') ' .
             'SELECT ' . implode(',', $selCols) . ' FROM users u ' .
             "WHERE LOWER(TRIM(u.role))='doctor' " .
             'ON CONFLICT (user_id) DO UPDATE SET ' .
             implode(', ', array_map(function($c){ return $c." = EXCLUDED.".$c; }, array_filter($insCols, function($c){ return $c !== 'user_id' && $c !== 'created_at'; })));
      $pdo->exec($sql);
    } else {
      // No user_id: avoid duplicates by email
      $sql = 'INSERT INTO doctor (' . implode(',', $insCols) . ') ' .
             'SELECT ' . implode(',', $selCols) . ' FROM users u ' .
             "WHERE LOWER(TRIM(u.role))='doctor' AND NOT EXISTS (SELECT 1 FROM doctor d WHERE LOWER(TRIM(d.email)) = LOWER(TRIM(u.email)))";
      $pdo->exec($sql);
    }
  }
} catch (Throwable $e) {
  error_log('[admin_users backfill] ' . $e->getMessage());
}
 
 // Ensure nurse table exists and backfill existing nurse accounts from users
 try {
   $pdo = get_pdo();
   // Create a minimal nurse table if completely missing (won't override existing columns)
   $pdo->exec(
     "CREATE TABLE IF NOT EXISTS nurse (
        id SERIAL PRIMARY KEY,
        full_name TEXT NOT NULL,
        email TEXT NOT NULL,
        created_at TIMESTAMPTZ DEFAULT now()
      )"
   );

   // Discover current columns in nurse table
   $colStmt = $pdo->prepare(
     "SELECT column_name FROM information_schema.columns
       WHERE table_schema = current_schema() AND table_name = 'nurse'"
   );
   $colStmt->execute();
   $nurseCols = array_map('strtolower', array_map('strval', array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
   $nHasUserId = in_array('user_id', $nurseCols, true);
   $nHasRole   = in_array('role', $nurseCols, true);
   $nHasPwd    = in_array('password_hash', $nurseCols, true);
   $nHasActive = in_array('active', $nurseCols, true);
   $nHasCreated= in_array('created_at', $nurseCols, true);

   $insCols = [];
   $selCols = [];
   if ($nHasUserId) { $insCols[] = 'user_id';      $selCols[] = 'u.id'; }
   if (in_array('full_name', $nurseCols, true)) { $insCols[] = 'full_name';  $selCols[] = 'u.full_name'; }
   if (in_array('email', $nurseCols, true))     { $insCols[] = 'email';      $selCols[] = 'u.email'; }
   if ($nHasRole)   { $insCols[] = 'role';        $selCols[] = "'nurse'"; }
   if ($nHasPwd)    { $insCols[] = 'password_hash'; $selCols[] = 'u.password_hash'; }
   if ($nHasActive) { $insCols[] = 'active';      $selCols[] = 'TRUE'; }
   if ($nHasCreated){ $insCols[] = 'created_at';  $selCols[] = 'now()'; }

   if (!empty($insCols)) {
     if ($nHasUserId) {
       $sql = 'INSERT INTO nurse (' . implode(',', $insCols) . ') '
            . 'SELECT ' . implode(',', $selCols) . ' FROM users u '
            . "WHERE LOWER(TRIM(u.role))='nurse' "
            . 'ON CONFLICT (user_id) DO UPDATE SET '
            . implode(', ', array_map(function($c){ return $c." = EXCLUDED.".$c; }, array_filter($insCols, function($c){ return $c !== 'user_id' && $c !== 'created_at'; })));
       $pdo->exec($sql);
     } else {
       $sql = 'INSERT INTO nurse (' . implode(',', $insCols) . ') '
            . 'SELECT ' . implode(',', $selCols) . ' FROM users u '
            . "WHERE LOWER(TRIM(u.role))='nurse' AND NOT EXISTS (SELECT 1 FROM nurse n WHERE LOWER(TRIM(n.email)) = LOWER(TRIM(u.email)))";
       $pdo->exec($sql);
     }
   }
 } catch (Throwable $e) {
   error_log('[admin_users backfill nurse] ' . $e->getMessage());
 }
 
 // Ensure pharmacist table exists and backfill existing pharmacist accounts from users
 try {
   $pdo = get_pdo();
   // Create a minimal pharmacist table if completely missing (won't override existing columns)
   $pdo->exec(
     "CREATE TABLE IF NOT EXISTS pharmacist (
        id SERIAL PRIMARY KEY,
        full_name TEXT NOT NULL,
        email TEXT NOT NULL,
        created_at TIMESTAMPTZ DEFAULT now()
      )"
   );

   // Discover current columns in pharmacist table
   $colStmt = $pdo->prepare(
     "SELECT column_name FROM information_schema.columns
       WHERE table_schema = current_schema() AND table_name = 'pharmacist'"
   );
   $colStmt->execute();
   $phCols = array_map('strtolower', array_map('strval', array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
   $pHasUserId = in_array('user_id', $phCols, true);
   $pHasRole   = in_array('role', $phCols, true);
   $pHasPwd    = in_array('password_hash', $phCols, true);
   $pHasActive = in_array('active', $phCols, true);
   $pHasCreated= in_array('created_at', $phCols, true);

   $insCols = [];
   $selCols = [];
   if ($pHasUserId) { $insCols[] = 'user_id';      $selCols[] = 'u.id'; }
   if (in_array('full_name', $phCols, true)) { $insCols[] = 'full_name';  $selCols[] = 'u.full_name'; }
   if (in_array('email', $phCols, true))     { $insCols[] = 'email';      $selCols[] = 'u.email'; }
   if ($pHasRole)   { $insCols[] = 'role';        $selCols[] = "'pharmacist'"; }
   if ($pHasPwd)    { $insCols[] = 'password_hash'; $selCols[] = 'u.password_hash'; }
   if ($pHasActive) { $insCols[] = 'active';      $selCols[] = 'TRUE'; }
   if ($pHasCreated){ $insCols[] = 'created_at';  $selCols[] = 'now()'; }

   if (!empty($insCols)) {
     if ($pHasUserId) {
       $sql = 'INSERT INTO pharmacist (' . implode(',', $insCols) . ') '
            . 'SELECT ' . implode(',', $selCols) . ' FROM users u '
            . "WHERE LOWER(TRIM(u.role))='pharmacist' "
            . 'ON CONFLICT (user_id) DO UPDATE SET '
            . implode(', ', array_map(function($c){ return $c." = EXCLUDED.".$c; }, array_filter($insCols, function($c){ return $c !== 'user_id' && $c !== 'created_at'; })));
       $pdo->exec($sql);
     } else {
       $sql = 'INSERT INTO pharmacist (' . implode(',', $insCols) . ') '
            . 'SELECT ' . implode(',', $selCols) . ' FROM users u '
            . "WHERE LOWER(TRIM(u.role))='pharmacist' AND NOT EXISTS (SELECT 1 FROM pharmacist p WHERE LOWER(TRIM(p.email)) = LOWER(TRIM(u.email)))";
       $pdo->exec($sql);
     }
   }
 } catch (Throwable $e) {
   error_log('[admin_users backfill pharmacist] ' . $e->getMessage());
 }
 
 // Ensure supervisor table exists and backfill existing supervisor accounts from users
 try {
   $pdo = get_pdo();
   // Create a minimal supervisor table if completely missing (won't override existing columns)
   $pdo->exec(
     "CREATE TABLE IF NOT EXISTS supervisor (
        id SERIAL PRIMARY KEY,
        full_name TEXT NOT NULL,
        email TEXT NOT NULL,
        created_at TIMESTAMPTZ DEFAULT now()
      )"
   );

   // Discover current columns in supervisor table
   $colStmt = $pdo->prepare(
     "SELECT column_name FROM information_schema.columns
       WHERE table_schema = current_schema() AND table_name = 'supervisor'"
   );
   $colStmt->execute();
   $supCols = array_map('strtolower', array_map('strval', array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
   $sHasUserId = in_array('user_id', $supCols, true);
   $sHasRole   = in_array('role', $supCols, true);
   $sHasPwd    = in_array('password_hash', $supCols, true);
   $sHasActive = in_array('active', $supCols, true);
   $sHasCreated= in_array('created_at', $supCols, true);

   $insCols = [];
   $selCols = [];
   if ($sHasUserId) { $insCols[] = 'user_id';      $selCols[] = 'u.id'; }
   if (in_array('full_name', $supCols, true)) { $insCols[] = 'full_name';  $selCols[] = 'u.full_name'; }
   if (in_array('email', $supCols, true))     { $insCols[] = 'email';      $selCols[] = 'u.email'; }
   if ($sHasRole)   { $insCols[] = 'role';        $selCols[] = "'supervisor'"; }
   if ($sHasPwd)    { $insCols[] = 'password_hash'; $selCols[] = 'u.password_hash'; }
   if ($sHasActive) { $insCols[] = 'active';      $selCols[] = 'TRUE'; }
   if ($sHasCreated){ $insCols[] = 'created_at';  $selCols[] = 'now()'; }

   if (!empty($insCols)) {
     if ($sHasUserId) {
       $sql = 'INSERT INTO supervisor (' . implode(',', $insCols) . ') '
            . 'SELECT ' . implode(',', $selCols) . ' FROM users u '
            . "WHERE LOWER(TRIM(u.role))='supervisor' "
            . 'ON CONFLICT (user_id) DO UPDATE SET '
            . implode(', ', array_map(function($c){ return $c." = EXCLUDED.".$c; }, array_filter($insCols, function($c){ return $c !== 'user_id' && $c !== 'created_at'; })));
       $pdo->exec($sql);
     } else {
       $sql = 'INSERT INTO supervisor (' . implode(',', $insCols) . ') '
            . 'SELECT ' . implode(',', $selCols) . ' FROM users u '
            . "WHERE LOWER(TRIM(u.role))='supervisor' AND NOT EXISTS (SELECT 1 FROM supervisor s WHERE LOWER(TRIM(s.email)) = LOWER(TRIM(u.email)))";
       $pdo->exec($sql);
     }
   }
 } catch (Throwable $e) {
   error_log('[admin_users backfill supervisor] ' . $e->getMessage());
 }
 
 // Ensure labstaff table exists and backfill existing labstaff accounts from users
 try {
   $pdo = get_pdo();
   // Create a minimal labstaff table if completely missing (won't override existing columns)
   $pdo->exec(
     "CREATE TABLE IF NOT EXISTS labstaff (
        id SERIAL PRIMARY KEY,
        full_name TEXT NOT NULL,
        email TEXT NOT NULL,
        created_at TIMESTAMPTZ DEFAULT now()
      )"
   );

   // Discover current columns in labstaff table
   $colStmt = $pdo->prepare(
     "SELECT column_name FROM information_schema.columns
       WHERE table_schema = current_schema() AND table_name = 'labstaff'"
   );
   $colStmt->execute();
   $labCols = array_map('strtolower', array_map('strval', array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
   $lHasUserId = in_array('user_id', $labCols, true);
   $lHasRole   = in_array('role', $labCols, true);
   $lHasPwd    = in_array('password_hash', $labCols, true);
   $lHasActive = in_array('active', $labCols, true);
   $lHasCreated= in_array('created_at', $labCols, true);

   $insCols = [];
   $selCols = [];
   if ($lHasUserId) { $insCols[] = 'user_id';      $selCols[] = 'u.id'; }
   if (in_array('full_name', $labCols, true)) { $insCols[] = 'full_name';  $selCols[] = 'u.full_name'; }
   if (in_array('email', $labCols, true))     { $insCols[] = 'email';      $selCols[] = 'u.email'; }
   if ($lHasRole)   { $insCols[] = 'role';        $selCols[] = "'labstaff'"; }
   if ($lHasPwd)    { $insCols[] = 'password_hash'; $selCols[] = 'u.password_hash'; }
   if ($lHasActive) { $insCols[] = 'active';      $selCols[] = 'TRUE'; }
   if ($lHasCreated){ $insCols[] = 'created_at';  $selCols[] = 'now()'; }

   if (!empty($insCols)) {
     if ($lHasUserId) {
       $sql = 'INSERT INTO labstaff (' . implode(',', $insCols) . ') '
            . 'SELECT ' . implode(',', $selCols) . ' FROM users u '
            . "WHERE LOWER(TRIM(u.role))='labstaff' "
            . 'ON CONFLICT (user_id) DO UPDATE SET '
            . implode(', ', array_map(function($c){ return $c." = EXCLUDED.".$c; }, array_filter($insCols, function($c){ return $c !== 'user_id' && $c !== 'created_at'; })));
       $pdo->exec($sql);
     } else {
       $sql = 'INSERT INTO labstaff (' . implode(',', $insCols) . ') '
            . 'SELECT ' . implode(',', $selCols) . ' FROM users u '
            . "WHERE LOWER(TRIM(u.role))='labstaff' AND NOT EXISTS (SELECT 1 FROM labstaff l WHERE LOWER(TRIM(l.email)) = LOWER(TRIM(u.email)))";
       $pdo->exec($sql);
     }
   }
 } catch (Throwable $e) {
   error_log('[admin_users backfill labstaff] ' . $e->getMessage());
 }
 
 // Ensure admin table exists and backfill existing admin accounts from users
 try {
   $pdo = get_pdo();
   // Create a minimal admin table if completely missing (won't override existing columns)
   $pdo->exec(
     "CREATE TABLE IF NOT EXISTS admin (
        id SERIAL PRIMARY KEY,
        full_name TEXT NOT NULL,
        email TEXT NOT NULL,
        created_at TIMESTAMPTZ DEFAULT now()
      )"
   );

   // Discover current columns in admin table
   $colStmt = $pdo->prepare(
     "SELECT column_name FROM information_schema.columns
       WHERE table_schema = current_schema() AND table_name = 'admin'"
   );
   $colStmt->execute();
   $admCols = array_map('strtolower', array_map('strval', array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
   $aHasUserId = in_array('user_id', $admCols, true);
   $aHasRole   = in_array('role', $admCols, true);
   $aHasPwd    = in_array('password_hash', $admCols, true);
   $aHasActive = in_array('active', $admCols, true);
   $aHasCreated= in_array('created_at', $admCols, true);

   $insCols = [];
   $selCols = [];
   if ($aHasUserId) { $insCols[] = 'user_id';      $selCols[] = 'u.id'; }
   if (in_array('full_name', $admCols, true)) { $insCols[] = 'full_name';  $selCols[] = 'u.full_name'; }
   if (in_array('email', $admCols, true))     { $insCols[] = 'email';      $selCols[] = 'u.email'; }
   if ($aHasRole)   { $insCols[] = 'role';        $selCols[] = "'admin'"; }
   if ($aHasPwd)    { $insCols[] = 'password_hash'; $selCols[] = 'u.password_hash'; }
   if ($aHasActive) { $insCols[] = 'active';      $selCols[] = 'TRUE'; }
   if ($aHasCreated){ $insCols[] = 'created_at';  $selCols[] = 'now()'; }

   if (!empty($insCols)) {
     if ($aHasUserId) {
       $sql = 'INSERT INTO admin (' . implode(',', $insCols) . ') '
            . 'SELECT ' . implode(',', $selCols) . ' FROM users u '
            . "WHERE LOWER(TRIM(u.role))='admin' "
            . 'ON CONFLICT (user_id) DO UPDATE SET '
            . implode(', ', array_map(function($c){ return $c." = EXCLUDED.".$c; }, array_filter($insCols, function($c){ return $c !== 'user_id' && $c !== 'created_at'; })));
       $pdo->exec($sql);
     } else {
       $sql = 'INSERT INTO admin (' . implode(',', $insCols) . ') '
            . 'SELECT ' . implode(',', $selCols) . ' FROM users u '
            . "WHERE LOWER(TRIM(u.role))='admin' AND NOT EXISTS (SELECT 1 FROM admin a WHERE LOWER(TRIM(a.email)) = LOWER(TRIM(u.email)))";
       $pdo->exec($sql);
     }
   }
 } catch (Throwable $e) {
   error_log('[admin_users backfill admin] ' . $e->getMessage());
 }
 
 // Handle form submissions
 if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $action = $_POST['action'] ?? '';
   $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
   
   if ($action === 'create') {
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? '');
    // Normalize role (map variants like 'lab_staff' to 'labstaff')
    $roleLower = strtolower((string)$role);
    $roleNorm = str_replace([' ', '_'], '', $roleLower);
    $validRoles = ['admin','doctor','nurse','supervisor','pharmacist','labstaff'];
    if (!in_array($roleNorm, $validRoles, true)) { $roleNorm = $roleLower; }
    $isActive = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true;

    // Basic validation
    $vErrors = [];
    if ($fullName === '') { $vErrors[] = 'Full name is required'; }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $vErrors[] = 'Valid email is required'; }
    if ($password === '' || strlen($password) < 6) { $vErrors[] = 'Password must be at least 6 characters'; }
    if ($roleNorm === '' || !in_array($roleNorm, $validRoles, true)) { $vErrors[] = 'Valid role is required'; }
    if (!empty($vErrors)) {
      if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'error'=>implode("\n", $vErrors)]);
        exit;
      }
      header('Location: /capstone/templates/admin/admin_users.php?error=create&reason=validation');
      exit;
    }
     
     try {
      $pdo = get_pdo();
     $pdo->beginTransaction();

     // Prevent duplicate emails
     $chk = $pdo->prepare("SELECT 1 FROM users WHERE LOWER(TRIM(email)) = LOWER(TRIM(:email)) LIMIT 1");
     $chk->execute([':email'=>$email]);
     if ($chk->fetchColumn()) {
       $pdo->rollBack();
       if ($isAjax) {
         header('Content-Type: application/json');
         echo json_encode(['ok'=>false,'error'=>'Email already exists']);
         exit;
       }
       header('Location: /capstone/templates/admin/admin_users.php?error=create&reason=duplicate');
       exit;
     }
      
      // Hash password
      $passwordHash = password_hash($password, PASSWORD_DEFAULT);
      
      // Insert user and get id
      $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (:full_name, :email, :password_hash, :role) RETURNING id");
     $stmt->execute([
       ':full_name' => $fullName,
       ':email' => $email,
       ':password_hash' => $passwordHash,
       ':role' => $roleNorm
     ]);
     $newUserId = (int)$stmt->fetchColumn();

      // If role is doctor, ensure doctor table exists and insert profile
      if ($roleNorm === 'doctor') {
        $pdo->exec(
          "CREATE TABLE IF NOT EXISTS doctor (
             id SERIAL PRIMARY KEY,
             user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
             full_name TEXT NOT NULL,
             email TEXT NOT NULL,
             created_at TIMESTAMPTZ DEFAULT now()
           )"
        );
        $insDoc = $pdo->prepare(
          "INSERT INTO doctor (user_id, full_name, email)
           VALUES (:uid, :full_name, :email)
           ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name, email = EXCLUDED.email"
        );
        $insDoc->execute([':uid'=>$newUserId, ':full_name'=>$fullName, ':email'=>$email]);
      }
      // If role is nurse, ensure nurse table exists and insert profile
      if ($roleNorm === 'nurse') {
        $pdo->exec(
          "CREATE TABLE IF NOT EXISTS nurse (
             id SERIAL PRIMARY KEY,
             user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
             full_name TEXT NOT NULL,
             email TEXT NOT NULL,
             created_at TIMESTAMPTZ DEFAULT now()
           )"
        );
        $insNurse = $pdo->prepare(
          "INSERT INTO nurse (user_id, full_name, email)
           VALUES (:uid, :full_name, :email)
           ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name, email = EXCLUDED.email"
        );
        $insNurse->execute([':uid'=>$newUserId, ':full_name'=>$fullName, ':email'=>$email]);
      }
      // If role is pharmacist, ensure pharmacist table exists and insert profile
      if ($roleNorm === 'pharmacist') {
        $pdo->exec(
          "CREATE TABLE IF NOT EXISTS pharmacist (
             id SERIAL PRIMARY KEY,
             user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
             full_name TEXT NOT NULL,
             email TEXT NOT NULL,
             created_at TIMESTAMPTZ DEFAULT now()
           )"
        );
        $insPh = $pdo->prepare(
          "INSERT INTO pharmacist (user_id, full_name, email)
           VALUES (:uid, :full_name, :email)
           ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name, email = EXCLUDED.email"
        );
        $insPh->execute([':uid'=>$newUserId, ':full_name'=>$fullName, ':email'=>$email]);
      }
      // If role is supervisor, ensure supervisor table exists and insert profile
      if ($roleNorm === 'supervisor') {
        $pdo->exec(
          "CREATE TABLE IF NOT EXISTS supervisor (
             id SERIAL PRIMARY KEY,
             user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
             full_name TEXT NOT NULL,
             email TEXT NOT NULL,
             created_at TIMESTAMPTZ DEFAULT now()
           )"
        );
        $insSup = $pdo->prepare(
          "INSERT INTO supervisor (user_id, full_name, email)
           VALUES (:uid, :full_name, :email)
           ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name, email = EXCLUDED.email"
        );
        $insSup->execute([':uid'=>$newUserId, ':full_name'=>$fullName, ':email'=>$email]);
      }
      // If role is labstaff, ensure labstaff table exists and insert profile
      if ($roleNorm === 'labstaff') {
        $pdo->exec(
          "CREATE TABLE IF NOT EXISTS labstaff (
             id SERIAL PRIMARY KEY,
             user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
             full_name TEXT NOT NULL,
             email TEXT NOT NULL,
             created_at TIMESTAMPTZ DEFAULT now()
           )"
        );
        $insLab = $pdo->prepare(
          "INSERT INTO labstaff (user_id, full_name, email)
           VALUES (:uid, :full_name, :email)
           ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name, email = EXCLUDED.email"
        );
        $insLab->execute([':uid'=>$newUserId, ':full_name'=>$fullName, ':email'=>$email]);
      }
      // If role is admin, ensure admin table exists and insert profile
      if ($roleNorm === 'admin') {
        $pdo->exec(
          "CREATE TABLE IF NOT EXISTS admin (
             id SERIAL PRIMARY KEY,
             user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
             full_name TEXT NOT NULL,
             email TEXT NOT NULL,
             created_at TIMESTAMPTZ DEFAULT now()
           )"
        );
        $insAdm = $pdo->prepare(
          "INSERT INTO admin (user_id, full_name, email)
           VALUES (:uid, :full_name, :email)
           ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name, email = EXCLUDED.email"
        );
        $insAdm->execute([':uid'=>$newUserId, ':full_name'=>$fullName, ':email'=>$email]);
      }

      $pdo->commit();
      if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok'=>true,'user'=>[
          'id'=>$newUserId,
          'full_name'=>$fullName,
          'email'=>$email,
          'role'=>$roleNorm
        ]]);
        exit;
      }
      header('Location: /capstone/templates/admin/admin_users.php?success=create');
      exit;
    } catch (Throwable $e) {
      if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
      error_log("Error creating user: " . $e->getMessage());
      if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'error'=>'Create failed']);
        exit;
      }
      header('Location: /capstone/templates/admin/admin_users.php?error=create');
      exit;
    }
  }
   
   if ($action === 'update' && $userId > 0) {
     $fullName = trim($_POST['full_name'] ?? '');
     $email = trim($_POST['email'] ?? '');
     $role = trim($_POST['role'] ?? '');
     
     try {
      $pdo = get_pdo();
      $pdo->beginTransaction();

      // Get previous role to detect role change
      $prevStmt = $pdo->prepare("SELECT role FROM users WHERE id = :id");
      $prevStmt->execute([':id'=>$userId]);
      $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);
      $prevRole = strtolower((string)($prev['role'] ?? ''));

      $stmt = $pdo->prepare("UPDATE users SET full_name = :full_name, email = :email, role = :role WHERE id = :id");
      $stmt->execute([
        ':full_name' => $fullName,
        ':email' => $email,
        ':role' => $role,
        ':id' => $userId
      ]);

      $newRole = strtolower($role);
      // Ensure doctor table exists if needed
      if ($newRole === 'doctor') {
        $pdo->exec(
          "CREATE TABLE IF NOT EXISTS doctor (
             id SERIAL PRIMARY KEY,
             user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
             full_name TEXT NOT NULL,
             email TEXT NOT NULL,
             created_at TIMESTAMPTZ DEFAULT now()
           )"
        );
        // Upsert doctor
        $up = $pdo->prepare(
          "INSERT INTO doctor (user_id, full_name, email)
           VALUES (:uid, :full_name, :email)
           ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name, email = EXCLUDED.email"
        );
        $up->execute([':uid'=>$userId, ':full_name'=>$fullName, ':email'=>$email]);
      } else if ($prevRole === 'doctor' && $newRole !== 'doctor') {
        // Remove doctor row if role changed away from doctor
        $del = $pdo->prepare("DELETE FROM doctor WHERE user_id = :uid");
        $del->execute([':uid'=>$userId]);
      }
      // Ensure nurse table exists if needed
      if ($newRole === 'nurse') {
        $pdo->exec(
          "CREATE TABLE IF NOT EXISTS nurse (
             id SERIAL PRIMARY KEY,
             user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
             full_name TEXT NOT NULL,
             email TEXT NOT NULL,
             created_at TIMESTAMPTZ DEFAULT now()
           )"
        );
        // Upsert nurse
        $upN = $pdo->prepare(
          "INSERT INTO nurse (user_id, full_name, email)
           VALUES (:uid, :full_name, :email)
           ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name, email = EXCLUDED.email"
        );
        $upN->execute([':uid'=>$userId, ':full_name'=>$fullName, ':email'=>$email]);
      } else if ($prevRole === 'nurse' && $newRole !== 'nurse') {
        // Remove nurse row if role changed away from nurse
        $delN = $pdo->prepare("DELETE FROM nurse WHERE user_id = :uid");
        $delN->execute([':uid'=>$userId]);
      }
      // Ensure pharmacist table exists if needed
      if ($newRole === 'pharmacist') {
        $pdo->exec(
          "CREATE TABLE IF NOT EXISTS pharmacist (
             id SERIAL PRIMARY KEY,
             user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
             full_name TEXT NOT NULL,
             email TEXT NOT NULL,
             created_at TIMESTAMPTZ DEFAULT now()
           )"
        );
        // Upsert pharmacist
        $upP = $pdo->prepare(
          "INSERT INTO pharmacist (user_id, full_name, email)
           VALUES (:uid, :full_name, :email)
           ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name, email = EXCLUDED.email"
        );
        $upP->execute([':uid'=>$userId, ':full_name'=>$fullName, ':email'=>$email]);
      } else if ($prevRole === 'pharmacist' && $newRole !== 'pharmacist') {
        // Remove pharmacist row if role changed away from pharmacist
        $delP = $pdo->prepare("DELETE FROM pharmacist WHERE user_id = :uid");
        $delP->execute([':uid'=>$userId]);
      }
      // Ensure supervisor table exists if needed
      if ($newRole === 'supervisor') {
        $pdo->exec(
          "CREATE TABLE IF NOT EXISTS supervisor (
             id SERIAL PRIMARY KEY,
             user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
             full_name TEXT NOT NULL,
             email TEXT NOT NULL,
             created_at TIMESTAMPTZ DEFAULT now()
           )"
        );
        // Upsert supervisor
        $upS = $pdo->prepare(
          "INSERT INTO supervisor (user_id, full_name, email)
           VALUES (:uid, :full_name, :email)
           ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name, email = EXCLUDED.email"
        );
        $upS->execute([':uid'=>$userId, ':full_name'=>$fullName, ':email'=>$email]);
      } else if ($prevRole === 'supervisor' && $newRole !== 'supervisor') {
        // Remove supervisor row if role changed away from supervisor
        $delS = $pdo->prepare("DELETE FROM supervisor WHERE user_id = :uid");
        $delS->execute([':uid'=>$userId]);
      }
      // Ensure labstaff table exists if needed
      if ($newRole === 'labstaff') {
        $pdo->exec(
          "CREATE TABLE IF NOT EXISTS labstaff (
             id SERIAL PRIMARY KEY,
             user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
             full_name TEXT NOT NULL,
             email TEXT NOT NULL,
             created_at TIMESTAMPTZ DEFAULT now()
           )"
        );
        // Upsert labstaff
        $upL = $pdo->prepare(
          "INSERT INTO labstaff (user_id, full_name, email)
           VALUES (:uid, :full_name, :email)
           ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name, email = EXCLUDED.email"
        );
        $upL->execute([':uid'=>$userId, ':full_name'=>$fullName, ':email'=>$email]);
      } else if ($prevRole === 'labstaff' && $newRole !== 'labstaff') {
        // Remove labstaff row if role changed away from labstaff
        $delL = $pdo->prepare("DELETE FROM labstaff WHERE user_id = :uid");
        $delL->execute([':uid'=>$userId]);
      }
      // Ensure admin table exists if needed
      if ($newRole === 'admin') {
        $pdo->exec(
          "CREATE TABLE IF NOT EXISTS admin (
             id SERIAL PRIMARY KEY,
             user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
             full_name TEXT NOT NULL,
             email TEXT NOT NULL,
             created_at TIMESTAMPTZ DEFAULT now()
           )"
        );
        // Upsert admin
        $upA = $pdo->prepare(
          "INSERT INTO admin (user_id, full_name, email)
           VALUES (:uid, :full_name, :email)
           ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name, email = EXCLUDED.email"
        );
        $upA->execute([':uid'=>$userId, ':full_name'=>$fullName, ':email'=>$email]);
      } else if ($prevRole === 'admin' && $newRole !== 'admin') {
        // Remove admin row if role changed away from admin
        $delA = $pdo->prepare("DELETE FROM admin WHERE user_id = :uid");
        $delA->execute([':uid'=>$userId]);
      }

      $pdo->commit();
      header('Location: /capstone/templates/admin/admin_users.php?success=update');
      exit;
    } catch (Throwable $e) {
      if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
      error_log("Error updating user: " . $e->getMessage());
      header('Location: /capstone/templates/admin/admin_users.php?error=update');
      exit;
    }
   }
   
   if ($action === 'delete' && $userId > 0) {
     try {
       $pdo = get_pdo();
       $pdo->beginTransaction();
       
       // Get role to detect if doctor
       $roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = :id");
       $roleStmt->execute([':id'=>$userId]);
       $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
       $role = strtolower((string)($role['role'] ?? ''));

       $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
       $stmt->execute([':id' => $userId]);
       
       header('Location: /capstone/templates/admin/admin_users.php?success=delete');
       exit;
     } catch (Throwable $e) {
       error_log("Error deleting user: " . $e->getMessage());
       header('Location: /capstone/templates/admin/admin_users.php?error=delete');
       exit;
     }
   }
 }
 
 include __DIR__.'/../../includes/header.php';
 
 $pdo = get_pdo();
$users = [];
// Pagination params
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$totalRows = 0;
$totalPages = 1;

try {
  // Total count
  $cntStmt = $pdo->query("SELECT COUNT(*)::int AS c FROM users");
  $totalRows = (int)($cntStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
  $totalPages = max(1, (int)ceil($totalRows / $perPage));
  if ($page > $totalPages) { $page = $totalPages; }
  $offset = ($page - 1) * $perPage;

  // Page of users
  $stmt = $pdo->prepare("SELECT id, full_name, email, role, created_at FROM users ORDER BY id DESC LIMIT :limit OFFSET :offset");
  $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  error_log("Error fetching users: " . $e->getMessage());
  $users = [];
  $totalRows = 0;
  $totalPages = 1;
  $page = 1;
}
 
 function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/admin/admin_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/admin/admin_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/appointment.png" alt="Users" style="width:18px;height:18px;object-fit:contain;"> Users</a></li>
          <li><a href="/capstone/templates/admin/admin_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
          <li><a href="/capstone/templates/admin/admin_settings.php"><img src="/capstone/assets/img/setting.png" alt="Settings" style="width:18px;height:18px;object-fit:contain;"> Settings</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div>
    <?php if (isset($_GET['success'])): ?>
      <div class="alert" style="margin-bottom:16px;border-color:#bbf7d0;background:#ecfdf5;color:#065f46;">
        <?php if ($_GET['success'] === 'create'): ?>
          User created successfully!
        <?php elseif ($_GET['success'] === 'update'): ?>
          User updated successfully!
        <?php elseif ($_GET['success'] === 'delete'): ?>
          User deleted successfully!
        <?php endif; ?>
      </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-error" style="margin-bottom:16px;">
        <?php 
          $err = $_GET['error']; 
          $reason = $_GET['reason'] ?? '';
        ?>
        <?php if ($err === 'create'): ?>
          <?php if ($reason === 'duplicate'): ?>
            Email already exists. Please use a different email.
          <?php elseif ($reason === 'validation'): ?>
            Please fill in all required fields: valid name, email, password (min 6 chars), and role.
          <?php else: ?>
            Failed to create user. Please try again.
          <?php endif; ?>
        <?php elseif ($err === 'update'): ?>
          Failed to update user. Please try again.
        <?php elseif ($err === 'delete'): ?>
          Failed to delete user. Please try again.
        <?php endif; ?>
      </div>
    <?php endif; ?>
    
    <section class="card" style="margin-bottom:16px;">
      <h2 class="dashboard-title" style="margin:0;">Users</h2>
      <div style="margin-top:8px;display:flex;gap:12px;align-items:center;">
        <div class="form-field" style="flex:1;">
          <label>Search</label>
          <input type="text" id="searchInput" placeholder="Search by Name, Email, or Role..." style="width:100%;height:48px;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
        </div>
      </div>
    </section>

    <section class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <h3 style="margin:0;">Directory</h3>
        <button class="btn btn-primary" id="openAddUserModal" type="button" style="height:36px;display:inline-flex;align-items:center;justify-content:center;">+ Add User</button>
      </div>
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php 
        if (empty($users)): ?>
          <tr>
            <td colspan="5" style="text-align:center;padding:20px;">
              <span class="muted">No users found in the database.</span>
            </td>
          </tr>
        <?php else: 
          foreach ($users as $u):
            $name = $u['full_name'] ?? '';
            $email = $u['email'] ?? '';
            $role = ucfirst($u['role'] ?? '');
            $createdAt = $u['created_at'] ?? '';
        ?>
          <tr>
            <td><?php echo h($name); ?></td>
            <td><?php echo h($email); ?></td>
            <td><?php echo h($role); ?></td>
            <td id="status-badge-<?php echo h($u['id'] ?? ''); ?>"><span class="badge" style="background:#10b981;color:#fff;">Active</span></td>
            <td>
              <div style="display:flex;gap:8px;">
                <button class="btn btn-outline edit-user-btn" data-id="<?php echo h($u['id'] ?? ''); ?>" data-name="<?php echo h($name); ?>" data-email="<?php echo h($email); ?>" data-role="<?php echo h($u['role'] ?? ''); ?>" style="padding:4px 8px;font-size:0.8rem;">Edit</button>
                <button class="btn btn-outline disable-user-btn" data-id="<?php echo h($u['id'] ?? ''); ?>" data-row-index="<?php echo h($u['id'] ?? ''); ?>" data-name="<?php echo h($name); ?>" data-email="<?php echo h($email); ?>" style="padding:4px 8px;font-size:0.8rem;">Disable</button>
                <button class="btn btn-outline delete-user-btn" data-id="<?php echo h($u['id'] ?? ''); ?>" data-name="<?php echo h($name); ?>" data-email="<?php echo h($email); ?>" style="padding:4px 8px;font-size:0.8rem;color:#dc2626;border-color:#dc2626;" onmouseover="this.style.backgroundColor='#dc2626';this.style.color='#fff';" onmouseout="this.style.backgroundColor='transparent';this.style.color='#dc2626';">Delete</button>
              </div>
            </td>
          </tr>
        <?php 
          endforeach; 
        endif; ?>
        </tbody>
      </table>
      <!-- Pager (inside Directory card) -->
      <div style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:6px;border-top:1px solid #e5e7eb;padding-top:8px;">
        <?php 
          $baseUrl = '/capstone/templates/admin/admin_users.php';
          $prevPage = max(1, $page - 1);
          $nextPage = min($totalPages, $page + 1);
        ?>
        <a href="<?php echo $baseUrl.'?page='.$prevPage; ?>" class="btn btn-outline" style="min-width:72px;height:32px;padding:6px 10px;font-size:.9rem;<?php echo $page<=1?'pointer-events:none;opacity:.6;':''; ?>">Prev</a>
        <?php 
          $start = max(1, $page - 2);
          $end = min($totalPages, $page + 2);
          // Show first page and leading ellipsis if needed
          if ($start > 1) {
            echo '<a href="'.$baseUrl.'?page=1" class="btn btn-outline" style="min-width:32px;height:32px;padding:4px 10px;font-size:.9rem;">1</a>';
            if ($start > 2) {
              echo '<span class="muted-small" style="padding:0 4px;">…</span>';
            }
          }
          // Middle range
          for ($i=$start; $i<=$end; $i++){
            $cls = ($i===$page)?'btn-primary':'btn-outline';
            $styleActive = ($i===$page)?'background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;':'';
            echo '<a href="'.$baseUrl.'?page='.$i.'" class="btn '.$cls.'" style="min-width:32px;height:32px;padding:4px 10px;font-size:.9rem;'.$styleActive.'">'.$i.'</a>';
          }
          // Trailing ellipsis and last page if needed
          if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
              echo '<span class="muted-small" style="padding:0 4px;">…</span>';
            }
            echo '<a href="'.$baseUrl.'?page='.$totalPages.'" class="btn btn-outline" style="min-width:32px;height:32px;padding:4px 10px;font-size:.9rem;">'.$totalPages.'</a>';
          }
        ?>
        <a href="<?php echo $baseUrl.'?page='.$nextPage; ?>" class="btn btn-outline" style="min-width:72px;height:32px;padding:6px 10px;font-size:.9rem;<?php echo $page>=$totalPages?'pointer-events:none;opacity:.6;':''; ?>">Next</a>
      </div>
    </section>

    
  </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" style="display:none;position:fixed;inset:0;z-index:1000;">
  <div data-backdrop style="position:absolute;inset:0;background:rgba(0,0,0,0.5);"></div>
  <div role="dialog" aria-modal="true" style="position:relative;max-width:600px;margin:2vh auto;background:#fff;border-radius:20px;box-shadow:0 25px 50px rgba(0,0,0,0.25);overflow:hidden;min-height:fit-content;">
    <!-- Modal Header -->
    <div style="background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;padding:24px 28px;border-radius:20px 20px 0 0;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h3 style="margin:0;font-size:1.3rem;font-weight:700;">Edit User</h3>
          <p style="margin:4px 0 0;opacity:0.9;font-size:0.9rem;">Update user information</p>
        </div>
        <button type="button" id="closeEditUserModal" style="background:rgba(255,255,255,0.2);border:none;color:#fff;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.3)'" onmouseout="this.style.backgroundColor='rgba(255,255,255,0.2)'">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6L6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>
    
    <form id="editUserForm" method="post" action="/capstone/templates/admin/admin_users.php" style="margin:0;">
      <input type="hidden" id="edit_user_id" name="user_id" />
      <input type="hidden" name="action" value="update" />
      
      <div style="padding:28px;">
        <div style="display:grid;gap:20px;">
          <!-- Full Name -->
          <div class="form-field">
            <label for="edit_full_name" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Full Name *</label>
            <input type="text" id="edit_full_name" name="full_name" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
          </div>
          
          <!-- Email -->
          <div class="form-field">
            <label for="edit_email" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Email Address *</label>
            <input type="email" id="edit_email" name="email" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
          </div>
          
          <!-- Role -->
          <div class="form-field">
            <label for="edit_role" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Role *</label>
            <select id="edit_role" name="role" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';">
              <option value="">Select Role</option>
              <option value="admin">Admin</option>
              <option value="doctor">Doctor</option>
              <option value="nurse">Nurse</option>
              <option value="supervisor">Supervisor</option>
              <option value="pharmacist">Pharmacist</option>
              <option value="lab_staff">Laboratory Staff</option>
            </select>
          </div>
        </div>
      </div>
      
      <!-- Modal Footer -->
      <div style="display:flex;justify-content:flex-end;gap:12px;padding:20px 28px;border-top:1px solid #e5e7eb;background:linear-gradient(135deg,#f8fafc,#f1f5f9);">
        <button type="button" class="btn btn-outline" id="cancelEditUserModal" style="padding:10px 20px;border-radius:10px;font-weight:600;">Cancel</button>
        <button class="btn btn-primary" type="submit" style="padding:10px 20px;border-radius:10px;font-weight:600;background:linear-gradient(135deg,#0a5d39,#10b981);">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" style="display:none;position:fixed;inset:0;z-index:1000;">
  <div data-backdrop style="position:absolute;inset:0;background:rgba(0,0,0,0.5);"></div>
  <div role="dialog" aria-modal="true" style="position:relative;max-width:600px;margin:2vh auto;background:#fff;border-radius:20px;box-shadow:0 25px 50px rgba(0,0,0,0.25);overflow-y:auto;max-height:90vh;display:flex;flex-direction:column;">
    <!-- Modal Header -->
    <div style="background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;padding:24px 28px;border-radius:20px 20px 0 0;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h3 style="margin:0;font-size:1.3rem;font-weight:700;">Add New User</h3>
          <p style="margin:4px 0 0;opacity:0.9;font-size:0.9rem;">Create a new user account</p>
        </div>
        <button type="button" id="closeAddUserModal" style="background:rgba(255,255,255,0.2);border:none;color:#fff;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.3)'" onmouseout="this.style.backgroundColor='rgba(255,255,255,0.2)'">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6L6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>
    
    <form id="addUserForm" method="post" action="/capstone/templates/admin/admin_users.php" style="margin:0;display:flex;flex-direction:column;flex:1;">
      <input type="hidden" name="action" value="create" />
      
      <div style="padding:28px;flex:1;overflow-y:auto;">
        <div style="display:grid;gap:20px;">
          <!-- Full Name -->
          <div class="form-field">
            <label for="add_full_name" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Full Name *</label>
            <input type="text" id="add_full_name" name="full_name" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" placeholder="Enter full name" />
          </div>
          
          <!-- Email -->
          <div class="form-field">
            <label for="add_email" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Email Address *</label>
            <input type="email" id="add_email" name="email" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" placeholder="Enter email address" />
          </div>
          
          <!-- Password -->
          <div class="form-field">
            <label for="add_password" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Password *</label>
            <div style="position:relative;z-index:0;">
              <input type="password" id="add_password" name="password" required style="width:100%;padding:12px 44px 12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" placeholder="Enter password" />
              <button type="button" id="add_pw_toggle" aria-label="Show password" title="Show/Hide Password" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);height:32px;min-width:32px;padding:0;border:none;border-radius:8px;background:transparent;color:#64748b;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:2;">
                <img id="add_pw_icon" src="/capstone/assets/img/eye.png" alt="Show" style="width:22px;height:22px;object-fit:contain;display:block;" />
              </button>
            </div>
          </div>
          
          <!-- Role -->
          <div class="form-field">
            <label for="add_role" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Role *</label>
            <select id="add_role" name="role" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';">
              <option value="">Select Role</option>
              <option value="admin">Admin</option>
              <option value="doctor">Doctor</option>
              <option value="nurse">Nurse</option>
              <option value="supervisor">Supervisor</option>
              <option value="pharmacist">Pharmacist</option>
              <option value="lab_staff">Laboratory Staff</option>
            </select>
          </div>
          
          <!-- Active Toggle -->
          <div class="form-field">
            <label style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Status</label>
            <div style="display:flex;align-items:center;gap:12px;">
              <label class="toggle-switch" style="position:relative;display:inline-block;width:50px;height:28px;">
                <input type="checkbox" id="add_is_active" name="is_active" checked style="opacity:0;width:0;height:0;" />
                <span class="toggle-slider" style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;transition:0.3s;border-radius:28px;"></span>
              </label>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Modal Footer -->
      <div style="display:flex;justify-content:flex-end;gap:12px;padding:20px 28px;border-top:1px solid #e5e7eb;background:linear-gradient(135deg,#f8fafc,#f1f5f9);flex-shrink:0;">
        <button type="button" class="btn btn-outline" id="cancelAddUserModal" style="padding:10px 20px;border-radius:10px;font-weight:600;">Cancel</button>
        <button class="btn btn-primary" type="submit" style="padding:10px 20px;border-radius:10px;font-weight:600;background:linear-gradient(135deg,#0a5d39,#10b981);">Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- Toggle Switch Styles -->
<style>
  /* Default (unchecked) red, override any external styles */
  .toggle-switch .toggle-slider { background-color: #ef4444 !important; }
  /* Checked green */
  .toggle-switch input:checked + .toggle-slider { background-color: #10b981 !important; }
  .toggle-slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
  }
  .toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(22px);
  }
</style>

<script>
(function(){
  // Handle Edit User button
  document.addEventListener('click', function(e){
    if(e.target.classList.contains('edit-user-btn')){
      e.preventDefault();
      var userId = e.target.getAttribute('data-id');
      var userName = e.target.getAttribute('data-name');
      var userEmail = e.target.getAttribute('data-email');
      var userRole = e.target.getAttribute('data-role');
      
      // Populate modal form
      document.getElementById('edit_user_id').value = userId;
      document.getElementById('edit_full_name').value = userName;
      document.getElementById('edit_email').value = userEmail;
      document.getElementById('edit_role').value = userRole;
      
      // Show modal
      document.getElementById('editUserModal').style.display = 'block';
      document.body.style.overflow = 'hidden';
    }
  });
  
  // Handle Disable User button
  document.addEventListener('click', function(e){
    if(e.target.classList.contains('disable-user-btn')){
      e.preventDefault();
      var userId = e.target.getAttribute('data-id');
      var userName = e.target.getAttribute('data-name');
      var currentStatus = e.target.textContent.trim();
      var isDisabling = currentStatus === 'Disable';
      
      if(confirm('Are you sure you want to ' + (isDisabling ? 'disable' : 'enable') + ' ' + userName + '?')){
        // Update status badge
        var badgeId = 'status-badge-' + userId;
        var statusCell = document.getElementById(badgeId);
        
        if(statusCell){
          var badge = statusCell.querySelector('.badge');
          if(isDisabling){
            // Change to Disabled
            badge.textContent = 'Disabled';
            badge.style.background = '#f59e0b';
          } else {
            // Change to Active
            badge.textContent = 'Active';
            badge.style.background = '#10b981';
          }
        }
        
        // Toggle button text and styling
        if(isDisabling){
          e.target.textContent = 'Enable';
          e.target.style.background = 'transparent';
          e.target.style.color = '';
          e.target.style.borderColor = '';
        } else {
          e.target.textContent = 'Disable';
          e.target.style.background = 'transparent';
          e.target.style.color = '';
          e.target.style.borderColor = '';
        }
        
        alert('User ' + (isDisabling ? 'disabled' : 'enabled') + ' successfully!');
      }
    }
  });
  
  // Handle Delete User button
  document.addEventListener('click', function(e){
    if(e.target.classList.contains('delete-user-btn')){
      e.preventDefault();
      var userId = e.target.getAttribute('data-id');
      var userName = e.target.getAttribute('data-name');
      var userEmail = e.target.getAttribute('data-email');
      
      var confirmMessage = 'Are you sure you want to delete user:\n\n' +
                          'Name: ' + userName + '\n' +
                          'Email: ' + userEmail + '\n\n' +
                          'This action cannot be undone!';
      
      if(confirm(confirmMessage)){
        // Create a form to submit delete request
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/capstone/templates/admin/admin_users.php';
        
        var userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = userId;
        form.appendChild(userIdInput);
        
        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        form.appendChild(actionInput);
        
        document.body.appendChild(form);
        form.submit();
      }
    }
  });
  
  // Handle Edit User Modal
  var editModal = document.getElementById('editUserModal');
  var closeEditBtn = document.getElementById('closeEditUserModal');
  var cancelEditBtn = document.getElementById('cancelEditUserModal');
  var backdropEdit = editModal ? editModal.querySelector('[data-backdrop]') : null;
  
  function closeEditModal(){
    if(editModal){
      editModal.style.display = 'none';
      document.body.style.overflow = '';
    }
  }
  
  if(closeEditBtn){ closeEditBtn.addEventListener('click', closeEditModal); }
  if(cancelEditBtn){ cancelEditBtn.addEventListener('click', closeEditModal); }
  if(backdropEdit){ backdropEdit.addEventListener('click', closeEditModal); }
  
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape' && editModal.style.display === 'block'){
      closeEditModal();
    }
  });
  
  // Search functionality
  var searchInput = document.getElementById('searchInput');
  var tableBody = document.querySelector('tbody');
  var originalRows = [];
  
  // Store original rows data on page load
  function initializeRows(){
    if(!tableBody) return;
    originalRows = Array.from(tableBody.querySelectorAll('tr')).map(function(row){
      var cells = row.querySelectorAll('td');
      if(cells.length === 0) return null;
      
      return {
        element: row,
        name: cells[0] ? cells[0].textContent.trim() : '',
        email: cells[1] ? cells[1].textContent.trim() : '',
        role: cells[2] ? cells[2].textContent.trim() : '',
        status: cells[3] ? cells[3].textContent.trim() : ''
      };
    }).filter(function(row){ return row !== null; });
  }
  
  function filterRows(){
    if(!tableBody || originalRows.length === 0) return;
    
    var searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
    
    // Clear table body
    tableBody.innerHTML = '';
    
    var filteredRows = originalRows.filter(function(row){
      // Search filter
      var matchesSearch = !searchTerm || 
        row.name.toLowerCase().includes(searchTerm) ||
        row.email.toLowerCase().includes(searchTerm) ||
        row.role.toLowerCase().includes(searchTerm);
      
      return matchesSearch;
    });
    
    // Show filtered results
    if(filteredRows.length === 0){
      var noResultsRow = document.createElement('tr');
      noResultsRow.innerHTML = '<td colspan="5" class="muted" style="text-align:center;padding:20px;">No users found matching "' + searchTerm + '".</td>';
      tableBody.appendChild(noResultsRow);
    } else {
      filteredRows.forEach(function(row){
        tableBody.appendChild(row.element);
      });
    }
  }
  
  // Event listeners for search
  if(searchInput){
    searchInput.addEventListener('input', function(){
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(filterRows, 300); // Debounce search
    });
  }
  
  // Initialize on page load
  initializeRows();
  
  // Handle Add User Modal
  var openAddBtn = document.getElementById('openAddUserModal');
  var addModal = document.getElementById('addUserModal');
  var closeAddBtn = document.getElementById('closeAddUserModal');
  var cancelAddBtn = document.getElementById('cancelAddUserModal');
  var backdropAdd = addModal ? addModal.querySelector('[data-backdrop]') : null;
  
  function openAddModal(){
    if(addModal){
      addModal.style.display = 'block';
      document.body.style.overflow = 'hidden';
    }
  }
  
  function closeAddModal(){
    if(addModal){
      addModal.style.display = 'none';
      document.body.style.overflow = '';
      // Reset form
      var form = document.getElementById('addUserForm');
      if(form) form.reset();
    }
  }
  
  if(openAddBtn){ openAddBtn.addEventListener('click', function(e){ e.preventDefault(); openAddModal(); }); }
  if(closeAddBtn){ closeAddBtn.addEventListener('click', closeAddModal); }
  if(cancelAddBtn){ cancelAddBtn.addEventListener('click', closeAddModal); }
  if(backdropAdd){ backdropAdd.addEventListener('click', closeAddModal); }
  
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape' && addModal && addModal.style.display === 'block'){
      closeAddModal();
    }
  });
})();

</script>

<script>
(function(){
  var addForm = document.getElementById('addUserForm');
  var addModal = document.getElementById('addUserModal');
  var tableBody = document.querySelector('tbody');
  var pw = document.getElementById('add_password');
  var pwToggle = document.getElementById('add_pw_toggle');
  var pwIcon = document.getElementById('add_pw_icon');

  function closeAddModal(){
    if(addModal){ addModal.style.display='none'; document.body.style.overflow=''; }
  }

  function capitalizeFirst(s){ s=String(s||''); return s.charAt(0).toUpperCase()+s.slice(1); }

  if(addForm && tableBody){
    addForm.addEventListener('submit', async function(e){
      e.preventDefault();
      try{
        var fd = new FormData(addForm);
        // Ensure action=create is present
        if(!fd.get('action')) fd.append('action','create');
        var res = await fetch('/capstone/templates/admin/admin_users.php',{
          method:'POST',
          headers:{ 'X-Requested-With':'XMLHttpRequest' },
          body: fd
        });
        var data = await res.json();
        if(!res.ok || !data || data.ok !== true){
          alert((data && data.error) ? data.error : 'Failed to create user.');
          return;
        }
        var u = data.user || {};
        // Prepend new row to the table (only if we're on first page)
        var roleLabel = (u.role==='labstaff') ? 'Labstaff' : capitalizeFirst(u.role||'');
        var tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${u.full_name||''}</td>
          <td>${u.email||''}</td>
          <td>${roleLabel}</td>
          <td id="status-badge-${u.id||''}"><span class="badge" style="background:#10b981;color:#fff;">Active</span></td>
          <td>
            <div style="display:flex;gap:8px;">
              <button class="btn btn-outline edit-user-btn" data-id="${u.id||''}" data-name="${u.full_name||''}" data-email="${u.email||''}" data-role="${u.role||''}" style="padding:4px 8px;font-size:0.8rem;">Edit</button>
              <button class="btn btn-outline disable-user-btn" data-id="${u.id||''}" data-row-index="${u.id||''}" data-name="${u.full_name||''}" data-email="${u.email||''}" style="padding:4px 8px;font-size:0.8rem;">Disable</button>
              <button class="btn btn-outline delete-user-btn" data-id="${u.id||''}" data-name="${u.full_name||''}" data-email="${u.email||''}" style="padding:4px 8px;font-size:0.8rem;color:#dc2626;border-color:#dc2626;" onmouseover="this.style.backgroundColor='#dc2626';this.style.color='#fff';" onmouseout="this.style.backgroundColor='transparent';this.style.color='#dc2626';">Delete</button>
            </div>
          </td>`;
        if (window.location.search.indexOf('page=') === -1 || /[?&]page=1(?!\d)/.test(window.location.search)){
          tableBody.insertBefore(tr, tableBody.firstChild);
        }
        addForm.reset();
        closeAddModal();
      }catch(err){
        alert('Error: '+ (err && err.message ? err.message : 'Unknown error'));
      }
    });
  }

  if(pw && pwToggle){
    pwToggle.addEventListener('click', function(){
      if(pw.type === 'password'){
        pw.type = 'text';
        if(pwIcon){ pwIcon.src = '/capstone/assets/img/hidden.png'; pwIcon.alt = 'Hide'; }
        pwToggle.setAttribute('aria-label','Hide password');
      } else {
        pw.type = 'password';
        if(pwIcon){ pwIcon.src = '/capstone/assets/img/eye.png'; pwIcon.alt = 'Show'; }
        pwToggle.setAttribute('aria-label','Show password');
      }
    });
  }
})();
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>
