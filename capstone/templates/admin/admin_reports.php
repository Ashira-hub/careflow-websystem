<?php
 $page='Admin Reports';
 require_once __DIR__.'/../../config/db.php';
 // Determine selected month/year from query (defaults to current month)
 $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
 $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
 if ($month < 1 || $month > 12) { $month = (int)date('n'); }
 if ($year < 1970 || $year > 9999) { $year = (int)date('Y'); }
 $startDate = sprintf('%04d-%02d-01', $year, $month);
 $dt = DateTime::createFromFormat('Y-m-d', $startDate) ?: new DateTime();
 $endDate = $dt->modify('first day of next month')->format('Y-m-d');
 $doctorCount = 0;
 $nurseCount = 0;
 $pharmacistCount = 0;
 $labstaffCount = 0;
 $recentCounts = [
   'admin' => 0,
   'supervisor' => 0,
   'doctor' => 0,
   'nurse' => 0,
   'pharmacist' => 0,
   'labstaff' => 0,
 ];
 try {
   $pdo = get_pdo();
   // Count registered doctor accounts
   $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(TRIM(role)) = 'doctor'");
   $doctorCount = (int) $stmt->fetchColumn();
   // Count registered nurse accounts
   $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(TRIM(role)) = 'nurse'");
   $nurseCount = (int) $stmt->fetchColumn();
   // Count registered pharmacist accounts
   $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(TRIM(role)) = 'pharmacist'");
   $pharmacistCount = (int) $stmt->fetchColumn();
   // Count registered lab staff accounts (handle both labstaff and lab_staff just in case)
   $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(TRIM(role)) IN ('labstaff','lab_staff')");
   $labstaffCount = (int) $stmt->fetchColumn();
   // Compute recent (this month) counts per role for the table
   $hasCreatedAt = false;
   try {
     $colStmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'users' AND column_name = 'created_at'");
     $colStmt->execute();
     $hasCreatedAt = (bool)$colStmt->fetchColumn();
   } catch (Throwable $ie) { $hasCreatedAt = false; }

   if ($hasCreatedAt) {
     $sql = "SELECT LOWER(TRIM(role)) AS r, COUNT(*) AS c
             FROM users
             WHERE created_at >= :start AND created_at < :end
               AND role IS NOT NULL
             GROUP BY r";
     $stmtM = $pdo->prepare($sql);
     $stmtM->execute([':start'=>$startDate, ':end'=>$endDate]);
     $rs = $stmtM;
   } else {
     // Fallback: total counts by role if created_at not available
     $sql = "SELECT LOWER(TRIM(role)) AS r, COUNT(*) AS c
             FROM users WHERE role IS NOT NULL GROUP BY r";
     $rs = $pdo->query($sql);
   }
   foreach ($rs as $row) {
     $r = (string)($row['r'] ?? '');
     $c = (int)($row['c'] ?? 0);
     if ($r === 'lab_staff') { $r = 'labstaff'; }
     if (isset($recentCounts[$r])) {
       $recentCounts[$r] += $c;
     }
   }

 } catch (Throwable $e) {
   $doctorCount = 0; // fallback on error
 }
 include __DIR__.'/../../includes/header.php';
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;" data-year="<?php echo (int)$year; ?>" data-month="<?php echo (int)$month; ?>">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/admin/admin_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/admin/admin_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/admin/admin_users.php"><img src="/capstone/assets/img/appointment.png" alt="Users" style="width:18px;height:18px;object-fit:contain;"> Users</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
          <li><a href="/capstone/templates/admin/admin_settings.php"><img src="/capstone/assets/img/setting.png" alt="Settings" style="width:18px;height:18px;object-fit:contain;"> Settings</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div>
    <section class="card" style="margin-bottom:16px;">
      <h2 class="dashboard-title" style="margin:0;">Reports</h2>
      <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:16px;align-items:center;">
        <div class="report-nav">
          <a class="btn btn-outline report-nav-btn" href="#" id="prevMonth" aria-label="Previous month">
            <span class="report-nav-icon">&#10094;</span>
          </a>
          <div class="report-nav-label-group">
            <div class="report-nav-month"><span id="currentMonthYear"></span></div>
            <div class="report-nav-year">
              <a class="btn btn-outline report-nav-btn" href="#" id="nextMonth" aria-label="Next month">
                <span class="report-nav-icon">&#10095;</span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section style="margin-bottom:16px;">
      <div class="stat-cards" style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;">
        <div class="stat-card" style="padding:24px;background:#fff;border-radius:16px;box-shadow:0 2px 8px rgba(0,0,0,0.1);border:1px solid #e2e8f0;"><h4 style="margin:0 0 8px;color:#0f172a;font-size:1.1rem;">Doctor</h4><div class="muted-small" style="color:#64748b;font-size:0.9rem;margin-bottom:12px;">Registered</div><div class="stat-value" style="font-size:2.5rem;font-weight:700;color:#0a5d39;"><?php echo number_format($doctorCount); ?></div></div>
        <div class="stat-card" style="padding:24px;background:#fff;border-radius:16px;box-shadow:0 2px 8px rgba(0,0,0,0.1);border:1px solid #e2e8f0;"><h4 style="margin:0 0 8px;color:#0f172a;font-size:1.1rem;">Nurses</h4><div class="muted-small" style="color:#64748b;font-size:0.9rem;margin-bottom:12px;">Registered</div><div class="stat-value" style="font-size:2.5rem;font-weight:700;color:#0a5d39;"><?php echo number_format($nurseCount); ?></div></div>
        <div class="stat-card" style="padding:24px;background:#fff;border-radius:16px;box-shadow:0 2px 8px rgba(0,0,0,0.1);border:1px solid #e2e8f0;"><h4 style="margin:0 0 8px;color:#0f172a;font-size:1.1rem;">Pharmacist</h4><div class="muted-small" style="color:#64748b;font-size:0.9rem;margin-bottom:12px;">Registered</div><div class="stat-value" style="font-size:2.5rem;font-weight:700;color:#0a5d39;"><?php echo number_format($pharmacistCount); ?></div></div>
        <div class="stat-card" style="padding:24px;background:#fff;border-radius:16px;box-shadow:0 2px 8px rgba(0,0,0,0.1);border:1px solid #e2e8f0;"><h4 style="margin:0 0 8px;color:#0f172a;font-size:1.1rem;">Lab Staff</h4><div class="muted-small" style="color:#64748b;font-size:0.9rem;margin-bottom:12px;">Registered</div><div class="stat-value" style="font-size:2.5rem;font-weight:700;color:#0a5d39;"><?php echo number_format($labstaffCount); ?></div></div>
      </div>
    </section>

    <section class="card">
      <h3 style="margin-top:0;">Recent Reports</h3>
      <table>
        <thead><tr><th>Role</th><th>New this month</th></tr></thead>
        <tbody>
          <tr><td>Admin</td><td><?php echo number_format($recentCounts['admin']); ?></td></tr>
          <tr><td>Supervisor</td><td><?php echo number_format($recentCounts['supervisor']); ?></td></tr>
          <tr><td>Doctor</td><td><?php echo number_format($recentCounts['doctor']); ?></td></tr>
          <tr><td>Nurse</td><td><?php echo number_format($recentCounts['nurse']); ?></td></tr>
          <tr><td>Pharmacy</td><td><?php echo number_format($recentCounts['pharmacist']); ?></td></tr>
          <tr><td>Laboratory</td><td><?php echo number_format($recentCounts['labstaff']); ?></td></tr>
        </tbody>
      </table>
    </section>
  </div>
</div>

<script>
(function(){
  // Month navigation functionality
  // Initialize from PHP-provided selected month/year
  var container = document.querySelector('.layout-sidebar');
  var y = parseInt(container.getAttribute('data-year'),10) || (new Date()).getFullYear();
  var m = parseInt(container.getAttribute('data-month'),10) || ((new Date()).getMonth()+1);
  var currentDate = new Date(y, m-1, 1);
  var currentMonthElement = document.getElementById('currentMonthYear');
  var prevButton = document.getElementById('prevMonth');
  var nextButton = document.getElementById('nextMonth');
  
  function updateMonthDisplay() {
    var monthNames = [
      'January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'
    ];
    currentMonthElement.textContent = monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
  }
  
  function loadMonthData() {
    // Show loading state
    var statCards = document.querySelectorAll('.stat-value');
    statCards.forEach(function(card) {
      card.textContent = '...';
    });
    
    // Simulate loading delay
    setTimeout(function() {
      // Here you would typically load data for the selected month
      console.log('Loading data for:', currentDate.getMonth() + 1, currentDate.getFullYear());
      
      // Update stat cards with sample data based on month
      var month = currentDate.getMonth() + 1;
      var year = currentDate.getFullYear();
      
      // Sample data variations based on month
      var doctors = 300 + (month * 5);
      var nurses = 15 + (month * 2);
      var pharmacists = 30 + (month * 3);
      var labStaff = 60 + (month * 1);
      
      // Update stat cards
      if(statCards.length >= 4) {
        statCards[0].textContent = doctors;
        statCards[1].textContent = nurses;
        statCards[2].textContent = pharmacists;
        statCards[3].textContent = labStaff;
      }
      
      // Update users by role table
      updateUsersByRole();
    }, 300);
  }
  
  function updateUsersByRole() {
    var roleTable = document.querySelector('tbody');
    if(!roleTable) return;
    
    var rows = roleTable.querySelectorAll('tr');
    var baseCounts = [6, 12, 210, 48, 80]; // Admin, Supervisor, Nurse, Pharmacy, Laboratory
    
    rows.forEach(function(row, index) {
      var countCell = row.querySelector('td:last-child');
      if(countCell && baseCounts[index]) {
        var month = currentDate.getMonth() + 1;
        var variation = Math.floor(Math.random() * 20) - 10; // -10 to +10
        var newCount = baseCounts[index] + variation;
        countCell.textContent = Math.max(0, newCount); // Ensure non-negative
      }
    });
  }
  
  function navigate(delta){
    var y = currentDate.getFullYear();
    var m = currentDate.getMonth() + 1;
    var nextM = m + delta;
    var nextY = y;
    if (nextM < 1) { nextM = 12; nextY = y - 1; }
    if (nextM > 12) { nextM = 1; nextY = y + 1; }
    var params = new URLSearchParams(window.location.search);
    params.set('year', String(nextY));
    params.set('month', String(nextM));
    window.location.search = params.toString();
  }

  if(prevButton) {
    prevButton.addEventListener('click', function(e) {
      e.preventDefault();
      navigate(-1);
    });
  }
  
  if(nextButton) {
    nextButton.addEventListener('click', function(e) {
      e.preventDefault();
      navigate(1);
    });
  }
  
  // Initialize
  updateMonthDisplay();
  // Remove simulated dynamic load since server renders counts for selected month
})();
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>

