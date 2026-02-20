<?php
$page = 'Analytics Dashboard';
require_once __DIR__ . '/../../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Determine selected month/year from query (default: current month)
$selectedYear  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
if ($selectedYear < 1970 || $selectedYear > 2100) {
  $selectedYear = (int)date('Y');
}
if ($selectedMonth < 1 || $selectedMonth > 12) {
  $selectedMonth = (int)date('n');
}
$selectedMonthStart = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);

$monthStart = $selectedMonthStart;
$monthEnd = date('Y-m-t', strtotime($monthStart));
$fromDate = $monthStart;
$toDate = $monthEnd;

$prevTs = mktime(0, 0, 0, $selectedMonth - 1, 1, $selectedYear);
$nextTs = mktime(0, 0, 0, $selectedMonth + 1, 1, $selectedYear);
$prevMonth = (int)date('n', $prevTs);
$prevYear = (int)date('Y', $prevTs);
$nextMonth = (int)date('n', $nextTs);
$nextYear = (int)date('Y', $nextTs);

$prevMonthStart = sprintf('%04d-%02d-01', $prevYear, $prevMonth);
$prevMonthEnd = date('Y-m-t', strtotime($prevMonthStart));
$prevFromDate = $prevMonthStart;
$prevToDate = $prevMonthEnd;

$testsThisMonth = 0;
$patientsThisMonth = 0;
$testTypesThisMonth = 0;
$avgTestsPerDay = 0;
$recentReports = [];
$dailyTests = [];
$prevTestsThisMonth = 0;
$prevPatientsThisMonth = 0;
$prevTestTypesThisMonth = 0;
$prevAvgTestsPerDay = 0;

function lr_escape($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
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

  $where = "$dateExpr >= :from_d::date AND $dateExpr <= :to_d::date";
  $params = [
    ':from_d' => $fromDate,
    ':to_d' => $toDate,
  ];
  if ($hasCreatedById && !empty($_SESSION['user']['id'])) {
    $where .= ' AND created_by_user_id = :uid';
    $params[':uid'] = (int)$_SESSION['user']['id'];
  }

  $prevWhere = "$dateExpr >= :from_d::date AND $dateExpr <= :to_d::date";
  $prevParams = [
    ':from_d' => $prevFromDate,
    ':to_d' => $prevToDate,
  ];
  if ($hasCreatedById && !empty($_SESSION['user']['id'])) {
    $prevWhere .= ' AND created_by_user_id = :uid';
    $prevParams[':uid'] = (int)$_SESSION['user']['id'];
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

  $sqlTypes = "SELECT COUNT(DISTINCT test_name)::int AS c FROM lab_tests WHERE $where";
  $stmt = $pdo->prepare($sqlTypes);
  $stmt->execute($params);
  $testTypesThisMonth = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

  // Per-test counts for this month (recent reports table)
  $sqlRecent = "SELECT test_name, COUNT(*)::int AS c FROM lab_tests WHERE $where GROUP BY test_name ORDER BY c DESC, test_name ASC LIMIT 10";
  $stmt = $pdo->prepare($sqlRecent);
  $stmt->execute($params);
  $recentReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $daysInMonth = (int)date('t', strtotime($monthStart));
  for ($d = 1; $d <= $daysInMonth; $d++) {
    $key = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $d);
    $dailyTests[$key] = 0;
  }
  $sqlDaily = "SELECT $dateExpr AS d, COUNT(*)::int AS c FROM lab_tests WHERE $where GROUP BY d ORDER BY d ASC";
  $stmt = $pdo->prepare($sqlDaily);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($rows as $r) {
    $dd = isset($r['d']) ? substr((string)$r['d'], 0, 10) : '';
    if ($dd !== '' && array_key_exists($dd, $dailyTests)) {
      $dailyTests[$dd] = (int)($r['c'] ?? 0);
    }
  }

  $avgTestsPerDay = $daysInMonth > 0 ? (int)round(((int)$testsThisMonth) / $daysInMonth) : 0;

  $stmt = $pdo->prepare("SELECT COUNT(*)::int AS c FROM lab_tests WHERE $prevWhere");
  $stmt->execute($prevParams);
  $prevTestsThisMonth = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

  $stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient)::int AS c FROM lab_tests WHERE $prevWhere");
  $stmt->execute($prevParams);
  $prevPatientsThisMonth = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

  $stmt = $pdo->prepare("SELECT COUNT(DISTINCT test_name)::int AS c FROM lab_tests WHERE $prevWhere");
  $stmt->execute($prevParams);
  $prevTestTypesThisMonth = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

  $prevDaysInMonth = (int)date('t', strtotime($prevMonthStart));
  $prevAvgTestsPerDay = $prevDaysInMonth > 0 ? (int)round(((int)$prevTestsThisMonth) / $prevDaysInMonth) : 0;
} catch (Throwable $e) {
  // Keep page usable even if stats fail
  error_log('Failed to load lab report stats: ' . $e->getMessage());
  $testsThisMonth = 0;
  $patientsThisMonth = 0;
  $testTypesThisMonth = 0;
  $avgTestsPerDay = 0;
  $recentReports = [];
  $dailyTests = [];
}

include __DIR__ . '/../../includes/header.php';
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

  <div class="content" style="width:100%;max-width:none;margin:0;">
    <?php
    $deltaTests = (int)$testsThisMonth - (int)$prevTestsThisMonth;
    $deltaPatients = (int)$patientsThisMonth - (int)$prevPatientsThisMonth;
    $deltaTypes = (int)$testTypesThisMonth - (int)$prevTestTypesThisMonth;
    $deltaAvg = (int)$avgTestsPerDay - (int)$prevAvgTestsPerDay;

    function lr_delta_badge($delta)
    {
      $d = (int)$delta;
      $isPos = $d >= 0;
      $color = $isPos ? '#16a34a' : '#dc2626';
      $sign = $isPos ? '+' : '';
      return '<span style="display:inline-flex;align-items:center;gap:6px;color:' . $color . ';font-weight:800;font-size:0.9rem;">' . ($isPos ? '↗' : '↘') . ' ' . $sign . $d . '</span>';
    }

    $maxDaily = 0;
    foreach ($dailyTests as $k => $v) {
      $maxDaily = max($maxDaily, (int)$v);
    }
    if ($maxDaily <= 0) $maxDaily = 1;
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
          <div class="ad-subtitle">Track your laboratory performance</div>
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
            <span><?php echo lr_escape(date('F Y', strtotime($monthStart))); ?></span>
          </div>
          <a href="?month=<?php echo (int)$nextMonth; ?>&year=<?php echo (int)$nextYear; ?>" aria-label="Next month">&#10095;</a>
        </div>
      </div>

      <div class="ad-grid">
        <div class="ad-card">
          <div class="ad-card-top">
            <div class="ad-icon" style="background:#dbeafe;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 12h4l2-5 4 10 2-5h4"></path>
              </svg>
            </div>
            <div><?php echo lr_delta_badge($deltaTests); ?></div>
          </div>
          <div class="ad-label">Tests</div>
          <div class="ad-value"><?php echo (int)$testsThisMonth; ?></div>
          <div class="ad-meta">This month</div>
        </div>

        <div class="ad-card">
          <div class="ad-card-top">
            <div class="ad-icon" style="background:#f3e8ff;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
            </div>
            <div><?php echo lr_delta_badge($deltaPatients); ?></div>
          </div>
          <div class="ad-label">Unique Patients</div>
          <div class="ad-value"><?php echo (int)$patientsThisMonth; ?></div>
          <div class="ad-meta">This month</div>
        </div>

        <div class="ad-card">
          <div class="ad-card-top">
            <div class="ad-icon" style="background:#dcfce7;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
            </div>
            <div><?php echo lr_delta_badge($deltaTypes); ?></div>
          </div>
          <div class="ad-label">Test Types</div>
          <div class="ad-value"><?php echo (int)$testTypesThisMonth; ?></div>
          <div class="ad-meta">Distinct</div>
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
            <div><?php echo lr_delta_badge($deltaAvg); ?></div>
          </div>
          <div class="ad-label">Avg / Day</div>
          <div class="ad-value"><?php echo (int)$avgTestsPerDay; ?></div>
          <div class="ad-meta">Approx.</div>
        </div>
      </div>

      <div class="ad-chart">
        <div class="ad-chart-head">
          <div>
            <h3 class="ad-chart-title">Daily Tests</h3>
            <div class="ad-chart-sub">Total: <?php echo (int)$testsThisMonth; ?> tests</div>
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
        foreach ($dailyTests as $day => $cnt) {
          $chartLabels[] = substr((string)$day, -2);
          $val = (int)$cnt;
          $chartDaily[] = $val;
          $running += $val;
          $chartCum[] = $running;
        }
        ?>
        <div class="ad-bars" style="height:260px;">
          <canvas id="labDailyTestsChart" aria-label="Daily tests chart" role="img"></canvas>
        </div>
        <script>
          (function() {
            var el = document.getElementById('labDailyTestsChart');
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