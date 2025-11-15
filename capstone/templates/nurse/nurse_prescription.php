<?php
$page='Nurse Prescription';
require_once __DIR__.'/../../config/db.php';
include __DIR__.'/../../includes/header.php';

function np_escape($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function np_load_prescriptions($file){
  if (!file_exists($file)) return [];
  $raw = file_get_contents($file);
  if ($raw === false || $raw === '') return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}
function np_status_badge($status){
  $map = [
    'accepted' => ['Accepted', '#0ea5e9'],
    'acknowledged' => ['Acknowledged', '#2563eb'],
    'done' => ['Done', '#16a34a'],
    'dispensed' => ['Dispensed', '#10b981'],
    'rejected' => ['Rejected', '#6b7280'],
    'pending' => ['Pending', '#f59e0b'],
    'new' => ['New', '#ef4444']
  ];
  $key = strtolower(trim((string)$status));
  $entry = $map[$key] ?? ['Pending', '#f59e0b'];
  return '<span class="badge" style="background:'.$entry[1].';color:#fff;">'.np_escape($entry[0]).'</span>';
}

$ready = [];
try {
  $pdo = get_pdo();
  $sql = "SELECT id, doctor_name, patient_name, medicine, quantity, dosage_strength, description, status, created_at
          FROM prescription
          WHERE lower(status) = 'accepted'
          ORDER BY created_at DESC";
  $stmt = $pdo->query($sql);
  foreach ($stmt as $row) {
    $ready[] = [
      'notification_id' => (int)($row['id'] ?? 0),
      'patient'         => (string)($row['patient_name'] ?? ''),
      'medicine'        => (string)($row['medicine'] ?? ''),
      'quantity'        => (string)($row['quantity'] ?? ''),
      'notes'           => (string)($row['description'] ?? ''),
      'status'          => (string)($row['status'] ?? 'accepted'),
      'updated_at'      => (string)($row['created_at'] ?? ''),
    ];
  }
} catch (Throwable $e) {
  $ready = [];
}
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/nurse/nurse_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/nurse/nurse_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/nurse/nurse_schedule.php"><img src="/capstone/assets/img/appointment.png" alt="Schedule" style="width:18px;height:18px;object-fit:contain;"> Schedule</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/prescription.png" alt="Prescription" style="width:18px;height:18px;object-fit:contain;"> Prescription</a></li>
          <li><a href="/capstone/templates/nurse/nurse_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div class="rx-layout">
    <section class="rx-form">
      <h3>Prescription Details</h3>
      <p class="muted-small">Select an accepted prescription from the list to review the prepared medication exactly as submitted by pharmacy.</p>
      <div id="rxDetailPlaceholder" class="muted" style="padding:12px 0;">No prescription selected yet.</div>
      <div id="rxDetailCard" class="rx-card" style="display:none;padding:16px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;box-shadow:0 2px 6px rgba(15,23,42,0.08);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
          <div>
            <div class="muted-small">Patient</div>
            <div id="rxDetailPatient" style="font-weight:600;font-size:1.05rem;">—</div>
          </div>
          <div id="rxDetailStatus">—</div>
        </div>
        <div style="margin-top:14px;display:grid;gap:10px;">
          <div>
            <div class="muted-small">Medication</div>
            <div id="rxDetailMedicine" style="font-size:1rem;font-weight:600;">—</div>
          </div>
          <div>
            <div class="muted-small">Quantity</div>
            <div id="rxDetailQuantity" style="font-size:1rem;font-weight:600;">—</div>
          </div>
          <div>
            <div class="muted-small">Prepared / Updated</div>
            <div id="rxDetailUpdated">—</div>
          </div>
          <div>
            <div class="muted-small">Notes</div>
            <div id="rxDetailNotes" style="white-space:pre-wrap;">—</div>
          </div>
        </div>
        <div class="rx-actions" id="rxDetailActions" style="margin-top:16px;gap:8px;justify-content:flex-end;">
          <button class="btn btn-outline" id="rxAckBtn" type="button">Acknowledge</button>
          <button class="btn btn-primary" id="rxDoneBtn" type="button">Done</button>
        </div>
      </div>
    </section>

    <aside class="rx-list" id="rxList">
      <div class="appt-list-header" style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
        <h3>Prescription List</h3>
        <div style="display:flex;gap:10px;align-items:center;">
          <a class="btn btn-primary" href="/capstone/templates/nurse/nurse_prescription.php" id="rxRefreshBtn">Refresh</a>
        </div>
      </div>
      <div id="rxLoadingOverlay" style="display:none;min-height:140px;display:none;align-items:center;justify-content:center;">
        <div class="muted" style="font-weight:600;">Loading...</div>
      </div>
      <div id="rxListBody">
        <?php if ($ready): ?>
          <?php foreach ($ready as $r): ?>
            <div class="rx-item">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                <div>
                  <div><strong><?php echo np_escape($r['medicine'] ?? ''); ?></strong> — <?php echo np_escape($r['patient'] ?? ''); ?></div>
                  <div class="muted-small rx-item-meta">Last update <?php echo np_escape($r['updated_at'] ?? $r['time'] ?? ''); ?><?php $route = trim((string)($r['route'] ?? '')); echo $route !== '' ? ' • '.np_escape($route) : ''; ?></div>
                </div>
                <div class="rx-item-status"><?php echo np_status_badge($r['status'] ?? 'pending'); ?></div>
              </div>
              <div class="rx-actions" style="justify-content:flex-start;">
                <button class="btn btn-outline load-ready" 
                  data-id="<?php echo (int)($r['notification_id'] ?? 0); ?>"
                  data-patient="<?php echo np_escape($r['patient'] ?? ''); ?>"
                  data-med="<?php echo np_escape($r['medicine'] ?? ''); ?>"
                  data-quantity="<?php echo np_escape($r['quantity'] ?? ''); ?>"
                  data-notes="<?php echo np_escape($r['notes'] ?? ''); ?>"
                  data-status="<?php echo np_escape($r['status'] ?? ''); ?>"
                  data-updated="<?php echo np_escape($r['updated_at'] ?? $r['time'] ?? ''); ?>">
                  Load
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="rx-item">
            <div><strong>No approved prescriptions yet</strong></div>
            <div class="muted-small">Pharmacist-approved prescriptions will appear here once marked as Accepted.</div>
          </div>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</div>

<script>
(function(){
  var placeholder = document.getElementById('rxDetailPlaceholder');
  var card = document.getElementById('rxDetailCard');
  var fields = {
    patient: document.getElementById('rxDetailPatient'),
    status: document.getElementById('rxDetailStatus'),
    medicine: document.getElementById('rxDetailMedicine'),
    quantity: document.getElementById('rxDetailQuantity'),
    updated: document.getElementById('rxDetailUpdated'),
    notes: document.getElementById('rxDetailNotes')
  };
  var ackBtn = document.getElementById('rxAckBtn');
  var doneBtn = document.getElementById('rxDoneBtn');
  var currentId = null;
  var currentStatus = null;
  var refreshBtn = document.getElementById('rxRefreshBtn');
  var listBody = document.getElementById('rxListBody');
  var loadingOv = document.getElementById('rxLoadingOverlay');

  if(refreshBtn){
    refreshBtn.addEventListener('click', function(){
      if(listBody){ listBody.style.display = 'none'; }
      if(loadingOv){ loadingOv.style.display = 'flex'; }
    });
  }

  function statusBadge(status){
    var map = {
      accepted: { label: 'Accepted', color: '#0ea5e9' },
      acknowledged: { label: 'Acknowledged', color: '#2563eb' },
      done: { label: 'Done', color: '#16a34a' },
      dispensed: { label: 'Dispensed', color: '#10b981' },
      rejected: { label: 'Rejected', color: '#6b7280' },
      pending: { label: 'Pending', color: '#f59e0b' },
      new: { label: 'New', color: '#ef4444' }
    };
    var key = (status || 'accepted').toLowerCase();
    var entry = map[key] || map.accepted;
    return '<span class="badge" style="background:'+entry.color+';color:#fff;">'+entry.label+'</span>';
  }
  function renderDetail(data){
    fields.patient.textContent = data.patient || '—';
    fields.medicine.textContent = data.med || data.medicine || '—';
    fields.quantity.textContent = data.quantity || '—';
    fields.updated.textContent = data.updated || data.time || data.statusTime || '—';
    fields.notes.textContent = data.notes || '—';
    fields.status.innerHTML = statusBadge(data.status || 'accepted');
    if(placeholder){ placeholder.style.display = 'none'; }
    if(card){ card.style.display = 'block'; }
    currentStatus = (data.status || 'accepted').toLowerCase();
    if(ackBtn){ ackBtn.disabled = currentStatus !== 'accepted'; }
    if(doneBtn){ doneBtn.disabled = currentStatus !== 'acknowledged'; }
  }
  Array.prototype.forEach.call(document.querySelectorAll('.load-ready'), function(btn){
    btn.addEventListener('click', function(){
      currentId = parseInt(this.dataset.id || '0', 10) || null;
      renderDetail({
        patient: this.dataset.patient,
        medicine: this.dataset.med,
        med: this.dataset.med,
        quantity: this.dataset.quantity,
        notes: this.dataset.notes,
        status: this.dataset.status,
        updated: this.dataset.updated
      });
    });
  });

  async function updateStatus(newStatus){
    if(!currentId) return;
    try{
      var res = await fetch('/capstone/notifications/pharmacy.php?id='+encodeURIComponent(currentId),{
        method:'PUT',
        headers:{ 'Content-Type':'application/json' },
        body: JSON.stringify({ status: newStatus })
      });
      if(!res.ok){ throw new Error('Failed to update prescription status'); }
      var updated = await res.json();
      renderDetail({
        patient: fields.patient.textContent,
        medicine: fields.medicine.textContent,
        med: fields.medicine.textContent,
        notes: fields.notes.textContent,
        status: updated.status,
        updated: updated.updated_at || updated.time || new Date().toISOString().slice(0,16)
      });
      var badge = document.querySelector('.load-ready[data-id="'+currentId+'"]').closest('.rx-item').querySelector('.rx-item-status');
      if(badge){ badge.innerHTML = statusBadge(updated.status); }
      
      // Send notifications when acknowledging
      if(newStatus === 'acknowledged'){
        await sendAcknowledgeNotifications();
      }
    }catch(err){ alert(err.message); }
  }

  async function sendAcknowledgeNotifications(){
    try{
      var patient = fields.patient.textContent || 'Unknown Patient';
      var medicine = fields.medicine.textContent || 'Unknown Medicine';
      var nurseName = <?php echo json_encode($_SESSION['user']['name'] ?? 'Nurse'); ?>;
      
      // Send notification to pharmacy
      var pharmacyNotification = {
        title: 'Prescription Acknowledged by Nurse',
        body: 'Nurse: ' + nurseName + ' | Patient: ' + patient + ' | Medication: ' + medicine + ' | Status: Acknowledged',
        status: 'pending'
      };
      
      var pharmacyRes = await fetch('/capstone/notifications/pharmacy.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(pharmacyNotification)
      });
      
      // Send notification to doctor
      var doctorNotification = {
        title: 'Prescription Acknowledged by Nurse',
        body: 'Nurse: ' + nurseName + ' | Patient: ' + patient + ' | Medication: ' + medicine + ' | Status: Acknowledged',
        status: 'pending'
      };
      
      var doctorRes = await fetch('/capstone/notifications/pharmacy.php?role=doctor', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(doctorNotification)
      });
      
      if(pharmacyRes.ok && doctorRes.ok){
        alert('Prescription acknowledged and notifications sent to pharmacy and doctor.');
      } else {
        alert('Prescription acknowledged but failed to send some notifications.');
      }
    }catch(err){
      console.error('Failed to send notifications:', err);
      alert('Prescription acknowledged but failed to send notifications.');
    }
  }

  if(ackBtn){ ackBtn.addEventListener('click', function(){ if(currentStatus === 'accepted'){ updateStatus('acknowledged'); } }); }
  if(doneBtn){ doneBtn.addEventListener('click', function(){ if(currentStatus === 'acknowledged'){ updateStatus('done'); } }); }
})();
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>
