<?php $page='Doctor Reports'; include __DIR__.'/../../includes/header.php'; ?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/doctor/doctor_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/doctor/doctor_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/doctor/doctor_appointment.php"><img src="/capstone/assets/img/appointment.png" alt="Appointment" style="width:18px;height:18px;object-fit:contain;"> Appointment</a></li>
          <li><a href="/capstone/templates/doctor/doctor_prescription.php"><img src="/capstone/assets/img/prescription.png" alt="Prescription" style="width:18px;height:18px;object-fit:contain;"> Prescription</a></li>
          <li><a href="/capstone/templates/doctor/doctor_records.php"><img src="/capstone/assets/img/medical-file.png" alt="Patient Record" style="width:18px;height:18px;object-fit:contain;"> Patient Record</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div>
    <?php
      // Preload per-doctor data for reports (ownership enforced)
      require_once __DIR__.'/../../config/db.php';
      $repRx = [];
      $repAppts = [];
      try {
        $pdo = get_pdo();
        $uid = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
        // Prescriptions created by this doctor
        $stmtRx = $pdo->prepare('SELECT patient_name, medicine, dosage_strength, quantity, created_at FROM prescription WHERE created_by_user_id = :uid ORDER BY created_at DESC LIMIT 1000');
        $stmtRx->execute([':uid'=>$uid]);
        $rowsRx = $stmtRx->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rowsRx as $r) {
          $repRx[] = [
            'time' => substr((string)($r['created_at'] ?? ''),0,19),
            'body' => 'Patient: '.trim((string)($r['patient_name'] ?? '')).' | Medicine: '.trim((string)($r['medicine'] ?? '')).(empty($r['dosage_strength'])?'':(' '.trim((string)$r['dosage_strength']))).' | Qty: '.trim((string)($r['quantity'] ?? ''))
          ];
        }
        // Appointments created by this doctor
        $stmtAp = $pdo->prepare('SELECT patient, "date", "time", done FROM appointments WHERE created_by_user_id = :uid ORDER BY "date" DESC, "time" DESC LIMIT 1000');
        $stmtAp->execute([':uid'=>$uid]);
        $rowsAp = $stmtAp->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rowsAp as $a) {
          $repAppts[] = [
            'date' => (string)($a['date'] ?? ''),
            'time' => (string)($a['time'] ?? ''),
            'patient' => (string)($a['patient'] ?? ''),
            'done' => (bool)($a['done'] ?? false)
          ];
        }
      } catch (Throwable $e) {
        $repRx = [];
        $repAppts = [];
      }
    ?>
    <section class="card" style="margin-bottom:16px;">
      <h2 class="dashboard-title" style="margin:0;">Reports</h2>
      <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:16px;align-items:center;">
        <div class="report-nav">
          <a class="btn btn-outline report-nav-btn" href="#" id="prevMonth" aria-label="Previous month">
            <span class="report-nav-icon">&#10094;</span>
          </a>
          <div class="report-nav-label-group">
            <div class="report-nav-month"><span id="currentMonthYear"></span></div>
            <div class="report-nav-year">
              <a class="btn btn-outline report-nav-btn" href="#" id="nextMonth" aria-label="Next month">
                <span class="report-nav-icon">&#10095;</span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section style="margin-bottom:16px;">
      <div class="stat-cards" style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
        <div class="stat-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
          <h4 style="margin:0 0 8px;color:#0f172a;font-size:1rem;font-weight:600;">Patient</h4>
          <div class="muted-small" style="color:#64748b;font-size:0.85rem;margin-bottom:8px;">This period</div>
          <div class="stat-value" id="statPatients" style="font-size:2rem;font-weight:700;color:#0a5d39;">0</div>
        </div>
        <div class="stat-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
          <h4 style="margin:0 0 8px;color:#0f172a;font-size:1rem;font-weight:600;">Appointment</h4>
          <div class="muted-small" style="color:#64748b;font-size:0.85rem;margin-bottom:8px;">This period</div>
          <div class="stat-value" id="statAppt" style="font-size:2rem;font-weight:700;color:#0a5d39;">0</div>
        </div>
        <div class="stat-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
          <h4 style="margin:0 0 8px;color:#0f172a;font-size:1rem;font-weight:600;">Prescription</h4>
          <div class="muted-small" style="color:#64748b;font-size:0.85rem;margin-bottom:8px;">This period</div>
          <div class="stat-value" id="statRx" style="font-size:2rem;font-weight:700;color:#0a5d39;">0</div>
        </div>
      </div>
    </section>

    <section style="margin-bottom:16px;">
      <div class="card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
        <h3 style="margin:0 0 16px;color:#0f172a;font-size:1.1rem;font-weight:600;">Top Patients</h3>
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="border-bottom:1px solid #e5e7eb;">
              <th style="padding:8px 0;text-align:left;font-weight:600;color:#0f172a;font-size:0.9rem;">Patient</th>
              <th style="padding:8px 0;text-align:right;font-weight:600;color:#0f172a;font-size:0.9rem;">Count</th>
            </tr>
          </thead>
          <tbody id="topList"></tbody>
        </table>
      </div>
    </section>
  </div>
</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>


<script>
  // Inject server data
  window.REP_RX = <?php echo json_encode($repRx, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
  window.REP_APPTS = <?php echo json_encode($repAppts, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
</script>
<script>
(function(){
  var currentMonthYear = document.getElementById('currentMonthYear');
  var prevMonthBtn = document.getElementById('prevMonth');
  var nextMonthBtn = document.getElementById('nextMonth');
  var statAppt = document.getElementById('statAppt');
  var statRx = document.getElementById('statRx');
  var statPatients = document.getElementById('statPatients');
  var topList = document.getElementById('topList');

  var rx = Array.isArray(window.REP_RX) ? window.REP_RX.slice() : [];
  var appts = Array.isArray(window.REP_APPTS) ? window.REP_APPTS.slice() : [];
  var currentDate = new Date();

  function escapeHtml(s){ return (s||'').replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
  function getDatePart(ts){ return (ts||'').slice(0,10); }
  function getCurrentMonthYear(){
    var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                     'July', 'August', 'September', 'October', 'November', 'December'];
    return monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
  }
  
  function updateMonthDisplay(){
    if(currentMonthYear) {
      currentMonthYear.textContent = getCurrentMonthYear();
    }
  }
  
  function navigateMonth(direction){
    if(direction === 'prev'){
      currentDate.setMonth(currentDate.getMonth() - 1);
    } else if(direction === 'next'){
      currentDate.setMonth(currentDate.getMonth() + 1);
    }
    updateMonthDisplay();
    renderStats();
  }
  function parseRxFields(n){
    var patient='', med='';
    var body = n.body||'';
    body.split('|').forEach(function(part){
      var p = part.trim();
      if(p.toLowerCase().startsWith('patient:')){ patient = p.split(':')[1]?.trim()||''; }
      if(p.toLowerCase().startsWith('medicine:')){ med = p.split(':')[1]?.trim()||''; }
    });
    return { patient: patient, medicine: med };
  }
  function groupKey(dateStr){
    // Fixed to month buckets (YYYY-MM)
    return (dateStr||'').slice(0,7);
  }
  function renderStats(){
    // Filter data by selected month
    var selectedMonth = currentDate.getFullYear() + '-' + String(currentDate.getMonth() + 1).padStart(2, '0');
    var rxIn = rx.filter(function(n){ 
      var recordMonth = (n.time||'').slice(0,7); // YYYY-MM
      return recordMonth === selectedMonth;
    });
    var apIn = appts.filter(function(a){ 
      var recordMonth = (a.date||'').slice(0,7); // YYYY-MM
      return recordMonth === selectedMonth;
    });
    // Totals
    statRx.textContent = rxIn.length;
    statAppt.textContent = apIn.length;
    // Unique patients
    var setP = {};
    rxIn.forEach(function(n){ var f = parseRxFields(n); if(f.patient) setP[f.patient]=1; });
    apIn.forEach(function(a){ if(a.patient) setP[a.patient]=1; });
    statPatients.textContent = Object.keys(setP).length;
    // Top patients (from both prescriptions and appointments)
    var counts = {};
    rxIn.forEach(function(n){ var f = parseRxFields(n); var p = f.patient || ''; if(!p) return; counts[p] = (counts[p]||0)+1; });
    apIn.forEach(function(a){ var p = a.patient || ''; if(!p) return; counts[p] = (counts[p]||0)+1; });
    var top = Object.keys(counts).map(function(k){ return { name:k, cnt:counts[k] }; }).sort(function(a,b){ return b.cnt - a.cnt; }).slice(0,10);
    if(topList){
      topList.innerHTML = '';
      if(top.length===0){ topList.innerHTML = '<tr><td colspan="2" class="muted" style="text-align:center;padding:20px;color:#64748b;">No data</td></tr>'; }
      top.forEach(function(it){
        var tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid #f1f5f9';
        tr.innerHTML = '<td style="padding:8px 0;color:#0f172a;font-size:0.9rem;">'+escapeHtml(it.name)+'</td><td style="padding:8px 0;text-align:right;color:#64748b;font-size:0.9rem;font-weight:600;">'+escapeHtml(String(it.cnt))+'</td>';
        topList.appendChild(tr);
      });
    }
  }
  async function load(){
    try{
      // Set current month/year display
      updateMonthDisplay();
      // rx and appts were preloaded from server per-doctor
      renderStats();
    }catch(err){
      if(statAppt) statAppt.textContent='-';
      if(statRx) statRx.textContent='-';
      if(statPatients) statPatients.textContent='-';
      if(topList) topList.innerHTML = '<tr><td colspan="2" class="muted" style="text-align:center;">'+escapeHtml(err.message)+'</td></tr>';
    }
  }
  
  // Add event listeners for navigation
  if(prevMonthBtn) prevMonthBtn.addEventListener('click', function(){ navigateMonth('prev'); });
  if(nextMonthBtn) nextMonthBtn.addEventListener('click', function(){ navigateMonth('next'); });
  
  load();
})();
</script>
