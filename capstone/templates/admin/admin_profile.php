<?php
 $page='Admin Profile';
 require_once __DIR__.'/../../config/db.php';
 if (session_status() === PHP_SESSION_NONE) { session_start(); }
 // Prevent caching so updates reflect immediately
 header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
 header('Cache-Control: post-check=0, pre-check=0', false);
 header('Pragma: no-cache');

 if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   // If there is no session user id (default admin), create one now so we can persist
   $userId = (int)($_SESSION['user']['id'] ?? 0);
   if ($userId <= 0) {
    try {
      $pdoTmp = get_pdo();
      // Discover users columns
      $uColStmt = $pdoTmp->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'users'");
      $uColStmt->execute();
      $uCols = array_map('strtolower', array_map('strval', array_column($uColStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
      $fullSeed = trim((string)($_SESSION['user']['full_name'] ?? 'Administrator'));
      $emailSeed = trim((string)($_SESSION['user']['email'] ?? 'admin@gmail.com'));

      // If email column exists, try to find an existing record first
      if (in_array('email', $uCols, true)) {
        $sel = $pdoTmp->prepare('SELECT id FROM users WHERE LOWER(TRIM(email)) = LOWER(TRIM(:em)) LIMIT 1');
        $sel->execute([':em' => $emailSeed]);
        $foundId = $sel->fetchColumn();
        if ($foundId) {
          $_SESSION['user']['id'] = (int)$foundId;
          $userId = (int)$foundId;
        }
      }

      // If still no id, insert a minimal but valid row respecting NOT NULL columns
      if ($userId <= 0) {
        $insCols = [];$insVals = [];$params = [];
        if (in_array('full_name',$uCols,true)) { $insCols[]='full_name'; $insVals[]=':fn'; $params[':fn']=$fullSeed; }
        if (in_array('email',$uCols,true))     { $insCols[]='email';     $insVals[]=':em'; $params[':em']=$emailSeed; }
        if (in_array('role',$uCols,true))      { $insCols[]='role';      $insVals[]="'admin'"; }
        if (in_array('active',$uCols,true))    { $insCols[]='active';    $insVals[]='TRUE'; }
        if (in_array('created_at',$uCols,true)) { $insCols[]='created_at'; $insVals[]='now()'; }
        // Provide password_hash if required by schema
        if (in_array('password_hash', $uCols, true)) {
          $tmpPwd = bin2hex(random_bytes(8));
          $tmpHash = password_hash($tmpPwd, PASSWORD_BCRYPT);
          $insCols[]='password_hash';
          $insVals[]=':pwd';
          $params[':pwd']=$tmpHash;
        }
        if (!empty($insCols)) {
          $sql = 'INSERT INTO users (' . implode(',', $insCols) . ') VALUES (' . implode(',', $insVals) . ') RETURNING id';
          try {
            $stmtSeed = $pdoTmp->prepare($sql);
            $stmtSeed->execute($params);
            $newId = $stmtSeed->fetchColumn();
            if ($newId) { $_SESSION['user']['id'] = (int)$newId; $userId = (int)$newId; }
          } catch (Throwable $ie) {
            // On unique email conflict, try to recover by selecting the id now
            if (in_array('email', $uCols, true)) {
              $sel2 = $pdoTmp->prepare('SELECT id FROM users WHERE LOWER(TRIM(email)) = LOWER(TRIM(:em)) LIMIT 1');
              $sel2->execute([':em' => $emailSeed]);
              $foundId2 = $sel2->fetchColumn();
              if ($foundId2) { $_SESSION['user']['id'] = (int)$foundId2; $userId = (int)$foundId2; }
            }
          }
        }
      }
    } catch (Throwable $e) {
      // Fall through; we'll error later if we still lack an id
    }
  }
   if ($userId <= 0) {
     $_SESSION['flash_error'] = 'Could not create a persistent admin account. Please try again.';
     header('Location: /capstone/templates/admin/admin_profile.php');
     exit;
   }

   $fullName = trim($_POST['full_name'] ?? '');
   $email = trim($_POST['email'] ?? '');
   $phone = trim($_POST['phone'] ?? '');
   $address = trim($_POST['address'] ?? '');
   $birthDate = trim($_POST['birth_date'] ?? '');
   $gender = trim($_POST['gender'] ?? '');
   $newAvatarUri = null;

   $errors = [];
   if ($fullName === '') { $errors[] = 'Full name is required.'; }
   if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'A valid email address is required.'; }
   if ($birthDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) { $errors[] = 'Birth date must be in YYYY-MM-DD format.'; }

   if ($errors) {
     $_SESSION['flash_error'] = implode("\n", $errors);
     header('Location: /capstone/templates/admin/admin_profile.php');
     exit;
   }

   // Normalize fields to satisfy DB constraints
  $allowedGenders = ['Male','Female','Other'];
  if (!in_array($gender, $allowedGenders, true)) { $gender = ''; }
  $birthParam = $birthDate !== '' ? $birthDate : null;
  $genderParam = $gender !== '' ? $gender : null;

   // Handle avatar upload (optional)
   if (!empty($_FILES['avatar']) && isset($_FILES['avatar']['tmp_name']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
     try {
       $tmp = $_FILES['avatar']['tmp_name'];
       $size = (int)($_FILES['avatar']['size'] ?? 0);
       if ($size > 2 * 1024 * 1024) { throw new RuntimeException('Avatar must be 2MB or smaller.'); }
       $finfo = new finfo(FILEINFO_MIME_TYPE);
       $mime = (string)$finfo->file($tmp);
       $ext = '';
       if ($mime === 'image/jpeg') { $ext = 'jpg'; }
       elseif ($mime === 'image/png') { $ext = 'png'; }
       elseif ($mime === 'image/webp') { $ext = 'webp'; }
       else { throw new RuntimeException('Unsupported avatar format. Allowed: JPG, PNG, WEBP'); }
       $uploadDir = realpath(__DIR__ . '/../../');
       $destDir = $uploadDir !== false ? $uploadDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars' : __DIR__ . '/../../uploads/avatars';
       if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }
       $fileName = 'u' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
       $destPath = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
       if (!move_uploaded_file($tmp, $destPath)) { throw new RuntimeException('Failed to save uploaded avatar.'); }
       $newAvatarUri = '/capstone/uploads/avatars/' . $fileName;
     } catch (Throwable $e) {
       $_SESSION['flash_error'] = 'Avatar upload failed: ' . $e->getMessage();
     }
   }

   try {
     $pdo = get_pdo();
     $pdo->beginTransaction();

     // Sync users table; include optional columns when present
    $uColStmt0 = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'users'");
    $uColStmt0->execute();
    $uCols0 = array_map('strtolower', array_map('strval', array_column($uColStmt0->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
    $set = [];
    $paramsU = [':id'=>$userId];
    if (in_array('full_name', $uCols0, true)) { $set[]='full_name = :u_full_name'; $paramsU[':u_full_name']=$fullName; }
    if (in_array('email', $uCols0, true))     { $set[]='email = :u_email';       $paramsU[':u_email']=$email; }
    if (in_array('phone', $uCols0, true))     { $set[]='phone = :u_phone';       $paramsU[':u_phone']=$phone; }
    if (in_array('address', $uCols0, true))   { $set[]='address = :u_address';   $paramsU[':u_address']=$address; }
    if (in_array('gender', $uCols0, true))    { $set[]='gender = :u_gender';     $paramsU[':u_gender']=$genderParam; }
    if (in_array('birthdate', $uCols0, true)) { $set[]='birthdate = :u_birthdate'; $paramsU[':u_birthdate']=$birthParam; }
    // Avatar sync to users table when column exists and a new avatar was uploaded this request
    if ($newAvatarUri !== null) {
      if (in_array('avatar_uri', $uCols0, true)) { $set[]='avatar_uri = :u_avatar_uri'; $paramsU[':u_avatar_uri']=$newAvatarUri; }
      elseif (in_array('avatar', $uCols0, true)) { $set[]='avatar = :u_avatar'; $paramsU[':u_avatar']=$newAvatarUri; }
      elseif (in_array('avatar_path', $uCols0, true)) { $set[]='avatar_path = :u_avatar_path'; $paramsU[':u_avatar_path']=$newAvatarUri; }
    }
    if (!empty($set)) {
      $sqlU = 'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id';
      $stmt = $pdo->prepare($sqlU);
      $stmt->execute($paramsU);
    } else {
      // Fallback minimal update
      $stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, email = :email WHERE id = :id');
      $stmt->execute([':full_name'=>$fullName, ':email'=>$email, ':id'=>$userId]);
    }
    if ($stmt->rowCount() === 0) {
      // Probe users table columns
      $uColStmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'users'");
      $uColStmt->execute();
      $uCols = array_map('strtolower', array_map('strval', array_column($uColStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
      $insCols = [];
      $insVals = [];
      $params  = [];
      if (in_array('full_name', $uCols, true)) { $insCols[]='full_name'; $insVals[]=':u_full_name'; $params[':u_full_name']=$fullName; }
      if (in_array('email', $uCols, true))     { $insCols[]='email';     $insVals[]=':u_email';     $params[':u_email']=$email; }
      // If users requires password_hash, provide a random temporary credential
      if (in_array('password_hash', $uCols, true)) {
        $tmpPwd = bin2hex(random_bytes(8));
        $tmpHash = password_hash($tmpPwd, PASSWORD_BCRYPT);
        $insCols[]='password_hash';
        $insVals[]=':u_pwd';
        $params[':u_pwd']=$tmpHash;
      }
      if ($newAvatarUri !== null) {
        if (in_array('avatar_uri', $uCols, true)) { $insCols[]='avatar_uri'; $insVals[]=':u_avatar_uri'; $params[':u_avatar_uri']=$newAvatarUri; }
        elseif (in_array('avatar', $uCols, true)) { $insCols[]='avatar'; $insVals[]=':u_avatar'; $params[':u_avatar']=$newAvatarUri; }
        elseif (in_array('avatar_path', $uCols, true)) { $insCols[]='avatar_path'; $insVals[]=':u_avatar_path'; $params[':u_avatar_path']=$newAvatarUri; }
      }
      if (in_array('role', $uCols, true))      { $insCols[]='role';      $insVals[]="'admin'"; }
      if (in_array('active', $uCols, true))    { $insCols[]='active';    $insVals[]='TRUE'; }
      if (in_array('created_at', $uCols, true)){ $insCols[]='created_at';$insVals[]='now()'; }
      // Perform insert if we have at least email
      if (!empty($insCols)) {
        $sql = 'INSERT INTO users (' . implode(',', $insCols) . ') VALUES (' . implode(',', $insVals) . ') RETURNING id';
        $stmtIns = $pdo->prepare($sql);
        $stmtIns->execute($params);
        $newId = $stmtIns->fetchColumn();
        if ($newId) {
          $userId = (int)$newId;
          $_SESSION['user']['id'] = $userId;
        }
      }
    }

     // Ensure profile table exists (Railway schema)
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS profile (
         id INT PRIMARY KEY,
         fullname TEXT NOT NULL,
         role TEXT,
         email TEXT NOT NULL,
         phone TEXT,
         address TEXT,
         gender TEXT,
         birthdate DATE,
         created_at TIMESTAMPTZ DEFAULT now(),
         last_edited TIMESTAMPTZ DEFAULT now(),
         avatar_uri TEXT
       )"
    );
    // Upsert into profile table (Railway schema)
     $existsStmt = $pdo->prepare('SELECT 1 FROM profile WHERE id = :id LIMIT 1');
     $existsStmt->execute([':id'=>$userId]);
     if ($existsStmt->fetch()) {
       if ($newAvatarUri !== null) {
         $upd = $pdo->prepare('UPDATE profile SET fullname=:full_name, email=:email, phone=:phone, address=:address, gender=:gender, birthdate=:birthdate, avatar_uri=:avatar_uri, last_edited=now() WHERE id=:id');
         $upd->execute([':full_name'=>$fullName, ':email'=>$email, ':phone'=>$phone, ':address'=>$address, ':gender'=>$genderParam, ':birthdate'=>$birthParam, ':avatar_uri'=>$newAvatarUri, ':id'=>$userId]);
       } else {
         $upd = $pdo->prepare('UPDATE profile SET fullname=:full_name, email=:email, phone=:phone, address=:address, gender=:gender, birthdate=:birthdate, last_edited=now() WHERE id=:id');
         $upd->execute([':full_name'=>$fullName, ':email'=>$email, ':phone'=>$phone, ':address'=>$address, ':gender'=>$genderParam, ':birthdate'=>$birthParam, ':id'=>$userId]);
       }
     } else {
       $ins = $pdo->prepare('INSERT INTO profile (id, fullname, role, email, phone, address, gender, birthdate, created_at, last_edited, avatar_uri) VALUES (:id,:full_name,:role,:email,:phone,:address,:gender,:birthdate, now(), now(), :avatar_uri)');
       $ins->execute([':id'=>$userId, ':full_name'=>$fullName, ':role'=>(string)($_SESSION['user']['role'] ?? 'admin'), ':email'=>$email, ':phone'=>$phone, ':address'=>$address, ':gender'=>$genderParam, ':birthdate'=>$birthParam, ':avatar_uri'=>$newAvatarUri]);
     }

    // Ensure admin table exists and upsert basic details
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin (id SERIAL PRIMARY KEY, full_name TEXT NOT NULL, email TEXT NOT NULL, created_at TIMESTAMPTZ DEFAULT now())");
    $colStmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'admin'");
    $colStmt->execute();
    $admCols = array_map('strtolower', array_map('strval', array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
    $hasUserId = in_array('user_id', $admCols, true);
    $hasRole   = in_array('role', $admCols, true);
    $hasActive = in_array('active', $admCols, true);
    $hasCreated= in_array('created_at', $admCols, true);
    $hasPwd    = in_array('password_hash', $admCols, true);
    if ($hasPwd) {
      // Generate a secure random temporary password for default/backfilled admin
      $tmpPwdA = bin2hex(random_bytes(8));
      $pwdHashA = password_hash($tmpPwdA, PASSWORD_BCRYPT);
    }
    if ($hasUserId) {
      $insCols = ['user_id','full_name','email'];
      $insVals = [':uid', ':full_name', ':email'];
      $updates = ['full_name = EXCLUDED.full_name', 'email = EXCLUDED.email'];
      if ($hasPwd)    { $insCols[]='password_hash'; $insVals[]=':pwd'; $updates[]='password_hash = EXCLUDED.password_hash'; }
      if ($hasRole)   { $insCols[]='role';   $insVals[]='\'admin\'';         $updates[]='role = EXCLUDED.role'; }
      if ($hasActive) { $insCols[]='active'; $insVals[]='TRUE';               $updates[]='active = COALESCE(EXCLUDED.active, TRUE)'; }
      if ($hasCreated){ $insCols[]='created_at'; $insVals[]='now()'; }
      $sql = 'INSERT INTO admin (' . implode(',', $insCols) . ') VALUES (' . implode(',', $insVals) . ') '
           . 'ON CONFLICT (user_id) DO UPDATE SET ' . implode(', ', $updates);
      $stmtA = $pdo->prepare($sql);
      $paramsA = [':uid'=>$userId, ':full_name'=>$fullName, ':email'=>$email];
      if ($hasPwd) { $paramsA[':pwd'] = $pwdHashA; }
      $stmtA->execute($paramsA);
    } else {
      $insCols = [];
      $selCols = [];
      if (in_array('full_name', $admCols, true)) { $insCols[]='full_name';  $selCols[]=':full_name::text'; }
      if (in_array('email', $admCols, true))     { $insCols[]='email';      $selCols[]=':email::text'; }
      if ($hasPwd)    { $insCols[]='password_hash'; $selCols[]=':pwd::text'; }
      if ($hasRole)   { $insCols[]='role';          $selCols[]='\'admin\''; }
      if ($hasActive) { $insCols[]='active';        $selCols[]='TRUE'; }
      if ($hasCreated){ $insCols[]='created_at';    $selCols[]='now()'; }
      if (!empty($insCols)) {
        $sql = 'INSERT INTO admin (' . implode(',', $insCols) . ') '
             . 'SELECT ' . implode(',', $selCols) . ' '
             . 'WHERE NOT EXISTS (SELECT 1 FROM admin a WHERE LOWER(TRIM(a.email)) = LOWER(TRIM(:email::text)))';
        $stmtA = $pdo->prepare($sql);
        $paramsA = [':full_name'=>$fullName, ':email'=>$email];
        if ($hasPwd) { $paramsA[':pwd'] = $pwdHashA; }
        $stmtA->execute($paramsA);
      }
    }

     $pdo->commit();

     // Update session
     $_SESSION['user']['full_name'] = $fullName;
     $_SESSION['user']['email'] = $email;
     if ($newAvatarUri !== null) { $_SESSION['user']['avatar_uri'] = $newAvatarUri; }
     $_SESSION['user']['name'] = $fullName;

     $_SESSION['flash_success'] = 'Profile updated successfully.';
   } catch (Throwable $e) {
     if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
     $_SESSION['flash_error'] = 'Failed to update profile: ' . $e->getMessage();
   }

   header('Location: /capstone/templates/admin/admin_profile.php');
   exit;
 }

 // Load current profile for display
 $profile = ['phone'=>'','address'=>'','birthdate'=>'','gender'=>''];
 if (!empty($_SESSION['user']['id'])) {
   try {
     $pdo = get_pdo();
     // Best-effort create uploads/avatars directory
     $base = realpath(__DIR__ . '/../../');
     $upDir = $base !== false ? $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars' : __DIR__ . '/../../uploads/avatars';
     if (!is_dir($upDir)) { @mkdir($upDir, 0775, true); }
     $stmt = $pdo->prepare('SELECT fullname AS full_name, email, phone, address, birthdate, gender, avatar_uri FROM profile WHERE id = :id LIMIT 1');
     $stmt->execute([':id' => $_SESSION['user']['id']]);
     $row = $stmt->fetch();
     if ($row) {
       $profile = [ 'phone'=>$row['phone'] ?? '', 'address'=>$row['address'] ?? '', 'birthdate'=>$row['birthdate'] ?? '', 'gender'=>$row['gender'] ?? '', 'avatar_uri'=>$row['avatar_uri'] ?? '' ];
       $_SESSION['user']['full_name'] = $row['full_name'] ?? ($_SESSION['user']['full_name'] ?? '');
       $_SESSION['user']['email'] = $row['email'] ?? ($_SESSION['user']['email'] ?? '');
     }
     // Ensure latest users table values reflected
     $stmtU = $pdo->prepare('SELECT full_name, email FROM users WHERE id = :id LIMIT 1');
     $stmtU->execute([':id' => $_SESSION['user']['id']]);
     $userRow = $stmtU->fetch();
     if ($userRow) {
       $_SESSION['user']['full_name'] = $userRow['full_name'] ?? ($_SESSION['user']['full_name'] ?? '');
       $_SESSION['user']['email'] = $userRow['email'] ?? ($_SESSION['user']['email'] ?? '');
     }
   } catch (Throwable $e) {
     $_SESSION['flash_error'] = 'Failed to load profile: ' . $e->getMessage();
   }
 }

 include __DIR__.'/../../includes/header.php';
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/admin/admin_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/admin/admin_users.php"><img src="/capstone/assets/img/appointment.png" alt="Users" style="width:18px;height:18px;object-fit:contain;"> Users</a></li>
          <li><a href="/capstone/templates/admin/admin_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
          <li><a href="/capstone/templates/admin/admin_settings.php"><img src="/capstone/assets/img/setting.png" alt="Settings" style="width:18px;height:18px;object-fit:contain;"> Settings</a></li>
        </ul>
      </nav>
    </div>

    
  </aside>

  <div>
    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert alert-error" style="margin-bottom:16px;">
        <?php echo nl2br(htmlspecialchars($_SESSION['flash_error'])); unset($_SESSION['flash_error']); ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="alert" style="border-color:#bbf7d0;background:#ecfdf5;color:#065f46;margin-bottom:16px;">
        <?php echo nl2br(htmlspecialchars($_SESSION['flash_success'])); unset($_SESSION['flash_success']); ?>
      </div>
    <?php endif; ?>
    <?php
      $full = $_SESSION['user']['full_name'] ?? '';
      $roleName = 'Administrator';
    ?>
    <div class="profile-header" style="background:linear-gradient(135deg,#ffffff,#f8fafc);border:1px solid #e2e8f0;border-radius:20px;padding:32px;box-shadow:0 8px 24px rgba(0,0,0,0.08);margin-bottom:24px;">
      <div class="profile-left" style="display:flex;align-items:center;gap:24px;">
        <div class="avatar" style="width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg,#0a5d39,#10b981);display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(10,93,57,0.3);border:4px solid #fff;position:relative;overflow:hidden;">
          <?php $avatarUri = trim((string)($profile['avatar_uri'] ?? '')); ?>
          <?php if ($avatarUri !== ''): ?>
            <img src="<?php echo htmlspecialchars($avatarUri); ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" />
          <?php else: ?>
            <img src="/capstone/assets/img/user.png" alt="Avatar" style="width:50px;height:50px;object-fit:contain;filter:brightness(0) invert(1);" />
          <?php endif; ?>
          <div style="position:absolute;bottom:-4px;right:-4px;width:24px;height:24px;background:#10b981;border:3px solid #fff;border-radius:50%;display:flex;align-items:center;justify-content:center;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#fff;">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
          </div>
        </div>
        <div>
          <h2 class="profile-name" style="margin:0 0 8px;font-size:1.8rem;font-weight:700;color:#0f172a;"><?php echo htmlspecialchars($full ?: 'Admin'); ?></h2>
          <p class="profile-role" style="margin:0 0 8px;color:#64748b;font-size:1rem;font-weight:500;text-transform:capitalize;"><?php echo htmlspecialchars($roleName); ?></p>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:16px;">
        <button id="openProfileModal" class="btn btn-primary" type="button" style="padding:12px 24px;border-radius:12px;font-weight:600;background:linear-gradient(135deg,#0a5d39,#10b981);box-shadow:0 4px 12px rgba(10,93,57,0.3);transition:all 0.2s ease;" onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 16px rgba(10,93,57,0.4)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 12px rgba(10,93,57,0.3)'">Update Profile</button>
      </div>
    </div>

    <h4 class="profile-section-title" style="color:#0a5d39;font-size:1.2rem;font-weight:700;margin-bottom:20px;padding-bottom:8px;border-bottom:2px solid #e2e8f0;">Admin Information</h4>
    <div class="info-table" style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.05);">
      <div class="info-row" style="display:flex;border-bottom:1px solid #f1f5f9;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
        <div class="info-cell info-label" style="flex:0 0 200px;padding:16px 20px;font-weight:600;color:#64748b;background:#f8fafc;border-right:1px solid #e2e8f0;">Full Name:</div>
        <div class="info-cell info-value" id="info_full_name" style="flex:1;padding:16px 20px;color:#0f172a;font-weight:500;"><?php echo htmlspecialchars($full); ?></div>
      </div>
      <div class="info-row" style="display:flex;border-bottom:1px solid #f1f5f9;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
        <div class="info-cell info-label" style="flex:0 0 200px;padding:16px 20px;font-weight:600;color:#64748b;background:#f8fafc;border-right:1px solid #e2e8f0;">Role:</div>
        <div class="info-cell info-value" style="flex:1;padding:16px 20px;color:#0f172a;font-weight:500;text-transform:capitalize;"><?php echo htmlspecialchars($roleName); ?></div>
      </div>
      <div class="info-row" style="display:flex;border-bottom:1px solid #f1f5f9;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
        <div class="info-cell info-label" style="flex:0 0 200px;padding:16px 20px;font-weight:600;color:#64748b;background:#f8fafc;border-right:1px solid #e2e8f0;">Email:</div>
        <div class="info-cell info-value" id="info_email" style="flex:1;padding:16px 20px;color:#0f172a;font-weight:500;"><?php echo htmlspecialchars($_SESSION['user']['email'] ?? ''); ?></div>
      </div>
      <div class="info-row" style="display:flex;border-bottom:1px solid #f1f5f9;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
        <div class="info-cell info-label" style="flex:0 0 200px;padding:16px 20px;font-weight:600;color:#64748b;background:#f8fafc;border-right:1px solid #e2e8f0;">Phone:</div>
        <div class="info-cell info-value" id="info_phone" style="flex:1;padding:16px 20px;color:#0f172a;font-weight:500;">
          <?php $v = trim((string)($profile['phone'] ?? '')); echo htmlspecialchars($v !== '' ? $v : 'Not provided'); ?>
        </div>
      </div>
      <div class="info-row" style="display:flex;border-bottom:1px solid #f1f5f9;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
        <div class="info-cell info-label" style="flex:0 0 200px;padding:16px 20px;font-weight:600;color:#64748b;background:#f8fafc;border-right:1px solid #e2e8f0;">Birth Date:</div>
        <div class="info-cell info-value" id="info_birth" style="flex:1;padding:16px 20px;color:#0f172a;font-weight:500;">
          <?php $v = trim((string)($profile['birthdate'] ?? '')); echo htmlspecialchars($v !== '' ? $v : 'Not provided'); ?>
        </div>
      </div>
      <div class="info-row" style="display:flex;border-bottom:1px solid #f1f5f9;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
        <div class="info-cell info-label" style="flex:0 0 200px;padding:16px 20px;font-weight:600;color:#64748b;background:#f8fafc;border-right:1px solid #e2e8f0;">Gender:</div>
        <div class="info-cell info-value" id="info_gender" style="flex:1;padding:16px 20px;color:#0f172a;font-weight:500;">
          <?php $v = trim((string)($profile['gender'] ?? '')); echo htmlspecialchars($v !== '' ? $v : 'Not provided'); ?>
        </div>
      </div>
      <div class="info-row" style="display:flex;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
        <div class="info-cell info-label" style="flex:0 0 200px;padding:16px 20px;font-weight:600;color:#64748b;background:#f8fafc;border-right:1px solid #e2e8f0;">Address:</div>
        <div class="info-cell info-value" id="info_address" style="flex:1;padding:16px 20px;color:#0f172a;font-weight:500;">
          <?php $v = trim((string)($profile['address'] ?? '')); echo htmlspecialchars($v !== '' ? $v : 'Not provided'); ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Profile Update Modal -->
<div id="profileModal" style="display:none;position:fixed;inset:0;z-index:1000;">
  <div data-backdrop style="position:absolute;inset:0;background:rgba(0,0,0,0.5);"></div>
  <div role="dialog" aria-modal="true" style="position:relative;max-width:600px;margin:2vh auto;background:#fff;border-radius:20px;box-shadow:0 25px 50px rgba(0,0,0,0.25);overflow:hidden;min-height:fit-content;">
    <!-- Modal Header -->
    <div style="background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;padding:24px 28px;border-radius:20px 20px 0 0;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h3 style="margin:0;font-size:1.3rem;font-weight:700;">Update Profile</h3>
          <p style="margin:4px 0 0;opacity:0.9;font-size:0.9rem;">Manage your personal information</p>
        </div>
        <button type="button" id="closeProfileModal" style="background:rgba(255,255,255,0.2);border:none;color:#fff;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.3)'" onmouseout="this.style.backgroundColor='rgba(255,255,255,0.2)'">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6L6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>

    <form id="profileForm" method="post" action="/capstone/templates/admin/admin_profile.php" enctype="multipart/form-data" style="margin:0;">
      <div style="padding:28px;max-height:70vh;overflow-y:auto;scrollbar-width:thin;scrollbar-color:#0a5d39 #f1f5f9;" class="scrollable-content">
        <!-- Avatar Section -->
        <div style="text-align:center;margin-bottom:32px;">
          <div style="position:relative;display:inline-block;">
            <div id="avatarPreview" style="width:120px;height:120px;border-radius:50%;background:linear-gradient(135deg,#0a5d39,#10b981);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;box-shadow:0 8px 24px rgba(10,93,57,0.3);border:4px solid #fff;overflow:hidden;">
              <?php $avatarUri = isset($avatarUri) ? $avatarUri : trim((string)($profile['avatar_uri'] ?? '')); ?>
              <?php if ($avatarUri !== ''): ?>
                <img src="<?php echo htmlspecialchars($avatarUri); ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;filter:none;" />
              <?php else: ?>
                <img src="/capstone/assets/img/user.png" alt="Avatar" style="width:60px;height:60px;object-fit:contain;filter:brightness(0) invert(1);" />
              <?php endif; ?>
            </div>
            <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display:none;" />
            <button type="button" id="changeAvatarBtn" style="position:absolute;bottom:8px;right:8px;width:36px;height:36px;border-radius:50%;background:#fff;border:2px solid #0a5d39;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 4px 8px rgba(0,0,0,0.15);transition:all 0.2s ease;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#0a5d39;">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                <circle cx="12" cy="13" r="4"/>
              </svg>
            </button>
          </div>
          <div style="color:#64748b;font-size:0.9rem;font-weight:500;">Click the camera icon to change your avatar</div>
        </div>
        
        <!-- Form Fields -->
        <div style="display:grid;gap:20px;">
          <!-- Full Name -->
          <div class="form-field">
            <label for="full_name" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Full Name</label>
            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($_SESSION['user']['full_name'] ?? ''); ?>" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
          </div>
          
          <!-- Role (Read-only) -->
          <div class="form-field">
            <label style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Role</label>
            <input type="text" value="<?php echo htmlspecialchars($roleName); ?>" readonly style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;background:#f1f5f9;color:#64748b;cursor:not-allowed;" />
          </div>
          
          <!-- Email -->
          <div class="form-field">
            <label for="email" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Email Address</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_SESSION['user']['email'] ?? ''); ?>" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
          </div>
          
          <!-- Phone -->
          <div class="form-field">
            <label for="phone" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Phone Number</label>
            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
          </div>
          
          <!-- Birth Date -->
          <div class="form-field">
            <label for="birth_date" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Birth Date</label>
            <input type="date" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($profile['birthdate'] ?? ''); ?>" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
          </div>
          
          <!-- Gender -->
          <div class="form-field">
            <label for="gender" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Gender</label>
            <select id="gender" name="gender" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';">
              <option value="">Select Gender</option>
              <option value="Male" <?php echo ($profile['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
              <option value="Female" <?php echo ($profile['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
              <option value="Other" <?php echo ($profile['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
            </select>
          </div>
          
          <!-- Address -->
          <div class="form-field">
            <label for="address" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Address</label>
            <textarea id="address" name="address" rows="3" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;resize:vertical;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
          </div>
        </div>
      </div>
      
      <!-- Modal Footer -->
      <div style="display:flex;justify-content:flex-end;gap:12px;padding:20px 28px;border-top:1px solid #e5e7eb;background:linear-gradient(135deg,#f8fafc,#f1f5f9);">
        <button type="button" class="btn btn-outline" id="cancelProfileModal" style="padding:10px 20px;border-radius:10px;font-weight:600;">Cancel</button>
        <button class="btn btn-primary" type="submit" style="padding:10px 20px;border-radius:10px;font-weight:600;background:linear-gradient(135deg,#0a5d39,#10b981);">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  var openBtn = document.getElementById('openProfileModal');
  var modal = document.getElementById('profileModal');
  var closeBtn = document.getElementById('closeProfileModal');
  var cancelBtn = document.getElementById('cancelProfileModal');
  var backdrop = modal ? modal.querySelector('[data-backdrop]') : null;
  var changeAvatarBtn = document.getElementById('changeAvatarBtn');
  var avatarInput = document.getElementById('avatarInput');
  var avatarPreview = document.getElementById('avatarPreview');
  
  function open(){ if(modal){ modal.style.display = 'block'; document.body.style.overflow='hidden'; } }
  function close(){ if(modal){ modal.style.display = 'none'; document.body.style.overflow=''; } }
  
  if(openBtn){ openBtn.addEventListener('click', function(e){ e.preventDefault(); open(); }); }
  if(closeBtn){ closeBtn.addEventListener('click', function(){ close(); }); }
  if(cancelBtn){ cancelBtn.addEventListener('click', function(){ close(); }); }
  if(backdrop){ backdrop.addEventListener('click', function(){ close(); }); }
  
  // Avatar change functionality
  if(changeAvatarBtn && avatarInput){
    changeAvatarBtn.addEventListener('click', function(){
      avatarInput.click();
    });
    
    avatarInput.addEventListener('change', function(e){
      var file = e.target.files[0];
      if(file){
        var reader = new FileReader();
        reader.onload = function(e){
          var img = avatarPreview.querySelector('img');
          if(img){
            img.src = e.target.result;
            img.style.width = '60px';
            img.style.height = '60px';
            img.style.objectFit = 'cover';
            img.style.borderRadius = '50%';
            img.style.filter = 'none';
          }
        };
        reader.readAsDataURL(file);
      }
    });
  }
  
  document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && modal && modal.style.display === 'block'){ close(); } });

  // Client-side apply of updates to visible fields
  var form = document.getElementById('profileForm');
  if(form){
    form.addEventListener('submit', function(ev){
      ev.preventDefault();
      var v = function(id){ var el=document.getElementById(id); return el? el.value.trim():''; };
      var fullName = v('full_name');
      var email = v('email');
      var phone = v('phone');
      var birthDate = v('birth_date');
      var gender = v('gender');
      var address = v('address');
      
      // Update header display
      var nameEl = document.querySelector('.profile-name');
      if(nameEl && fullName){ nameEl.textContent = fullName; }
      var emailEl = document.querySelector('.profile-email');
      if(emailEl && email){ emailEl.textContent = email; }
      
      // Update info table
      var setText = function(sel, val){ var el=document.getElementById(sel); if(el!==null){ el.textContent = val; } };
      setText('info_full_name', fullName);
      setText('info_email', email);
      setText('info_phone', phone);
      setText('info_birth', birthDate);
      setText('info_gender', gender);
      setText('info_address', address);

      // Update main profile avatar if changed
      var mainAvatar = document.querySelector('.avatar');
      if(mainAvatar && avatarInput.files[0]){
        var reader = new FileReader();
        reader.onload = function(e){
          mainAvatar.innerHTML = '<img src="'+e.target.result+'" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
        };
        reader.readAsDataURL(avatarInput.files[0]);
      }

      // Submit the form
      form.submit();
    });
  }
})();
</script>

<style>
/* Custom scrollbar styling for the modal */
.scrollable-content::-webkit-scrollbar {
  width: 8px;
}

.scrollable-content::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 4px;
}

.scrollable-content::-webkit-scrollbar-thumb {
  background: #0a5d39;
  border-radius: 4px;
  transition: background 0.2s ease;
}

.scrollable-content::-webkit-scrollbar-thumb:hover {
  background: #059669;
}

/* Ensure modal is fully scrollable on mobile */
@media (max-height: 600px) {
  #profileModal {
    padding: 10px;
  }
  
  #profileModal > div[role="dialog"] {
    margin: 10px auto;
    max-height: 95vh;
  }
  
  .scrollable-content {
    max-height: 60vh !important;
  }
}

@media (max-width: 768px) {
  #profileModal > div[role="dialog"] {
    margin: 10px;
    max-width: calc(100% - 20px);
  }
}
</style>

<?php include __DIR__.'/../../includes/footer.php'; ?>

