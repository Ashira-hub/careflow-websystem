<?php
 $page='Lab Reports';
 require_once __DIR__.'/../../config/db.php';
 if (session_status() === PHP_SESSION_NONE) { session_start(); }

 // Determine selected month/year from query (default: current month)
 $selectedYear  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
 $selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
 if ($selectedYear < 1970 || $selectedYear > 2100) { $selectedYear = (int)date('Y'); }
 if ($selectedMonth < 1 || $selectedMonth > 12) { $selectedMonth = (int)date('n'); }
 $selectedMonthStart = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);

 $testsThisMonth = 0;
 $patientsThisMonth = 0;
 $recentReports = [];
 try {
   $pdo = get_pdo();
   // Discover columns to choose appropriate date/owner fields
   $colStmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'lab_tests'");
   $colStmt->execute();
   $cols = array_map('strtolower', array_map('strval', array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
   $hasLegacyDate  = in_array('date', $cols, true);
   $hasTestDate    = in_array('test_date', $cols, true);
   $hasCreatedById = in_array('created_by_user_id', $cols, true);

   // Prefer legacy `date`, then `test_date`, else created_at::date
   if ($hasLegacyDate) {
     $dateExpr = 'date::date';
   } elseif ($hasTestDate) {
     $dateExpr = 'test_date';
   } else {
     $dateExpr = 'created_at::date';
   }

   $where = "date_trunc('month', $dateExpr) = date_trunc('month', :month_start::date)";
   $params = [':month_start' => $selectedMonthStart];
   if ($hasCreatedById && !empty($_SESSION['user']['id'])) {
     $where .= ' AND created_by_user_id = :uid';
     $params[':uid'] = (int)$_SESSION['user']['id'];
   }

   // Total tests this month
   $sqlTests = "SELECT COUNT(*)::int AS c FROM lab_tests WHERE $where";
   $stmt = $pdo->prepare($sqlTests);
   $stmt->execute($params);
   $testsThisMonth = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

   // Distinct patients this month
   $sqlPatients = "SELECT COUNT(DISTINCT patient)::int AS c FROM lab_tests WHERE $where";
   $stmt = $pdo->prepare($sqlPatients);
   $stmt->execute($params);
   $patientsThisMonth = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

   // Per-test counts for this month (recent reports table)
   $sqlRecent = "SELECT test_name, COUNT(*)::int AS c FROM lab_tests WHERE $where GROUP BY test_name ORDER BY c DESC, test_name ASC LIMIT 10";
   $stmt = $pdo->prepare($sqlRecent);
   $stmt->execute($params);
   $recentReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
 } catch (Throwable $e) {
   // Keep page usable even if stats fail
   error_log('Failed to load lab report stats: ' . $e->getMessage());
   $testsThisMonth = 0;
   $patientsThisMonth = 0;
   $recentReports = [];
 }

 include __DIR__.'/../../includes/header.php';
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/laboratory/lab_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/laboratory/lab_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/laboratory/lab_orders.php"><img src="/capstone/assets/img/appointment.png" alt="Orders" style="width:18px;height:18px;object-fit:contain;"> Laboratory</a></li>
          <li><a href="/capstone/templates/laboratory/lab_results.php"><img src="/capstone/assets/img/prescription.png" alt="Results" style="width:18px;height:18px;object-fit:contain;"> Lab Records</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
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
      <div class="stat-cards" style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
        <div class="stat-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
          <h4 style="margin:0 0 8px;color:#0f172a;font-size:1rem;font-weight:600;">Tests</h4>
          <div class="muted-small" style="color:#64748b;font-size:0.85rem;margin-bottom:8px;">This month</div>
          <div class="stat-value" style="font-size:2rem;font-weight:700;color:#0a5d39;">
            <?php echo (int)$testsThisMonth; ?>
          </div>
        </div>
        <div class="stat-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
          <h4 style="margin:0 0 8px;color:#0f172a;font-size:1rem;font-weight:600;">Total Patient</h4>
          <div class="muted-small" style="color:#64748b;font-size:0.85rem;margin-bottom:8px;">This month</div>
          <div class="stat-value" style="font-size:2rem;font-weight:700;color:#0a5d39;">
            <?php echo (int)$patientsThisMonth; ?>
          </div>
        </div>
      </div>
    </section>

    <section style="display:grid;grid-template-columns:1fr;gap:20px;margin-bottom:16px;">
      <div class="card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
        <h3 style="margin:0 0 16px;color:#0f172a;font-size:1.1rem;font-weight:600;">Recent Reports</h3>
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="border-bottom:1px solid #e5e7eb;">
              <th style="padding:8px 0;text-align:left;font-weight:600;color:#0f172a;font-size:0.9rem;">Test</th>
              <th style="padding:8px 0;text-align:right;font-weight:600;color:#0f172a;font-size:0.9rem;">Count (this month)</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentReports)): ?>
              <tr>
                <td colspan="2" style="padding:12px 0;text-align:center;color:#64748b;font-size:0.9rem;">
                  No tests recorded this month.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($recentReports as $row): ?>
                <tr style="border-bottom:1px solid #f1f5f9;">
                  <td style="padding:8px 0;color:#0f172a;font-size:0.9rem;">
                    <?php echo htmlspecialchars($row['test_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                  </td>
                  <td style="padding:8px 0;text-align:right;color:#64748b;font-size:0.9rem;font-weight:600;">
                    <?php echo (int)($row['c'] ?? 0); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>

<script>
(function(){
  var currentMonthYear = document.getElementById('currentMonthYear');
  var prevMonthBtn = document.getElementById('prevMonth');
  var nextMonthBtn = document.getElementById('nextMonth');

  // Initialize from PHP-selected month/year
  var selectedYear  = <?php echo (int)$selectedYear; ?>;
  var selectedMonth = <?php echo (int)$selectedMonth; ?>; // 1-12

  function getMonthLabel(year, month){
    var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                     'July', 'August', 'September', 'October', 'November', 'December'];
    return monthNames[month - 1] + ' ' + year;
  }
  
  function updateMonthDisplay(){
    if(currentMonthYear) {
      currentMonthYear.textContent = getMonthLabel(selectedYear, selectedMonth);
    }
  }
  
  function navigateMonth(direction){
    if(direction === 'prev'){
      selectedMonth -= 1;
      if (selectedMonth < 1) { selectedMonth = 12; selectedYear -= 1; }
    } else if(direction === 'next'){
      selectedMonth += 1;
      if (selectedMonth > 12) { selectedMonth = 1; selectedYear += 1; }
    }

    // Reload page with new month/year so PHP recomputes stats
    var url = new URL(window.location.href);
    url.searchParams.set('year', String(selectedYear));
    url.searchParams.set('month', String(selectedMonth));
    window.location.href = url.toString();
  }
  
  function renderActivityChart(){
    // Sample data for demonstration (still static; cards & table are real)
    var sampleData = [34, 28, 45, 32, 50, 38, 42, 29, 35, 41, 48, 33];
    var maxValue = Math.max(...sampleData);
    var activityBars = document.getElementById('activityBars');
    
    if(activityBars){
      activityBars.innerHTML = '';
      sampleData.forEach(function(value, index){
        var height = (value / maxValue) * 100;
        var div = document.createElement('div');
        div.style.height = Math.max(8, height) + 'px';
        div.style.background = 'linear-gradient(135deg,#0a5d39,#10b981)';
        div.style.borderRadius = '4px 4px 0 0';
        div.style.transition = 'all 0.2s ease';
        div.style.cursor = 'pointer';
        div.title = 'Day ' + (index + 1) + ': ' + value + ' tests';
        div.addEventListener('mouseenter', function(){ 
          this.style.opacity = '0.8'; 
          this.style.transform = 'scaleY(1.1)';
        });
        div.addEventListener('mouseleave', function(){ 
          this.style.opacity = '1'; 
          this.style.transform = 'scaleY(1)';
        });
        activityBars.appendChild(div);
      });
    }
  }
  
  // Add event listeners for navigation
  if(prevMonthBtn) prevMonthBtn.addEventListener('click', function(e){ e.preventDefault(); navigateMonth('prev'); });
  if(nextMonthBtn) prevMonthBtn && nextMonthBtn.addEventListener('click', function(e){ e.preventDefault(); navigateMonth('next'); });
  
  // Initialize on page load
  updateMonthDisplay();
  renderActivityChart();
})();
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>

