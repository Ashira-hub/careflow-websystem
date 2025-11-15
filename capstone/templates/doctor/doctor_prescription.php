<?php $page='Doctor Prescription'; include __DIR__.'/../../includes/header.php'; ?>
<?php
require_once __DIR__.'/../../config/db.php';
$patientOptions = [];
$selectedPatient = isset($_GET['patient']) ? (string)$_GET['patient'] : '';
try {
  $pdo = get_pdo();
  $uid = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
  $stmt = $pdo->prepare("SELECT DISTINCT patient
                       FROM appointments
                       WHERE COALESCE(done, false) = false
                         AND patient IS NOT NULL AND patient <> ''
                         AND created_by_user_id = :uid
                       ORDER BY patient ASC");
  $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
  $stmt->execute();
  $patientOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) { $patientOptions = []; }
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/doctor/doctor_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/doctor/doctor_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/doctor/doctor_appointment.php"><img src="/capstone/assets/img/appointment.png" alt="Appointment" style="width:18px;height:18px;object-fit:contain;"> Appointment</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/prescription.png" alt="Prescription" style="width:18px;height:18px;object-fit:contain;"> Prescription</a></li>
          <li><a href="/capstone/templates/doctor/doctor_records.php"><img src="/capstone/assets/img/medical-file.png" alt="Patient Record" style="width:18px;height:18px;object-fit:contain;"> Patient Record</a></li>
          <li><a href="/capstone/templates/doctor/doctor_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    <script>
window.addEventListener('DOMContentLoaded', function(){
  var form = document.querySelector('.rx-form form');
  if(!form) return;
  form.addEventListener('submit', async function(e){
    e.preventDefault();
    var doctor = (document.getElementById('doctor')||{}).value||'';
    var patient = (document.getElementById('patient')||{}).value||'';
    var medicine = (document.getElementById('medicine')||{}).value||'';
    var qty = (document.getElementById('quantity_prescribed')||{}).value||'';
    var dose = (document.getElementById('dosage_strength')||{}).value||'';
    var desc = (document.getElementById('description')||{}).value||'';
    
    // Validate required fields
    if(!doctor || !patient || !medicine || !qty || !dose){
      alert('Please fill in all required fields: Doctor, Patient, Medicine, Quantity, and Dosage.');
      return;
    }
    
    var title = 'New prescription from '+doctor;
    var parts = [];
    if(patient) parts.push('Patient: '+patient);
    if(medicine) parts.push('Medicine: '+medicine+(dose? (' '+dose):''));
    if(qty) parts.push('Qty: '+qty);
    if(desc) parts.push(desc);
    var body = parts.join(' | ');
    
    try{
      // Save prescription to database
      var dbRes = await fetch('/capstone/prescriptions/create.php',{
        method:'POST', 
        headers:{ 'Content-Type':'application/json' }, 
        body: JSON.stringify({ 
          doctor_name: doctor,
          patient_name: patient,
          medicine: medicine,
          quantity: qty,
          dosage_strength: dose,
          description: desc
        })
      });
      
      if(!dbRes.ok){ 
        var dbError = await dbRes.text().catch(function(){return '';}); 
        throw new Error(dbError || 'Failed to save prescription'); 
      }
      
      // Notify pharmacy
      var notifRes = await fetch('/capstone/notifications/pharmacy.php',{
        method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify({ title:title, body:body })
      });
      
      if(!notifRes.ok){ 
        var t = await notifRes.text().catch(function(){return '';}); 
        throw new Error(t||'Failed to notify pharmacy'); 
      }
      
      alert('Prescription submitted successfully. Pharmacy has been notified.');
      form.reset();
      if(document.getElementById('patient')){ document.getElementById('patient').focus(); }
    }catch(err){
      alert('Error: '+err.message);
    }
  });
});
    </script>
</div>
  </aside>

  <div class="rx-layout">
    <section class="rx-form" style="background:#fff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,0.08);">
      <div style="background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;padding:24px 28px;border-top-left-radius:20px;border-top-right-radius:20px;">
        <h3 style="margin:0;font-size:1.3rem;font-weight:700;color:#fff;">Create Prescription</h3>
        <p style="margin:4px 0 0;opacity:0.9;font-size:0.9rem;">Fill in the details below</p>
      </div>
      <form method="post" action="#" onsubmit="return false;">
        <div style="padding:28px;display:grid;gap:20px;">
          <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div class="form-field">
              <label for="doctor" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Doctor Name</label>
              <input type="text" id="doctor" name="doctor" value="<?php echo htmlspecialchars($_SESSION['user']['full_name'] ?? ''); ?>" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
            </div>
            <div class="form-field">
              <label for="patient" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Patient Name</label>
              <div style="position:relative;">
                <select id="patient" name="patient" required autofocus style="width:100%;padding:12px 40px 12px 16px;border:2px solid #e2e8f0;border-radius:12px;background:#f8fafc;appearance:none;-webkit-appearance:none;-moz-appearance:none;transition:all 0.2s ease;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';">
                  <option value="" <?php echo $selectedPatient === '' ? 'selected' : ''; ?>>Select patient</option>
                  <?php foreach ($patientOptions as $p): $isSel = ($selectedPatient !== '' && strcasecmp($selectedPatient, $p) === 0); ?>
                    <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $isSel ? 'selected' : ''; ?>><?php echo htmlspecialchars($p); ?></option>
                  <?php endforeach; ?>
                </select>
                <div style="position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:#64748b;">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 9l6 6 6-6"/>
                  </svg>
                </div>
              </div>
            </div>
          </div>
          <div class="form-field">
            <label for="medicine" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Medicine</label>
            <input type="text" id="medicine" name="medicine" placeholder="Enter medicine" require style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
          </div>
          <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div class="form-field">
              <label for="quantity_prescribed" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Quantity Prescribed</label>
              <input type="text" id="quantity_prescribed" name="quantity_prescribed" placeholder="Enter quantity" require style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
            </div>
            <div class="form-field">
              <label for="dosage_strength" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Dosage Strength</label>
              <input type="text" id="dosage_strength" name="dosage_strength" placeholder="Enter dosage strength" require style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
            </div>
          </div>
          <div class="form-field">
            <label for="description" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Description</label>
            <textarea id="description" name="description" rows="3" placeholder="Enter description" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;resize:vertical;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';"></textarea>
          </div>
        </div>
        <div class="rx-actions" style="display:flex;justify-content:center;gap:12px;padding:24px 28px;border-top:1px solid #e5e7eb;background:linear-gradient(135deg,#f8fafc,#f1f5f9);">
          <button class="btn btn-primary" type="submit" style="padding:14px 28px;border-radius:12px;font-weight:700;font-size:1rem;background:linear-gradient(135deg,#0a5d39,#10b981);">Submit Prescription</button>
        </div>
      </form>
    </section>
  </div>
</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>
