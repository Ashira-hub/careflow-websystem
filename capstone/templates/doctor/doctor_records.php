<?php $page='Doctor Records'; include __DIR__.'/../../includes/header.php'; ?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/doctor/doctor_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/doctor/doctor_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/doctor/doctor_appointment.php"><img src="/capstone/assets/img/appointment.png" alt="Appointment" style="width:18px;height:18px;object-fit:contain;"> Appointment</a></li>
          <li><a href="/capstone/templates/doctor/doctor_prescription.php"><img src="/capstone/assets/img/prescription.png" alt="Prescription" style="width:18px;height:18px;object-fit:contain;"> Prescription</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/medical-file.png" alt="Patient Record" style="width:18px;height:18px;object-fit:contain;"> Patient Record</a></li>
          <li><a href="/capstone/templates/doctor/doctor_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <?php
    require_once __DIR__.'/../../config/db.php';
    $rxItems = [];
    $apItems = [];
    try {
      $pdo = get_pdo();
      $uid = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
      // Prescriptions created by the logged-in doctor
      $stmtRx = $pdo->prepare("SELECT patient_name, medicine, dosage_strength, quantity, created_at FROM prescription WHERE created_by_user_id = :uid ORDER BY created_at DESC LIMIT 500");
      $stmtRx->bindValue(':uid', $uid, PDO::PARAM_INT);
      $stmtRx->execute();
      $rowsRx = $stmtRx->fetchAll(PDO::FETCH_ASSOC) ?: [];
      foreach ($rowsRx as $r) {
        $patient = trim((string)($r['patient_name'] ?? ''));
        $med = trim((string)($r['medicine'] ?? ''));
        $dose = trim((string)($r['dosage_strength'] ?? ''));
        $qty = isset($r['quantity']) ? (string)$r['quantity'] : '';
        $parts = [];
        if ($patient !== '') $parts[] = 'Patient: '.$patient;
        if ($med !== '') $parts[] = 'Medicine: '.$med.($dose ? (' '.$dose) : '');
        if ($qty !== '') $parts[] = 'Qty: '.$qty;
        $body = implode(' | ', $parts);
        $rxItems[] = [
          'time' => substr((string)($r['created_at'] ?? ''), 0, 16),
          'body' => $body,
          'status' => 'submitted'
        ];
      }
      // Appointments created by the logged-in doctor
      $stmtAp = $pdo->prepare("SELECT patient, \"date\", \"time\", notes, done FROM appointments WHERE created_by_user_id = :uid ORDER BY id DESC LIMIT 500");
      $stmtAp->bindValue(':uid', $uid, PDO::PARAM_INT);
      $stmtAp->execute();
      $rowsAp = $stmtAp->fetchAll(PDO::FETCH_ASSOC) ?: [];
      foreach ($rowsAp as $a) {
        $apItems[] = [
          'patient' => (string)($a['patient'] ?? ''),
          'date' => (string)($a['date'] ?? ''),
          'time' => (string)($a['time'] ?? ''),
          'notes' => (string)($a['notes'] ?? ''),
          'done' => (bool)($a['done'] ?? false)
        ];
      }
    } catch (Throwable $e) {
      $rxItems = [];
      $apItems = [];
    }
  ?>

  <div>
    <section class="card" style="margin-bottom:16px;">
      <h2 class="dashboard-title" style="margin:0;">Patient Records</h2>
      <div class="grid-3" style="display:grid;grid-template-columns:1fr auto;gap:12px;margin-top:8px;align-items:end;">
        <div class="form-field">
          <label for="filter_patient">Search patient</label>
          <input type="text" id="filter_patient" placeholder="Type a name..." style="width:100%;" />
        </div>
        <div class="form-field">
          <label for="filter_date">Filter</label>
          <input type="date" id="filter_date" style="width:160px;" />
        </div>
      </div>
    </section>

    <section class="card">
      <div>
        <h3 style="margin:0;">Patient Records</h3>
      </div>
      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr style="background:#f8fafc;border-bottom:2px solid #e5e7eb;">
            <th style="padding:12px;text-align:left;font-weight:600;color:#0f172a;">Patient</th>
            <th style="padding:12px;text-align:center;font-weight:600;color:#0f172a;">Records</th>
            <th style="padding:12px;text-align:left;font-weight:600;color:#0f172a;">Date</th>
            <th style="padding:12px;text-align:left;font-weight:600;color:#0f172a;">Time</th>
            <th style="padding:12px;text-align:center;font-weight:600;color:#0f172a;">Action</th>
          </tr>
        </thead>
        <tbody id="drxBodyRows">
          <tr><td colspan="5" class="muted" style="text-align:center;padding:20px;">No records yet.</td></tr>
        </tbody>
      </table>
    </section>
  </div>
  <script>
    // Inject DB-backed data for this doctor (ownership enforced server-side)
    window.DRX_RX = <?php echo json_encode($rxItems, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
    window.DRX_APPTS = <?php echo json_encode($apItems, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
  </script>
</div>

<!-- Details Modal -->
<div id="drxModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:16px;box-shadow:0 25px 50px rgba(0,0,0,0.25);max-width:800px;width:100%;max-height:90vh;overflow:hidden;position:relative;">
    <!-- Modal Header -->
    <div style="background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;padding:20px 24px;border-radius:16px 16px 0 0;">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:48px;height:48px;border-radius:12px;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:24px;">📋</div>
        <div style="flex:1;">
          <h3 id="drxTitle" style="margin:0;font-size:1.25rem;font-weight:700;">Medical History</h3>
          <div id="drxTime" style="margin:4px 0 0;font-size:0.9rem;opacity:0.9;"></div>
        </div>
        <button id="drxClose" style="background:rgba(255,255,255,0.2);border:none;color:#fff;width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.3)'" onmouseout="this.style.backgroundColor='rgba(255,255,255,0.2)'">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6L6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>
    
    <!-- Modal Body -->
    <div style="padding:24px;max-height:calc(90vh - 120px);overflow-y:auto;">
      <div id="drxBody" style="margin-bottom:16px;"></div>
      <div id="drxMeta" style="padding:16px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;font-size:0.9rem;color:#64748b;"></div>
    </div>
  </div>
</div>

<script>
(function(){
  var modal = document.getElementById('drxModal');
  var mTitle = document.getElementById('drxTitle');
  var mTime = document.getElementById('drxTime');
  var mBody = document.getElementById('drxBody');
  var mMeta = document.getElementById('drxMeta');
  var bClose = document.getElementById('drxClose');
  var table = document.querySelector('section.card table');
  var tbody = document.getElementById('drxBodyRows');
  var fPatient = document.getElementById('filter_patient');
  var fDate = document.getElementById('filter_date');
  
  var cache = [];
  var appts = [];
  function escapeHtml(s){ return (s||'').replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
  function parseFields(n){
    var patient='', med='', dose='';
    var body = n.body||'';
    body.split('|').forEach(function(part){
      part = part.trim();
      if(part.toLowerCase().startsWith('patient:')){ patient = part.split(':')[1]?.trim()||''; }
      if(part.toLowerCase().startsWith('medicine:')){ med = part.split(':')[1]?.trim()||''; }
      if(part.toLowerCase().startsWith('qty:')){ dose = (dose? (dose+' • '):'') + part; }
    });
    return { patient: patient, medicine: med, dosage: dose };
  }
  function statusText(n){
    var s = (n.status||'new');
    if(s==='accepted') return 'Accepted';
    if(s==='rejected') return 'Rejected';
    if(s==='dispensed') return 'Dispensed';
    return 'Submitted';
  }
  function formatApptRow(a){
    var d = (a.date||''); var t = (a.time||''); var dt = (d + (t? (' '+t):''));
    var status = a.done ? 'Done' : 'Scheduled';
    return {
      type: 'appointment',
      time: dt,
      patient: a.patient||'',
      medication: 'Appointment',
      dosage: (a.notes||'').trim(),
      status: status
    };
  }
  function buildRows(){
    var rows = [];
    cache.forEach(function(n){
      var f = parseFields(n);
      rows.push({ type: 'prescription', time: n.time||'', patient: f.patient||'', medication: f.medicine||'', dosage: f.dosage||'', status: statusText(n) });
    });
    appts.forEach(function(a){ rows.push(formatApptRow(a)); });
    return rows;
  }
  function passesFilters(r){
    var p = (fPatient && fPatient.value || '').trim().toLowerCase();
    var d = (fDate && fDate.value || '').trim(); // YYYY-MM-DD
    if(p){
      var hay = (r.patient+' '+r.medication+' '+(r.dosage||'')).toLowerCase();
      if(hay.indexOf(p) === -1) return false;
    }
    if(d){
      // r.time starts with YYYY-MM-DD for both sources
      if((r.time||'').indexOf(d) !== 0) return false;
    }
    return true;
  }
  function renderGrouped(rows){
    tbody.innerHTML='';
    if(rows.length===0){ tbody.innerHTML = '<tr><td colspan="5" class="muted" style="text-align:center;">No records yet.</td></tr>'; return; }
    var groups = {};
    rows.forEach(function(r){
      var key = (r.patient||'Unknown patient');
      if(!groups[key]) groups[key] = [];
      groups[key].push(r);
    });
    var patientNames = Object.keys(groups).sort(function(a,b){ return a.localeCompare(b); });
    patientNames.forEach(function(pName){
      var items = groups[pName].slice().sort(function(a,b){ return (a.time>b.time?-1:(a.time<b.time?1:0)); });
      var latestRecord = items.length > 0 ? items[0] : null;
      var latestDate = latestRecord ? latestRecord.time.split(' ')[0] : 'No records';
      var latestTime = latestRecord ? latestRecord.time.split(' ')[1] || '' : '';
      
      var tr = document.createElement('tr');
      tr.className = 'patient-row';
      tr.setAttribute('data-patient', pName);
      tr.style.cssText = 'transition:background-color 0.2s ease;';
      tr.addEventListener('mouseenter', function(){ this.style.backgroundColor = '#f8fafc'; });
      tr.addEventListener('mouseleave', function(){ this.style.backgroundColor = 'transparent'; });
      tr.innerHTML = '<td style="padding:12px;border-bottom:1px solid #e5e7eb;vertical-align:middle;"><strong style="color:#0f172a;">'+escapeHtml(pName)+'</strong></td>'+
                     '<td style="padding:12px;text-align:center;border-bottom:1px solid #e5e7eb;vertical-align:middle;"><span class="badge" style="background:#e8fff1;color:#0a5d39;border:1px solid #b8f0cf;padding:4px 8px;border-radius:6px;font-size:0.8rem;font-weight:500;">'+items.length+' record(s)</span></td>'+
                     '<td style="padding:12px;color:#64748b;border-bottom:1px solid #e5e7eb;vertical-align:middle;">'+escapeHtml(latestDate)+'</td>'+
                     '<td style="padding:12px;color:#64748b;border-bottom:1px solid #e5e7eb;vertical-align:middle;">'+escapeHtml(latestTime)+'</td>'+
                     '<td style="padding:12px;text-align:center;border-bottom:1px solid #e5e7eb;vertical-align:middle;"><button class="btn btn-primary view-patient-btn" data-patient="'+escapeHtml(pName)+'" style="padding:6px 12px;font-size:0.85rem;">View History</button></td>';
      tbody.appendChild(tr);
    });
  }
  function applyAndRender(){
    var rows = buildRows();
    var filtered = rows.filter(passesFilters);
    renderGrouped(filtered);
  }
  async function load(){
    try{
      // Use data injected from server-side queries scoped by created_by_user_id
      cache = Array.isArray(window.DRX_RX) ? window.DRX_RX : [];
      appts = Array.isArray(window.DRX_APPTS) ? window.DRX_APPTS : [];
      applyAndRender();
    }catch(err){
      tbody.innerHTML = '<tr><td colspan="5" class="muted" style="text-align:center;">'+escapeHtml(err.message)+'</td></tr>';
    }
  }
  function openModal(patientName, patientRecords){
    mTitle.textContent = 'Medical History - ' + patientName;
    mTime.textContent = patientRecords.length + ' record(s) found';
    
    var historyHtml = '<div style="display:grid;gap:16px;">';
    if(patientRecords.length === 0){
      historyHtml += '<div style="text-align:center;padding:60px 20px;color:#64748b;">';
      historyHtml += '<div style="width:80px;height:80px;border-radius:50%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:32px;">📋</div>';
      historyHtml += '<div style="font-size:1.2rem;font-weight:600;margin-bottom:8px;color:#0f172a;">No Records Found</div>';
      historyHtml += '<div style="font-size:0.95rem;">No medical records available for this patient.</div>';
      historyHtml += '</div>';
    } else {
      var apptItems = patientRecords.filter(function(r){ return r.type === 'appointment'; });
      var rxItems = patientRecords.filter(function(r){ return r.type === 'prescription'; });

      if(apptItems.length){
        historyHtml += '<div style="margin:8px 0 4px 0;font-weight:700;color:#0a5d39;">Appointments</div>';
        apptItems.forEach(function(record){
          var recordDate = record.time ? record.time.split(' ')[0] : '';
          var recordTime = record.time ? record.time.split(' ')[1] : '';
          historyHtml += '<div style="border:1px solid #e5e7eb;border-radius:16px;padding:16px;background:#fff;box-shadow:0 2px 4px rgba(0,0,0,0.04);margin-top:8px;">';
          historyHtml += '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">';
          historyHtml += '<div style="font-weight:600;color:#0f172a;">'+escapeHtml(recordDate)+'</div>';
          if(recordTime) historyHtml += '<div style="color:#64748b;font-size:0.9rem;">'+escapeHtml(recordTime)+'</div>';
          historyHtml += '</div>';
          historyHtml += '<div style="display:flex;gap:12px;color:#64748b;font-size:0.9rem;">';
          historyHtml += '<div><strong>Status:</strong> '+escapeHtml(record.status)+'</div>';
          if(record.dosage) historyHtml += '<div><strong>Notes:</strong> '+escapeHtml(record.dosage)+'</div>';
          historyHtml += '</div>';
          historyHtml += '</div>';
        });
      }

      if(rxItems.length){
        historyHtml += '<div style="margin:16px 0 4px 0;font-weight:700;color:#0a5d39;">Prescriptions</div>';
        rxItems.forEach(function(record){
          var recordDate = record.time ? record.time.split(' ')[0] : '';
          var recordTime = record.time ? record.time.split(' ')[1] : '';
          historyHtml += '<div style="border:1px solid #e5e7eb;border-radius:16px;padding:16px;background:#fff;box-shadow:0 2px 4px rgba(0,0,0,0.04);margin-top:8px;">';
          historyHtml += '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">';
          historyHtml += '<div style="font-weight:600;color:#0f172a;">'+escapeHtml(recordDate)+'</div>';
          if(recordTime) historyHtml += '<div style="color:#64748b;font-size:0.9rem;">'+escapeHtml(recordTime)+'</div>';
          historyHtml += '</div>';
          historyHtml += '<div style="display:grid;gap:8px;color:#64748b;font-size:0.9rem;">';
          if(record.medication) historyHtml += '<div><strong>Medicine:</strong> '+escapeHtml(record.medication)+'</div>';
          if(record.dosage) historyHtml += '<div><strong>Dosage/Qty:</strong> '+escapeHtml(record.dosage)+'</div>';
          historyHtml += '<div><strong>Status:</strong> '+escapeHtml(record.status)+'</div>';
          historyHtml += '</div>';
          historyHtml += '</div>';
        });
      }
    }
    historyHtml += '</div>';
    
    mBody.innerHTML = historyHtml;
    mMeta.textContent = 'Complete medical history for ' + patientName;
    modal.style.display = 'flex';
  }
  function closeModal(){ modal.style.display='none'; }
  if(bClose){ bClose.addEventListener('click', closeModal); }
  if(modal){ modal.addEventListener('click', function(e){ if(e.target===modal) closeModal(); }); }
  if(table){
    table.addEventListener('click', function(e){
      var btn = e.target.closest('.view-patient-btn');
      if(!btn) return;
      e.preventDefault();
      
      var patientName = btn.getAttribute('data-patient');
      if(!patientName) return;
      
      // Get all records for this patient
      var allRows = buildRows();
      var patientRecords = allRows.filter(function(r){
        return (r.patient||'').trim() === patientName;
      }).sort(function(a,b){ 
        return (a.time>b.time?-1:(a.time<b.time?1:0)); 
      });
      
      openModal(patientName, patientRecords);
    });
  }
  if(fPatient){ fPatient.addEventListener('input', function(){ applyAndRender(); }); }
  if(fDate){ fDate.addEventListener('change', function(){ applyAndRender(); }); }
  
  load();
})();
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>

