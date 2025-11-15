<?php
$page='Supervisor Nurses';
include __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../config/db.php';

function sn_escape($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function sn_lower($s){ return strtolower(trim((string)$s)); }

$pdo = null;
$nurseRecords = [];
try {
  $pdo = get_pdo();
  $stmt = $pdo->query("SELECT * FROM users WHERE role = 'nurse' ORDER BY created_at DESC");
  $nurseRecords = $stmt->fetchAll();
} catch (Throwable $e) {
  $nurseRecords = [];
}

$requestFile = __DIR__.'/../../data/nurse_shift_requests.json';
$requestMap = [];
if (file_exists($requestFile)) {
  $raw = file_get_contents($requestFile);
  $items = json_decode($raw, true);
  if (is_array($items)) {
    foreach ($items as $item) {
      $nameKey = sn_lower($item['nurse'] ?? '');
      $emailKey = sn_lower($item['nurse_email'] ?? '');
      $nurseId = (int)($item['nurse_id'] ?? 0);
      if ($nameKey === '') continue;
      $tsStr = $item['updated_at'] ?? $item['status_changed_at'] ?? $item['created_at'] ?? null;
      $ts = $tsStr ? strtotime((string)$tsStr) : 0;
      $mapKey = $nameKey;
      if ($nurseId > 0) {
        $mapKey = 'id:'.$nurseId;
      } elseif ($emailKey !== '') {
        $mapKey = 'email:'.$emailKey;
      }
      if (!isset($requestMap[$mapKey]) || $ts >= ($requestMap[$mapKey]['ts'] ?? 0)) {
        $requestMap[$mapKey] = [
          'status' => strtolower((string)($item['status'] ?? 'request')),
          'shift' => $item['shift'] ?? '',
          'ward' => $item['ward'] ?? '',
          'time' => $item['time'] ?? '',
          'end_time' => $item['end_time'] ?? '',
          'date' => $item['date'] ?? '',
          'notes' => $item['notes'] ?? '',
          'updated_at' => $tsStr,
          'ts' => $ts,
          'name_key' => $nameKey,
          'email_key' => $emailKey,
          'nurse_id' => $nurseId,
        ];
      }
    }
  }
}

$nurseData = [];
foreach ($nurseRecords as $row) {
  $name = trim((string)($row['full_name'] ?? $row['name'] ?? (($row['first_name'] ?? '').' '.($row['last_name'] ?? ''))));
  if ($name === '') {
    $name = 'Nurse #'.(int)($row['id'] ?? 0);
  }
  $email = trim((string)($row['email'] ?? $row['email_address'] ?? ''));
  $statusCode = 'none';
  $statusLabel = 'No requests yet';
  $unit = '—';
  $shift = '—';
  $updated = null;
  $reqKeyName = sn_lower($name);
  $reqKeyEmail = sn_lower($email);
  $reqKeyId = (int)($row['id'] ?? 0);
  $req = null;
  if ($reqKeyId > 0 && isset($requestMap['id:'.$reqKeyId])) {
    $req = $requestMap['id:'.$reqKeyId];
  } elseif ($reqKeyEmail !== '' && isset($requestMap['email:'.$reqKeyEmail])) {
    $req = $requestMap['email:'.$reqKeyEmail];
  } elseif (isset($requestMap[$reqKeyName])) {
    $req = $requestMap[$reqKeyName];
  }
  if ($req) {
    $statusCode = in_array($req['status'], ['accepted','pending','rejected','request'], true) ? $req['status'] : 'request';
    if ($statusCode === 'accepted') $statusLabel = 'On Duty';
    elseif ($statusCode === 'pending' || $statusCode === 'request') $statusLabel = 'Request';
    elseif ($statusCode === 'rejected') $statusLabel = 'Off Duty';
    $unit = $req['ward'] !== '' ? $req['ward'] : '—';
    $shift = $req['shift'] !== '' ? ucfirst($req['shift']) : '—';
    $updated = $req['updated_at'] ?? null;
  }
  $profileUrl = '/capstone/templates/nurse/nurse_profile.php?id='.(int)($row['id'] ?? 0);
  $nurseData[] = [
    'id' => (int)($row['id'] ?? 0),
    'name' => $name,
    'name_lc' => sn_lower($name),
    'email' => $email,
    'email_lc' => sn_lower($email),
    'unit' => $unit,
    'status_code' => $statusCode,
    'status_label' => $statusLabel,
    'shift' => $shift,
    'updated_at' => $updated,
    'request_date' => isset($req) ? ($req['date'] ?? '') : '',
    'request_time' => isset($req) ? ($req['time'] ?? '') : '',
    'request_end_time' => isset($req) ? ($req['end_time'] ?? '') : '',
    'request_notes' => isset($req) ? ($req['notes'] ?? '') : '',
    'request_status' => $statusCode,
    'profile_url' => $profileUrl,
  ];
}

usort($nurseData, function($a, $b){
  return strcmp($a['name_lc'], $b['name_lc']);
});

$unitOptions = [];
$statusOptions = ['all' => 'All', 'accepted' => 'On Duty', 'pending' => 'Pending', 'rejected' => 'Off Duty', 'none' => 'No requests yet'];
foreach ($nurseData as $item) {
  $u = trim($item['unit']);
  if ($u !== '' && $u !== '—') {
    $unitOptions[sn_lower($u)] = $u;
  }
}
ksort($unitOptions);
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/supervisor/supervisor_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/supervisor/supervisor_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/supervisor/supervisor_schedules.php"><img src="/capstone/assets/img/appointment.png" alt="Schedules" style="width:18px;height:18px;object-fit:contain;"> Schedule</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/nurse.png" alt="Nurses" style="width:18px;height:18px;object-fit:contain;"> List</a></li>
          <li><a href="/capstone/templates/supervisor/supervisor_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div>
    <section class="card" style="margin-bottom:16px;">
      <h2 class="dashboard-title" style="margin:0;">List of Nurses</h2>
      <div class="grid-3" style="display:grid;grid-template-columns:1fr auto;gap:12px;margin-top:8px;">
        <div class="form-field"><label>Search</label><input id="nurseSearch" type="text" placeholder="Name or email" /></div>
        <div class="form-field" style="width:180px;max-width:100%;"><label>Status</label>
          <select id="nurseStatusFilter" style="min-width:0;width:100%;">
            <?php foreach ($statusOptions as $key => $label): ?>
              <option value="<?php echo sn_escape($key); ?>"<?php echo $key==='all' ? ' selected' : ''; ?>><?php echo sn_escape($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </section>

    <section class="card">
      <h3 style="margin:0 0 8px;">Roster</h3>
      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr><th>Name</th><th>Email</th><th>Unit</th><th>Shift</th><th>Status</th><th>Last Update</th><th>Action</th></tr>
          </thead>
          <tbody id="nurseTableBody">
            <?php if (empty($nurseData)): ?>
              <tr><td colspan="7" class="muted" style="text-align:center;padding:18px 0;">No nurses found.</td></tr>
            <?php else: ?>
              <?php foreach ($nurseData as $n):
                $statusCode = $n['status_code'];
                $badgeColor = '#94a3b8';
                if ($statusCode === 'accepted') $badgeColor = '#10b981';
                elseif ($statusCode === 'pending') $badgeColor = '#f59e0b';
                elseif ($statusCode === 'rejected') $badgeColor = '#ef4444';
                $updated = $n['updated_at'] ? date('M d, Y H:i', strtotime($n['updated_at'])) : '—';
              ?>
              <tr
                data-name="<?php echo sn_escape($n['name_lc']); ?>"
                data-email="<?php echo sn_escape($n['email_lc']); ?>"
                data-unit="<?php echo sn_escape(sn_lower($n['unit'])); ?>"
                data-status="<?php echo sn_escape($statusCode); ?>"
                data-full-name="<?php echo sn_escape($n['name']); ?>"
                data-email-display="<?php echo sn_escape($n['email']); ?>"
                data-unit-label="<?php echo sn_escape($n['unit']); ?>"
                data-shift-label="<?php echo sn_escape($n['shift']); ?>"
                data-status-label="<?php echo sn_escape($n['status_label']); ?>"
                data-updated-label="<?php echo sn_escape($updated); ?>"
                data-request-date="<?php echo sn_escape($n['request_date']); ?>"
                data-request-time="<?php echo sn_escape($n['request_time']); ?>"
                data-request-end-time="<?php echo sn_escape($n['request_end_time']); ?>"
                data-request-notes="<?php echo sn_escape($n['request_notes']); ?>"
                data-request-status="<?php echo sn_escape($n['request_status']); ?>"
                data-profile-url="<?php echo sn_escape($n['profile_url']); ?>"
              >
                <td><?php echo sn_escape($n['name']); ?></td>
                <td><?php echo sn_escape($n['email']); ?></td>
                <td><?php echo sn_escape($n['unit']); ?></td>
                <td><?php echo sn_escape($n['shift']); ?></td>
                <td><span class="badge" style="background:<?php echo $badgeColor; ?>;color:#fff;"><?php echo sn_escape($n['status_label']); ?></span></td>
                <td><?php echo sn_escape($updated); ?></td>
                <td><a class="btn btn-outline" href="#" data-action="snView">View Details</a></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <tbody id="nurseEmptyState" style="<?php echo empty($nurseData) ? '' : 'display:none;'; ?>">
            <tr><td colspan="7" class="muted" style="text-align:center;padding:18px 0;">No nurses match the selected filters.</td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <div id="snDetailModal" style="display:none;position:fixed;inset:0;z-index:40;align-items:center;justify-content:center;">
    <div class="sn-modal-backdrop" style="position:absolute;inset:0;background:rgba(15,23,42,0.45);"></div>
    <div class="sn-modal-dialog" style="position:relative;background:#fff;border-radius:12px;box-shadow:0 12px 32px rgba(15,23,42,0.2);max-width:460px;width:90%;padding:20px;z-index:41;">
      <button type="button" id="snModalClose" style="position:absolute;top:12px;right:12px;border:none;background:transparent;font-size:22px;cursor:pointer;line-height:1;color:#64748b;">&times;</button>
      <h3 id="snModalName" style="margin-top:0;margin-bottom:4px;">Nurse</h3>
      <div id="snModalEmail" class="muted-small" style="margin-bottom:16px;">—</div>
      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:16px;">
        <div><div class="muted-small">Station</div><div id="snModalUnit" style="font-weight:600;">—</div></div>
        <div><div class="muted-small">Date</div><div id="snModalShift" style="font-weight:600;">—</div></div>
        <div><div class="muted-small">Time</div><div id="snModalStatus" style="font-weight:600;">—</div></div>
      </div>
      <div style="margin-bottom:16px;">
        <div class="muted-small">Supervisor Notes</div>
        <div id="snModalNotes" style="white-space:pre-line;">—</div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button class="btn" type="button" id="snModalCloseFooter">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var tableBody = document.getElementById('nurseTableBody');
  var emptyState = document.getElementById('nurseEmptyState');
  var searchInput = document.getElementById('nurseSearch');
  var statusFilter = document.getElementById('nurseStatusFilter');
  if(!tableBody || !searchInput || !statusFilter) return;

  var rows = Array.prototype.slice.call(tableBody.querySelectorAll('tr'));

  function matches(row){
    var q = (searchInput.value || '').trim().toLowerCase();
    var status = statusFilter.value || 'all';
    var name = row.getAttribute('data-name') || '';
    var email = row.getAttribute('data-email') || '';
    var rowStatus = row.getAttribute('data-status') || '';

    if(q && name.indexOf(q) === -1 && email.indexOf(q) === -1) return false;
    if(status !== 'all'){
      if(status === 'none') return rowStatus === 'none';
      if(rowStatus !== status) return false;
    }
    return true;
  }

  function applyFilters(){
    var visible = 0;
    rows.forEach(function(row){
      if(matches(row)){
        row.style.display = '';
        visible++;
      } else {
        row.style.display = 'none';
      }
    });
    if(emptyState){
      emptyState.style.display = visible === 0 ? '' : 'none';
    }
  }

  searchInput.addEventListener('input', applyFilters);
  statusFilter.addEventListener('change', applyFilters);

  var modal = document.getElementById('snDetailModal');
  if(!modal) return;
  var closeButtons = [
    document.getElementById('snModalClose'),
    document.getElementById('snModalCloseFooter')
  ];
  var backdrop = modal.querySelector('.sn-modal-backdrop');
  var fieldName = document.getElementById('snModalName');
  var fieldEmail = document.getElementById('snModalEmail');
  var fieldUnit = document.getElementById('snModalUnit');
  var fieldShift = document.getElementById('snModalShift');
  var fieldStatus = document.getElementById('snModalStatus');
  var fieldNotes = document.getElementById('snModalNotes');

  function fillField(el, value){
    if(!el) return;
    el.textContent = value && value.trim() !== '' ? value : '—';
  }

  function fmtTime(t){
    if(!t) return '—';
    var parts = (t+'').split(':');
    if(parts.length < 2) return t;
    var h = parseInt(parts[0],10);
    var m = parts[1];
    var ampm = h >= 12 ? 'pm' : 'am';
    h = h % 12; if(h === 0) h = 12;
    return h+':'+m+' '+ampm;
  }

  function openModalFromRow(row){
    if(!row) return;
    fillField(fieldName, row.getAttribute('data-full-name') || 'Nurse');
    fillField(fieldEmail, row.getAttribute('data-email-display') || '');
    fillField(fieldUnit, row.getAttribute('data-unit-label') || '');
    fillField(fieldShift, row.getAttribute('data-request-date') || '');
    var t1 = row.getAttribute('data-request-time') || '';
    var t2 = row.getAttribute('data-request-end-time') || '';
    var range = t1 ? fmtTime(t1) : '—';
    if(t2){ range += ' - ' + fmtTime(t2); }
    fillField(fieldStatus, range);
    fillField(fieldNotes, row.getAttribute('data-request-notes') || '');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }

  function closeModal(){
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }

  closeButtons.forEach(function(btn){
    if(btn){ btn.addEventListener('click', closeModal); }
  });
  if(backdrop){ backdrop.addEventListener('click', closeModal); }
  document.addEventListener('keydown', function(evt){
    if(evt.key === 'Escape' && modal.style.display !== 'none'){
      closeModal();
    }
  });

  tableBody.addEventListener('click', function(evt){
    var trigger = evt.target.closest('[data-action="snView"]');
    if(!trigger) return;
    evt.preventDefault();
    var row = trigger.closest('tr');
    openModalFromRow(row);
  });
})();
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>
