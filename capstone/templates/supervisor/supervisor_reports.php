<?php
$page='Supervisor Reports';
require_once __DIR__.'/../../config/db.php';

function sr_escape($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$pdo = null;
$nurseMap = [];
try {
  $pdo = get_pdo();
  $stmt = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'nurse'");
  foreach ($stmt as $row) {
    $id = (int)($row['id'] ?? 0);
    if ($id <= 0) continue;
    $nurseMap[$id] = [
      'name' => trim((string)($row['full_name'] ?? '')),
      'email' => trim((string)($row['email'] ?? '')),
    ];
  }
} catch (Throwable $e) {
  $nurseMap = [];
}

$requestFile = __DIR__.'/../../data/nurse_shift_requests.json';
$requests = [];
if (file_exists($requestFile)) {
  $raw = file_get_contents($requestFile);
  $decoded = json_decode($raw, true);
  if (is_array($decoded)) {
    $requests = $decoded;
  }
}

function sr_group_key($ts, $group){
  if(!$ts) return 'Unknown';
  if($group === 'month') return date('Y-m', $ts);
  if($group === 'week') return date('o-\WW', $ts);
  return date('Y-m-d', $ts);
}

$from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$to = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
$group = isset($_GET['group']) ? strtolower((string)$_GET['group']) : 'day';
$group = in_array($group, ['day','week','month'], true) ? $group : 'day';
$statusFilter = isset($_GET['status']) ? strtolower((string)$_GET['status']) : 'all';

$fromTs = $from !== '' ? strtotime($from.' 00:00:00') : null;
$toTs = $to !== '' ? strtotime($to.' 23:59:59') : null;

$normalized = [];
foreach ($requests as $row) {
  $item = $row;
  $item['date'] = trim((string)($item['date'] ?? ''));
  $item['time'] = trim((string)($item['time'] ?? ''));
  $item['end_time'] = trim((string)($item['end_time'] ?? ''));
  $item['shift'] = trim((string)($item['shift'] ?? ''));
  $item['ward'] = trim((string)($item['ward'] ?? ''));
  $item['notes'] = trim((string)($item['notes'] ?? ''));
  $item['status'] = strtolower((string)($item['status'] ?? 'request'));
  $item['nurse'] = trim((string)($item['nurse'] ?? ''));
  $nurseId = (int)($item['nurse_id'] ?? 0);
  if ($nurseId > 0 && isset($nurseMap[$nurseId])) {
    if ($item['nurse'] === '' && $nurseMap[$nurseId]['name'] !== '') {
      $item['nurse'] = $nurseMap[$nurseId]['name'];
    }
    $item['nurse_email'] = $nurseMap[$nurseId]['email'] ?? ($item['nurse_email'] ?? '');
  }
  $combined = trim($item['date'].' '.$item['time']);
  $ts = $combined !== '' ? strtotime($combined) : ($item['date'] !== '' ? strtotime($item['date']) : null);
  $item['ts'] = $ts ?: null;
  $normalized[] = $item;
}

$filtered = [];
$statusCounters = ['request'=>0,'pending'=>0,'accepted'=>0,'rejected'=>0];
$unitStats = [];
$uniqueKeys = [];
$grouped = [];
$dailyAccepted = [];

foreach ($normalized as $item) {
  $ts = $item['ts'];
  if(!$ts) continue;
  if($fromTs && $ts < $fromTs) continue;
  if($toTs && $ts > $toTs) continue;
  $status = $item['status'];
  if(!isset($statusCounters[$status])) { $status = 'request'; }
  $statusCounters[$status]++;
  $unit = $item['ward'] !== '' ? $item['ward'] : 'Unassigned';
  if(!isset($unitStats[$unit])) {
    $unitStats[$unit] = ['unit'=>$unit,'total'=>0,'accepted'=>0,'open'=>0,'rejected'=>0];
  }
  $unitStats[$unit]['total']++;
  if($status === 'accepted') {
    $unitStats[$unit]['accepted']++;
    $dayKey = date('Y-m-d', $ts);
    if(!isset($dailyAccepted[$dayKey])) { $dailyAccepted[$dayKey] = 0; }
    $dailyAccepted[$dayKey]++;
  } elseif ($status === 'rejected') {
    $unitStats[$unit]['rejected']++;
  } else {
    $unitStats[$unit]['open']++;
  }
  $key = '';
  $nurseId = (int)($item['nurse_id'] ?? 0);
  if ($nurseId > 0) { $key = 'id:'.$nurseId; }
  elseif (($item['nurse_email'] ?? '') !== '') { $key = 'email:'.strtolower((string)$item['nurse_email']); }
  elseif ($item['nurse'] !== '') { $key = 'name:'.strtolower($item['nurse']); }
  if ($key !== '') { $uniqueKeys[$key] = true; }
  $groupKey = sr_group_key($ts, $group);
  if(!isset($grouped[$groupKey])) { $grouped[$groupKey] = 0; }
  $grouped[$groupKey]++;
  $filtered[] = $item;
}

usort($filtered, function($a, $b){
  $aKey = ($a['date'] ?? '').' '.($a['time'] ?? '');
  $bKey = ($b['date'] ?? '').' '.($b['time'] ?? '');
  return strcmp($bKey, $aKey);
});

ksort($grouped);
$activityList = [];
foreach ($grouped as $label => $value) {
  $activityList[] = ['label'=>$label, 'value'=>$value];
}
$activityList = array_slice($activityList, -12);
$activityMax = 0;
foreach ($activityList as $entry) {
  if ($entry['value'] > $activityMax) { $activityMax = $entry['value']; }
}

$totalRequests = count($filtered);
$acceptedCount = $statusCounters['accepted'];
$openCount = ($statusCounters['request'] ?? 0) + ($statusCounters['pending'] ?? 0);
$rejectedCount = $statusCounters['rejected'];
$uniqueNurses = count($uniqueKeys);
$avgOnDuty = $dailyAccepted ? array_sum($dailyAccepted) / max(1, count($dailyAccepted)) : 0;

// Compute total hours from accepted shifts in the filtered set
$totalHours = 0.0;
foreach ($filtered as $it) {
  if (($it['status'] ?? '') !== 'accepted') continue;
  $start = (string)($it['time'] ?? '');
  $end = (string)($it['end_time'] ?? '');
  if ($start === '' || $end === '') continue;
  // Parse HH:MM 24h format and handle overnight
  list($sh, $sm) = array_pad(explode(':', $start, 2), 2, '0');
  list($eh, $em) = array_pad(explode(':', $end, 2), 2, '0');
  $sMin = ((int)$sh) * 60 + ((int)$sm);
  $eMin = ((int)$eh) * 60 + ((int)$em);
  if ($eMin < $sMin) { $eMin += 24 * 60; }
  $totalHours += max(0, ($eMin - $sMin)) / 60.0;
}

$unitRows = array_values($unitStats);
usort($unitRows, function($a, $b){
  return $b['total'] <=> $a['total'];
});

include __DIR__.'/../../includes/header.php';
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/supervisor/supervisor_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/supervisor/supervisor_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/supervisor/supervisor_schedules.php"><img src="/capstone/assets/img/appointment.png" alt="Schedules" style="width:18px;height:18px;object-fit:contain;"> Schedule</a></li>
          <li><a href="/capstone/templates/supervisor/supervisor_nurses.php"><img src="/capstone/assets/img/nurse.png" alt="Nurses" style="width:18px;height:18px;object-fit:contain;"> List</a></li>
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
      <div class="stat-cards" style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
        <div class="stat-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
          <h4 style="margin:0 0 8px;color:#0f172a;font-size:1rem;font-weight:600;">Nurses</h4>
          <div class="muted-small" style="color:#64748b;font-size:0.85rem;margin-bottom:8px;">This period</div>
          <div class="stat-value" id="statNurses" style="font-size:2rem;font-weight:700;color:#0a5d39;"><?php echo sr_escape((string)$uniqueNurses); ?></div>
        </div>
        <div class="stat-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
          <h4 style="margin:0 0 8px;color:#0f172a;font-size:1rem;font-weight:600;">Shifts</h4>
          <div class="muted-small" style="color:#64748b;font-size:0.85rem;margin-bottom:8px;">This period</div>
          <div class="stat-value" id="statShifts" style="font-size:2rem;font-weight:700;color:#0a5d39;"><?php echo sr_escape((string)$acceptedCount); ?></div>
        </div>
        <div class="stat-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
          <h4 style="margin:0 0 8px;color:#0f172a;font-size:1rem;font-weight:600;">Total Hours</h4>
          <div class="muted-small" style="color:#64748b;font-size:0.85rem;margin-bottom:8px;">This period</div>
          <div class="stat-value" id="statTotalHours" style="font-size:2rem;font-weight:700;color:#0a5d39;"><?php echo sr_escape(number_format((float)$totalHours, 1)); ?></div>
        </div>
      </div>
    </section>

    <section style="margin-bottom:16px;">
      <div class="card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
        <h3 style="margin:0 0 16px;color:#0f172a;font-size:1.1rem;font-weight:600;">Recent Reports</h3>
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="border-bottom:1px solid #e5e7eb;">
              <th style="padding:8px 0;text-align:left;font-weight:600;color:#0f172a;font-size:0.9rem;">Nurse</th>
              <th style="padding:8px 0;text-align:right;font-weight:600;color:#0f172a;font-size:0.9rem;">Shifts</th>
            </tr>
          </thead>
          <tbody id="topNursesList">
            <?php 
            // Get top nurses by accepted shifts
            $nurseStats = [];
            foreach ($filtered as $item) {
              if ($item['status'] === 'accepted') {
                $nurseName = $item['nurse'] ?: 'Unknown';
                $nurseStats[$nurseName] = ($nurseStats[$nurseName] ?? 0) + 1;
              }
            }
            arsort($nurseStats);
            $topNurses = array_slice($nurseStats, 0, 10, true);
            ?>
            <?php if (empty($topNurses)): ?>
              <tr><td colspan="2" class="muted" style="text-align:center;padding:20px;color:#64748b;">No data</td></tr>
            <?php else: ?>
              <?php foreach ($topNurses as $nurseName => $count): ?>
                <tr style="border-bottom:1px solid #f1f5f9;">
                  <td style="padding:8px 0;color:#0f172a;font-size:0.9rem;"><?php echo sr_escape($nurseName); ?></td>
                  <td style="padding:8px 0;text-align:right;color:#64748b;font-size:0.9rem;font-weight:600;"><?php echo sr_escape((string)$count); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

  </div>
</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>

<script>
(function(){
  var currentMonthYear = document.getElementById('currentMonthYear');
  var prevMonthBtn = document.getElementById('prevMonth');
  var nextMonthBtn = document.getElementById('nextMonth');
  var statNurses = document.getElementById('statNurses');
  var statShifts = document.getElementById('statShifts');
  var statTotalHours = document.getElementById('statTotalHours');
  var topNursesList = document.getElementById('topNursesList');

  var scheduleData = <?php echo json_encode($filtered); ?>;
  var currentDate = new Date();

  function escapeHtml(s){ return (s||'').replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
  function getDatePart(ts){ return (ts||'').slice(0,10); }
  function getCurrentMonthYear(){
    var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                     'July', 'August', 'September', 'October', 'November', 'December'];
    return monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
  }
  
  function updateMonthDisplay(){
    if(currentMonthYear) {
      currentMonthYear.textContent = getCurrentMonthYear();
    }
  }
  
  function navigateMonth(direction){
    if(direction === 'prev'){
      currentDate.setMonth(currentDate.getMonth() - 1);
    } else if(direction === 'next'){
      currentDate.setMonth(currentDate.getMonth() + 1);
    }
    updateMonthDisplay();
    renderStats();
  }
  
  function renderStats(){
    // Filter data by selected month
    var selectedMonth = currentDate.getFullYear() + '-' + String(currentDate.getMonth() + 1).padStart(2, '0');
    var monthData = scheduleData.filter(function(item){ 
      var recordMonth = (item.date||'').slice(0,7); // YYYY-MM
      return recordMonth === selectedMonth;
    });
    
    // Calculate stats
    var uniqueNurses = new Set();
    var acceptedCount = 0;
    var totalHours = 0;
    var nurseStats = {};
    
    monthData.forEach(function(item){
      if(item.nurse) uniqueNurses.add(item.nurse);
      if(item.status === 'accepted') {
        acceptedCount++;
        nurseStats[item.nurse] = (nurseStats[item.nurse] || 0) + 1;
        var s = item.time || '';
        var e = item.end_time || '';
        if(s && e){
          var sp = s.split(':'), ep = e.split(':');
          var sMin = parseInt(sp[0]||'0',10)*60 + parseInt(sp[1]||'0',10);
          var eMin = parseInt(ep[0]||'0',10)*60 + parseInt(ep[1]||'0',10);
          if(eMin < sMin) eMin += 24*60; // overnight
          totalHours += Math.max(0, (eMin - sMin))/60;
        }
      }
    });
    
    // Update stat cards
    if(statNurses) statNurses.textContent = uniqueNurses.size;
    if(statShifts) statShifts.textContent = acceptedCount;
    if(statTotalHours) statTotalHours.textContent = totalHours.toFixed(1);
    
    // Update top nurses list
    if(topNursesList) {
      topNursesList.innerHTML = '';
      var topNurses = Object.keys(nurseStats)
        .map(function(name) { return { name: name, count: nurseStats[name] }; })
        .sort(function(a, b) { return b.count - a.count; })
        .slice(0, 10);
      
      if(topNurses.length === 0) {
        topNursesList.innerHTML = '<tr><td colspan="2" class="muted" style="text-align:center;padding:20px;color:#64748b;">No data</td></tr>';
      } else {
        topNurses.forEach(function(nurse) {
          var tr = document.createElement('tr');
          tr.style.borderBottom = '1px solid #f1f5f9';
          tr.innerHTML = '<td style="padding:8px 0;color:#0f172a;font-size:0.9rem;">' + escapeHtml(nurse.name) + '</td><td style="padding:8px 0;text-align:right;color:#64748b;font-size:0.9rem;font-weight:600;">' + escapeHtml(String(nurse.count)) + '</td>';
          topNursesList.appendChild(tr);
        });
      }
    }
  }
  
  // Add event listeners for navigation
  if(prevMonthBtn) prevMonthBtn.addEventListener('click', function(){ navigateMonth('prev'); });
  if(nextMonthBtn) nextMonthBtn.addEventListener('click', function(){ navigateMonth('next'); });
  
  // Initialize
  updateMonthDisplay();
  renderStats();
})();
</script>


