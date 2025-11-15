<?php $page='Nurse Dashboard'; include __DIR__.'/../../includes/header.php'; ?>
<?php
require_once __DIR__.'/../../config/db.php';
$cntSchedule = 0; $cntRx = 0; $cntRequest = 0; $cntReports = 0;
try {
  $pdo = get_pdo();
  $uid = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
  $q1 = $pdo->prepare('SELECT COUNT(*) FROM schedules WHERE nurse_id = :uid');
  $q1->execute([':uid'=>$uid]);
  $cntSchedule = (int)$q1->fetchColumn();
  $q2 = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE nurse_id = :uid AND LOWER(COALESCE(status,'pending')) = 'pending'");
  $q2->execute([':uid'=>$uid]);
  $cntRequest = (int)$q2->fetchColumn();
} catch (Throwable $e) {
  $cntSchedule = $cntSchedule ?? 0; $cntRequest = $cntRequest ?? 0;
}
try {
  $rxFile = __DIR__.'/../../data/nurse_prescriptions.json';
  $rxRaw = @file_get_contents($rxFile);
  $rx = $rxRaw ? json_decode($rxRaw, true) : [];
  if (!is_array($rx)) { $rx = []; }
  $cntRx = count($rx);
  $cntReports = 0;
  foreach ($rx as $row) { $s = strtolower((string)($row['status'] ?? '')); if ($s === 'done') { $cntReports++; } }
} catch (Throwable $e) {
  $cntRx = $cntRx ?? 0; $cntReports = $cntReports ?? 0;
}
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a class="active" href="/capstone/templates/nurse/nurse_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/nurse/nurse_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/nurse/nurse_schedule.php"><img src="/capstone/assets/img/appointment.png" alt="Schedule" style="width:18px;height:18px;object-fit:contain;"> Schedule</a></li>
          <li><a href="/capstone/templates/nurse/nurse_prescription.php"><img src="/capstone/assets/img/prescription.png" alt="Prescription" style="width:18px;height:18px;object-fit:contain;"> Prescription</a></li>
          <li><a href="/capstone/templates/nurse/nurse_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div>
    <section id="home" class="dashboard-hero" style="padding-top:10px;">
      <h1 class="dashboard-title">Nurse Dashboard</h1>
      <div class="stat-cards">
        <div class="card"><h4>Schedule</h4><div class="muted-small">Total</div><div class="stat-value" id="nrSch"><?php echo (int)$cntSchedule; ?></div></div>
        <div class="card"><h4>Prescriptions</h4><div class="muted-small">Total</div><div class="stat-value" id="nrRx"><?php echo (int)$cntRx; ?></div></div>
        <div class="card"><h4>Requests</h4><div class="muted-small">Pending</div><div class="stat-value" id="nrReq"><?php echo (int)$cntRequest; ?></div></div>
        <div class="card"><h4>Reports</h4><div class="muted-small">Completed</div><div class="stat-value" id="nrRep"><?php echo (int)$cntReports; ?></div></div>
      </div>

      <div class="card">
        <div class="recent-activity-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
          <h3 class="recent-activity-title" style="margin:0;">Recent Activity</h3>
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

  function escapeHtml(s){ return (s||'').replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
  
  async function load(){
    try{
      // Simulate loading nurse activities
      var activities = [
        {
          title: 'Signed in',
          meta: '<?php echo date('Y-m-d H:i'); ?>',
          body: 'Last login at <?php echo date('Y-m-d H:i'); ?>'
        },
        {
          title: 'Viewed schedule',
          meta: '<?php echo date('Y-m-d H:i'); ?>',
          body: 'Opened schedule page'
        },
        {
          title: 'Patient vitals recorded',
          meta: '<?php echo date('Y-m-d H:i'); ?>',
          body: 'Anna Cruz • Blood pressure: 120/80'
        },
        {
          title: 'Medication administered',
          meta: '<?php echo date('Y-m-d H:i'); ?>',
          body: 'Peter Park • Aspirin 100mg'
        }
      ];
      
      // Show limited or all activities based on toggle
      var activitiesToShow = showAllActivities ? activities : activities.slice(0, 2);
      
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
          var iconSrc = it.title.includes('Signed') ? '/capstone/assets/img/user.png' : 
                       it.title.includes('schedule') ? '/capstone/assets/img/appointment.png' : 
                       it.title.includes('vitals') ? '/capstone/assets/img/medical-file.png' : 
                       it.title.includes('Medication') ? '/capstone/assets/img/prescription.png' : '/capstone/assets/img/medical-file.png';
          var statusColor = it.title.includes('Signed') ? '#10b981' : 
                          it.title.includes('schedule') ? '#3b82f6' : 
                          it.title.includes('vitals') ? '#f59e0b' : 
                          it.title.includes('Medication') ? '#8b5cf6' : '#0a5d39';
          return '<div class="activity-item" style="animation-delay:'+(index*0.1)+'s;">'
            +'<div class="activity-icon" style="background:'+statusColor+'20;color:'+statusColor+';display:flex;align-items:center;justify-content:center;">'
              +'<img src="'+iconSrc+'" alt="Activity" style="width:18px;height:18px;object-fit:contain;">'
            +'</div>'
            +'<div class="activity-content">'
              +'<div class="activity-title">'+escapeHtml(it.title)+'</div>'
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
  
  // View All button functionality
  function toggleViewAll(){
    showAllActivities = !showAllActivities;
    viewAllBtn.textContent = showAllActivities ? 'Show Less' : 'View All';
    load(); // Reload to show/hide activities
  }
  
  if(viewAllBtn){
    viewAllBtn.addEventListener('click', toggleViewAll);
  }
  
  load();
})();
</script>
