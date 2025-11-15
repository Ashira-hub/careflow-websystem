<?php
 $page='Nurse Schedule';
 require_once __DIR__.'/../../config/db.php';
 if (session_status() === PHP_SESSION_NONE) { session_start(); }

 $nurseId   = (int)($_SESSION['user']['id'] ?? 0);
 $nurseName = trim((string)($_SESSION['user']['full_name'] ?? ($_SESSION['user']['name'] ?? '')));
 $nurseEmail= strtolower((string)($_SESSION['user']['email'] ?? ''));

 $requests = [];
 try {
   $pdo = get_pdo();
   // Be tolerant of different column names; just select everything and map in PHP
   $sql = 'SELECT * FROM schedules ORDER BY "date" ASC, start_time ASC';
   $stmt = $pdo->query($sql);
   foreach ($stmt as $row) {
     $row = (array)$row;
     $id          = (int)($row['id'] ?? 0);
     $nurse       = (string)($row['nurse'] ?? ($row['nurse_name'] ?? ($row['requested_by'] ?? ($row['requester'] ?? ''))));
     $nurseIdVal  = isset($row['nurse_id']) ? (int)$row['nurse_id'] : null;
     $nurseEmailV = strtolower((string)($row['nurse_email'] ?? ''));

     // Keep only schedules that belong to the logged-in nurse
     $belongs = false;
     // Exact nurse_id match when column exists
     if ($nurseId > 0 && $nurseIdVal === $nurseId) {
       $belongs = true;
     }
     // Exact email match when column exists
     elseif ($nurseEmail !== '' && $nurseEmailV !== '' && $nurseEmailV === $nurseEmail) {
       $belongs = true;
     }
     // Fallback to name matching (case-insensitive, trimmed, and allowing partial match)
     else {
       $a = trim(mb_strtolower($nurseName));
       $b = trim(mb_strtolower($nurse));
       if ($a !== '' && $b !== '') {
         if ($a === $b || strpos($a, $b) !== false || strpos($b, $a) !== false) {
           $belongs = true;
         }
       }
     }
     if (!$belongs) {
       continue;
     }

     $dateVal = (string)($row['date'] ?? ($row['schedule_date'] ?? ''));
     // Normalize to YYYY-MM-DD if it looks like a date/time
     if ($dateVal !== '') {
       $ts = strtotime($dateVal);
       if ($ts !== false) { $dateVal = date('Y-m-d', $ts); }
     }

     $shiftVal = (string)($row['shift'] ?? ($row['title'] ?? ($row['name'] ?? '')));
     $wardVal  = (string)($row['ward'] ?? ($row['station'] ?? ($row['unit'] ?? ($row['department'] ?? ''))));
     $notesVal = (string)($row['notes'] ?? ($row['note'] ?? ($row['remarks'] ?? ($row['description'] ?? ''))));
     $statusVal= strtolower((string)($row['status'] ?? ($row['state'] ?? 'request')));

     $startRaw = (string)($row['start_time'] ?? ($row['start'] ?? ($row['time_start'] ?? ($row['from_time'] ?? ''))));
     $endRaw   = (string)($row['end_time'] ?? ($row['end'] ?? ($row['time_end'] ?? ($row['to_time'] ?? ''))));

     $timeLabel = trim($startRaw);
     if ($endRaw !== '') {
       $timeLabel = ($timeLabel !== '' ? $timeLabel.' - ' : '').$endRaw;
     }

     $requests[] = [
       'id'           => $id,
       'nurse'        => $nurse,
       'nurse_id'     => $nurseIdVal,
       'nurse_email'  => $nurseEmailV,
       'date'         => $dateVal,
       'time'         => $timeLabel,
       'start_time'   => $startRaw,
       'end_time'     => $endRaw,
       'shift'        => $shiftVal,
       'ward'         => $wardVal,
       'notes'        => $notesVal,
       'status'       => $statusVal,
     ];
   }
 } catch (Throwable $e) {
   $requests = [];
 }

 include __DIR__.'/../../includes/header.php';
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar"> 
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li>
            <a href="/capstone/templates/nurse/nurse_dashboard.php">
              <img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;">
              Home
            </a>
          </li>
          <li>
            <a href="/capstone/templates/nurse/nurse_profile.php">
              <img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;">
              Profile
            </a>
          </li>
          <li>
            <a class="active" href="#">
              <img src="/capstone/assets/img/appointment.png" alt="Schedule" style="width:18px;height:18px;object-fit:contain;">
              Schedule
            </a>
          </li>
          <li>
            <a href="/capstone/templates/nurse/nurse_prescription.php">
              <img src="/capstone/assets/img/prescription.png" alt="Prescription" style="width:18px;height:18px;object-fit:contain;">
              Prescription
            </a>
          </li>
          <li>
            <a href="/capstone/templates/nurse/nurse_reports.php">
              <img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;">
              Reports
            </a>
          </li>
        </ul>
      </nav>
    </div>
  </aside>

  <div class="appt-layout">
    <section class="calendar-card">
      <?php
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
      <div class="calendar-grid" id="calendarDays">
        <?php for($i=0; $i<$cells; $i++):
          $dayNum = $startDayOffset + $i; // relative to current month
          if ($dayNum < 1) {
            $display = $daysInPrev + $dayNum; // prev month
            $muted = true;
            $cellY = $prevY; $cellM = $prevM; $cellD = $display;
          } elseif ($dayNum > $daysInMonth) {
            $display = $dayNum - $daysInMonth; // next month
            $muted = true;
            $cellY = $nextY; $cellM = $nextM; $cellD = $display;
          } else {
            $display = $dayNum; // current month
            $muted = false;
            $cellY = $y; $cellM = $m; $cellD = $display;
          }
          $isToday = (!$muted && $y === $todayY && $m === $todayM && $display === $todayD);
          $classes = 'calendar-cell' . ($muted ? ' muted' : '') . ($isToday ? ' active' : '');
          $cellDate = sprintf('%04d-%02d-%02d', $cellY, $cellM, $cellD);
        ?>
          <div class="<?php echo $classes; ?>" data-date="<?php echo htmlspecialchars($cellDate); ?>"<?php echo $muted ? ' data-muted="1"' : ''; ?> title="<?php echo htmlspecialchars($cellDate); ?>">
            <div><?php echo $display; ?></div>
          </div>
        <?php endfor; ?>
      </div>
    </section>

    <aside class="appt-list" style="margin-left:12px;display:flex;flex-direction:column;">
      <div class="appt-list-header" style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
        <h3 style="margin-left:8px;">Schedule List</h3>
        <div style="display:flex;gap:8px;align-items:center;">
          <a class="btn btn-primary" href="#" id="openRequestModal">+ Request </a>
        </div>
      </div>
      <div style="height:1px;background:#e5e7eb;margin:8px 0 10px 0;"></div>
      <div id="shiftList" style="display:flex;flex-direction:column;gap:14px;"></div>
      <div id="shiftEmpty" class="muted" style="display:none;padding:12px 0;text-align:center;">No schedule yet.</div>
      <div style="height:1px;background:#e5e7eb;margin-top:auto;"></div>
      <div id="nsPager" style="display:flex;align-items:center;justify-content:center;gap:10px;padding:10px 0 4px 0;">
        <button type="button" id="nsPrevPage" class="btn btn-outline" style="padding:6px 14px;font-size:0.9rem;">Prev</button>
        <span id="nsPageIndicator" class="muted-small" style="min-width:120px;text-align:center;font-size:0.9rem;">Page 1 / 1</span>
        <button type="button" id="nsNextPage" class="btn btn-outline" style="padding:6px 14px;font-size:0.9rem;">Next</button>
      </div>
    </aside>
  </div>
</div>

<div id="requestModal" style="display:none;position:fixed;inset:0;z-index:1000;">
  <div data-backdrop style="position:absolute;inset:0;background:rgba(0,0,0,0.45);"></div>
  <div role="dialog" aria-modal="true" style="position:relative;max-width:600px;margin:2vh auto;background:#fff;border-radius:20px;box-shadow:0 25px 50px rgba(0,0,0,0.25);overflow:hidden;min-height:fit-content;">
    <div style="background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;padding:24px 28px;border-radius:20px 20px 0 0;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h3 style="margin:0;font-size:1.3rem;font-weight:700;">Request Schedule</h3>
          <p style="margin:4px 0 0;opacity:0.9;font-size:0.9rem;">Fill in your request details</p>
        </div>
        <button type="button" id="closeRequestModal" style="background:rgba(255,255,255,0.2);border:none;color:#fff;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.3)'" onmouseout="this.style.backgroundColor='rgba(255,255,255,0.2)'">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6L6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>
    <form id="requestForm" action="#" method="post" style="display:flex;flex-direction:column;max-height:70vh;">
      <div style="padding:28px;display:grid;gap:20px;overflow-y:auto;">
        <div>
          <label for="req_shift" class="muted-small" style="display:block;margin-bottom:6px;">Title</label>
          <input id="req_shift" name="shift" type="text" placeholder="e.g., Morning" class="input" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;" required />
        </div>
        <div>
          <label for="req_date" class="muted-small" style="display:block;margin-bottom:6px;">Date</label>
          <input id="req_date" name="date" type="date" class="input" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;" required />
        </div>
        <div>
          <label for="req_station" class="muted-small" style="display:block;margin-bottom:6px;">Station</label>
          <input id="req_station" name="station" type="text" class="input" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;" required />
        </div>
        <div>
          <label for="req_start" class="muted-small" style="display:block;margin-bottom:6px;">Start Time</label>
          <input id="req_start" name="start_time" type="time" class="input" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;" required />
        </div>
        <div>
          <label for="req_end" class="muted-small" style="display:block;margin-bottom:6px;">End Time</label>
          <input id="req_end" name="end_time" type="time" class="input" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;" required />
        </div>
        <div>
          <label for="req_notes" class="muted-small" style="display:block;margin-bottom:6px;">Note</label>
          <textarea id="req_notes" name="note" rows="3" class="input" placeholder="Optional details" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;resize:vertical;"></textarea>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:12px;padding:20px 28px;border-top:1px solid #e5e7eb;background:linear-gradient(135deg,#f8fafc,#f1f5f9);">
        <button type="button" class="btn btn-outline" id="cancelRequestModal">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Request</button>
      </div>
    </form>
  </div>
  <button type="button" id="srCloseBackdrop" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">Close</button>
  <span aria-hidden="true" id="srFocusTrap" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;"></span>
  <script>
    window.NURSE_SCHEDULES = <?php echo json_encode($requests, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
  </script>
  <script>
    (function(){
      var openBtn = document.getElementById('openRequestModal');
      var modal = document.getElementById('requestModal');
      var closeBtn = document.getElementById('closeRequestModal');
      var cancelBtn = document.getElementById('cancelRequestModal');
      var backdrop = modal.querySelector('[data-backdrop]');
      function open(){ modal.style.display = 'block'; document.body.style.overflow='hidden'; }
      function close(){ modal.style.display = 'none'; document.body.style.overflow=''; }
      if(openBtn){ openBtn.addEventListener('click', function(e){ e.preventDefault(); open(); }); }
      if(closeBtn){ closeBtn.addEventListener('click', function(){ close(); }); }
      if(cancelBtn){ cancelBtn.addEventListener('click', function(){ close(); }); }
      if(backdrop){ backdrop.addEventListener('click', function(){ close(); }); }
      // Refresh button removed
      document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && modal.style.display === 'block'){ close(); } });
      // Make calendar cells interactive: click to select date and open request modal
      var calGrid = document.getElementById('calendarDays');
      // Highlight today's cell using the user's local timezone
      (function highlightTodayLocal(){
        if(!calGrid) return;
        try{
          var now = new Date();
          var yyyy = now.getFullYear();
          var mm = String(now.getMonth()+1).padStart(2,'0');
          var dd = String(now.getDate()).padStart(2,'0');
          var todayStr = yyyy+'-'+mm+'-'+dd;
          var cell = calGrid.querySelector('.calendar-cell[data-date="'+todayStr+'"]:not([data-muted])');
          if(cell){
            // Remove any previous active marker then set active on the local today cell
            var prev = calGrid.querySelector('.calendar-cell.active');
            if(prev && prev !== cell){ prev.classList.remove('active'); }
            cell.classList.add('active');
          }
        }catch(_){}
      })();
      // Prevent selecting past dates in the request date picker
      try {
        var dateInputInit = document.getElementById('req_date');
        if (dateInputInit) {
          var todayIso = new Date();
          var yyyy = todayIso.getFullYear();
          var mm = String(todayIso.getMonth()+1).padStart(2,'0');
          var dd = String(todayIso.getDate()).padStart(2,'0');
          var minStr = yyyy+'-'+mm+'-'+dd;
          dateInputInit.min = minStr;
          if (dateInputInit.value && dateInputInit.value < minStr) {
            dateInputInit.value = minStr;
          }
        }
      } catch (_){ }
      if(calGrid){
        // Make calendar cells highlight only; no request creation on click
        calGrid.addEventListener('click', function(ev){
          var cell = ev.target.closest('.calendar-cell');
          if(!cell) return;
          if(cell.hasAttribute('data-muted')) return; // ignore prev/next month
          // Highlight selection without opening the request modal
          var prevSel = calGrid.querySelector('.calendar-cell.selected');
          if(prevSel){
            prevSel.classList.remove('selected');
            prevSel.style.boxShadow='';
            prevSel.style.border='';
            prevSel.style.borderRadius='';
          }
          cell.classList.add('selected');
          cell.style.border = '2px solid #0a5d39';
          cell.style.borderRadius = '8px';
          cell.style.boxShadow = '0 0 0 2px #bbf7d0';
        });
      }
      var form = document.getElementById('requestForm');
      var nurseName = <?php echo json_encode($_SESSION['user']['full_name'] ?? ($_SESSION['user']['name'] ?? 'Nurse')); ?>;
      var nurseId = <?php echo json_encode((int)($_SESSION['user']['id'] ?? 0)); ?>;
      var nurseEmail = <?php echo json_encode($_SESSION['user']['email'] ?? ''); ?>;
      var shiftList = document.getElementById('shiftList');
      var shiftEmpty = document.getElementById('shiftEmpty');
      var nsPager = document.getElementById('nsPager');
      var nsPrevPage = document.getElementById('nsPrevPage');
      var nsNextPage = document.getElementById('nsNextPage');
      var nsPageIndicator = document.getElementById('nsPageIndicator');
      var loadingRequests = false;
      var refreshTimer = null;
      var nsAllItems = [];
      var nsPageSize = 5;
      var nsCurrentPage = 1;

      // Local persistence for pending requests so they survive refresh (resilient across sessions)
      function pendingKeys(){
        var keys = [];
        var idKey = 'nurse_pending_'+(nurseId||'0');
        var email = (nurseEmail||'').toLowerCase();
        var emailKey = email ? ('nurse_pending_email_'+email) : null;
        keys.push(idKey);
        if(emailKey) keys.push(emailKey);
        // Legacy generic key
        keys.push('nurse_pending_local');
        return keys;
      }
      function loadLocalPending(){
        try{
          var keys = pendingKeys();
          var map = {};
          function keyOf(it){ return composeKey(it); }
          keys.forEach(function(k){
            try{
              var arr = JSON.parse(localStorage.getItem(k)||'[]');
              if(Array.isArray(arr)){
                arr.forEach(function(it){ map[keyOf(it)] = it; });
              }
            }catch(_){ }
          });
          return Object.values(map);
        }catch(_){ return []; }
      }
      function saveLocalPending(items){
        try{
          var keys = pendingKeys();
          var json = JSON.stringify(items||[]);
          keys.forEach(function(k){ try{ localStorage.setItem(k, json); }catch(_){ } });
        }catch(_){ }
      }
      function composeKey(it){ return (it && it.id) ? ('id:'+it.id) : ('k:' + [it && it.date || '', it && it.shift || '', it && it.start_time || '', it && it.end_time || '', (it && (it.ward||it.station)) || ''].join('|')); }
      function upsertLocalPending(item){
        try{
          var items = loadLocalPending();
          var k = composeKey(item);
          var idx = items.findIndex(function(x){ return composeKey(x) === k; });
          if(idx === -1) items.unshift(item); else items[idx] = item;
          saveLocalPending(items);
        }catch(_){ }
      }
      function reconcileLocalWithServer(serverItems){
        // Do not re-surface locally stored pending items after refresh
        // We want the list to reflect server-visible items only
        saveLocalPending([]);
        return [];
      }

      function statusBadge(status){
        var s = (status == null ? 'request' : String(status)).trim().toLowerCase();
        var color = '#f59e0b';
        var label = 'PENDING';
        if(s === 'accepted'){ color = '#10b981'; label = 'ACCEPTED'; }
        else if(s === 'rejected'){ color = '#ef4444'; label = 'REJECTED'; }
        else if(s === 'created'){ color = '#0ea5e9'; label = 'CREATED'; }
        else if(s === 'pending' || s === 'request'){ color = '#f59e0b'; label = 'PENDING'; }
        return '<span class="badge" style="background:'+color+';color:#fff;">'+label+'</span>';
      }

      function escapeHtml(str){
        return (str || '').replace(/[&<>"']/g, function(c){
          return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c] || c;
        });
      }

      function renderRequests(items){
        if(!shiftList || !shiftEmpty) return;
        nsAllItems = Array.isArray(items) ? items.slice() : [];
        // Sort once by date+time, ascending
        nsAllItems.sort(function(a,b){
          var da = (a.date||'') + ' ' + (a.time||'');
          var db = (b.date||'') + ' ' + (b.time||'');
          return da < db ? -1 : da > db ? 1 : 0;
        });
        nsCurrentPage = 1;
        renderNsPage();
      }

      function renderNsPage(){
        if(!shiftList || !shiftEmpty) return;
        shiftList.innerHTML = '';
        var total = nsAllItems.length;
        if(total === 0){
          shiftEmpty.textContent = 'No schedule yet.';
          shiftEmpty.style.display = '';
          if(nsPager) nsPager.style.display = 'none';
          return;
        }
        shiftEmpty.style.display = 'none';
        var totalPages = Math.max(1, Math.ceil(total / nsPageSize));
        if(nsCurrentPage > totalPages) nsCurrentPage = totalPages;
        var start = (nsCurrentPage - 1) * nsPageSize;
        var end = start + nsPageSize;
        nsAllItems.slice(start, end).forEach(function(it){ appendClientRequest(it, true); });
        if(nsPager) nsPager.style.display = 'flex';
        if(nsPageIndicator){ nsPageIndicator.textContent = 'Page ' + nsCurrentPage + ' / ' + totalPages; }
        if(nsPrevPage){ nsPrevPage.disabled = (nsCurrentPage <= 1); }
        if(nsNextPage){ nsNextPage.disabled = (nsCurrentPage >= totalPages); }
      }

      function appendClientRequest(it, fromRender){
        if(!shiftList || !shiftEmpty) return;
        shiftEmpty.style.display = 'none';
        var card = document.createElement('div');
        card.setAttribute('style','border:1px solid #e5e7eb;border-radius:10px;padding:12px;display:flex;flex-direction:column;gap:6px;background:#fff;');
        var notes = (it.notes||'').trim();
        var dateStr = escapeHtml(it.date||'');
        var timeStr = escapeHtml(it.time || ((it.start_time||'') + (it.end_time? ' - '+it.end_time : '')));
        var shiftStr = escapeHtml(it.shift||'');
        var wardStr = escapeHtml(it.ward||it.station||'');
        var notesStr = escapeHtml(notes);
        card.innerHTML = `
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
            <div>
              <div style="font-weight:600;">${dateStr} • ${timeStr}</div>
              <div class="muted-small">Shift: ${shiftStr} • Station: ${wardStr}</div>
            </div>
            <div>${statusBadge(it.status)}</div>
          </div>
          ${notesStr ? `<div class="muted-small" style="white-space:pre-wrap;">${notesStr}</div>` : ''}
        `;
        if(fromRender){ shiftList.appendChild(card); }
        else { shiftList.insertBefore(card, shiftList.firstChild); }
      }

      async function loadRequests(){
        if(loadingRequests) return;
        if(shiftList){ shiftList.innerHTML = ''; }
        if(shiftEmpty){ shiftEmpty.textContent = 'Loading...'; shiftEmpty.style.display = ''; }
        loadingRequests = true;
        try{
          var items = Array.isArray(window.NURSE_SCHEDULES) ? window.NURSE_SCHEDULES : [];
          console.debug('[nurse_schedule] loaded items from PHP:', items);
          var mine = items;
          var localPending = reconcileLocalWithServer(mine);
          var merged = mine.concat(localPending);
          // Show all schedules, regardless of status
          var visible = merged;
          console.debug('[nurse_schedule] visible:', visible);
          renderRequests(visible);
        }catch(err){
          console.error(err);
          if(shiftList){ shiftList.innerHTML = ''; }
          if(shiftEmpty){ shiftEmpty.textContent = 'No schedule yet.'; shiftEmpty.style.display = ''; }
        } finally {
          loadingRequests = false;
        }
      }

      // Pager button handlers
      if(nsPrevPage){
        nsPrevPage.addEventListener('click', function(){
          if(nsCurrentPage > 1){ nsCurrentPage--; renderNsPage(); }
        });
      }
      if(nsNextPage){
        nsNextPage.addEventListener('click', function(){
          nsCurrentPage++; renderNsPage();
        });
      }

      if(form){
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          var shiftEl = document.getElementById('req_shift');
          var dateEl = document.getElementById('req_date');
          var stationEl = document.getElementById('req_station');
          var startEl = document.getElementById('req_start');
          var endEl = document.getElementById('req_end');
          var notesEl = document.getElementById('req_notes');
          var shift = (shiftEl && shiftEl.value) || '';
          var date = (dateEl && dateEl.value) || '';
          var station = (stationEl && stationEl.value) || '';
          var startTime = (startEl && startEl.value) || '';
          var endTime = (endEl && endEl.value) || '';
          var note = (notesEl && notesEl.value) || '';
          if(!shift || !date || !station || !startTime || !endTime){ alert('Complete all required fields.'); return; }
          // Disallow requesting schedules for past dates (previous days/months)
          try {
            var today = new Date();
            today.setHours(0,0,0,0);
            var reqDate = new Date(date);
            if (isNaN(reqDate.getTime())) {
              alert('Please select a valid date.');
              return;
            }
            reqDate.setHours(0,0,0,0);
            if (reqDate < today) {
              alert('You cannot request a schedule for a past date.');
              return;
            }
          } catch (_){ }
          var bodyParts = [
            'Shift: '+shift,
            'Date: '+date,
            'Station: '+station,
            'Start Time: '+startTime,
            'End Time: '+endTime
          ];
          var cleanNotes = note.trim();
          if(cleanNotes){ bodyParts.push('Notes: '+cleanNotes); }
          try{
            var payload = {
              nurse: nurseName,
              shift: shift,
              date: date,
              station: station,
              start_time: startTime,
              end_time: endTime,
              note: cleanNotes,
              nurse_id: nurseId,
              nurse_email: nurseEmail,
              status: 'pending'
            };
            var storeRes = await fetch('../../schedules/requests.php',{
              method:'POST',
              headers:{ 'Content-Type':'application/json' },
              body: JSON.stringify(payload)
            });
            if(!storeRes.ok){ var storeText = await storeRes.text().catch(function(){ return ''; }); throw new Error(storeText || 'Failed to save request'); }
            var storeData = await storeRes.json();
            var requestId = storeData && storeData.item && storeData.item.id ? storeData.item.id : (storeData && storeData.id ? storeData.id : null);

            // Optimistically show the pending request in the list immediately
            var newItem = (storeData && storeData.item) ? storeData.item : {
              id: requestId || Date.now(),
              nurse: nurseName,
              nurse_id: nurseId,
              nurse_email: nurseEmail,
              date: date,
              shift: shift,
              ward: station,
              notes: cleanNotes,
              status: 'pending',
              start_time: startTime,
              end_time: endTime,
              time: startTime + ' - ' + endTime
            };
            appendClientRequest(newItem, false);
            // Persist locally so it remains after refresh
            upsertLocalPending(newItem);

            var notifPayload = {
              title: nurseName+' submitted a schedule request',
              body: bodyParts.join(' | '),
              status: 'pending'
            };
            if(requestId){ notifPayload.request_id = requestId; newItem.id = requestId; upsertLocalPending(newItem); }

            var res = await fetch('/capstone/notifications/supervisor.php',{
              method:'POST',
              headers:{ 'Content-Type':'application/json' },
              body: JSON.stringify(notifPayload)
            });
            if(!res.ok){ var text = await res.text().catch(function(){ return ''; }); throw new Error(text || 'Failed to submit request'); }
            form.reset();
            close();
            // Keep the optimistic item; periodic refresh will reconcile
            alert('Request sent to supervisor.');
          }catch(err){ alert('Error: '+err.message); }
        });
      }
      loadRequests();
    })();
  </script>
</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>
