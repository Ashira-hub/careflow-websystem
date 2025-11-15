<?php $page='Doctor Appointment'; include __DIR__.'/../../includes/header.php'; ?>

<?php
// Calendar logic aligned with nurse_schedule.php
$y = isset($_GET['y']) ? intval($_GET['y']) : intval(date('Y'));
$m = isset($_GET['m']) ? intval($_GET['m']) : intval(date('n'));
// Normalize month out-of-range (e.g., 0 or 13)
$normTs = mktime(0,0,0,$m,1,$y);
$y = intval(date('Y',$normTs));
$m = intval(date('n',$normTs));
$firstTs = mktime(0,0,0,$m,1,$y);
$firstDow = intval(date('w',$firstTs)); // 0=Sun..6=Sat
$daysInMonth = intval(date('t',$firstTs));
$prevTs = mktime(0,0,0,$m-1,1,$y);
$nextTs = mktime(0,0,0,$m+1,1,$y);
$prevY = intval(date('Y',$prevTs)); $prevM = intval(date('n',$prevTs));
$nextY = intval(date('Y',$nextTs)); $nextM = intval(date('n',$nextTs));
$daysInPrev = intval(date('t',$prevTs));
$todayY = intval(date('Y')); $todayM = intval(date('n')); $todayD = intval(date('j'));
$startDayOffset = 1 - $firstDow; // value added to index to get day number
$cells = 42; // 6 weeks grid
?>

<?php
// Load appointments from PostgreSQL
require_once __DIR__.'/../../config/db.php';
$appointments = [];
try {
  $pdo = get_pdo();
  // Pagination: 'ap' page number (1..1000), 10 items per page
  $ap = isset($_GET['ap']) ? max(1, min(1000, intval($_GET['ap']))) : 1;
  $perPage = 6;
  $offset = ($ap - 1) * $perPage;
  $userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
  $stmt = $pdo->prepare("SELECT id, patient, \"date\", \"time\", notes, done
                         FROM appointments
                         WHERE COALESCE(done, false) = false
                           AND (created_by_user_id = :uid)
                         ORDER BY \"date\" ASC, \"time\" ASC
                         LIMIT :limit OFFSET :offset");
  $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
  $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $appointments = $stmt->fetchAll();
} catch (Throwable $e) {
  $appointments = [];
}
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/doctor/doctor_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/doctor/doctor_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/appointment.png" alt="Appointment" style="width:18px;height:18px;object-fit:contain;"> Appointment</a></li>
          <li><a href="/capstone/templates/doctor/doctor_prescription.php"><img src="/capstone/assets/img/prescription.png" alt="Prescription" style="width:18px;height:18px;object-fit:contain;"> Prescription</a></li>
          <li><a href="/capstone/templates/doctor/doctor_records.php"><img src="/capstone/assets/img/medical-file.png" alt="Patient Record" style="width:18px;height:18px;object-fit:contain;"> Patient Record</a></li>
          <li><a href="/capstone/templates/doctor/doctor_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div class="appt-layout">
    <section class="calendar-card">
      <div class="calendar-title" style="font-size:1.8rem;font-weight:800;letter-spacing:0.1em;">CALENDAR</div>
      <div class="calendar-header" style="display:flex;align-items:center;justify-content:space-between;width:100%;">
        <a class="btn" href="?y=<?php echo $prevY; ?>&m=<?php echo $prevM; ?>" style="margin-right:auto;">&lt;</a>
        <div class="month-name" style="flex:1;text-align:center;"><?php echo date('F Y', $firstTs); ?></div>
        <a class="btn" href="?y=<?php echo $nextY; ?>&m=<?php echo $nextM; ?>" style="margin-left:auto;">&gt;</a>
      </div>
      <div class="calendar-grid" style="margin-bottom:6px;color:#64748b;font-weight:700;">
        <div style="text-align:center;">Sun</div>
        <div style="text-align:center;">Mon</div>
        <div style="text-align:center;">Tue</div>
        <div style="text-align:center;">Wed</div>
        <div style="text-align:center;">Thu</div>
        <div style="text-align:center;">Fri</div>
        <div style="text-align:center;">Sat</div>
      </div>
      <div class="calendar-grid">
        <?php for($i=0; $i<$cells; $i++):
          $dayNum = $startDayOffset + $i; // relative to current month
          if ($dayNum < 1) {
            $display = $daysInPrev + $dayNum; // prev month
            $muted = true;
          } elseif ($dayNum > $daysInMonth) {
            $display = $dayNum - $daysInMonth; // next month
            $muted = true;
          } else {
            $display = $dayNum; // current month
            $muted = false;
          }
          $isToday = (!$muted && $y === $todayY && $m === $todayM && $display === $todayD);
          $classes = 'calendar-cell' . ($muted ? ' muted' : '') . ($isToday ? ' active' : '');
        ?>
          <div class="<?php echo $classes; ?>">
            <div><?php echo $display; ?></div>
          </div>
        <?php endfor; ?>
      </div>
    </section>

    <aside class="appt-list" style="display:flex;flex-direction:column;">
      <div class="appt-list-header">
        <h3>Appointment List</h3>
        <a class="btn btn-primary" href="#" id="openApptModal">+ New</a>
      </div>
      <div id="apptItems" style="padding:0;">
        <?php if (!empty($appointments)): ?>
          <?php foreach ($appointments as $row):
            $date = !empty($row['date']) ? new DateTime($row['date']) : null;
            $mon = $date ? strtoupper($date->format('M')) : '';
            $day = $date ? $date->format('j') : '';
            $year = $date ? $date->format('Y') : '';
            $timeLabel = !empty($row['time']) ? substr($row['time'],0,5) : '';
          ?>
          <div class="appt-item" data-id="<?php echo (int)($row['id']??0); ?>" data-patient="<?php echo htmlspecialchars($row['patient']??'', ENT_QUOTES); ?>" data-date="<?php echo htmlspecialchars($row['date']??'', ENT_QUOTES); ?>" data-time="<?php echo htmlspecialchars(substr((string)($row['time']??''),0,8), ENT_QUOTES); ?>" data-notes="<?php echo htmlspecialchars($row['notes']??'', ENT_QUOTES); ?>" style="display:flex;align-items:center;gap:6px;padding:8px 0;border-bottom:1px solid #e5e7eb;">
            <div class="appt-left" style="display:flex;align-items:center;gap:0;">
              <input type="checkbox" class="appt-check" aria-label="Select appointment" style="margin:0 15px 0 0;">
              <div class="appt-date" style="width:56px;min-width:56px;text-align:center;">
                <div style="font-size:.70rem;color:#64748b;letter-spacing:.4px;"><?php echo htmlspecialchars($mon); ?></div>
                <div style="font-size:1.1rem;font-weight:600;line-height:1;"><?php echo htmlspecialchars($day); ?></div>
                <div style="font-size:.70rem;color:#94a3b8;line-height:1.1;"><?php echo htmlspecialchars($year); ?></div>
              </div>
            </div>
            <div class="appt-meta" style="flex:1;min-width:0;">
              <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <div style="font-weight:600;color:#0f172a;margin-bottom:2px;"><?php echo htmlspecialchars($row['patient'] ?? ''); ?></div>
                <div style="font-size:0.9rem;color:#64748b;"><?php echo htmlspecialchars($timeLabel); ?></div>
              </div>
            </div>
            <div class="appt-actions" style="margin-left:auto;display:flex;align-items:center;gap:6px;">
              <button class="btn-ghost btn-notify" title="Set reminder" style="border:none;background:transparent;box-shadow:none;padding:4px;cursor:pointer;"><img src="/capstone/assets/img/notification.png" alt="Remind" style="width:18px;height:18px;object-fit:contain;"></button>
              <button class="btn-ghost btn-edit" title="Edit appointment" style="border:none;background:transparent;box-shadow:none;padding:4px;cursor:pointer;"><img src="/capstone/assets/img/pencil.png" alt="Edit" style="width:18px;height:18px;object-fit:contain;"></button>
              <button class="btn-ghost btn-delete" title="Delete appointment" style="border:none;background:transparent;box-shadow:none;padding:4px;cursor:pointer;"><img src="/capstone/assets/img/bin.png" alt="Delete" style="width:18px;height:18px;object-fit:contain;"></button>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div id="noApptMsg" class="muted" style="padding:8px 0;<?php echo !empty($appointments) ? 'display:none;' : '';?>">No appointments yet.</div>
      
      <?php
        // Pager controls: Prev/Next and page select (1..1000)
        $prevAp = max(1, ($ap ?? 1) - 1);
        $nextAp = min(1000, ($ap ?? 1) + 1);
        // Preserve calendar y/m in pager links
        $baseY = isset($y) ? intval($y) : intval(date('Y'));
        $baseM = isset($m) ? intval($m) : intval(date('n'));
        $self = htmlspecialchars($_SERVER['PHP_SELF']);
        $qsPrev = "?y={$baseY}&m={$baseM}&ap={$prevAp}";
        $qsNext = "?y={$baseY}&m={$baseM}&ap={$nextAp}";
      ?>
      <div class="pager" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:16px;border-top:1px solid #e5e7eb;margin-top:auto;">
        <button class="btn btn-outline" type="button" onclick="window.location.href='<?php echo $qsPrev; ?>'" style="padding:8px 16px;border-radius:8px;font-weight:600;<?php echo ($ap ?? 1) <= 1 ? 'opacity:0.5;cursor:not-allowed;' : ''; ?>" <?php echo ($ap ?? 1) <= 1 ? 'disabled' : ''; ?>>← Prev</button>
        <div style="display:flex;align-items:center;gap:8px;">
          <span class="muted-small" style="font-size:0.9rem;color:#64748b;">Page</span>
          <select id="apPageSelect" style="padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9rem;cursor:pointer;" onchange="window.location.href='?y=<?php echo $baseY; ?>&m=<?php echo $baseM; ?>&ap='+this.value">
            <?php for($i=1;$i<=100;$i++): ?>
              <option value="<?php echo $i; ?>" <?php echo ($i===($ap??1)) ? 'selected' : ''; ?>><?php echo $i; ?></option>
            <?php endfor; ?>
          </select>
          <span class="muted-small" style="font-size:0.9rem;color:#64748b;">of 100</span>
        </div>
        <button class="btn btn-outline" type="button" onclick="window.location.href='<?php echo $qsNext; ?>'" style="padding:8px 16px;border-radius:8px;font-weight:600;<?php echo ($ap ?? 1) >= 100 ? 'opacity:0.5;cursor:not-allowed;' : ''; ?>" <?php echo ($ap ?? 1) >= 100 ? 'disabled' : ''; ?>>Next →</button>
      </div>
    </aside>
  </div>
</div>

<div id="apptModal" style="display:none;position:fixed;inset:0;z-index:1000;">
  <div data-backdrop style="position:absolute;inset:0;background:rgba(0,0,0,0.45);"></div>
  <div role="dialog" aria-modal="true" style="position:relative;max-width:600px;margin:2vh auto;background:#fff;border-radius:20px;box-shadow:0 25px 50px rgba(0,0,0,0.25);overflow:hidden;min-height:fit-content;">
    <div style="background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;padding:24px 28px;border-radius:20px 20px 0 0;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h3 id="apptModalTitle" style="margin:0;font-size:1.3rem;font-weight:700;">New Appointment</h3>
          <p style="margin:4px 0 0;opacity:0.9;font-size:0.9rem;">Create or edit an appointment</p>
        </div>
        <button type="button" id="closeApptModal" style="background:rgba(255,255,255,0.2);border:none;color:#fff;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.3)'" onmouseout="this.style.backgroundColor='rgba(255,255,255,0.2)'">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6L6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>
    <form id="apptForm" action="#" method="post">
      <div style="padding:28px;display:grid;gap:20px;">
        <div>
          <label for="appt_patient" class="muted-small" style="display:block;margin-bottom:6px;">Patient Name</label>
          <input id="appt_patient" name="patient" type="text" class="input" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;" required />
        </div>
        <div>
          <label for="appt_date" class="muted-small" style="display:block;margin-bottom:6px;">Date</label>
          <input id="appt_date" name="date" type="date" class="input" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;" required />
        </div>
        <div>
          <label for="appt_time" class="muted-small" style="display:block;margin-bottom:6px;">Time</label>
          <input id="appt_time" name="time" type="time" class="input" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;" required />
        </div>
        <div>
          <label for="appt_reason" class="muted-small" style="display:block;margin-bottom:6px;">Notes</label>
          <textarea id="appt_reason" name="reason" rows="3" class="input" placeholder="Optional details" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;resize:vertical;"></textarea>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:12px;padding:20px 28px;border-top:1px solid #e5e7eb;background:linear-gradient(135deg,#f8fafc,#f1f5f9);">
        <button type="button" class="btn btn-outline" id="cancelApptModal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="submitApptBtn">Create Appointment</button>
      </div>
    </form>
  </div>
  <button type="button" id="srCloseBackdropAppt" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">Close</button>
  <span aria-hidden="true" id="srFocusTrapAppt" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;"></span>
  <script>
    (function(){
      var openBtn = document.getElementById('openApptModal');
      var modal = document.getElementById('apptModal');
      var closeBtn = document.getElementById('closeApptModal');
      var cancelBtn = document.getElementById('cancelApptModal');
      var backdrop = modal.querySelector('[data-backdrop]');
      var modalTitle = document.getElementById('apptModalTitle');
      var submitBtn = document.getElementById('submitApptBtn');
      var listEl = document.getElementById('apptItems');
      var editingId = null; // null=create, number=edit
      var rModal = null, rBackdrop=null, rClose=null, rCancel=null, rConfirm=null, rCustom=null, rTitle=null;
      var currentReminder = null;
      function ensureReminderBindings(){
        if(rModal) return; // already bound
        rModal = document.getElementById('reminderModal');
        rBackdrop = rModal ? rModal.querySelector('[data-backdrop]') : null;
        rClose = document.getElementById('closeReminderModal');
        rCancel = document.getElementById('cancelReminder');
        rConfirm = document.getElementById('confirmReminder');
        rCustom = document.getElementById('reminderCustom');
        rTitle = document.getElementById('reminderTitle');
        if(rBackdrop){ rBackdrop.addEventListener('click', function(){ if(rModal){ rModal.style.display='none'; document.body.style.overflow=''; } }); }
        if(rClose){ rClose.addEventListener('click', function(){ if(rModal){ rModal.style.display='none'; document.body.style.overflow=''; } }); }
        if(rCancel){ rCancel.addEventListener('click', function(){ if(rModal){ rModal.style.display='none'; document.body.style.overflow=''; } }); }
        if(rConfirm){ rConfirm.addEventListener('click', async function(){
          if(!currentReminder) return;
          var cus = rCustom ? parseInt(rCustom.value,10) : NaN;
          var m = (!isNaN(cus) && cus>=0) ? cus : 0; // allow 0 = on-time
          if(isNaN(cus) || cus < 0){ alert('Please enter minutes (0 or more)'); return; }
          try{
            var resR = await fetch('/capstone/appointments/reminders.php',{
              method:'POST', headers:{ 'Content-Type':'application/json' },
              body: JSON.stringify({ appointment_id: currentReminder.id, patient: currentReminder.patient, date: currentReminder.date, time: currentReminder.time, offset_minutes: m, role: 'doctor' })
            });
            if(!resR.ok){ var txtR = await resR.text().catch(function(){return '';}); throw new Error(txtR || 'Failed to set reminder'); }
            alert('Reminder saved');
            if(rModal){ rModal.style.display='none'; document.body.style.overflow=''; }
          }catch(err){ alert('Error: '+err.message); }
        }); }
      }
      function open(){ modal.style.display = 'block'; document.body.style.overflow='hidden'; }
      function close(){ modal.style.display = 'none'; document.body.style.overflow=''; }
      function resetFormMode(){ editingId = null; if(modalTitle) modalTitle.textContent='New Appointment'; if(submitBtn) submitBtn.textContent='Create Appointment'; }
      function setFormValues(p, d, t, n){
        var fP = document.getElementById('appt_patient'); if(fP) fP.value = p || '';
        var fD = document.getElementById('appt_date'); if(fD) fD.value = d || '';
        var fT = document.getElementById('appt_time'); if(fT) fT.value = t || '';
        var fN = document.getElementById('appt_reason'); if(fN) fN.value = n || '';
      }
      function getFormValues(){
        return {
          patient: (document.getElementById('appt_patient')||{}).value || '',
          date: (document.getElementById('appt_date')||{}).value || '',
          time: (document.getElementById('appt_time')||{}).value || '',
          notes: (document.getElementById('appt_reason')||{}).value || ''
        };
      }
      function monthAbbr(dateStr){
        if(!dateStr) return '';
        try{ var d = new Date(dateStr+'T00:00:00'); return d.toLocaleString('en-US', {month:'short'}).toUpperCase(); }catch(e){ return ''; }
      }
      function yearPart(dateStr){ if(!dateStr) return ''; try{ return String(new Date(dateStr+'T00:00:00').getFullYear()); }catch(e){ return ''; } }
      function dayPart(dateStr){ if(!dateStr) return ''; try{ return String(new Date(dateStr+'T00:00:00').getDate()); }catch(e){ return ''; } }
      function timeLabel(t){ if(!t) return ''; return t.length>5 ? t.substring(0,5) : t; }
      function prependApptItem(appt){
        if(!listEl) return;
        var id = appt.id || '';
        var patient = appt.patient || '';
        var date = appt.date || '';
        var time = appt.time || '';
        var notes = appt.notes || '';
        var mon = monthAbbr(date);
        var day = dayPart(date);
        var year = yearPart(date);
        var tLabel = timeLabel(time);
        var wrapper = document.createElement('div');
        wrapper.className = 'appt-item';
        wrapper.setAttribute('data-id', id);
        wrapper.setAttribute('data-patient', patient);
        wrapper.setAttribute('data-date', date);
        wrapper.setAttribute('data-time', time);
        wrapper.setAttribute('data-notes', notes);
        wrapper.style.cssText = 'display:flex;align-items:center;gap:6px;padding:8px 0;border-bottom:1px solid #e5e7eb;';
        wrapper.innerHTML = ''+
          '<div class="appt-left" style="display:flex;align-items:center;gap:0;">'+
            '<input type="checkbox" class="appt-check" aria-label="Select appointment" style="margin:0 15px 0 0;">'+
            '<div class="appt-date" style="width:56px;min-width:56px;text-align:center;">'+
              '<div style="font-size:.70rem;color:#64748b;letter-spacing:.4px;">'+(mon||'')+'</div>'+
              '<div style="font-size:1.1rem;font-weight:600;line-height:1;">'+(day||'')+'</div>'+
              '<div style="font-size:.70rem;color:#94a3b8;line-height:1.1;">'+(year||'')+'</div>'+
            '</div>'+
          '</div>'+
          '<div class="appt-meta" style="flex:1;min-width:0;">'+
            '<div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'+
              '<div style="font-weight:600;color:#0f172a;margin-bottom:2px;">'+(patient||'')+'</div>'+
              '<div style="font-size:0.9rem;color:#64748b;">'+(tLabel||'')+'</div>'+
            '</div>'+
          '</div>'+
          '<div class="appt-actions" style="margin-left:auto;display:flex;align-items:center;gap:6px;">'+
            '<button class="btn-ghost btn-notify" title="Set reminder" style="border:none;background:transparent;box-shadow:none;padding:4px;cursor:pointer;"><img src="/capstone/assets/img/notification.png" alt="Remind" style="width:18px;height:18px;object-fit:contain;"></button>'+
            '<button class="btn-ghost btn-edit" title="Edit appointment" style="border:none;background:transparent;box-shadow:none;padding:4px;cursor:pointer;"><img src="/capstone/assets/img/pencil.png" alt="Edit" style="width:18px;height:18px;object-fit:contain;"></button>'+
            '<button class="btn-ghost btn-delete" title="Delete appointment" style="border:none;background:transparent;box-shadow:none;padding:4px;cursor:pointer;"><img src="/capstone/assets/img/bin.png" alt="Delete" style="width:18px;height:18px;object-fit:contain;"></button>'+
          '</div>';
        listEl.insertBefore(wrapper, listEl.firstChild);
        var emptyMsg = document.getElementById('noApptMsg');
        if(emptyMsg){ emptyMsg.style.display = 'none'; }
      }
      if(openBtn){ openBtn.addEventListener('click', function(e){ e.preventDefault(); open(); }); }
      if(closeBtn){ closeBtn.addEventListener('click', function(){ close(); }); }
      if(cancelBtn){ cancelBtn.addEventListener('click', function(){ close(); }); }
      if(backdrop){ backdrop.addEventListener('click', function(){ close(); }); }
      document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && modal.style.display === 'block'){ close(); } });
      var form = document.getElementById('apptForm');
      if(form){ form.addEventListener('submit', async function(e){
        e.preventDefault();
        var vals = getFormValues();
        try{
          if(editingId){
            // Get doctor's name from session or use default
            var doctorName = '<?php echo htmlspecialchars($_SESSION["user"]["full_name"] ?? "Doctor", ENT_QUOTES); ?>';
            
            var resU = await fetch('/capstone/appointments/create.php?id='+encodeURIComponent(editingId),{
              method:'PUT', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify({ patient: vals.patient, date: vals.date, time: vals.time, notes: vals.notes, createdByName: doctorName })
            });
            if(!resU.ok){ var textU = await resU.text().catch(function(){return '';}); throw new Error(textU || 'Failed to update'); }
            var updated = await resU.json();
            // Reload to ensure list reflects only DB records
            window.location.reload();
            return;
          } else {
            // Get doctor's name from session or use default
            var doctorName = '<?php echo htmlspecialchars($_SESSION["user"]["full_name"] ?? "Doctor", ENT_QUOTES); ?>';
            
            var res = await fetch('/capstone/appointments/create.php',{
              method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify({ patient: vals.patient, date: vals.date, time: vals.time, notes: vals.notes, done:false, createdByName: doctorName })
            });
            if(!res.ok){ var text = await res.text().catch(function(){ return ''; }); throw new Error(text || 'Failed to save'); }
            var data = await res.json();
            // Try to prepend without full reload if we got an id back
            var newId = (data && (data.id || (data.appointment && data.appointment.id))) || null;
            if(newId){
              prependApptItem({ id:newId, patient: vals.patient, date: vals.date, time: vals.time, notes: vals.notes });
              form.reset();
              resetFormMode();
              close();
              return;
            }
            // Fallback to reload if response doesn't include id
            window.location.reload();
            return;
          }
          form.reset();
          resetFormMode();
          close();
        }catch(err){ alert('Error: '+ err.message); }
      }); }

      function apttimeLabel(t){
        if(!t) return '';
        return t.length>5 ? t.substring(0,5) : t;
      }
      // Pager select change
      (function(){
        var sel = document.getElementById('apPageSelect');
        if(!sel) return;
        sel.addEventListener('change', function(){
          var p = parseInt(sel.value,10)||1;
          var url = new URL(window.location.href);
          url.searchParams.set('ap', p);
          // Preserve y/m if present
          if(!url.searchParams.has('y')) url.searchParams.set('y','<?php echo isset($y)?$y:intval(date('Y')); ?>');
          if(!url.searchParams.has('m')) url.searchParams.set('m','<?php echo isset($m)?$m:intval(date('n')); ?>');
          window.location.href = url.pathname + '?' + url.searchParams.toString();
        });
      })();
      // Edit/Delete handlers (event delegation)
      if(listEl){
        listEl.addEventListener('click', async function(e){
          var btn = e.target.closest('button'); if(!btn) return;
          var item = e.target.closest('.appt-item'); if(!item) return;
          var id = item.getAttribute('data-id');
          if(btn.classList.contains('btn-edit')){
            editingId = id ? parseInt(id,10) : null;
            if(modalTitle) modalTitle.textContent = 'Edit Appointment';
            if(submitBtn) submitBtn.textContent = 'Save Changes';
            setFormValues(item.getAttribute('data-patient')||'', item.getAttribute('data-date')||'', item.getAttribute('data-time')||'', item.getAttribute('data-notes')||'');
            open();
          } else if(btn.classList.contains('btn-delete')){
            if(!id){ alert('Missing appointment id'); return; }
            var ok = confirm('Delete this appointment?'); if(!ok) return;
            try{
              var resD = await fetch('/capstone/appointments/create.php?id='+encodeURIComponent(id),{ method:'DELETE' });
              if(!resD.ok && resD.status!==204){ var txtD = await resD.text().catch(function(){return '';}); throw new Error(txtD || 'Failed to delete'); }
              // Reload to ensure list reflects only DB records
              window.location.reload();
              return;
            }catch(err){ alert('Error: '+ err.message); }
          } else if(btn.classList.contains('btn-notify')){
            if(!id){ alert('Missing appointment id'); return; }
            var date = item.getAttribute('data-date') || '';
            var time = item.getAttribute('data-time') || '';
            var patient = item.getAttribute('data-patient') || '';
            if(!date || !time){ alert('Missing schedule to set reminder'); return; }
            ensureReminderBindings();
            currentReminder = { id: parseInt(id,10), date: date, time: time, patient: patient };
            if(rTitle){ rTitle.textContent = 'Set Reminder - '+patient; }
            if(rCustom){ rCustom.value = ''; }
            if(rModal){ rModal.style.display='block'; document.body.style.overflow='hidden'; }
          }
        });
        // Mark done via checkbox
        listEl.addEventListener('change', async function(e){
          var cb = e.target.closest('.appt-check'); if(!cb) return;
          var item = e.target.closest('.appt-item'); if(!item) return;
          var id = item.getAttribute('data-id'); if(!id){ alert('Missing appointment id'); cb.checked=false; return; }
          if(!cb.checked){ return; }
          // Ask for confirmation before marking as done
          var confirmDone = confirm('Mark this appointment as done?');
          if(!confirmDone){ cb.checked = false; return; }
          try{
            var res = await fetch('/capstone/appointments/create.php?id='+encodeURIComponent(id),{
              method:'PUT', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify({ done: true })
            });
            if(!res.ok){ var t = await res.text().catch(function(){return '';}); throw new Error(t||'Failed to mark done'); }
            // Remove from UI list
            item.parentNode.removeChild(item);
            // Show empty state if needed
            var remain = listEl.querySelector('.appt-item');
            var emptyMsg = document.getElementById('noApptMsg');
            if(!remain && emptyMsg){ emptyMsg.style.display=''; }
          }catch(err){ alert('Error: '+err.message); cb.checked=false; }
        });
      }

      // Periodically trigger due reminders -> append to doctor notifications
      (function startReminderPolling(){
        async function poll(){
          try{
            await fetch('/capstone/appointments/reminders.php?role=doctor&due=1', { cache: 'no-store' });
          }catch(e){ /* silent */ }
        }
        // initial and every 15s
        poll();
        setInterval(poll, 15000);
      })();
    })();
  </script>
</div>

<div id="reminderModal" style="display:none;position:fixed;inset:0;z-index:1000;">
  <div data-backdrop style="position:absolute;inset:0;background:rgba(0,0,0,0.45);"></div>
  <div role="dialog" aria-modal="true" style="position:relative;max-width:420px;margin:10vh auto;background:#fff;border-radius:16px;box-shadow:0 25px 50px rgba(0,0,0,0.25);overflow:hidden;min-height:fit-content;">
    <div style="background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;padding:16px 20px;border-radius:16px 16px 0 0;display:flex;align-items:center;justify-content:space-between;gap:12px;">
      <h3 id="reminderTitle" style="margin:0;font-size:1.05rem;font-weight:700;">Set Reminder</h3>
      <button type="button" id="closeReminderModal" style="background:rgba(255,255,255,0.2);border:none;color:#fff;width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div style="padding:18px 20px;display:grid;gap:12px;">
      <div class="form-field">
        <label for="reminderCustom" class="muted-small">Enter a custom number of minutes</label>
        <input type="number" id="reminderCustom" min="1" placeholder="e.g. 45" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;" />
      </div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:10px;padding:14px 20px;border-top:1px solid #e5e7eb;background:#f8fafc;">
      <button type="button" id="cancelReminder" class="btn btn-outline">Cancel</button>
      <button type="button" id="confirmReminder" class="btn btn-primary">Save</button>
    </div>
  </div>
</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>
