<?php
$page = 'Analytics Dashboard';
require_once __DIR__ . '/../../config/db.php';
$items = [];
$reportError = '';
try {
  $pdo = get_pdo();
  // Align with working inventory query: avoid depending on a possibly missing reorder_level column
  $stmt = $pdo->query('SELECT id, generic_name, brand_name, category, dosage_type, strength, unit, expiration_date, description, stock, created_at FROM inventory ORDER BY created_at DESC');
  $items = $stmt->fetchAll();
} catch (Throwable $e) {
  $reportError = 'Unable to load pharmacy data right now.';
}

function pr_escape($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function pr_parse_ts($row)
{
  $ts = $row['created_at'] ?? '';
  $time = strtotime((string)$ts);
  return $time ?: null;
}
function pr_group_key($ts, $group)
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

$prevFirstTs = mktime(0, 0, 0, $prevMonth, 1, $prevYear);
$prevLastDay = (int)date('t', $prevFirstTs);
$prevFromTs = $prevFirstTs;
$prevToTs = mktime(23, 59, 59, $prevMonth, $prevLastDay, $prevYear);

$dailyDispensed = [];
for ($d = 1; $d <= $lastDay; $d++) {
  $key = sprintf('%04d-%02d-%02d', $year, $month, $d);
  $dailyDispensed[$key] = 0;
}

$filtered = array_filter($items, function ($row) use ($fromTs, $toTs) {
  $ts = pr_parse_ts($row);
  if (!$ts) return false;
  if ($fromTs && $ts < $fromTs) return false;
  if ($toTs && $ts > $toTs) return false;
  return true;
});

$prevFiltered = array_filter($items, function ($row) use ($prevFromTs, $prevToTs) {
  $ts = pr_parse_ts($row);
  if (!$ts) return false;
  if ($prevFromTs && $ts < $prevFromTs) return false;
  if ($prevToTs && $ts > $prevToTs) return false;
  return true;
});

// Count inventory items from pharmacy_inventory.php
$totalProducts = count($filtered);

$prevTotalProducts = count($prevFiltered);

// Count low stock items from pharmacy_medicine.php (items with stock <= reorder level)
$lowStock = 0;
$reorderLevel = 10; // Default reorder level
foreach ($filtered as $row) {
  $stock = (int)($row['stock'] ?? 0);
  $reorder = (int)($row['reorder_level'] ?? $reorderLevel);
  $threshold = $reorder > 0 ? $reorder : $reorderLevel;
  if ($stock <= $threshold && $stock > 0) {
    $lowStock++;
  }
}

$prevLowStock = 0;
foreach ($prevFiltered as $row) {
  $stock = (int)($row['stock'] ?? 0);
  $reorder = (int)($row['reorder_level'] ?? $reorderLevel);
  $threshold = $reorder > 0 ? $reorder : $reorderLevel;
  if ($stock <= $threshold && $stock > 0) {
    $prevLowStock++;
  }
}

// Count dispensed prescriptions based on the same JSON used by pharmacy_prescription.php
$dispensedCount = 0;
try {
  $jsonPath = __DIR__ . '/../../data/nurse_prescriptions.json';
  if (file_exists($jsonPath)) {
    $raw = file_get_contents($jsonPath);
    $data = json_decode($raw, true);
    $items = [];
    if (is_array($data)) {
      if (isset($data['items']) && is_array($data['items'])) {
        $items = $data['items'];
      } else {
        $items = $data; // fallback if file is a plain array
      }
    }
    foreach ($items as $n) {
      $status = strtolower((string)($n['status'] ?? ''));
      if ($status !== 'dispensed') {
        continue;
      }
      $timeStr = (string)($n['time'] ?? '');
      $ts = $timeStr !== '' ? strtotime($timeStr) : null;
      if (!$ts) {
        continue;
      }
      if ($fromTs && $ts < $fromTs) {
        continue;
      }
      if ($toTs && $ts > $toTs) {
        continue;
      }
      $dispensedCount++;
      $dayKey = date('Y-m-d', $ts);
      if (array_key_exists($dayKey, $dailyDispensed)) {
        $dailyDispensed[$dayKey] = (int)$dailyDispensed[$dayKey] + 1;
      }
    }
  }
} catch (Throwable $e) {
  $dispensedCount = 0;
}

$prevDispensedCount = 0;
try {
  $jsonPathPrev = __DIR__ . '/../../data/nurse_prescriptions.json';
  if (file_exists($jsonPathPrev)) {
    $rawPrev = file_get_contents($jsonPathPrev);
    $dataPrev = json_decode($rawPrev, true);
    $itemsPrev = [];
    if (is_array($dataPrev)) {
      if (isset($dataPrev['items']) && is_array($dataPrev['items'])) {
        $itemsPrev = $dataPrev['items'];
      } else {
        $itemsPrev = $dataPrev;
      }
    }
    foreach ($itemsPrev as $n) {
      $status = strtolower((string)($n['status'] ?? ''));
      if ($status !== 'dispensed') {
        continue;
      }
      $timeStr = (string)($n['time'] ?? '');
      $ts = $timeStr !== '' ? strtotime($timeStr) : null;
      if (!$ts) {
        continue;
      }
      if ($prevFromTs && $ts < $prevFromTs) {
        continue;
      }
      if ($prevToTs && $ts > $prevToTs) {
        continue;
      }
      $prevDispensedCount++;
    }
  }
} catch (Throwable $e) {
  $prevDispensedCount = 0;
}

// Total medicine dispensed (sum of quantities from dispensed prescriptions in JSON for this month)
$totalMedsDispensed = 0;
try {
  $jsonPath = __DIR__ . '/../../data/nurse_prescriptions.json';
  if (file_exists($jsonPath)) {
    $raw = file_get_contents($jsonPath);
    $data = json_decode($raw, true);
    $items = [];
    if (is_array($data)) {
      if (isset($data['items']) && is_array($data['items'])) {
        $items = $data['items'];
      } else {
        $items = $data; // fallback if file is a plain array
      }
    }
    foreach ($items as $n) {
      $status = strtolower((string)($n['status'] ?? ''));
      if ($status !== 'dispensed') {
        continue;
      }
      $timeStr = (string)($n['time'] ?? '');
      $ts = $timeStr !== '' ? strtotime($timeStr) : null;
      if (!$ts) {
        continue;
      }
      if ($fromTs && $ts < $fromTs) {
        continue;
      }
      if ($toTs && $ts > $toTs) {
        continue;
      }
      // Parse quantity from body: look for 'Qty:' pattern
      $body = (string)($n['body'] ?? '');
      $qty = 0;
      if ($body !== '') {
        $parts = explode('|', $body);
        foreach ($parts as $part) {
          $raw = trim($part);
          if (stripos($raw, 'qty:') === 0) {
            $num = trim(substr($raw, 4));
            $val = (int)preg_replace('/[^0-9]/', '', $num);
            if ($val > 0) {
              $qty = $val;
            }
            break;
          }
        }
      }
      if ($qty <= 0) {
        $qty = 1;
      } // fallback: count as 1 if no explicit qty
      $totalMedsDispensed += $qty;
    }
  }
} catch (Throwable $e) {
  $totalMedsDispensed = 0;
}

$prevTotalMedsDispensed = 0;
try {
  $jsonPathPrev = __DIR__ . '/../../data/nurse_prescriptions.json';
  if (file_exists($jsonPathPrev)) {
    $rawPrev = file_get_contents($jsonPathPrev);
    $dataPrev = json_decode($rawPrev, true);
    $itemsPrev = [];
    if (is_array($dataPrev)) {
      if (isset($dataPrev['items']) && is_array($dataPrev['items'])) {
        $itemsPrev = $dataPrev['items'];
      } else {
        $itemsPrev = $dataPrev;
      }
    }
    foreach ($itemsPrev as $n) {
      $status = strtolower((string)($n['status'] ?? ''));
      if ($status !== 'dispensed') {
        continue;
      }
      $timeStr = (string)($n['time'] ?? '');
      $ts = $timeStr !== '' ? strtotime($timeStr) : null;
      if (!$ts) {
        continue;
      }
      if ($prevFromTs && $ts < $prevFromTs) {
        continue;
      }
      if ($prevToTs && $ts > $prevToTs) {
        continue;
      }
      $body = (string)($n['body'] ?? '');
      $qty = 0;
      if ($body !== '') {
        $parts = explode('|', $body);
        foreach ($parts as $part) {
          $raw = trim($part);
          if (stripos($raw, 'qty:') === 0) {
            $num = trim(substr($raw, 4));
            $val = (int)preg_replace('/[^0-9]/', '', $num);
            if ($val > 0) {
              $qty = $val;
            }
            break;
          }
        }
      }
      if ($qty <= 0) {
        $qty = 1;
      }
      $prevTotalMedsDispensed += $qty;
    }
  }
} catch (Throwable $e) {
  $prevTotalMedsDispensed = 0;
}

$maxDaily = 0;
foreach ($dailyDispensed as $k => $v) {
  $maxDaily = max($maxDaily, (int)$v);
}
if ($maxDaily <= 0) $maxDaily = 1;

function pr_delta_badge($delta)
{
  $d = (int)$delta;
  $isPos = $d >= 0;
  $color = $isPos ? '#16a34a' : '#dc2626';
  $sign = $isPos ? '+' : '';
  return '<span style="display:inline-flex;align-items:center;gap:6px;color:' . $color . ';font-weight:800;font-size:0.9rem;">' . ($isPos ? '↗' : '↘') . ' ' . $sign . $d . '</span>';
}

$categories = [];
$grouped = [];
$latestTs = null;

// Persist this report run into the existing reports table (date/time + counts)
try {
  if (isset($pdo)) {
    // Ensure extra user metadata columns exist (matching existing schema)
    $pdo->exec("ALTER TABLE reports ADD COLUMN IF NOT EXISTS name TEXT");
    $pdo->exec("ALTER TABLE reports ADD COLUMN IF NOT EXISTS role TEXT");
    $pdo->exec("ALTER TABLE reports ADD COLUMN IF NOT EXISTS created_by_user_id INTEGER");

    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    $userId   = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
    $userName = isset($_SESSION['user']['full_name']) ? (string)$_SESSION['user']['full_name'] : '';
    $userRole = isset($_SESSION['user']['role']) ? (string)$_SESSION['user']['role'] : '';

    $stmtSave = $pdo->prepare("INSERT INTO reports (date, time, created_at, inventory_items, low_stock_count, dispensed_count, total_meds_dispensed, name, role, created_by_user_id)
      VALUES (:d, :t, NOW(), :inv, :low, :disp, :tot, :uname, :urole, :uid)");
    $stmtSave->execute([
      ':d'    => date('Y-m-d', $firstTs),
      ':t'    => date('H:i:s'),
      ':inv'  => $totalProducts,
      ':low'  => $lowStock,
      ':disp' => $dispensedCount,
      ':tot'  => $totalMedsDispensed,
      ':uname' => $userName,
      ':urole' => $userRole,
      ':uid'  => $userId,
    ]);
  }
} catch (Throwable $e) {
}

foreach ($filtered as $row) {
  $stock = (int)($row['stock'] ?? 0);
  $cat = trim((string)($row['category'] ?? 'Uncategorized')) ?: 'Uncategorized';
  if (!isset($categories[$cat])) {
    $categories[$cat] = 0;
  }
  $categories[$cat]++;
  $ts = pr_parse_ts($row);
  $bucket = pr_group_key($ts, $group);
  if (!isset($grouped[$bucket])) {
    $grouped[$bucket] = 0;
  }
  $grouped[$bucket] += max(0, $stock);
  if ($ts && (!$latestTs || $ts > $latestTs)) {
    $latestTs = $ts;
  }
}

arsort($categories);
$topCategories = array_slice($categories, 0, 5, true);

$topProducts = $filtered;
usort($topProducts, function ($a, $b) {
  return (int)($b['stock'] ?? 0) <=> (int)($a['stock'] ?? 0);
});
$topProducts = array_slice($topProducts, 0, 5);

ksort($grouped);
$activityKeys = array_keys($grouped);
$activityValues = array_values($grouped);
$activityMax = $activityValues ? max($activityValues) : 0;

include __DIR__ . '/../../includes/header.php';
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/pharmacy/pharmacy_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_inventory.php"><img src="/capstone/assets/img/appointment.png" alt="Inventory" style="width:18px;height:18px;object-fit:contain;"> Inventory</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_prescription.php"><img src="/capstone/assets/img/prescription.png" alt="Prescription" style="width:18px;height:18px;object-fit:contain;"> Prescription</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_medicine.php"><img src="/capstone/assets/img/drug.png" alt="Medicine" style="width:18px;height:18px;object-fit:contain;"> Medicine</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div class="content" style="width:100%;max-width:none;margin:0;">
    <?php
    $deltaInventory = (int)$totalProducts - (int)$prevTotalProducts;
    $deltaLowStock = (int)$lowStock - (int)$prevLowStock;
    $deltaDispensed = (int)$dispensedCount - (int)$prevDispensedCount;
    $deltaTotalMeds = (int)$totalMedsDispensed - (int)$prevTotalMedsDispensed;
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
          <div class="ad-subtitle">Track your pharmacy performance</div>
          <?php if ($reportError !== ''): ?>
            <div class="muted" style="margin-top:10px;color:#b91c1c;"><?php echo pr_escape($reportError); ?></div>
          <?php endif; ?>
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
            <span><?php echo pr_escape(date('F Y', $firstTs)); ?></span>
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
            <div><?php echo pr_delta_badge($deltaInventory); ?></div>
          </div>
          <div class="ad-label">Inventory Items</div>
          <div class="ad-value"><?php echo (int)$totalProducts; ?></div>
          <div class="ad-meta"><?php echo pr_escape($periodLabel); ?></div>
        </div>

        <div class="ad-card">
          <div class="ad-card-top">
            <div class="ad-icon" style="background:#ffedd5;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 12h4l2-5 4 10 2-5h4"></path>
              </svg>
            </div>
            <div><?php echo pr_delta_badge($deltaLowStock); ?></div>
          </div>
          <div class="ad-label">Low Stock</div>
          <div class="ad-value"><?php echo (int)$lowStock; ?></div>
          <div class="ad-meta">≤ reorder level</div>
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
            <div><?php echo pr_delta_badge($deltaDispensed); ?></div>
          </div>
          <div class="ad-label">Dispensed Prescriptions</div>
          <div class="ad-value"><?php echo (int)$dispensedCount; ?></div>
          <div class="ad-meta">This month</div>
        </div>

        <div class="ad-card">
          <div class="ad-card-top">
            <div class="ad-icon" style="background:#dcfce7;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 3v18h18"></path>
                <path d="M7 13v5"></path>
                <path d="M12 9v9"></path>
                <path d="M17 6v12"></path>
              </svg>
            </div>
            <div><?php echo pr_delta_badge($deltaTotalMeds); ?></div>
          </div>
          <div class="ad-label">Total Medicine Dispensed</div>
          <div class="ad-value"><?php echo (int)$totalMedsDispensed; ?></div>
          <div class="ad-meta">Qty (approx.)</div>
        </div>
      </div>

      <div class="ad-chart">
        <div class="ad-chart-head">
          <div>
            <h3 class="ad-chart-title">Daily Dispensed Prescriptions</h3>
            <div class="ad-chart-sub">Total: <?php echo (int)$dispensedCount; ?> dispensed</div>
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
        foreach ($dailyDispensed as $day => $cnt) {
          $chartLabels[] = substr((string)$day, -2);
          $val = (int)$cnt;
          $chartDaily[] = $val;
          $running += $val;
          $chartCum[] = $running;
        }
        ?>
        <div class="ad-bars" style="height:260px;">
          <canvas id="pharmDailyDispensedChart" aria-label="Daily dispensed prescriptions chart" role="img"></canvas>
        </div>
        <script>
          (function() {
            var el = document.getElementById('pharmDailyDispensedChart');
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