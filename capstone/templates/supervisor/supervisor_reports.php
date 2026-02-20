<?php
$page = 'Analytics Dashboard';
require_once __DIR__ . '/../../config/db.php';

function sr_escape($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

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

$requestFile = __DIR__ . '/../../data/nurse_shift_requests.json';
$requests = [];
if (file_exists($requestFile)) {
  $raw = file_get_contents($requestFile);
  $decoded = json_decode($raw, true);
  if (is_array($decoded)) {
    $requests = $decoded;
  }
}

function sr_group_key($ts, $group)
{
  if (!$ts) return 'Unknown';
  if ($group === 'month') return date('Y-m', $ts);
  if ($group === 'week') return date('o-\WW', $ts);
  return date('Y-m-d', $ts);
}

$selectedYear  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
if ($selectedYear < 1970 || $selectedYear > 2100) {
  $selectedYear = (int)date('Y');
}
if ($selectedMonth < 1 || $selectedMonth > 12) {
  $selectedMonth = (int)date('n');
}

$monthStart = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
$monthEnd = date('Y-m-t', strtotime($monthStart));
$fromTs = strtotime($monthStart . ' 00:00:00');
$toTs = strtotime($monthEnd . ' 23:59:59');

$prevTs = mktime(0, 0, 0, $selectedMonth - 1, 1, $selectedYear);
$nextTs = mktime(0, 0, 0, $selectedMonth + 1, 1, $selectedYear);
$prevMonth = (int)date('n', $prevTs);
$prevYear = (int)date('Y', $prevTs);
$nextMonth = (int)date('n', $nextTs);
$nextYear = (int)date('Y', $nextTs);

$prevMonthStart = sprintf('%04d-%02d-01', $prevYear, $prevMonth);
$prevMonthEnd = date('Y-m-t', strtotime($prevMonthStart));
$prevFromTs = strtotime($prevMonthStart . ' 00:00:00');
$prevToTs = strtotime($prevMonthEnd . ' 23:59:59');

$statusFilter = isset($_GET['status']) ? strtolower((string)$_GET['status']) : 'all';
$group = 'day';

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
  $combined = trim($item['date'] . ' ' . $item['time']);
  $ts = $combined !== '' ? strtotime($combined) : ($item['date'] !== '' ? strtotime($item['date']) : null);
  $item['ts'] = $ts ?: null;
  $normalized[] = $item;
}

$filtered = [];
$statusCounters = ['request' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0];
$unitStats = [];
$uniqueKeys = [];
$grouped = [];
$dailyAccepted = [];

foreach ($normalized as $item) {
  $ts = $item['ts'];
  if (!$ts) continue;
  if ($fromTs && $ts < $fromTs) continue;
  if ($toTs && $ts > $toTs) continue;
  $status = $item['status'];
  if (!isset($statusCounters[$status])) {
    $status = 'request';
  }
  $statusCounters[$status]++;
  $unit = $item['ward'] !== '' ? $item['ward'] : 'Unassigned';
  if (!isset($unitStats[$unit])) {
    $unitStats[$unit] = ['unit' => $unit, 'total' => 0, 'accepted' => 0, 'open' => 0, 'rejected' => 0];
  }
  $unitStats[$unit]['total']++;
  if ($status === 'accepted') {
    $unitStats[$unit]['accepted']++;
    $dayKey = date('Y-m-d', $ts);
    if (!isset($dailyAccepted[$dayKey])) {
      $dailyAccepted[$dayKey] = 0;
    }
    $dailyAccepted[$dayKey]++;
  } elseif ($status === 'rejected') {
    $unitStats[$unit]['rejected']++;
  } else {
    $unitStats[$unit]['open']++;
  }
  $key = '';
  $nurseId = (int)($item['nurse_id'] ?? 0);
  if ($nurseId > 0) {
    $key = 'id:' . $nurseId;
  } elseif (($item['nurse_email'] ?? '') !== '') {
    $key = 'email:' . strtolower((string)$item['nurse_email']);
  } elseif ($item['nurse'] !== '') {
    $key = 'name:' . strtolower($item['nurse']);
  }
  if ($key !== '') {
    $uniqueKeys[$key] = true;
  }
  $groupKey = sr_group_key($ts, $group);
  if (!isset($grouped[$groupKey])) {
    $grouped[$groupKey] = 0;
  }
  $grouped[$groupKey]++;
  $filtered[] = $item;
}

usort($filtered, function ($a, $b) {
  $aKey = ($a['date'] ?? '') . ' ' . ($a['time'] ?? '');
  $bKey = ($b['date'] ?? '') . ' ' . ($b['time'] ?? '');
  return strcmp($bKey, $aKey);
});

ksort($grouped);
$activityList = [];
foreach ($grouped as $label => $value) {
  $activityList[] = ['label' => $label, 'value' => $value];
}
$activityList = array_slice($activityList, -12);
$activityMax = 0;
foreach ($activityList as $entry) {
  if ($entry['value'] > $activityMax) {
    $activityMax = $entry['value'];
  }
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
  if ($eMin < $sMin) {
    $eMin += 24 * 60;
  }
  $totalHours += max(0, ($eMin - $sMin)) / 60.0;
}

$prevStatusCounters = ['request' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0];
$prevUniqueKeys = [];
$prevDailyAccepted = [];
$prevTotalHours = 0.0;
foreach ($normalized as $item) {
  $ts = $item['ts'];
  if (!$ts) continue;
  if ($prevFromTs && $ts < $prevFromTs) continue;
  if ($prevToTs && $ts > $prevToTs) continue;
  $status = $item['status'];
  if (!isset($prevStatusCounters[$status])) {
    $status = 'request';
  }
  $prevStatusCounters[$status]++;
  $key = '';
  $nurseId = (int)($item['nurse_id'] ?? 0);
  if ($nurseId > 0) {
    $key = 'id:' . $nurseId;
  } elseif (($item['nurse_email'] ?? '') !== '') {
    $key = 'email:' . strtolower((string)$item['nurse_email']);
  } elseif (($item['nurse'] ?? '') !== '') {
    $key = 'name:' . strtolower((string)$item['nurse']);
  }
  if ($key !== '') {
    $prevUniqueKeys[$key] = true;
  }
  if ($status === 'accepted') {
    $dayKey = date('Y-m-d', $ts);
    if (!isset($prevDailyAccepted[$dayKey])) {
      $prevDailyAccepted[$dayKey] = 0;
    }
    $prevDailyAccepted[$dayKey]++;
    $start = (string)($item['time'] ?? '');
    $end = (string)($item['end_time'] ?? '');
    if ($start !== '' && $end !== '') {
      list($sh, $sm) = array_pad(explode(':', $start, 2), 2, '0');
      list($eh, $em) = array_pad(explode(':', $end, 2), 2, '0');
      $sMin = ((int)$sh) * 60 + ((int)$sm);
      $eMin = ((int)$eh) * 60 + ((int)$em);
      if ($eMin < $sMin) {
        $eMin += 24 * 60;
      }
      $prevTotalHours += max(0, ($eMin - $sMin)) / 60.0;
    }
  }
}

$prevTotalRequests = (int)array_sum($prevStatusCounters);
$prevAcceptedCount = (int)($prevStatusCounters['accepted'] ?? 0);
$prevUniqueNurses = (int)count($prevUniqueKeys);

$daysInMonth = (int)date('t', strtotime($monthStart));
$dailyAcceptedSeries = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
  $key = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $d);
  $dailyAcceptedSeries[$key] = isset($dailyAccepted[$key]) ? (int)$dailyAccepted[$key] : 0;
}

$maxDaily = 0;
foreach ($dailyAcceptedSeries as $k => $v) {
  $maxDaily = max($maxDaily, (int)$v);
}
if ($maxDaily <= 0) $maxDaily = 1;

function sr_delta_badge($delta)
{
  $d = (int)$delta;
  $isPos = $d >= 0;
  $color = $isPos ? '#16a34a' : '#dc2626';
  $sign = $isPos ? '+' : '';
  return '<span style="display:inline-flex;align-items:center;gap:6px;color:' . $color . ';font-weight:800;font-size:0.9rem;">' . ($isPos ? '↗' : '↘') . ' ' . $sign . $d . '</span>';
}

$unitRows = array_values($unitStats);
usort($unitRows, function ($a, $b) {
  return $b['total'] <=> $a['total'];
});

include __DIR__ . '/../../includes/header.php';
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

  <div class="content" style="width:100%;max-width:none;margin:0;">
    <?php
    $deltaNurses = (int)$uniqueNurses - (int)$prevUniqueNurses;
    $deltaRequests = (int)$totalRequests - (int)$prevTotalRequests;
    $deltaAccepted = (int)$acceptedCount - (int)$prevAcceptedCount;
    $deltaHours = (float)$totalHours - (float)$prevTotalHours;
    ?>

    <style>
      .ad-page {
        background: #f8fafc;
      }

      .ad-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
      }

      .ad-title {
        margin: 0;
        font-size: 28px;
        font-weight: 800;
        color: #0f172a;
      }

      .ad-subtitle {
        margin-top: 6px;
        color: #64748b;
        font-weight: 500;
      }

      .ad-month {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 12px 14px;
        box-shadow: 0 2px 10px rgba(2, 6, 23, 0.04);
      }

      .ad-month a {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        text-decoration: none;
        color: #0f172a;
        border: 1px solid #e5e7eb;
        background: #fff;
      }

      .ad-month a:hover {
        background: #f8fafc;
      }

      .ad-month-label {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 800;
        color: #0f172a;
        min-width: 160px;
        justify-content: center;
      }

      .ad-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 18px;
        margin-bottom: 18px;
      }

      .ad-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 18px;
        box-shadow: 0 2px 10px rgba(2, 6, 23, 0.04);
        position: relative;
      }

      .ad-card-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
      }

      .ad-icon {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .ad-label {
        margin-top: 14px;
        color: #334155;
        font-weight: 800;
      }

      .ad-value {
        margin-top: 6px;
        font-size: 38px;
        font-weight: 900;
        color: #0f172a;
        line-height: 1;
      }

      .ad-meta {
        margin-top: 6px;
        color: #64748b;
        font-size: 0.9rem;
      }

      .ad-chart {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 18px;
        box-shadow: 0 2px 10px rgba(2, 6, 23, 0.04);
      }

      .ad-chart-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
      }

      .ad-chart-title {
        margin: 0;
        font-weight: 900;
        color: #0f172a;
        font-size: 1.05rem;
      }

      .ad-chart-sub {
        margin-top: 4px;
        color: #64748b;
        font-weight: 600;
        font-size: 0.9rem;
      }

      .ad-bars {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        height: 190px;
        padding: 12px 8px;
        border-radius: 14px;
        background: #ffffff;
      }

      .ad-bar {
        flex: 1;
        min-width: 6px;
        border-radius: 8px 8px 0 0;
        background: #e9d5ff;
      }

      @media (max-width: 900px) {
        .ad-header {
          flex-direction: column;
          align-items: stretch;
        }

        .ad-grid {
          grid-template-columns: 1fr;
        }

        .ad-month-label {
          min-width: 120px;
        }
      }
    </style>

    <div class="ad-page">
      <div class="ad-header">
        <div>
          <h2 class="ad-title">Analytics Dashboard</h2>
          <div class="ad-subtitle">Track your staffing performance</div>
        </div>
        <div class="ad-month" aria-label="Month selector">
          <a href="?month=<?php echo (int)$prevMonth; ?>&year=<?php echo (int)$prevYear; ?>" aria-label="Previous month">&#10094;</a>
          <div class="ad-month-label">
            <span style="width:32px;height:32px;border-radius:10px;background:#f3e8ff;display:flex;align-items:center;justify-content:center;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
            </span>
            <span><?php echo sr_escape(date('F Y', strtotime($monthStart))); ?></span>
          </div>
          <a href="?month=<?php echo (int)$nextMonth; ?>&year=<?php echo (int)$nextYear; ?>" aria-label="Next month">&#10095;</a>
        </div>
      </div>

      <div class="ad-grid">
        <div class="ad-card">
          <div class="ad-card-top">
            <div class="ad-icon" style="background:#dbeafe;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
            </div>
            <div><?php echo sr_delta_badge($deltaNurses); ?></div>
          </div>
          <div class="ad-label">Unique Nurses</div>
          <div class="ad-value"><?php echo (int)$uniqueNurses; ?></div>
          <div class="ad-meta">This month</div>
        </div>

        <div class="ad-card">
          <div class="ad-card-top">
            <div class="ad-icon" style="background:#f3e8ff;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
            </div>
            <div><?php echo sr_delta_badge($deltaRequests); ?></div>
          </div>
          <div class="ad-label">Total Requests</div>
          <div class="ad-value"><?php echo (int)$totalRequests; ?></div>
          <div class="ad-meta">This month</div>
        </div>

        <div class="ad-card">
          <div class="ad-card-top">
            <div class="ad-icon" style="background:#dcfce7;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 11l3 3L22 4"></path>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
              </svg>
            </div>
            <div><?php echo sr_delta_badge($deltaAccepted); ?></div>
          </div>
          <div class="ad-label">Accepted Shifts</div>
          <div class="ad-value"><?php echo (int)$acceptedCount; ?></div>
          <div class="ad-meta">This month</div>
        </div>

        <div class="ad-card">
          <div class="ad-card-top">
            <div class="ad-icon" style="background:#ffedd5;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 3v18h18"></path>
                <path d="M7 13v5"></path>
                <path d="M12 9v9"></path>
                <path d="M17 6v12"></path>
              </svg>
            </div>
            <div><?php echo sr_delta_badge((int)round($deltaHours)); ?></div>
          </div>
          <div class="ad-label">Total Hours</div>
          <div class="ad-value"><?php echo sr_escape(number_format((float)$totalHours, 1)); ?></div>
          <div class="ad-meta">Accepted shifts</div>
        </div>
      </div>

      <div class="ad-chart">
        <div class="ad-chart-head">
          <div>
            <h3 class="ad-chart-title">Daily Accepted Shifts</h3>
            <div class="ad-chart-sub">Total: <?php echo (int)$acceptedCount; ?> accepted</div>
          </div>
          <div style="width:36px;height:36px;border-radius:12px;background:#f3e8ff;display:flex;align-items:center;justify-content:center;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
          </div>
        </div>
        <?php
        $chartLabels = [];
        $chartDaily = [];
        $chartCum = [];
        $running = 0;
        foreach ($dailyAcceptedSeries as $day => $cnt) {
          $chartLabels[] = substr((string)$day, -2);
          $val = (int)$cnt;
          $chartDaily[] = $val;
          $running += $val;
          $chartCum[] = $running;
        }
        ?>
        <div class="ad-bars" style="height:260px;">
          <canvas id="supervisorDailyAcceptedChart" aria-label="Daily accepted shifts chart" role="img"></canvas>
        </div>
        <script>
          (function() {
            var el = document.getElementById('supervisorDailyAcceptedChart');
            if (!el || !window.Chart) return;
            var labels = <?php echo json_encode($chartLabels); ?>;
            var daily = <?php echo json_encode($chartDaily); ?>;
            var cum = <?php echo json_encode($chartCum); ?>;
            new Chart(el, {
              data: {
                labels: labels,
                datasets: [{
                  type: 'bar',
                  label: 'Units Sold',
                  data: daily,
                  backgroundColor: '#2563eb',
                  borderRadius: 6,
                  yAxisID: 'y'
                }, {
                  type: 'line',
                  label: 'Total Transaction',
                  data: cum,
                  borderColor: '#f97316',
                  backgroundColor: 'rgba(249, 115, 22, 0.15)',
                  tension: 0.35,
                  pointRadius: 3,
                  pointHoverRadius: 4,
                  yAxisID: 'y1'
                }]
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                  legend: {
                    position: 'bottom'
                  }
                },
                scales: {
                  y: {
                    beginAtZero: true,
                    title: {
                      display: true,
                      text: 'Units Sold'
                    }
                  },
                  y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: {
                      drawOnChartArea: false
                    },
                    title: {
                      display: true,
                      text: 'Total Transactions'
                    }
                  }
                }
              }
            });
          })();
        </script>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>