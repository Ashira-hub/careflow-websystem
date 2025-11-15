<?php
$page='Nurse Reports';
$storeFile = __DIR__.'/../../data/nurse_prescriptions.json';
function nr_escape($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function nr_load($file){
  if(!file_exists($file)) return [];
  $raw = file_get_contents($file);
  if($raw === false || $raw === '') return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}
function nr_parse_ts($item){
  $ts = $item['updated_at'] ?? $item['time'] ?? '';
  $time = strtotime((string)$ts);
  return $time ?: null;
}
function nr_group_key($ts, $group){
  if(!$ts) return 'Unknown';
  if($group === 'month') return date('Y-m', $ts);
  if($group === 'week') return date('o-\WW', $ts);
  return date('Y-m-d', $ts);
}

$inputMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$inputYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$firstTs = mktime(0, 0, 0, $inputMonth, 1, $inputYear);
$month = (int)date('n', $firstTs);
$year = (int)date('Y', $firstTs);
$lastDay = (int)date('t', $firstTs);
$fromTs = $firstTs;
$toTs = mktime(23, 59, 59, $month, $lastDay, $year);
$periodLabel = date('F Y', $firstTs);
$group = 'day';

$prevTs = mktime(0, 0, 0, $month - 1, 1, $year);
$nextTs = mktime(0, 0, 0, $month + 1, 1, $year);
$prevMonth = (int)date('n', $prevTs);
$prevYear = (int)date('Y', $prevTs);
$nextMonth = (int)date('n', $nextTs);
$nextYear = (int)date('Y', $nextTs);

$entries = array_filter(nr_load($storeFile), function($item) use ($fromTs, $toTs){
  $ts = nr_parse_ts($item);
  if(!$ts) return false;
  if($fromTs && $ts < $fromTs) return false;
  if($toTs && $ts > $toTs) return false;
  return true;
});

$statusCounts = [
  'accepted' => 0,
  'acknowledged' => 0,
  'done' => 0,
  'pending' => 0
];
$patients = [];
$grouped = [];
$latestTs = null;

foreach($entries as $entry){
  $status = strtolower((string)($entry['status'] ?? ''));
  if(isset($statusCounts[$status])){
    $statusCounts[$status]++;
  } else {
    $statusCounts['pending']++;
  }
  $patientKey = trim((string)($entry['patient'] ?? 'Unknown patient')) ?: 'Unknown patient';
  if(!isset($patients[$patientKey])){ $patients[$patientKey] = 0; }
  $patients[$patientKey]++;
  $ts = nr_parse_ts($entry);
  $bucket = nr_group_key($ts, $group);
  if(!isset($grouped[$bucket])){ $grouped[$bucket] = 0; }
  $grouped[$bucket]++;
  if($ts && (!$latestTs || $ts > $latestTs)){ $latestTs = $ts; }
}

$totalPrescriptions = count($entries);
$pendingTotal = $totalPrescriptions - ($statusCounts['accepted'] + $statusCounts['acknowledged'] + $statusCounts['done']);
if($pendingTotal > 0){ $statusCounts['pending'] = $pendingTotal; }
$topPatients = $patients;
arsort($topPatients);
$topPatients = array_slice($topPatients, 0, 5, true);

ksort($grouped);
$activityKeys = array_keys($grouped);
$activityCounts = array_values($grouped);
$activityMax = $activityCounts ? max($activityCounts) : 0;

include __DIR__.'/../../includes/header.php';
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/nurse/nurse_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/nurse/nurse_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/nurse/nurse_schedule.php"><img src="/capstone/assets/img/appointment.png" alt="Schedule" style="width:18px;height:18px;object-fit:contain;"> Schedule</a></li>
          <li><a href="/capstone/templates/nurse/nurse_prescription.php"><img src="/capstone/assets/img/prescription.png" alt="Prescription" style="width:18px;height:18px;object-fit:contain;"> Prescription</a></li>
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
          <a class="btn btn-outline report-nav-btn" href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" aria-label="Previous month">
            <span class="report-nav-icon">&#10094;</span>
          </a>
          <div class="report-nav-label-group">
            <div class="report-nav-month"><?php echo nr_escape(date('F', $firstTs)); ?></div>
            <div class="report-nav-year">
              <?php echo nr_escape(date('Y', $firstTs)); ?>
              <a class="btn btn-outline report-nav-btn" href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" aria-label="Next month">
                <span class="report-nav-icon">&#10095;</span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section style="margin-bottom:16px;">
      <div class="stat-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;align-items:stretch;">
        <div class="stat-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:20px 24px;box-shadow:0 6px 18px rgba(0,0,0,0.06);">
          <h4 style="margin:0 0 6px;font-size:1.05rem;">Requests</h4>
          <div class="muted-small" style="margin-bottom:8px;">Filtered range</div>
          <div class="stat-value" style="font-size:1.6rem;font-weight:800;line-height:1;">&nbsp;<?php echo nr_escape($totalPrescriptions); ?></div>
        </div>
        <div class="stat-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:20px 24px;box-shadow:0 6px 18px rgba(0,0,0,0.06);">
          <h4 style="margin:0 0 6px;font-size:1.05rem;">Total Hours</h4>
          <div class="muted-small" style="margin-bottom:8px;">Awaiting nurse action</div>
          <div class="stat-value" style="font-size:1.6rem;font-weight:800;line-height:1;">&nbsp;<?php echo nr_escape($statusCounts['accepted']); ?></div>
        </div>
        <div class="stat-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:20px 24px;box-shadow:0 6px 18px rgba(0,0,0,0.06);">
          <h4 style="margin:0 0 6px;font-size:1.05rem;">Active Shifts</h4>
          <div class="muted-small" style="margin-bottom:8px;">In progress</div>
          <div class="stat-value" style="font-size:1.6rem;font-weight:800;line-height:1;">&nbsp;<?php echo nr_escape($statusCounts['acknowledged']); ?></div>
        </div>
      </div>
    </section>

    <section class="two-col" style="display:block;">
      <div class="card" style="width:100%;">
        <h3 style="margin-top:0;">Top Patients</h3>
        <?php if ($topPatients): ?>
          <table>
            <thead><tr><th>Patient</th><th>Prescriptions</th></tr></thead>
            <tbody>
              <?php foreach ($topPatients as $patient => $count): ?>
                <tr><td><?php echo nr_escape($patient); ?></td><td><?php echo nr_escape($count); ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="muted">No prescription activity for the selected dates.</div>
        <?php endif; ?>
      </div>
    </section>

  </div>
</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>

