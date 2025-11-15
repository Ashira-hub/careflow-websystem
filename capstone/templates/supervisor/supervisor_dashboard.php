<?php
$page='Supervisor Dashboard';
require_once __DIR__.'/../../config/db.php';

function sd_escape($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$nurses = [];
$dbError = '';
try {
  $pdo = get_pdo();
  $stmt = $pdo->query("SELECT id, full_name, email, created_at FROM users WHERE role = 'nurse' ORDER BY created_at DESC");
  $nurses = $stmt->fetchAll();
} catch (Throwable $e) {
  $dbError = 'Unable to load nurse roster.';
}

$requestFile = __DIR__.'/../../data/nurse_shift_requests.json';
$requests = [];
if (file_exists($requestFile)) {
  $raw = file_get_contents($requestFile);
  $decoded = json_decode($raw, true);
  if (is_array($decoded)) { $requests = $decoded; }
}

function sd_norm($s){ return strtolower(trim((string)$s)); }
$requestMap = [];
$openRequests = [];
foreach ($requests as $item) {
  $nameKey = sd_norm($item['nurse'] ?? '');
  $status = strtolower((string)($item['status'] ?? 'pending'));
  $timeStr = $item['updated_at'] ?? $item['status_changed_at'] ?? $item['created_at'] ?? '';
  $timeTs = $timeStr ? strtotime((string)$timeStr) : 0;
  if ($status === 'pending') {
    $openRequests[] = [
      'nurse' => $item['nurse'] ?? '',
      'ward' => $item['ward'] ?? '',
      'shift' => $item['shift'] ?? '',
      'date' => $item['date'] ?? '',
      'time' => $item['time'] ?? '',
      'notes' => $item['notes'] ?? '',
      'status' => ucfirst($status),
      'updated_at' => $timeStr,
    ];
  }
  if ($nameKey === '') continue;
  if (!isset($requestMap[$nameKey]) || $timeTs >= ($requestMap[$nameKey]['ts'] ?? 0)) {
    $requestMap[$nameKey] = [
      'status' => $status,
      'shift' => $item['shift'] ?? '',
      'ward' => $item['ward'] ?? '',
      'updated_at' => $timeStr,
      'ts' => $timeTs,
    ];
  }
}

$staffOnDuty = 0;
$openTasks = count($openRequests);
$incidentCount = 0;
$escalations = 0;
$roster = [];

foreach ($nurses as $row) {
  $name = trim((string)($row['full_name'] ?? ''));
  if ($name === '') { $name = 'Nurse #'.(int)($row['id'] ?? 0); }
  $key = sd_norm($name);
  $status = 'No requests yet';
  $badgeColor = '#94a3b8';
  $unit = '—';
  $shift = '—';
  $updated = '';
  if (isset($requestMap[$key])) {
    $info = $requestMap[$key];
    $statusCode = $info['status'];
    if ($statusCode === 'accepted') {
      $status = 'On Duty';
      $badgeColor = '#10b981';
      $staffOnDuty++;
    } elseif ($statusCode === 'pending') {
      $status = 'Pending';
      $badgeColor = '#f59e0b';
    } elseif ($statusCode === 'rejected') {
      $status = 'Off Duty';
      $badgeColor = '#ef4444';
    }
    $unit = $info['ward'] !== '' ? $info['ward'] : '—';
    $shift = $info['shift'] !== '' ? ucfirst($info['shift']) : '—';
    $updated = $info['updated_at'] ?? '';
  }
  $roster[] = [
    'id' => (int)($row['id'] ?? 0),
    'name' => $name,
    'email' => $row['email'] ?? '',
    'unit' => $unit,
    'shift' => $shift,
    'status' => $status,
    'badge' => $badgeColor,
    'updated_at' => $updated,
  ];
}

usort($roster, function($a,$b){ return strcmp(sd_norm($a['name']), sd_norm($b['name'])); });

include __DIR__.'/../../includes/header.php';
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a class="active" href="/capstone/templates/supervisor/supervisor_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/supervisor/supervisor_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/supervisor/supervisor_schedules.php"><img src="/capstone/assets/img/appointment.png" alt="Schedules" style="width:18px;height:18px;object-fit:contain;"> Schedule</a></li>
          <li><a href="/capstone/templates/supervisor/supervisor_nurses.php"><img src="/capstone/assets/img/nurse.png" alt="Nurses" style="width:18px;height:18px;object-fit:contain;"> List</a></li>
          <li><a href="/capstone/templates/supervisor/supervisor_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div>
    <section id="home" class="dashboard-hero doctor-dashboard-hero">
      <h1 class="dashboard-title">Supervisor Nurse Dashboard</h1>
      <div class="stat-cards">
        <div class="card"><h4>Schedules</h4><div class="muted-small">Manage Schedules</div><div class="stat-value"><?php echo sd_escape($staffOnDuty); ?></div></div>
        <div class="card"><h4>Reports</h4><div class="muted-small">Reports Details</div><div class="stat-value"><?php echo sd_escape($openTasks); ?></div></div>
        <div class="card"><h4>Notifications</h4><div class="muted-small">View Notifications</div><div class="stat-value"><?php echo sd_escape($incidentCount); ?></div></div>
        <div class="card"><h4>Staff</h4><div class="muted-small">Nurse List</div><div class="stat-value"><?php echo sd_escape($escalations); ?></div></div>
      </div>

      <div class="card">
        <div class="recent-activity-header" style="display:flex;justify-content:space-between;align-items:center;">
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
(function(){
  var recent = document.getElementById('dbRecent');
  var viewAllBtn = document.getElementById('viewAllBtn');
  var showAllActivities = false;

  function escapeHtml(s){ return (s||'').replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
  
  async function load(){
    try{
      // Fetch supervisor notifications
      var resNot = await fetch('/capstone/notifications/supervisor.php');
      var dataNot = resNot.ok ? await resNot.json() : {items:[]};
      var nots = Array.isArray(dataNot.items)?dataNot.items:[];

      if(recent){
        var items = [];
        // Show all or a subset based on toggle
        var notsToShow = showAllActivities ? nots : nots.slice(0, 3);
        notsToShow.forEach(function(n){
          items.push({
            title: n.title || 'Notification',
            meta: (n.time||'').slice(0,16),
            body: n.body || ''
          });
        });

        if(items.length===0){
          recent.innerHTML = '<div class="activity-empty">'
            +'<div class="activity-empty-icon">'
              +'<img src="/capstone/assets/img/medical-file.png" alt="No activity" style="width:32px;height:32px;object-fit:contain;opacity:0.5;">'
            +'</div>'
            +'<div class="activity-empty-text">No recent activity</div>'
            +'<div class="activity-empty-subtext">Activity will appear here as it happens</div>'
          +'</div>';
        } else {
          recent.innerHTML = items.map(function(it, index){
            var iconSrc = it.title.toLowerCase().includes('schedule') ? '/capstone/assets/img/appointment.png' :
                         it.title.toLowerCase().includes('nurse') ? '/capstone/assets/img/nurse.png' : '/capstone/assets/img/medical-file.png';
            var statusColor = it.title.toLowerCase().includes('approved') ? '#10b981' :
                              it.title.toLowerCase().includes('pending') ? '#f59e0b' : '#0a5d39';
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

