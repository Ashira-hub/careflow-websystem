<?php
$page = 'Analytics Dashboard';
$storeFile = __DIR__ . '/../../data/nurse_prescriptions.json';
function nr_escape($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function nr_load($file)
{
  if (!file_exists($file)) return [];
  $raw = file_get_contents($file);
  if ($raw === false || $raw === '') return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}
function nr_parse_ts($item)
{
  $ts = $item['updated_at'] ?? $item['time'] ?? '';
  $time = strtotime((string)$ts);
  return $time ?: null;
}
function nr_group_key($ts, $group)
{
  if (!$ts) return 'Unknown';
  if ($group === 'month') return date('Y-m', $ts);
  if ($group === 'week') return date('o-\WW', $ts);
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

$entries = array_filter(nr_load($storeFile), function ($item) use ($fromTs, $toTs) {
  $ts = nr_parse_ts($item);
  if (!$ts) return false;
  if ($fromTs && $ts < $fromTs) return false;
  if ($toTs && $ts > $toTs) return false;
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

foreach ($entries as $entry) {
  $status = strtolower((string)($entry['status'] ?? ''));
  if (isset($statusCounts[$status])) {
    $statusCounts[$status]++;
  } else {
    $statusCounts['pending']++;
  }
  $patientKey = trim((string)($entry['patient'] ?? 'Unknown patient')) ?: 'Unknown patient';
  if (!isset($patients[$patientKey])) {
    $patients[$patientKey] = 0;
  }
  $patients[$patientKey]++;
  $ts = nr_parse_ts($entry);
  $bucket = nr_group_key($ts, $group);
  if (!isset($grouped[$bucket])) {
    $grouped[$bucket] = 0;
  }
  $grouped[$bucket]++;
  if ($ts && (!$latestTs || $ts > $latestTs)) {
    $latestTs = $ts;
  }
}

$totalPrescriptions = count($entries);
$pendingTotal = $totalPrescriptions - ($statusCounts['accepted'] + $statusCounts['acknowledged'] + $statusCounts['done']);
if ($pendingTotal > 0) {
  $statusCounts['pending'] = $pendingTotal;
}
$topPatients = $patients;
arsort($topPatients);
$topPatients = array_slice($topPatients, 0, 5, true);

ksort($grouped);
$activityKeys = array_keys($grouped);
$activityCounts = array_values($grouped);
$activityMax = $activityCounts ? max($activityCounts) : 0;

$dailyRequests = [];
for ($d = 1; $d <= $lastDay; $d++) {
  $key = sprintf('%04d-%02d-%02d', $year, $month, $d);
  $dailyRequests[$key] = 0;
}
foreach ($entries as $entry) {
  $ts = nr_parse_ts($entry);
  if (!$ts) continue;
  $dayKey = date('Y-m-d', $ts);
  if (array_key_exists($dayKey, $dailyRequests)) {
    $dailyRequests[$dayKey] = (int)$dailyRequests[$dayKey] + 1;
  }
}

// Schedule requests per day (nurse shift requests)
$scheduleFile = __DIR__ . '/../../data/nurse_shift_requests.json';
$scheduleItems = [];
if (file_exists($scheduleFile)) {
  $raw = file_get_contents($scheduleFile);
  $decoded = json_decode($raw, true);
  if (is_array($decoded)) {
    $scheduleItems = $decoded;
  }
}
$dailyScheduleRequests = [];
for ($d = 1; $d <= $lastDay; $d++) {
  $key = sprintf('%04d-%02d-%02d', $year, $month, $d);
  $dailyScheduleRequests[$key] = 0;
}
foreach ($scheduleItems as $item) {
  if (!is_array($item)) continue;
  $dateStr = trim((string)($item['date'] ?? ''));
  $timeStr = trim((string)($item['time'] ?? ''));
  $combined = trim($dateStr . ' ' . $timeStr);
  $ts = $combined !== '' ? strtotime($combined) : ($dateStr !== '' ? strtotime($dateStr) : null);
  if (!$ts) continue;
  if ($fromTs && $ts < $fromTs) continue;
  if ($toTs && $ts > $toTs) continue;
  $dayKey = date('Y-m-d', $ts);
  if (array_key_exists($dayKey, $dailyScheduleRequests)) {
    $dailyScheduleRequests[$dayKey] = (int)$dailyScheduleRequests[$dayKey] + 1;
  }
}

$maxDaily = 0;
foreach ($dailyRequests as $k => $v) {
  $maxDaily = max($maxDaily, (int)$v);
}
if ($maxDaily <= 0) $maxDaily = 1;

$prevFirstTs = mktime(0, 0, 0, $prevMonth, 1, $prevYear);
$prevLastDay = (int)date('t', $prevFirstTs);
$prevFromTs = $prevFirstTs;
$prevToTs = mktime(23, 59, 59, $prevMonth, $prevLastDay, $prevYear);

$prevEntries = array_filter(nr_load($storeFile), function ($item) use ($prevFromTs, $prevToTs) {
  $ts = nr_parse_ts($item);
  if (!$ts) return false;
  if ($prevFromTs && $ts < $prevFromTs) return false;
  if ($prevToTs && $ts > $prevToTs) return false;
  return true;
});

$prevStatusCounts = [
  'accepted' => 0,
  'acknowledged' => 0,
  'done' => 0,
  'pending' => 0
];
foreach ($prevEntries as $entry) {
  $status = strtolower((string)($entry['status'] ?? ''));
  if (isset($prevStatusCounts[$status])) {
    $prevStatusCounts[$status]++;
  } else {
    $prevStatusCounts['pending']++;
  }
}
$prevTotalPrescriptions = count($prevEntries);
$prevPendingTotal = $prevTotalPrescriptions - ($prevStatusCounts['accepted'] + $prevStatusCounts['acknowledged'] + $prevStatusCounts['done']);
if ($prevPendingTotal > 0) {
  $prevStatusCounts['pending'] = $prevPendingTotal;
}

function nr_delta_badge($delta)
{
  $d = (int)$delta;
  $isPos = $d >= 0;
  $color = $isPos ? '#16a34a' : '#dc2626';
  $sign = $isPos ? '+' : '';
  return '<span style="display:inline-flex;align-items:center;gap:6px;color:' . $color . ';font-weight:800;font-size:0.9rem;">' . ($isPos ? '↗' : '↘') . ' ' . $sign . $d . '</span>';
}

include __DIR__ . '/../../includes/header.php';
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

  <div class="content" style="width:100%;max-width:none;margin:0;">
    <?php
    $deltaRequests = (int)$totalPrescriptions - (int)$prevTotalPrescriptions;
    $deltaAccepted = (int)($statusCounts['accepted'] ?? 0) - (int)($prevStatusCounts['accepted'] ?? 0);
    $deltaAcknowledged = (int)($statusCounts['acknowledged'] ?? 0) - (int)($prevStatusCounts['acknowledged'] ?? 0);
    $deltaDone = (int)($statusCounts['done'] ?? 0) - (int)($prevStatusCounts['done'] ?? 0);
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
          <div class="ad-subtitle">Track your nursing performance</div>
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
            <span><?php echo nr_escape(date('F Y', $firstTs)); ?></span>
          </div>
          <a href="?month=<?php echo (int)$nextMonth; ?>&year=<?php echo (int)$nextYear; ?>" aria-label="Next month">&#10095;</a>
        </div>
      </div>

      <div class="ad-grid">
        <div class="ad-card">
          <div class="ad-card-top">
            <div class="ad-icon" style="background:#dbeafe;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
            </div>
            <div><?php echo nr_delta_badge($deltaRequests); ?></div>
          </div>
          <div class="ad-label">Requests</div>
          <div class="ad-value"><?php echo (int)$totalPrescriptions; ?></div>
          <div class="ad-meta">Pending: <?php echo (int)($statusCounts['pending'] ?? 0); ?></div>
        </div>

        <div class="ad-card">
          <div class="ad-card-top">
            <div class="ad-icon" style="background:#f3e8ff;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
              </svg>
            </div>
            <div><?php echo nr_delta_badge($deltaAccepted); ?></div>
          </div>
          <div class="ad-label">Accepted</div>
          <div class="ad-value"><?php echo (int)($statusCounts['accepted'] ?? 0); ?></div>
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
            <div><?php echo nr_delta_badge($deltaAcknowledged); ?></div>
          </div>
          <div class="ad-label">Acknowledged</div>
          <div class="ad-value"><?php echo (int)($statusCounts['acknowledged'] ?? 0); ?></div>
          <div class="ad-meta">In progress</div>
        </div>

        <div class="ad-card">
          <div class="ad-card-top">
            <div class="ad-icon" style="background:#ffedd5;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
              </svg>
            </div>
            <div><?php echo nr_delta_badge($deltaDone); ?></div>
          </div>
          <div class="ad-label">Done</div>
          <div class="ad-value"><?php echo (int)($statusCounts['done'] ?? 0); ?></div>
          <div class="ad-meta">Completed</div>
        </div>
      </div>

      <div class="ad-chart">
        <div class="ad-chart-head">
          <div>
            <h3 class="ad-chart-title">Daily Requests</h3>
            <div class="ad-chart-sub">Total: <?php echo (int)$totalPrescriptions; ?> requests</div>
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
        foreach ($dailyScheduleRequests as $day => $cnt) {
          $chartLabels[] = substr((string)$day, -2);
          $val = (int)$cnt;
          $chartDaily[] = $val;
          $running += $val;
          $chartCum[] = $running;
        }
        ?>
        <div class="ad-bars" style="height:260px;">
          <canvas id="nurseDailyRequestsChart" aria-label="Daily requests chart" role="img"></canvas>
        </div>
        <script>
          (function() {
            var el = document.getElementById('nurseDailyRequestsChart');
            if (!el || !window.Chart) return;
            var labels = <?php echo json_encode($chartLabels); ?>;
            var daily = <?php echo json_encode($chartDaily); ?>;
            var cum = <?php echo json_encode($chartCum); ?>;
            new Chart(el, {
              data: {
                labels: labels,
                datasets: [{
                  type: 'bar',
                  label: 'Request Schedule',
                  data: daily,
                  backgroundColor: '#2563eb',
                  borderRadius: 6,
                  yAxisID: 'y'
                }, {
                  type: 'line',
                  label: 'Total Request Schedule',
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
                    min: 0,
                    max: 50,
                    ticks: {
                      stepSize: 1,
                      callback: function(value) {
                        var allowed = {
                          0: true,
                          1: true,
                          10: true,
                          20: true,
                          30: true,
                          50: true
                        };
                        return allowed[value] ? value : '';
                      }
                    },
                    title: {
                      display: true,
                      text: 'Request Schedule'
                    }
                  },
                  y1: {
                    beginAtZero: true,
                    min: 0,
                    max: 50,
                    position: 'right',
                    grid: {
                      drawOnChartArea: false
                    },
                    ticks: {
                      stepSize: 1,
                      callback: function(value) {
                        var allowed = {
                          0: true,
                          1: true,
                          10: true,
                          20: true,
                          30: true,
                          50: true
                        };
                        return allowed[value] ? value : '';
                      }
                    },
                    title: {
                      display: true,
                      text: 'Total Request Schedule'
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