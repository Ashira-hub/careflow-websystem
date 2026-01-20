<?php
$page = 'Admin Reports';
require_once __DIR__ . '/../../config/db.php';
// Determine selected month/year from query (defaults to current month)
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
if ($month < 1 || $month > 12) {
  $month = (int)date('n');
}
if ($year < 1970 || $year > 9999) {
  $year = (int)date('Y');
}
$startDate = sprintf('%04d-%02d-01', $year, $month);
$dt = DateTime::createFromFormat('Y-m-d', $startDate) ?: new DateTime();
$endDate = $dt->modify('first day of next month')->format('Y-m-d');

$monthDt = DateTime::createFromFormat('Y-m-d', $startDate) ?: new DateTime();
$prevMonthDt = (clone $monthDt)->modify('-1 month');
$nextMonthDt = (clone $monthDt)->modify('+1 month');
$prevStartDate = $prevMonthDt->format('Y-m-01');
$prevEndDate = (clone $prevMonthDt)->modify('first day of next month')->format('Y-m-d');

function ar_escape($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function ar_delta_badge($delta)
{
  $delta = (int)$delta;
  if ($delta > 0) {
    $cls = 'pos';
    $arrow = '▲';
    $txt = '+' . number_format($delta);
  } elseif ($delta < 0) {
    $cls = 'neg';
    $arrow = '▼';
    $txt = number_format($delta);
  } else {
    $cls = 'zero';
    $arrow = '•';
    $txt = '0';
  }
  return '<span class="ad-delta ' . $cls . '"><span class="ad-delta-arrow">' . $arrow . '</span>' . ar_escape($txt) . '</span>';
}

$hasCreatedAt = false;
$doctorCount = 0;
$nurseCount = 0;
$pharmacistCount = 0;
$labstaffCount = 0;
$recentCounts = [
  'admin' => 0,
  'supervisor' => 0,
  'doctor' => 0,
  'nurse' => 0,
  'pharmacist' => 0,
  'labstaff' => 0,
];
$prevCounts = $recentCounts;

$daysInMonth = (int)$monthDt->format('t');
$dailyNewUsers = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
  $dateKey = sprintf('%04d-%02d-%02d', (int)$monthDt->format('Y'), (int)$monthDt->format('m'), $d);
  $dailyNewUsers[$dateKey] = 0;
}
try {
  $pdo = get_pdo();
  // Count registered doctor accounts
  $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(TRIM(role)) = 'doctor'");
  $doctorCount = (int) $stmt->fetchColumn();
  // Count registered nurse accounts
  $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(TRIM(role)) = 'nurse'");
  $nurseCount = (int) $stmt->fetchColumn();
  // Count registered pharmacist accounts
  $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(TRIM(role)) = 'pharmacist'");
  $pharmacistCount = (int) $stmt->fetchColumn();
  // Count registered lab staff accounts (handle both labstaff and lab_staff just in case)
  $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(TRIM(role)) IN ('labstaff','lab_staff')");
  $labstaffCount = (int) $stmt->fetchColumn();
  // Compute recent (this month) counts per role for the table
  try {
    $colStmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'users' AND column_name = 'created_at'");
    $colStmt->execute();
    $hasCreatedAt = (bool)$colStmt->fetchColumn();
  } catch (Throwable $ie) {
    $hasCreatedAt = false;
  }

  $normalizeRole = function ($r) {
    $r = (string)$r;
    if ($r === 'lab_staff') {
      return 'labstaff';
    }
    return $r;
  };

  if ($hasCreatedAt) {
    $sql = "SELECT LOWER(TRIM(role)) AS r, COUNT(*) AS c
             FROM users
             WHERE created_at >= :start AND created_at < :end
               AND role IS NOT NULL
             GROUP BY r";
    $stmtM = $pdo->prepare($sql);
    $stmtM->execute([':start' => $startDate, ':end' => $endDate]);
    $rs = $stmtM;

    $stmtPrev = $pdo->prepare($sql);
    $stmtPrev->execute([':start' => $prevStartDate, ':end' => $prevEndDate]);
    $rsPrev = $stmtPrev;

    $sqlDaily = "SELECT DATE(created_at) AS d, COUNT(*) AS c
                 FROM users
                 WHERE created_at >= :start AND created_at < :end
                 GROUP BY DATE(created_at)
                 ORDER BY DATE(created_at)";
    $stmtDaily = $pdo->prepare($sqlDaily);
    $stmtDaily->execute([':start' => $startDate, ':end' => $endDate]);
    foreach ($stmtDaily as $row) {
      $d = (string)($row['d'] ?? '');
      $c = (int)($row['c'] ?? 0);
      if ($d !== '' && array_key_exists($d, $dailyNewUsers)) {
        $dailyNewUsers[$d] = $c;
      }
    }
  } else {
    // Fallback: total counts by role if created_at not available
    $sql = "SELECT LOWER(TRIM(role)) AS r, COUNT(*) AS c
             FROM users WHERE role IS NOT NULL GROUP BY r";
    $rs = $pdo->query($sql);
    $rsPrev = $pdo->query($sql);
  }
  foreach ($rs as $row) {
    $r = $normalizeRole((string)($row['r'] ?? ''));
    $c = (int)($row['c'] ?? 0);
    if (isset($recentCounts[$r])) {
      $recentCounts[$r] += $c;
    }
  }

  foreach ($rsPrev as $row) {
    $r = $normalizeRole((string)($row['r'] ?? ''));
    $c = (int)($row['c'] ?? 0);
    if (isset($prevCounts[$r])) {
      $prevCounts[$r] += $c;
    }
  }
} catch (Throwable $e) {
  $doctorCount = 0; // fallback on error
}
include __DIR__ . '/../../includes/header.php';
?>

<style>
  .ad-shell {
    width: 100%;
  }

  .ad-top {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 16px;
  }

  .ad-title {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
    color: #0f172a;
  }

  .ad-subtitle {
    margin-top: 4px;
    color: #64748b;
    font-size: 0.95rem;
  }

  .ad-month {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 8px 12px;
  }

  .ad-month-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: #fff;
    border: 1px solid #d1d5db;
    color: #111827;
    text-decoration: none;
    transition: all 0.15s ease;
  }

  .ad-month-btn:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
  }

  .ad-month-label {
    font-weight: 700;
    color: #0f172a;
    min-width: 140px;
    text-align: center;
  }

  .ad-kpis {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 16px;
  }

  .ad-kpi {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 18px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  }

  .ad-kpi-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
  }

  .ad-kpi-title {
    margin: 0;
    font-size: 1.02rem;
    font-weight: 700;
    color: #0f172a;
  }

  .ad-kpi-sub {
    margin-top: 4px;
    color: #64748b;
    font-size: 0.9rem;
  }

  .ad-kpi-value {
    font-size: 2.2rem;
    font-weight: 800;
    color: #0a5d39;
    line-height: 1.1;
  }

  .ad-delta {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.85rem;
    border: 1px solid transparent;
    white-space: nowrap;
  }

  .ad-delta-arrow {
    font-size: 0.8rem;
  }

  .ad-delta.pos {
    background: #ecfdf5;
    color: #047857;
    border-color: #a7f3d0;
  }

  .ad-delta.neg {
    background: #fef2f2;
    color: #b91c1c;
    border-color: #fecaca;
  }

  .ad-delta.zero {
    background: #f1f5f9;
    color: #334155;
    border-color: #e2e8f0;
  }

  .ad-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 18px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  }

  .ad-card-title {
    margin: 0 0 10px;
    font-weight: 800;
    color: #0f172a;
  }

  .ad-bars {
    height: 210px;
    display: grid;
    grid-template-columns: repeat(<?php echo (int)$daysInMonth; ?>, 1fr);
    gap: 6px;
    align-items: end;
    padding-top: 10px;
  }

  .ad-bar {
    background: linear-gradient(135deg, #0a5d39, #10b981);
    border-radius: 6px 6px 0 0;
    min-height: 6px;
  }

  @media (max-width: 1200px) {
    .ad-kpis {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  @media (max-width: 640px) {
    .ad-top {
      flex-direction: column;
      align-items: flex-start;
    }

    .ad-month {
      width: 100%;
      justify-content: space-between;
    }

    .ad-kpis {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;" data-year="<?php echo (int)$year; ?>" data-month="<?php echo (int)$month; ?>">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/admin/admin_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/admin/admin_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/admin/admin_users.php"><img src="/capstone/assets/img/appointment.png" alt="Users" style="width:18px;height:18px;object-fit:contain;"> Users</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
          <li><a href="/capstone/templates/admin/admin_settings.php"><img src="/capstone/assets/img/setting.png" alt="Settings" style="width:18px;height:18px;object-fit:contain;"> Settings</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div>
    <?php
    $kpiSubtitle = $hasCreatedAt ? 'Created this month' : 'Registered';
    $kpiDoctor = (int)($recentCounts['doctor'] ?? 0);
    $kpiNurse = (int)($recentCounts['nurse'] ?? 0);
    $kpiPharmacist = (int)($recentCounts['pharmacist'] ?? 0);
    $kpiLab = (int)($recentCounts['labstaff'] ?? 0);
    $deltaDoctor = $kpiDoctor - (int)($prevCounts['doctor'] ?? 0);
    $deltaNurse = $kpiNurse - (int)($prevCounts['nurse'] ?? 0);
    $deltaPharmacist = $kpiPharmacist - (int)($prevCounts['pharmacist'] ?? 0);
    $deltaLab = $kpiLab - (int)($prevCounts['labstaff'] ?? 0);
    $totalNewUsers = array_sum(array_map('intval', $dailyNewUsers));
    $maxDaily = max(1, max(array_map('intval', $dailyNewUsers)));
    ?>

    <div class="ad-shell">
      <div class="ad-top">
        <div>
          <h2 class="ad-title">Analytics Dashboard</h2>
          <div class="ad-subtitle">Admin reports for <?php echo ar_escape($monthDt->format('F Y')); ?></div>
        </div>
        <div class="ad-month" aria-label="Month selector">
          <a class="ad-month-btn" href="?year=<?php echo (int)$prevMonthDt->format('Y'); ?>&month=<?php echo (int)$prevMonthDt->format('n'); ?>" aria-label="Previous month">&#10094;</a>
          <div class="ad-month-label"><?php echo ar_escape($monthDt->format('F Y')); ?></div>
          <a class="ad-month-btn" href="?year=<?php echo (int)$nextMonthDt->format('Y'); ?>&month=<?php echo (int)$nextMonthDt->format('n'); ?>" aria-label="Next month">&#10095;</a>
        </div>
      </div>

      <div class="ad-kpis">
        <div class="ad-kpi">
          <div class="ad-kpi-head">
            <div>
              <h3 class="ad-kpi-title">Doctors</h3>
              <div class="ad-kpi-sub"><?php echo ar_escape($kpiSubtitle); ?></div>
            </div>
            <?php echo ar_delta_badge($deltaDoctor); ?>
          </div>
          <div class="ad-kpi-value"><?php echo number_format($kpiDoctor); ?></div>
        </div>

        <div class="ad-kpi">
          <div class="ad-kpi-head">
            <div>
              <h3 class="ad-kpi-title">Nurses</h3>
              <div class="ad-kpi-sub"><?php echo ar_escape($kpiSubtitle); ?></div>
            </div>
            <?php echo ar_delta_badge($deltaNurse); ?>
          </div>
          <div class="ad-kpi-value"><?php echo number_format($kpiNurse); ?></div>
        </div>

        <div class="ad-kpi">
          <div class="ad-kpi-head">
            <div>
              <h3 class="ad-kpi-title">Pharmacists</h3>
              <div class="ad-kpi-sub"><?php echo ar_escape($kpiSubtitle); ?></div>
            </div>
            <?php echo ar_delta_badge($deltaPharmacist); ?>
          </div>
          <div class="ad-kpi-value"><?php echo number_format($kpiPharmacist); ?></div>
        </div>

        <div class="ad-kpi">
          <div class="ad-kpi-head">
            <div>
              <h3 class="ad-kpi-title">Lab Staff</h3>
              <div class="ad-kpi-sub"><?php echo ar_escape($kpiSubtitle); ?></div>
            </div>
            <?php echo ar_delta_badge($deltaLab); ?>
          </div>
          <div class="ad-kpi-value"><?php echo number_format($kpiLab); ?></div>
        </div>
      </div>

      <div class="ad-card" style="margin-bottom:16px;">
        <h3 class="ad-card-title">Daily new accounts (<?php echo number_format((int)$totalNewUsers); ?>)</h3>
        <div class="ad-bars" aria-label="Daily new accounts bar chart">
          <?php foreach ($dailyNewUsers as $day => $cnt): $h = max(6, (int)round(((int)$cnt / $maxDaily) * 100)); ?>
            <div class="ad-bar" title="<?php echo ar_escape($day); ?>: <?php echo (int)$cnt; ?>" style="height:<?php echo (int)$h; ?>%;"></div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="ad-card">
        <h3 class="ad-card-title">Role breakdown</h3>
        <table>
          <thead>
            <tr>
              <th>Role</th>
              <th><?php echo $hasCreatedAt ? 'New this month' : 'Registered'; ?></th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Admin</td>
              <td><?php echo number_format((int)$recentCounts['admin']); ?></td>
            </tr>
            <tr>
              <td>Supervisor</td>
              <td><?php echo number_format((int)$recentCounts['supervisor']); ?></td>
            </tr>
            <tr>
              <td>Doctor</td>
              <td><?php echo number_format((int)$recentCounts['doctor']); ?></td>
            </tr>
            <tr>
              <td>Nurse</td>
              <td><?php echo number_format((int)$recentCounts['nurse']); ?></td>
            </tr>
            <tr>
              <td>Pharmacy</td>
              <td><?php echo number_format((int)$recentCounts['pharmacist']); ?></td>
            </tr>
            <tr>
              <td>Laboratory</td>
              <td><?php echo number_format((int)$recentCounts['labstaff']); ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>