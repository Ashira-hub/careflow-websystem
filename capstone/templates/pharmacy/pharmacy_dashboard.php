<?php
$page='Pharmacy Dashboard';
require_once __DIR__.'/../../config/db.php';

function pd_escape($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$inventoryItems = [];
$inventoryError = '';
try {
  $pdo = get_pdo();
  // Align inventory query with pharmacy_inventory.php to avoid depending on optional columns
  $stmt = $pdo->query('SELECT id, generic_name, brand_name, category, dosage_type, strength, unit, expiration_date, stock, created_at FROM inventory ORDER BY created_at DESC');
  $inventoryItems = $stmt->fetchAll();
} catch (Throwable $e) {
  $inventoryError = 'Unable to load inventory data: ' . $e->getMessage();
}

$rxFile = __DIR__.'/../../data/nurse_prescriptions.json';
$rxItems = [];
if (file_exists($rxFile)) {
  $raw = file_get_contents($rxFile);
  $decoded = json_decode($raw, true);
  if (is_array($decoded)) {
    if (isset($decoded['items']) && is_array($decoded['items'])) {
      $rxItems = $decoded['items'];
    } else {
      $rxItems = $decoded;
    }
  }
}

// Compute total medicine dispensed for the current month using nurse_prescriptions.json
$reportsCount = 0;
try {
  if (!empty($rxItems)) {
    // Current month window
    $fromTs = strtotime(date('Y-m-01 00:00:00'));
    $toTs = strtotime(date('Y-m-t 23:59:59'));
    foreach ($rxItems as $n) {
      $status = strtolower((string)($n['status'] ?? ''));
      if ($status !== 'dispensed') { continue; }
      $timeStr = (string)($n['time'] ?? '');
      $ts = $timeStr !== '' ? strtotime($timeStr) : null;
      if (!$ts) { continue; }
      if ($fromTs && $ts < $fromTs) { continue; }
      if ($toTs && $ts > $toTs) { continue; }
      // Parse quantity from body: look for 'Qty:'
      $body = (string)($n['body'] ?? '');
      $qty = 0;
      if ($body !== '') {
        $parts = explode('|', $body);
        foreach ($parts as $part) {
          $rawPart = trim($part);
          if (stripos($rawPart, 'qty:') === 0) {
            $num = trim(substr($rawPart, 4));
            $val = (int)preg_replace('/[^0-9]/', '', $num);
            if ($val > 0) { $qty = $val; }
            break;
          }
        }
      }
      if ($qty <= 0) { $qty = 1; } // fallback: count as 1 if no explicit qty
      $reportsCount += $qty;
    }
  }
} catch (Throwable $e) {
  $reportsCount = 0;
}

// Determine pending prescriptions and build dashboard counts
$pendingStatuses = ['accepted','acknowledged'];
$pendingRxAll = array_values(array_filter($rxItems, function($row) use ($pendingStatuses){
  $status = strtolower((string)($row['status'] ?? ''));
  return in_array($status, $pendingStatuses, true);
}));

usort($pendingRxAll, function($a,$b){
  $ta = strtotime((string)($a['updated_at'] ?? $a['time'] ?? '')) ?: 0;
  $tb = strtotime((string)($b['updated_at'] ?? $b['time'] ?? '')) ?: 0;
  return $tb <=> $ta;
});
$pendingRx = array_slice($pendingRxAll, 0, 5);

// Count only pending prescriptions (accepted/acknowledged) for the dashboard stat
$prescriptionCount = count($pendingRxAll);

// For now, medicines correspond to inventory entries used by the medicine list
$inventoryCount = count($inventoryItems);
$medicineCount = $inventoryCount;
$totalStockUnits = 0;
$lowStockCount = 0;
$outStockCount = 0;
$expiringSoonCount = 0;
$recentInventory = $inventoryItems;
$latestInventoryTs = null;

$now = time();
$soonThreshold = strtotime('+30 days');

foreach ($inventoryItems as $row) {
  $stock = (int)($row['stock'] ?? 0);
  $totalStockUnits += $stock;
  if ($stock <= 0) { $outStockCount++; }
  else {
    $threshold = (int)($row['reorder_level'] ?? 0);
    if ($threshold <= 0) { $threshold = 10; }
    if ($stock <= $threshold) { $lowStockCount++; }
  }
  $expRaw = $row['expiration_date'] ?? '';
  $expTs = $expRaw ? strtotime((string)$expRaw) : null;
  if ($expTs && $expTs >= $now && $expTs <= $soonThreshold) { $expiringSoonCount++; }
  $createdTs = $row['created_at'] ? strtotime((string)$row['created_at']) : null;
  if ($createdTs && (!$latestInventoryTs || $createdTs > $latestInventoryTs)) { $latestInventoryTs = $createdTs; }
}

usort($recentInventory, function($a,$b){
  $ta = strtotime((string)($a['created_at'] ?? '')) ?: 0;
  $tb = strtotime((string)($b['created_at'] ?? '')) ?: 0;
  return $tb <=> $ta;
});
$recentInventory = array_slice($recentInventory, 0, 3);

// Build recent activity items for pharmacy (session-scoped)
$recentPharmacyItems = [];
try {
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (!isset($_SESSION['session_started_at'])) { $_SESSION['session_started_at'] = date('Y-m-d H:i:s'); }
  $since = $_SESSION['session_started_at'];

  // Recent inventory events (added/updated items since session start)
  foreach ($inventoryItems as $row) {
    $createdAt = (string)($row['created_at'] ?? '');
    if ($createdAt === '') continue;
    if (strcmp($createdAt, $since) < 0) continue;
    $name = trim((string)($row['generic_name'] ?? $row['brand_name'] ?? ''));    
    $stock = (string)($row['stock'] ?? '');
    $parts = [];
    if ($name !== '') $parts[] = 'Medicine: '.$name;
    if ($stock !== '') $parts[] = 'Stock: '.$stock;
    $recentPharmacyItems[] = [
      'title' => 'Inventory updated',
      'meta'  => substr($createdAt, 0, 16),
      'body'  => implode(' • ', $parts),
      'ts'    => $createdAt,
    ];
  }

  // Recent prescription events from nurse_prescriptions.json (if timestamps available)
  foreach ($rxItems as $rx) {
    $time = (string)($rx['time'] ?? $rx['created_at'] ?? '');
    if ($time !== '' && strcmp($time, $since) < 0) continue;
    $status = strtolower((string)($rx['status'] ?? ''));
    $patient = trim((string)($rx['patient'] ?? $rx['patient_name'] ?? ''));
    $medicine = trim((string)($rx['medicine'] ?? $rx['medicine_name'] ?? ''));
    $bodyParts = [];
    if ($patient !== '') $bodyParts[] = 'Patient: '.$patient;
    if ($medicine !== '') $bodyParts[] = 'Medicine: '.$medicine;
    if (!empty($rx['notes'])) $bodyParts[] = 'Notes: '.trim((string)$rx['notes']);
    $title = 'Prescription';
    if ($status === 'accepted') $title = 'Prescription accepted';
    elseif ($status === 'rejected') $title = 'Prescription rejected';
    elseif ($status === 'dispensed') $title = 'Prescription dispensed';
    elseif ($status !== '') $title = 'Prescription '.$status;
    $recentPharmacyItems[] = [
      'title' => $title,
      'meta'  => $time !== '' ? substr($time, 0, 16) : '',
      'body'  => implode(' • ', $bodyParts),
      'ts'    => $time,
    ];
  }

  // Merge session-scoped pharmacy page views if present
  if (isset($_SESSION['pharmacy_activity']) && is_array($_SESSION['pharmacy_activity'])) {
    foreach ($_SESSION['pharmacy_activity'] as $ev) {
      $recentPharmacyItems[] = [
        'title' => (string)($ev['title'] ?? 'Viewed page'),
        'meta'  => (string)($ev['meta'] ?? ''),
        'body'  => (string)($ev['body'] ?? ''),
        'ts'    => (string)($ev['ts'] ?? ''),
      ];
    }
  }

  // Sort by timestamp descending and limit to 5
  usort($recentPharmacyItems, function($x, $y){
    return strcmp((string)($y['ts'] ?? ''), (string)($x['ts'] ?? ''));
  });
  $recentPharmacyItems = array_slice($recentPharmacyItems, 0, 5);
} catch (Throwable $e) {
  $recentPharmacyItems = [];
}

include __DIR__.'/../../includes/header.php';
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a class="active" href="#home"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_inventory.php"><img src="/capstone/assets/img/appointment.png" alt="Inventory" style="width:18px;height:18px;object-fit:contain;"> Inventory</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_prescription.php"><img src="/capstone/assets/img/prescription.png" alt="Prescription" style="width:18px;height:18px;object-fit:contain;"> Prescription</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_medicine.php"><img src="/capstone/assets/img/drug.png" alt="Medicine" style="width:18px;height:18px;object-fit:contain;"> Medicine</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div>
    <section id="home" class="dashboard-hero" style="padding-top:10px;">
      <h1 class="dashboard-title">Pharmacist Dashboard</h1>
      <div class="stat-cards">
        <div class="card">
          <h4 style="margin:0 0 2px;">Inventory</h4>
          <div class="muted-small" style="margin:0;">Items in inventory</div>
          <div class="stat-value" style="font-size:1.6rem;font-weight:700;color:#0a5d39;">
            <?php echo (int)$inventoryCount; ?>
          </div>
        </div>
        <div class="card">
          <h4 style="margin:0 0 2px;">Prescription</h4>
          <div class="muted-small" style="margin:0;">Pending prescriptions</div>
          <div class="stat-value" style="font-size:1.6rem;font-weight:700;color:#0a5d39;">
            <?php echo (int)$prescriptionCount; ?>
          </div>
        </div>
        <div class="card">
          <h4 style="margin:0 0 2px;">Medicine</h4>
          <div class="muted-small" style="margin:0;">Medicines in list</div>
          <div class="stat-value" style="font-size:1.6rem;font-weight:700;color:#0a5d39;">
            <?php echo (int)$medicineCount; ?>
          </div>
        </div>
        <div class="card">
          <h4 style="margin:0 0 2px;">Reports</h4>
          <div class="muted-small" style="margin:0;">Total medicine dispensed</div>
          <div class="stat-value" style="font-size:1.6rem;font-weight:700;color:#0a5d39;">
            <?php echo (int)$reportsCount; ?>
          </div>
        </div>
      </div>

      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
          <h3 style="margin:0;color:#0a5d39;">Recent Activity</h3>
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
  </div>
</div>

<script>
  // Preloaded recent activity for pharmacy (session-scoped)
  window.PHARMACY_RECENT = <?php echo json_encode(array_map(function($it){ return ['title'=>$it['title'],'meta'=>$it['meta'],'body'=>$it['body']]; }, $recentPharmacyItems), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>

<script>
(function(){
  var recent = document.getElementById('dbRecent');
  var viewAllBtn = document.getElementById('viewAllBtn');
  var showAllActivities = false;

  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]; }); }
  
  function getActivities(){
    var items = Array.isArray(window.PHARMACY_RECENT) ? window.PHARMACY_RECENT.slice() : [];
    if(!showAllActivities && items.length > 3){
      return items.slice(0, 3);
    }
    return items;
  }

  function load(){
    try{
      var activities = getActivities();

      if(!recent) return;

      if(!activities || activities.length === 0){
        recent.innerHTML = '<div class="activity-empty">'
          +'<div class="activity-empty-icon">'
            +'<img src="/capstone/assets/img/drug.png" alt="No activity" style="width:32px;height:32px;object-fit:contain;opacity:0.5;">'
          +'</div>'
          +'<div class="activity-empty-text">No recent activity</div>'
          +'<div class="activity-empty-subtext">Activity will appear here as it happens</div>'
        +'</div>';
        return;
      }

      recent.innerHTML = activities.map(function(it, index){
        var title = String(it.title || '');
        var iconSrc = title.indexOf('Inventory') !== -1 ? '/capstone/assets/img/appointment.png' :
                      title.indexOf('Prescription') !== -1 ? '/capstone/assets/img/prescription.png' :
                      title.indexOf('Report') !== -1 ? '/capstone/assets/img/bar-chart.png' :
                      '/capstone/assets/img/drug.png';
        var lower = title.toLowerCase();
        var statusColor = lower.indexOf('low stock') !== -1 ? '#f59e0b' :
                          lower.indexOf('dispensed') !== -1 ? '#10b981' :
                          lower.indexOf('accepted') !== -1 ? '#3b82f6' :
                          lower.indexOf('rejected') !== -1 ? '#ef4444' :
                          lower.indexOf('inventory') !== -1 ? '#10b981' : '#0a5d39';
        return '<div class="activity-item" style="animation-delay:'+(index*0.1)+'s;">'
          +'<div class="activity-icon" style="background:'+statusColor+'20;color:'+statusColor+';display:flex;align-items:center;justify-content:center;">'
            +'<img src="'+iconSrc+'" alt="Activity" style="width:18px;height:18px;object-fit:contain;">'
          +'</div>'
          +'<div class="activity-content">'
            +'<div class="activity-title">'+escapeHtml(title)+'</div>'
            +'<div class="activity-meta">'+escapeHtml(it.meta||'')+'</div>'
            +'<div class="activity-body">'+escapeHtml(it.body||'')+'</div>'
          +'</div>'
          +'<div class="activity-time">'
            +'<div style="width:4px;height:4px;border-radius:50%;background:'+statusColor+';"></div>'
          +'</div>'
        +'</div>';
      }).join('');

    }catch(err){
      if(recent) recent.textContent = 'Error: '+err.message;
    }
  }
  
  // View All button functionality
  function toggleViewAll(){
    showAllActivities = !showAllActivities;
    if(viewAllBtn){ viewAllBtn.textContent = showAllActivities ? 'Show Less' : 'View All'; }
    load();
  }
  
  if(viewAllBtn){
    viewAllBtn.addEventListener('click', toggleViewAll);
  }
  
  load();
})();
</script>

