<?php
 $page='Laboratory Dashboard';
 require_once __DIR__.'/../../config/db.php';
 if (session_status() === PHP_SESSION_NONE) { session_start(); }

 $totalTests = 0;
 $testsThisMonth = 0;
 $patientsThisMonth = 0;
 $pendingTests = 0;
 $recentActivities = [];
 try {
   $pdo = get_pdo();
   $colStmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'lab_tests'");
   $colStmt->execute();
   $cols = array_map('strtolower', array_map('strval', array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
   $hasLegacyDate  = in_array('date', $cols, true);
   $hasTestDate    = in_array('test_date', $cols, true);
   $hasCreatedById = in_array('created_by_user_id', $cols, true);
   $hasCreatedAt   = in_array('created_at', $cols, true);

   if ($hasLegacyDate) {
     $dateExpr = 'date::date';
   } elseif ($hasTestDate) {
     $dateExpr = 'test_date';
   } else {
     $dateExpr = 'created_at::date';
   }

   $whereUser = '';
   $paramsUser = [];
   if ($hasCreatedById && !empty($_SESSION['user']['id'])) {
     $whereUser = 'WHERE created_by_user_id = :uid';
     $paramsUser[':uid'] = (int)$_SESSION['user']['id'];
   }

   // Total tests for this lab user (or all tests if no creator column)
   $sqlTotal = 'SELECT COUNT(*)::int AS c FROM lab_tests ' . $whereUser;
   $stmt = $pdo->prepare($sqlTotal);
   $stmt->execute($paramsUser);
   $totalTests = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

   // Tests this month
   $sqlMonth = "SELECT COUNT(*)::int AS c FROM lab_tests "
             . ($whereUser ? $whereUser . " AND " : 'WHERE ')
             . "date_trunc('month', $dateExpr) = date_trunc('month', CURRENT_DATE)";
   $stmt = $pdo->prepare($sqlMonth);
   $stmt->execute($paramsUser);
   $testsThisMonth = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

   // Distinct patients this month
   $sqlPatients = "SELECT COUNT(DISTINCT patient)::int AS c FROM lab_tests "
                . ($whereUser ? $whereUser . " AND " : 'WHERE ')
                . "date_trunc('month', $dateExpr) = date_trunc('month', CURRENT_DATE)";
   $stmt = $pdo->prepare($sqlPatients);
   $stmt->execute($paramsUser);
   $patientsThisMonth = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

   // Pending tests (overall)
   $sqlPending = 'SELECT COUNT(*)::int AS c FROM lab_tests ' . ($whereUser ? $whereUser . " AND status = 'Pending'" : "WHERE status = 'Pending'");
   $stmt = $pdo->prepare($sqlPending);
   $stmt->execute($paramsUser);
   $pendingTests = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

   // Ensure a session-scoped start timestamp (like doctor_dashboard)
   if (!isset($_SESSION['session_started_at']) || !is_string($_SESSION['session_started_at']) || $_SESSION['session_started_at'] === '') {
     $_SESSION['session_started_at'] = date('Y-m-d H:i:s');
   }
   $since = $_SESSION['session_started_at'];

   // Recent activity: derive from latest lab_tests rows for this user, limited to this session
   $recentWhere = $whereUser;
   if ($hasCreatedAt) {
     if ($recentWhere === '') {
       $recentWhere = 'WHERE created_at >= :since';
     } else {
       $recentWhere .= ' AND created_at >= :since';
     }
     $paramsUser[':since'] = $since;
   }

   $sqlRecent = 'SELECT id, patient, test_name, status, ' . $dateExpr . ' AS d '
              . 'FROM lab_tests '
              . ($recentWhere ? $recentWhere . ' ' : '')
              . 'ORDER BY ' . $dateExpr . ' DESC NULLS LAST, id DESC '
              . 'LIMIT 10';
   $stmt = $pdo->prepare($sqlRecent);
   $stmt->execute($paramsUser);
   $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
   foreach ($rows as $r) {
     $dateStr = isset($r['d']) && $r['d'] !== null ? substr((string)$r['d'], 0, 16) : date('Y-m-d H:i');
     $statusLabel = trim((string)($r['status'] ?? ''));
     $statusLabel = $statusLabel === '' ? 'Pending' : $statusLabel;
     $title = 'Lab test ' . strtolower($statusLabel);
     $patient = trim((string)($r['patient'] ?? 'Unknown patient'));
     $testName = trim((string)($r['test_name'] ?? 'Laboratory test'));
     $bodyParts = [];
     if ($testName !== '') { $bodyParts[] = $testName; }
     if ($patient !== '') { $bodyParts[] = 'Patient: ' . $patient; }
     $recentActivities[] = [
       'title' => $title,
       'meta'  => $dateStr,
       'body'  => implode(' • ', $bodyParts),
       'ts'    => isset($r['d']) && $r['d'] !== null ? (string)$r['d'] : $dateStr,
     ];
   }

   // Merge session-scoped lab page views from header logger
   $sessionActs = [];
   if (isset($_SESSION['lab_activity']) && is_array($_SESSION['lab_activity'])) {
     foreach ($_SESSION['lab_activity'] as $ev) {
       $sessionActs[] = [
         'title' => (string)($ev['title'] ?? 'Viewed page'),
         'meta'  => (string)($ev['meta'] ?? ''),
         'body'  => (string)($ev['body'] ?? ''),
         'ts'    => (string)($ev['ts'] ?? ''),
       ];
     }
   }
   $recentActivities = array_merge($sessionActs, $recentActivities);
   // Sort by timestamp descending and limit to 10
   usort($recentActivities, function($a, $b){ return strcmp((string)($b['ts'] ?? ''), (string)($a['ts'] ?? '')); });
   $recentActivities = array_slice($recentActivities, 0, 10);
 } catch (Throwable $e) {
   error_log('Failed to load lab dashboard stats: ' . $e->getMessage());
   $totalTests = $testsThisMonth = $patientsThisMonth = $pendingTests = 0;
   $recentActivities = [];
 }

 include __DIR__.'/../../includes/header.php';
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a class="active" href="/capstone/templates/laboratory/lab_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/laboratory/lab_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/laboratory/lab_orders.php"><img src="/capstone/assets/img/appointment.png" alt="Orders" style="width:18px;height:18px;object-fit:contain;"> Laboratory</a></li>
          <li><a href="/capstone/templates/laboratory/lab_results.php"><img src="/capstone/assets/img/prescription.png" alt="Results" style="width:18px;height:18px;object-fit:contain;"> Lab Records</a></li>
          <li><a href="/capstone/templates/laboratory/lab_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div>
    <!-- Header Card (aligned with lab_orders.php style) -->
    <section class="card" style="margin-bottom:16px;">
      <h2 class="dashboard-title" style="margin:0;">Laboratory</h2>
    </section>

    <!-- Stat Cards wrapped in a card to match lab_orders.php visual pattern -->
    <section class="card" style="margin-bottom:16px;">
      <div class="stat-cards">
        <div class="card"><h4>Laboratory</h4><div class="muted-small">Total Tests</div><div class="stat-value" id="dbLab"><?php echo (int)$totalTests; ?></div></div>
        <div class="card"><h4>Lab Records</h4><div class="muted-small">Tests This Month</div><div class="stat-value" id="dbRecords"><?php echo (int)$testsThisMonth; ?></div></div>
        <div class="card"><h4>Reports</h4><div class="muted-small">Patients This Month</div><div class="stat-value" id="dbReports"><?php echo (int)$patientsThisMonth; ?></div></div>
        <div class="card"><h4>Notification</h4><div class="muted-small">Pending Tests</div><div class="stat-value" id="dbProfile"><?php echo (int)$pendingTests; ?></div></div>
      </div>
    </section>

    <!-- Recent Activity remains a card and styling already matches -->
    <section class="card">
      <div class="recent-activity-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 class="recent-activity-title" style="margin:0;color:#0a5d39;">Recent Activity</h3>
        <button id="viewAllBtn" class="btn btn-outline view-all-btn">
          View All
        </button>
      </div>
      <div id="dbRecent" class="activity-feed">
        <div class="activity-loading">
          <div class="loading-spinner"></div>
          <span class="muted-small">Loading activity...</span>
        </div>
      </div>
    </section>
  </div>
</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>

<script>
(function(){
  var recent = document.getElementById('dbRecent');
  var viewAllBtn = document.getElementById('viewAllBtn');
  var showAllActivities = false;

  // Injected from PHP: recent lab activity
  var activities = <?php echo json_encode($recentActivities, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || [];

  function escapeHtml(s){ return (s||'').replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
  
  function load(){
    try{
      var activitiesToShow = showAllActivities ? activities : activities.slice(0, 3);
      
      if(activitiesToShow.length === 0){
        recent.innerHTML = '<div class="activity-empty">'
          +'<div class="activity-empty-icon">'
            +'<img src="/capstone/assets/img/medical-file.png" alt="No activity" style="width:32px;height:32px;object-fit:contain;opacity:0.5;">'
          +'</div>'
          +'<div class="activity-empty-text">No recent activity</div>'
          +'<div class="activity-empty-subtext">Activity will appear here as it happens</div>'
        +'</div>';
      } else {
        recent.innerHTML = activitiesToShow.map(function(it, index){
          var title = String(it.title||'');
          var iconSrc = title.includes('signed') ? '/capstone/assets/img/user.png' : 
                       title.includes('test') ? '/capstone/assets/img/prescription.png' : 
                       title.includes('order') ? '/capstone/assets/img/appointment.png' : 
                       title.includes('report') ? '/capstone/assets/img/bar-chart.png' : '/capstone/assets/img/medical-file.png';
          var lowerTitle = title.toLowerCase();
          var statusColor = lowerTitle.includes('signed') ? '#10b981' : 
                            lowerTitle.includes('completed') ? '#10b981' : 
                            lowerTitle.includes('pending') ? '#f97316' :
                            lowerTitle.includes('cancelled') ? '#ef4444' : '#0a5d39';
          return '<div class="activity-item" style="animation-delay:'+(index*0.1)+'s;">'
            +'<div class="activity-icon" style="background:'+statusColor+'20;color:'+statusColor+';display:flex;align-items:center;justify-content:center;">'
              +'<img src="'+iconSrc+'" alt="Activity" style="width:18px;height:18px;object-fit:contain;">'
            +'</div>'
            +'<div class="activity-content">'
              +'<div class="activity-title">'+escapeHtml(title)+'</div>'
              +'<div class="activity-meta">'+escapeHtml(it.meta||'')+'</div>'
              +'<div class="activity-body">'+escapeHtml(it.body||'')+'</div>'
            +'</div>'
            +'<div class="activity-time">'
              +'<div style="width:4px;height:4px;border-radius:50%;background:'+statusColor+';"></div>'
            +'</div>'
          +'</div>';
        }).join('');
      }

    }catch(err){
      if(recent) recent.textContent = 'Error: '+err.message;
    }
  }
  
  function toggleViewAll(){
    showAllActivities = !showAllActivities;
    viewAllBtn.textContent = showAllActivities ? 'Show Less' : 'View All';
    load();
  }
  
  if(viewAllBtn){
    viewAllBtn.addEventListener('click', toggleViewAll);
  }
  
  load();
})();
</script>

