<?php
$page='Pharmacy Reports';
require_once __DIR__.'/../../config/db.php';
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

function pr_escape($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function pr_parse_ts($row){
  $ts = $row['created_at'] ?? '';
  $time = strtotime((string)$ts);
  return $time ?: null;
}
function pr_group_key($ts, $group){
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

$filtered = array_filter($items, function($row) use ($fromTs, $toTs){
  $ts = pr_parse_ts($row);
  if(!$ts) return false;
  if($fromTs && $ts < $fromTs) return false;
  if($toTs && $ts > $toTs) return false;
  return true;
});

// Count inventory items from pharmacy_inventory.php
$totalProducts = count($filtered);

// Count low stock items from pharmacy_medicine.php (items with stock <= reorder level)
$lowStock = 0;
$reorderLevel = 10; // Default reorder level
foreach($filtered as $row){
  $stock = (int)($row['stock'] ?? 0);
  $reorder = (int)($row['reorder_level'] ?? $reorderLevel);
  $threshold = $reorder > 0 ? $reorder : $reorderLevel;
  if($stock <= $threshold && $stock > 0){ 
    $lowStock++; 
  }
}

// Count dispensed prescriptions based on the same JSON used by pharmacy_prescription.php
$dispensedCount = 0;
try {
  $jsonPath = __DIR__.'/../../data/nurse_prescriptions.json';
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
      if ($status !== 'dispensed') { continue; }
      $timeStr = (string)($n['time'] ?? '');
      $ts = $timeStr !== '' ? strtotime($timeStr) : null;
      if (!$ts) { continue; }
      if ($fromTs && $ts < $fromTs) { continue; }
      if ($toTs && $ts > $toTs) { continue; }
      $dispensedCount++;
    }
  }
} catch (Throwable $e) {
  $dispensedCount = 0;
}

// Total medicine dispensed (sum of quantities from dispensed prescriptions in JSON for this month)
$totalMedsDispensed = 0;
try {
  $jsonPath = __DIR__.'/../../data/nurse_prescriptions.json';
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
      if ($status !== 'dispensed') { continue; }
      $timeStr = (string)($n['time'] ?? '');
      $ts = $timeStr !== '' ? strtotime($timeStr) : null;
      if (!$ts) { continue; }
      if ($fromTs && $ts < $fromTs) { continue; }
      if ($toTs && $ts > $toTs) { continue; }
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
            if ($val > 0) { $qty = $val; }
            break;
          }
        }
      }
      if ($qty <= 0) { $qty = 1; } // fallback: count as 1 if no explicit qty
      $totalMedsDispensed += $qty;
    }
  }
} catch (Throwable $e) {
  $totalMedsDispensed = 0;
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

    if (session_status() === PHP_SESSION_NONE) { session_start(); }
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
      ':uname'=> $userName,
      ':urole'=> $userRole,
      ':uid'  => $userId,
    ]);
  }
} catch (Throwable $e) {
}

foreach($filtered as $row){
  $stock = (int)($row['stock'] ?? 0);
  $cat = trim((string)($row['category'] ?? 'Uncategorized')) ?: 'Uncategorized';
  if(!isset($categories[$cat])){ $categories[$cat] = 0; }
  $categories[$cat]++;
  $ts = pr_parse_ts($row);
  $bucket = pr_group_key($ts, $group);
  if(!isset($grouped[$bucket])){ $grouped[$bucket] = 0; }
  $grouped[$bucket] += max(0, $stock);
  if($ts && (!$latestTs || $ts > $latestTs)){ $latestTs = $ts; }
}

arsort($categories);
$topCategories = array_slice($categories, 0, 5, true);

$topProducts = $filtered;
usort($topProducts, function($a,$b){ return (int)($b['stock'] ?? 0) <=> (int)($a['stock'] ?? 0); });
$topProducts = array_slice($topProducts, 0, 5);

ksort($grouped);
$activityKeys = array_keys($grouped);
$activityValues = array_values($grouped);
$activityMax = $activityValues ? max($activityValues) : 0;

include __DIR__.'/../../includes/header.php';
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

  <div>
    <section class="card" style="margin-bottom:16px;">
      <h2 class="dashboard-title" style="margin:0;">Reports</h2>
      <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
        <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" aria-label="Previous month" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:1px solid #0a5d39;border-radius:8px;color:#0a5d39;background:#fff;text-decoration:none;">
          &#10094;
        </a>
        <span id="currentMonthYear" style="color:#0a5d39;font-weight:700;"><?php echo pr_escape(date('F Y', $firstTs)); ?></span>
        <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" aria-label="Next month" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:1px solid #0a5d39;border-radius:8px;color:#0a5d39;background:#fff;text-decoration:none;">
          &#10095;
        </a>
      </div>
    </section>

    <section style="margin-bottom:16px;">
      <div class="stat-cards" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
        <div class="stat-card" onclick="navigateToInventory()" style="cursor:pointer;transition:all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,0.12)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)'"><h4>Inventory Items</h4><div class="muted-small"><?php echo pr_escape($periodLabel); ?></div><div class="stat-value"><?php echo pr_escape($totalProducts); ?></div></div>
        <div class="stat-card" onclick="navigateToLowStock()" style="cursor:pointer;transition:all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,0.12)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)'"><h4>Low Stock</h4><div class="muted-small">≤ reorder level</div><div class="stat-value"><?php echo pr_escape($lowStock); ?></div></div>
        <div class="stat-card" onclick="navigateToDispensed()" style="cursor:pointer;transition:all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,0.12)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)'"><h4>Dispensed Prescriptions</h4><div class="muted-small">This month</div><div class="stat-value"><?php echo pr_escape($dispensedCount); ?></div></div>
        <div class="stat-card" style="cursor:default;transition:all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,0.12)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)'"><h4>Total Medicine Dispensed</h4><div class="muted-small">This month</div><div class="stat-value"><?php echo pr_escape($totalMedsDispensed); ?></div></div>
      </div>
    </section>

    <?php
      // Build Top Medicine (Dispensed) for the selected month based on nurse_prescriptions.json
      $topDispensed = [];
      try {
        $jsonPathTop = __DIR__.'/../../data/nurse_prescriptions.json';
        if (file_exists($jsonPathTop)) {
          $rawTop = file_get_contents($jsonPathTop);
          $dataTop = json_decode($rawTop, true);
          $itemsTop = [];
          if (is_array($dataTop)) {
            if (isset($dataTop['items']) && is_array($dataTop['items'])) {
              $itemsTop = $dataTop['items'];
            } else {
              $itemsTop = $dataTop;
            }
          }
          $agg = [];
          foreach ($itemsTop as $n) {
            $status = strtolower((string)($n['status'] ?? ''));
            if ($status !== 'dispensed') { continue; }
            $timeStr = (string)($n['time'] ?? '');
            $ts = $timeStr !== '' ? strtotime($timeStr) : null;
            if (!$ts) { continue; }
            if ($fromTs && $ts < $fromTs) { continue; }
            if ($toTs && $ts > $toTs) { continue; }
            $body = (string)($n['body'] ?? '');
            $medicine = '';
            $qty = 0;
            if ($body !== '') {
              $parts = explode('|', $body);
              foreach ($parts as $part) {
                $raw = trim($part);
                $lower = strtolower($raw);
                if (strpos($lower, 'medicine:') === 0) {
                  $medicine = trim(substr($raw, strlen('medicine:')));
                } elseif (strpos($lower, 'qty:') === 0) {
                  $num = trim(substr($raw, strlen('qty:')));
                  $val = (int)preg_replace('/[^0-9]/', '', $num);
                  if ($val > 0) { $qty = $val; }
                }
              }
            }
            if ($medicine === '') { $medicine = (string)($n['title'] ?? ''); }
            if ($qty <= 0) { $qty = 1; }
            $key = $medicine !== '' ? $medicine : 'Unknown';
            if (!isset($agg[$key])) { $agg[$key] = 0; }
            $agg[$key] += $qty;
          }
          if (!empty($agg)) {
            // Convert to array of ['name'=>..., 'total'=>...] and sort desc
            $rows = [];
            foreach ($agg as $name => $total) {
              $rows[] = ['name' => $name, 'total' => $total];
            }
            usort($rows, function($a,$b){ return (int)($b['total'] ?? 0) <=> (int)($a['total'] ?? 0); });
            $topDispensed = array_slice($rows, 0, 5);
          }
        }
      } catch (Throwable $e) {
        $topDispensed = [];
      }
    ?>
    <section class="card">
      <h3 style="margin-top:0;">Top Medicine (Dispensed)</h3>
      <?php if (!empty($topDispensed)): ?>
        <table>
          <thead><tr><th>Medicine</th><th style="text-align:right;">Total Dispensed</th></tr></thead>
          <tbody>
            <?php foreach ($topDispensed as $row): ?>
              <tr>
                <td><?php echo pr_escape($row['name'] ?? ''); ?></td>
                <td style="text-align:right;"><?php echo pr_escape($row['total'] ?? 0); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="muted">No dispensed data for this period.</div>
      <?php endif; ?>
    </section>
  </div>
</div>

<style>
.report-nav {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 8px 16px;
}

.report-nav-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: #fff;
  border: 1px solid #d1d5db;
  color: #374151;
  text-decoration: none;
  transition: all 0.2s ease;
}

.report-nav-btn:hover {
  background: #f3f4f6;
  border-color: #9ca3af;
  color: #111827;
}

.report-nav-icon {
  font-size: 14px;
  font-weight: 600;
}

.report-nav-label-group {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

.report-nav-month {
  font-size: 1.1rem;
  font-weight: 600;
  color: #0f172a;
}

.report-nav-year {
  font-size: 0.9rem;
  color: #64748b;
}
</style>

<script>
function navigateToInventory() {
  // Navigate to inventory page
  window.location.href = '/capstone/templates/pharmacy/pharmacy_inventory.php';
}

function navigateToLowStock() {
  // Navigate to inventory page with low stock filter
  window.location.href = '/capstone/templates/pharmacy/pharmacy_inventory.php?filter=low_stock';
}

function navigateToDispensed() {
  // Navigate to prescription page to view dispensed items
  window.location.href = '/capstone/templates/pharmacy/pharmacy_prescription.php?status=dispensed';
}
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>

