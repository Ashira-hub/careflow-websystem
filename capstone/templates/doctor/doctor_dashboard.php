<?php $page='Doctor Dashboard'; include __DIR__.'/../../includes/header.php'; ?>
<?php
// Server-side counts scoped to the logged-in doctor
require_once __DIR__.'/../../config/db.php';
$countAppt = 0; $countRx = 0; $uniquePatients = 0;
try {
  $pdo = get_pdo();
  $uid = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
  // Appointments total (per doctor)
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE created_by_user_id = :uid");
  $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
  $stmt->execute();
  $countAppt = (int)$stmt->fetchColumn();
  // Prescriptions total
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM prescription WHERE created_by_user_id = :uid");
  $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
  $stmt->execute();
  $countRx = (int)$stmt->fetchColumn();
  // Unique patients across appointments and prescriptions
  $stmt = $pdo->prepare("SELECT DISTINCT patient FROM appointments WHERE created_by_user_id = :uid AND patient IS NOT NULL AND patient <> ''");
  $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
  $stmt->execute();
  $patientsA = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
  $stmt = $pdo->prepare("SELECT DISTINCT patient_name FROM prescription WHERE created_by_user_id = :uid AND patient_name IS NOT NULL AND patient_name <> ''");
  $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
  $stmt->execute();
  $patientsB = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
  $set = [];
  foreach ($patientsA as $p) { $k = strtolower(trim((string)$p)); if($k!=='') $set[$k] = 1; }
  foreach ($patientsB as $p) { $k = strtolower(trim((string)$p)); if($k!=='') $set[$k] = 1; }
  $uniquePatients = count($set);
} catch (Throwable $e) {
  $countAppt = 0; $countRx = 0; $uniquePatients = 0;
}
?>
<?php
require_once __DIR__.'/../../config/db.php';
$cntAppt = 0; $cntRx = 0; $cntPatients = 0; $cntReports = 0;
try {
  $pdo = get_pdo();
  $uid = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
  // Appointments (upcoming)
  $q1 = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE created_by_user_id = :uid AND COALESCE(done,false) = false');
  $q1->execute([':uid'=>$uid]);
  $cntAppt = (int)$q1->fetchColumn();
  // Prescriptions (total by this doctor)
  $q2 = $pdo->prepare('SELECT COUNT(*) FROM prescription WHERE created_by_user_id = :uid');
  $q2->execute([':uid'=>$uid]);
  $cntRx = (int)$q2->fetchColumn();
  // Patient records (unique patients from appointments + prescriptions for this doctor)
  $q3 = $pdo->prepare(
    "SELECT COUNT(*) FROM (
       SELECT DISTINCT patient AS p FROM appointments WHERE created_by_user_id = :uid AND patient IS NOT NULL AND patient <> ''
       UNION
       SELECT DISTINCT patient_name AS p FROM prescription WHERE created_by_user_id = :uid AND patient_name IS NOT NULL AND patient_name <> ''
     ) s"
  );
  $q3->execute([':uid'=>$uid]);
  $cntPatients = (int)$q3->fetchColumn();
  // Reports card: count completed appointments for this doctor
  $q4 = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE created_by_user_id = :uid AND COALESCE(done,false) = true');
  try { $q4->execute([':uid'=>$uid]); $cntReports = (int)$q4->fetchColumn(); } catch (Throwable $e) { $cntReports = 0; }
} catch (Throwable $e) {
  $cntAppt = $cntAppt ?? 0; $cntRx = $cntRx ?? 0; $cntPatients = $cntPatients ?? 0; $cntReports = $cntReports ?? 0;
}
?>
<?php
// Build recent activity items for this doctor (server-side, ownership enforced)
$recentItems = [];
try {
  if (!isset($pdo)) { $pdo = get_pdo(); }
  if (!isset($uid)) { $uid = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0; }
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (!isset($_SESSION['session_started_at'])) { $_SESSION['session_started_at'] = date('Y-m-d H:i:s'); }
  $since = $_SESSION['session_started_at'];
  // Recent prescriptions
  $rxStmt = $pdo->prepare('SELECT patient_name, medicine, dosage_strength, quantity, created_at
                           FROM prescription
                           WHERE created_by_user_id = :uid AND created_at >= :since
                           ORDER BY created_at DESC LIMIT 20');
  $rxStmt->execute([':uid'=>$uid, ':since'=>$since]);
  $rxRows = $rxStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($rxRows as $r) {
    $p = trim((string)($r['patient_name'] ?? ''));
    $m = trim((string)($r['medicine'] ?? ''));
    $d = trim((string)($r['dosage_strength'] ?? ''));
    $q = (string)($r['quantity'] ?? '');
    $parts = [];
    if ($p !== '') $parts[] = 'Patient: '.$p;
    if ($m !== '') $parts[] = 'Medicine: '.$m.($d ? (' '.$d) : '');
    if ($q !== '') $parts[] = 'Qty: '.$q;
    $recentItems[] = [
      'title' => 'Prescription sent',
      'meta'  => substr((string)($r['created_at'] ?? ''), 0, 16),
      'body'  => implode(' • ', $parts),
      'ts'    => (string)($r['created_at'] ?? '')
    ];
  }
  // Recent appointments
  $apStmt = $pdo->prepare('SELECT patient, "date", "time", done, created_at
                           FROM appointments
                           WHERE created_by_user_id = :uid AND created_at >= :since
                           ORDER BY created_at DESC, id DESC LIMIT 20');
  $apStmt->execute([':uid'=>$uid, ':since'=>$since]);
  $apRows = $apStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($apRows as $a) {
    $dt = substr((string)($a['created_at'] ?? ''), 0, 16);
    $meta = $dt;
    $recentItems[] = [
      'title' => !empty($a['done']) ? 'Appointment completed' : 'Upcoming appointment',
      'meta'  => $meta,
      'body'  => (string)($a['patient'] ?? ''),
      'ts'    => (string)($a['created_at'] ?? '')
    ];
  }
  // Merge session-scoped page views from header logger
  $sessActs = [];
  if (isset($_SESSION['doctor_activity']) && is_array($_SESSION['doctor_activity'])) {
    foreach ($_SESSION['doctor_activity'] as $ev) {
      $sessActs[] = [
        'title' => (string)($ev['title'] ?? 'Viewed page'),
        'meta'  => (string)($ev['meta'] ?? ''),
        'body'  => (string)($ev['body'] ?? ''),
        'ts'    => (string)($ev['ts'] ?? ''),
      ];
    }
  }
  $recentItems = array_merge($sessActs, $recentItems);
  // Sort by timestamp descending and limit to 5
  usort($recentItems, function($x, $y){ return strcmp((string)($y['ts'] ?? ''), (string)($x['ts'] ?? '')); });
  $recentItems = array_slice($recentItems, 0, 5);
} catch (Throwable $e) {
  $recentItems = [];
}
?>

<div class="layout-sidebar full-bleed doctor-dashboard-layout">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a class="active" href="#home"><img src="/capstone/assets/img/home (2).png" alt="Home" class="sidebar-nav-icon"> Home</a></li>
          <li><a href="/capstone/templates/doctor/doctor_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" class="sidebar-nav-icon"> Profile</a></li>
          <li><a href="/capstone/templates/doctor/doctor_appointment.php"><img src="/capstone/assets/img/appointment.png" alt="Appointment" class="sidebar-nav-icon"> Appointment</a></li>
          <li><a href="/capstone/templates/doctor/doctor_prescription.php"><img src="/capstone/assets/img/prescription.png" alt="Prescription" class="sidebar-nav-icon"> Prescription</a></li>
          <li><a href="/capstone/templates/doctor/doctor_records.php"><img src="/capstone/assets/img/medical-file.png" alt="Patient Record" class="sidebar-nav-icon"> Patient Record</a></li>
          <li><a href="/capstone/templates/doctor/doctor_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" class="sidebar-nav-icon"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div>
    <section id="home" class="dashboard-hero doctor-dashboard-hero">
      <h1 class="dashboard-title">Doctor Dashboard</h1>
      <div class="stat-cards">
        <div class="card"><h4>Appointments</h4><div class="muted-small">Total</div><div class="stat-value" id="dbAppt"><?php echo (int)$countAppt; ?></div></div>
        <div class="card"><h4>Prescriptions</h4><div class="muted-small">Total</div><div class="stat-value" id="dbRx"><?php echo (int)$countRx; ?></div></div>
        <div class="card"><h4>Patient Records</h4><div class="muted-small">Total</div><div class="stat-value" id="dbPatients"><?php echo (int)$uniquePatients; ?></div></div>
        <div class="card"><h4>Reports</h4><div class="muted-small">Available</div><div class="stat-value" id="dbUnread"><?php echo (int)$cntReports; ?></div></div>
      </div>

      <div class="card">
        <div class="recent-activity-header">
          <h3 class="recent-activity-title">Recent Activity</h3>
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
  // Preloaded recent activity from the server (per-doctor)
  window.DOCTOR_RECENT = <?php echo json_encode(array_map(function($it){ return ['title'=>$it['title'],'meta'=>$it['meta'],'body'=>$it['body']]; }, $recentItems), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
</script>
<script>
(function(){
  var statAppt = document.getElementById('dbAppt');
  var statRx = document.getElementById('dbRx');
  var statPatients = document.getElementById('dbPatients');
  var statUnread = document.getElementById('dbUnread');
  var recent = document.getElementById('dbRecent');
  var viewAllBtn = document.getElementById('viewAllBtn');
  var showAllActivities = false;

  function escapeHtml(s){ return (s||'').replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
  function parseRx(n){
    var patient='', med='';
    (n.body||'').split('|').forEach(function(part){
      var p = part.trim();
      if(p.toLowerCase().startsWith('patient:')) patient = p.split(':')[1]?.trim()||'';
      if(p.toLowerCase().startsWith('medicine:')) med = p.split(':')[1]?.trim()||'';
    });
    return { patient: patient, medicine: med, time: n.time||'' };
  }
  function renderList(container, items, emptyText){
    if(!container) return;
    if(!items || items.length===0){ container.innerHTML = '<div class="muted">'+escapeHtml(emptyText||'No data')+'</div>'; return; }
    var html = '<ul class="plain-list">'+items.map(function(x){ return '<li>'+x+'</li>'; }).join('')+'</ul>';
    container.innerHTML = html;
  }
  async function load(){
    try{
      // Use server-side counts already scoped by created_by_user_id
      if(statAppt) statAppt.textContent = <?php echo (int)$countAppt; ?>;
      if(statRx) statRx.textContent = <?php echo (int)$countRx; ?>;
      if(statPatients) statPatients.textContent = <?php echo (int)$uniquePatients; ?>;
      // Render recent activity from preloaded items
      if(recent){
        var items = Array.isArray(window.DOCTOR_RECENT) ? window.DOCTOR_RECENT.slice() : [];
        if(items.length === 0){
          recent.innerHTML = '<div class="activity-empty">'
            +'<div class="activity-empty-icon">'
            +  '<img src="/capstone/assets/img/medical-file.png" alt="No activity" style="width:32px;height:32px;object-fit:contain;opacity:0.5;">'
            +'</div>'
            +'<div class="activity-empty-text">No recent activity</div>'
            +'<div class="activity-empty-subtext">Activity will appear here as it happens</div>'
            +'</div>';
        } else {
          recent.innerHTML = items.map(function(it, index){
            var iconSrc = it.title.indexOf('Prescription') !== -1 ? '/capstone/assets/img/prescription.png' : 
                          it.title.indexOf('Appointment') !== -1 ? '/capstone/assets/img/appointment.png' : '/capstone/assets/img/medical-file.png';
            var statusColor = it.title.indexOf('completed') !== -1 ? '#10b981' : 
                              it.title.indexOf('Upcoming') !== -1 ? '#f59e0b' : '#0a5d39';
            return '<div class="activity-item" style="animation-delay:'+(index*0.1)+'s;">'
              +'<div class="activity-icon" style="background:'+statusColor+'20;color:'+statusColor+';display:flex;align-items:center;justify-content:center;">'
              +  '<img src="'+iconSrc+'" alt="Activity" style="width:18px;height:18px;object-fit:contain;">'
              +'</div>'
              +'<div class="activity-content">'
              +  '<div class="activity-title">'+escapeHtml(it.title||'')+'</div>'
              +  '<div class="activity-meta">'+escapeHtml(it.meta||'')+'</div>'
              +  '<div class="activity-body">'+escapeHtml(it.body||'')+'</div>'
              +'</div>'
              +'<div class="activity-time">'
              +  '<div style="width:4px;height:4px;border-radius:50%;background:'+statusColor+';"></div>'
              +'</div>'
              +'</div>';
          }).join('');
        }
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
