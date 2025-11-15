<?php
$page='Supervisor Schedules';
require_once __DIR__.'/../../config/db.php';

function ss_escape($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$pdo = null;
$nurseMap = [];
try {
  $pdo = get_pdo();
  $stmt = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'nurse'");
  foreach ($stmt as $row) {
    $id = (int)($row['id'] ?? 0);
    if ($id <= 0) continue;
    $nurseMap[$id] = [
      'name' => trim((string)($row['full_name'] ?? '')),
      'email' => trim((string)($row['email'] ?? '')),
    ];
  }
} catch (Throwable $e) {
  $nurseMap = [];
}

// Load requests from DB schedules table
$requests = [];
try {
  if (!$pdo) { $pdo = get_pdo(); }
  $sql = "SELECT id, nurse, nurse_id, nurse_email, to_char(\"date\", 'YYYY-MM-DD') AS date, shift, ward, notes, lower(status) AS status, to_char(start_time, 'HH24:MI') AS start_time, to_char(end_time, 'HH24:MI') AS end_time, created_at, updated_at FROM schedules ORDER BY created_at DESC";
  $stmtReq = $pdo->query($sql);
  foreach ($stmtReq as $row) {
    $nurseId = (int)($row['nurse_id'] ?? 0);
    $name = trim((string)($row['nurse'] ?? ''));
    if ($name === '' && $nurseId > 0 && isset($nurseMap[$nurseId]['name'])) {
      $name = $nurseMap[$nurseId]['name'];
    }
    $email = trim((string)($row['nurse_email'] ?? ''));
    if ($email === '' && $nurseId > 0 && isset($nurseMap[$nurseId]['email'])) {
      $email = $nurseMap[$nurseId]['email'];
    }
    $timeLabel = trim((string)($row['start_time'] ?? ''));
    $endLabel = trim((string)($row['end_time'] ?? ''));
    if ($endLabel !== '') { $timeLabel = ($timeLabel !== '' ? $timeLabel.' - ' : '').$endLabel; }
    $requests[] = [
      'id' => (int)$row['id'],
      'nurse' => $name,
      'nurse_id' => $nurseId,
      'nurse_email' => $email,
      'date' => trim((string)($row['date'] ?? '')),
      'time' => $timeLabel,
      'end_time' => trim((string)($row['end_time'] ?? '')),
      'shift' => trim((string)($row['shift'] ?? '')),
      'ward' => trim((string)($row['ward'] ?? '')),
      'notes' => trim((string)($row['notes'] ?? '')),
      'status' => trim((string)($row['status'] ?? 'request')),
    ];
  }
} catch (Throwable $e) {
  $requests = [];
}

$today = date('Y-m-d');
$calendarMonth = isset($_GET['month']) ? (string)$_GET['month'] : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $calendarMonth)) {
  $calendarMonth = date('Y-m');
}

$monthStartTs = strtotime($calendarMonth.'-01');
$monthLabel = date('F Y', $monthStartTs);
$daysInMonth = (int)date('t', $monthStartTs);

$requestsByDay = [];
foreach ($requests as $item) {
  $dayKey = $item['date'] ?: '';
  if ($dayKey === '') continue;
  if (!isset($requestsByDay[$dayKey])) { $requestsByDay[$dayKey] = []; }
  $requestsByDay[$dayKey][] = $item;
}

usort($requests, function($a, $b){
  $aKey = ($a['date'] ?? '').' '.($a['time'] ?? '');
  $bKey = ($b['date'] ?? '').' '.($b['time'] ?? '');
  return strcmp($aKey, $bKey);
});

include __DIR__.'/../../includes/header.php';
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/supervisor/supervisor_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/supervisor/supervisor_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/appointment.png" alt="Schedules" style="width:18px;height:18px;object-fit:contain;"> Schedule</a></li>
          <li><a href="/capstone/templates/supervisor/supervisor_nurses.php"><img src="/capstone/assets/img/nurse.png" alt="Nurses" style="width:18px;height:18px;object-fit:contain;"> List</a></li>
          <li><a href="/capstone/templates/supervisor/supervisor_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div class="appt-layout">
    <section class="calendar-card">
      <div class="calendar-title" style="font-size:1.8rem;font-weight:800;letter-spacing:0.1em;">CALENDAR</div>
      <div class="calendar-header" style="display:flex;align-items:center;justify-content:space-between;width:100%;">
        <a class="btn" href="?month=<?php echo date('Y-m', strtotime($calendarMonth.'-01 -1 month')); ?>" style="margin-right:auto;">&lt;</a>
        <div class="month-name" style="flex:1;text-align:center;"><?php echo ss_escape($monthLabel); ?></div>
        <a class="btn" href="?month=<?php echo date('Y-m', strtotime($calendarMonth.'-01 +1 month')); ?>" style="margin-left:auto;">&gt;</a>
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
        <?php
        // Calculate calendar grid like doctor_appointment.php
        $firstDow = (int)date('w', $monthStartTs); // 0=Sun..6=Sat
        $startDayOffset = 1 - $firstDow; // value added to index to get day number
        $cells = 42; // 6 weeks grid
        $prevMonth = date('Y-m', strtotime($calendarMonth.'-01 -1 month'));
        $daysInPrev = (int)date('t', strtotime($prevMonth.'-01'));
        $todayY = (int)date('Y'); $todayM = (int)date('n'); $todayD = (int)date('j');
        
        for($i=0; $i<$cells; $i++):
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
          $isToday = (!$muted && $calendarMonth === date('Y-m') && $display === $todayD);
          $classes = 'calendar-cell' . ($muted ? ' muted' : '') . ($isToday ? ' active' : '');
          
          // Get requests for this day
          $dateValue = '';
          if (!$muted) {
            $dateValue = date('Y-m-d', strtotime($calendarMonth.'-'.str_pad((string)$display, 2, '0', STR_PAD_LEFT)));
          $dayRequests = $requestsByDay[$dateValue] ?? [];
            $matching = $dayRequests; // Show all requests since no filtering
          }
        ?>
          <div class="<?php echo $classes; ?>">
            <div><?php echo $display; ?></div>
            <?php if (!$muted && !empty($matching)): ?>
              <div style="font-size:0.7rem;color:#64748b;margin-top:2px;">
                <?php echo count($matching); ?> shift<?php echo count($matching) !== 1 ? 's' : ''; ?>
              </div>
          <?php endif; ?>
        </div>
        <?php endfor; ?>
      </div>
    </section>

    <aside class="appt-list">
      <div class="appt-list-header">
        <h3>Schedule List</h3>
        <a class="btn btn-primary" href="#" id="openScheduleModal">+ New</a>
      </div>
      <div id="scheduleItems" style="padding:0;">
        <?php if (!empty($requests)): ?>
          <?php foreach ($requests as $row):
            $date = !empty($row['date']) ? new DateTime($row['date']) : null;
            $mon = $date ? strtoupper($date->format('M')) : '';
            $day = $date ? $date->format('j') : '';
            $year = $date ? $date->format('Y') : '';
            $timeLabel = !empty($row['time']) ? substr($row['time'],0,5) : '';
                $status = strtolower((string)($row['status'] ?? 'request'));
                $badgeColor = '#f59e0b';
                if ($status === 'accepted') $badgeColor = '#10b981';
                elseif ($status === 'rejected') $badgeColor = '#ef4444';
                elseif ($status === 'pending') $badgeColor = '#6366f1';
            ?>
          <div class="appt-item schedule-item" 
               data-id="<?php echo ss_escape($row['id'] ?? ''); ?>"
               data-nurse="<?php echo ss_escape($row['nurse'] ?: 'Unknown Nurse'); ?>"
               data-date="<?php echo ss_escape($row['date'] ?: ''); ?>"
               data-time="<?php echo ss_escape($row['time'] ?: ''); ?>"
               data-end-time="<?php echo ss_escape($row['end_time'] ?? ''); ?>"
               data-shift="<?php echo ss_escape($row['shift'] ?: ''); ?>"
               data-ward="<?php echo ss_escape($row['ward'] ?: ''); ?>"
               data-status="<?php echo ss_escape($status); ?>"
               data-notes="<?php echo ss_escape($row['notes'] ?: ''); ?>"
               data-nurse-email="<?php echo ss_escape($row['nurse_email'] ?? ''); ?>"
               data-nurse-id="<?php echo ss_escape($row['nurse_id'] ?? ''); ?>"
               style="display:flex;align-items:center;gap:6px;padding:8px 0;border-bottom:1px solid #e5e7eb;cursor:pointer;transition:background-color 0.2s ease;" 
               onmouseover="this.style.backgroundColor='#f8fafc'" 
               onmouseout="this.style.backgroundColor='transparent'">
            <div class="appt-date" style="width:56px;min-width:56px;text-align:center;">
              <div style="font-size:.70rem;color:#64748b;letter-spacing:.4px;"><?php echo htmlspecialchars($mon); ?></div>
              <div style="font-size:1.1rem;font-weight:600;line-height:1;"><?php echo htmlspecialchars($day); ?></div>
              <div style="font-size:.70rem;color:#94a3b8;line-height:1.1;"><?php echo htmlspecialchars($year); ?></div>
            </div>
            <div class="appt-meta" style="flex:1;min-width:0;">
              <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <div style="font-weight:600;color:#0f172a;margin-bottom:2px;"><?php echo ss_escape($row['nurse'] ?: 'Unknown Nurse'); ?></div>
                <div style="font-size:0.9rem;color:#64748b;"><?php echo ss_escape($timeLabel); ?> • <?php echo ss_escape($row['shift'] ? ucfirst($row['shift']) : 'Shift'); ?></div>
                <div style="font-size:0.8rem;color:#64748b;margin-top:2px;"><?php echo ss_escape($row['ward'] ?: 'No Unit'); ?></div>
              </div>
            </div>
            <div class="appt-actions" style="margin-left:auto;display:flex;align-items:center;gap:6px;">
              <button type="button" class="btn btn-outline" data-action="editSchedule">Edit</button>
              <button type="button" class="btn btn-outline" data-action="deleteSchedule" style="border-color:#ef4444;color:#ef4444;">Delete</button>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div id="noScheduleMsg" class="muted" style="padding:8px 0;<?php echo !empty($requests) ? 'display:none;' : '';?>">No schedule requests yet.</div>
    </aside>
  </div>
</div>

<!-- Edit Schedule Modal -->
<div id="editScheduleModal" style="display:none;position:fixed;inset:0;z-index:12000;">
  <div data-backdrop style="position:absolute;inset:0;background:rgba(0,0,0,0.45);z-index:0;"></div>
  <div role="dialog" aria-modal="true" style="position:relative;z-index:1;max-width:520px;margin:8vh auto;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.2);overflow:hidden;">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #e5e7eb;background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;">
      <div style="font-weight:600;font-size:1.05rem;">Edit Schedule</div>
      <button type="button" id="closeEditScheduleModal" class="btn btn-outline" style="padding:6px 10px;background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.3);color:#fff;">✕</button>
    </div>
    <form id="editScheduleForm" action="#" method="post">
      <input type="hidden" id="edit_schedule_id" />
      <div style="padding:20px;display:grid;gap:16px;">
        <div>
          <label for="edit_schedule_nurse" style="display:block;margin-bottom:6px;font-weight:600;color:#0f172a;font-size:0.9rem;">Nurse</label>
          <select id="edit_schedule_nurse" name="nurse" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';">
            <option value="">Select Nurse</option>
          </select>
        </div>
        <div>
          <label for="edit_schedule_shift" style="display:block;margin-bottom:6px;font-weight:600;color:#0f172a;font-size:0.9rem;">Title</label>
          <input id="edit_schedule_shift" name="shift" type="text" placeholder="e.g., Day Shift" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
        </div>
        <div>
          <label for="edit_schedule_ward" style="display:block;margin-bottom:6px;font-weight:600;color:#0f172a;font-size:0.9rem;">Station</label>
          <input id="edit_schedule_ward" name="ward" type="text" placeholder="e.g., ICU, Emergency" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
          <div>
            <label for="edit_schedule_date" style="display:block;margin-bottom:6px;font-weight:600;color:#0f172a;font-size:0.9rem;">Date</label>
            <input id="edit_schedule_date" name="date" type="date" required min="<?php echo date('Y-m-d'); ?>" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor:'#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
          </div>
          <div>
            <label for="edit_schedule_time" style="display:block;margin-bottom:6px;font-weight:600;color:#0f172a;font-size:0.9rem;">Start Time</label>
            <input id="edit_schedule_time" name="time" type="time" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
          </div>
          <div>
            <label for="edit_schedule_end_time" style="display:block;margin-bottom:6px;font-weight:600;color:#0f172a;font-size:0.9rem;">End Time</label>
            <input id="edit_schedule_end_time" name="end_time" type="time" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
          </div>
        </div>
        <div>
          <label for="edit_schedule_notes" style="display:block;margin-bottom:6px;font-weight:600;color:#0f172a;font-size:0.9rem;">Notes</label>
          <textarea id="edit_schedule_notes" name="notes" rows="3" placeholder="Optional details about the schedule" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;resize:vertical;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';"></textarea>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid #e5e7eb;background:#f9fafb;">
        <button type="button" class="btn btn-outline" id="cancelEditScheduleModal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="submitEditScheduleBtn" style="background:linear-gradient(135deg,#0a5d39,#10b981);">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Schedule Details Modal -->
<div id="scheduleModal" style="display:none;position:fixed;inset:0;z-index:5000;">
  <div data-backdrop style="position:absolute;inset:0;background:rgba(0,0,0,0.45);z-index:0;"></div>
  <div role="dialog" aria-modal="true" style="position:relative;z-index:1;max-width:520px;margin:8vh auto;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.2);overflow:hidden;">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #e5e7eb;background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;">
      <div id="scheduleModalTitle" style="font-weight:600;font-size:1.05rem;">Schedule Request Details</div>
      <button type="button" id="closeScheduleModal" class="btn btn-outline" style="padding:6px 10px;background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.3);color:#fff;">✕</button>
    </div>
    <div style="padding:20px;">
      <div style="display:grid;gap:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#f8fafc;border-radius:8px;">
          <div>
            <div style="font-size:0.9rem;color:#64748b;margin-bottom:4px;">Nurse</div>
            <div id="modalNurse" style="font-weight:600;color:#0f172a;">—</div>
          </div>
          <div style="text-align:right;">
            <div style="font-size:0.9rem;color:#64748b;margin-bottom:4px;">Status</div>
            <div id="modalStatus" style="font-weight:600;">—</div>
          </div>
        </div>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div>
            <div style="font-size:0.9rem;color:#64748b;margin-bottom:4px;">Date</div>
            <div id="modalDate" style="font-weight:600;color:#0f172a;">—</div>
          </div>
          <div>
            <div style="font-size:0.9rem;color:#64748b;margin-bottom:4px;">Time</div>
            <div id="modalTime" style="font-weight:600;color:#0f172a;">—</div>
          </div>
        </div>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div>
            <div style="font-size:0.9rem;color:#64748b;margin-bottom:4px;">Shift Type</div>
            <div id="modalShift" style="font-weight:600;color:#0f172a;">—</div>
          </div>
          <div>
            <div style="font-size:0.9rem;color:#64748b;margin-bottom:4px;">Unit/Ward</div>
            <div id="modalWard" style="font-weight:600;color:#0f172a;">—</div>
          </div>
        </div>
        
        <div>
          <div style="font-size:0.9rem;color:#64748b;margin-bottom:4px;">Email</div>
          <div id="modalEmail" style="font-weight:600;color:#0f172a;">—</div>
        </div>
        
        <div>
          <div style="font-size:0.9rem;color:#64748b;margin-bottom:4px;">Notes</div>
          <div id="modalNotes" style="font-weight:600;color:#0f172a;min-height:40px;padding:8px;background:#f8fafc;border-radius:6px;">—</div>
        </div>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-top:1px solid #e5e7eb;background:#f9fafb;">
      <div id="actionButtons" style="display:none;gap:8px;">
        <button type="button" class="btn btn-primary" id="acceptScheduleBtn" style="background:#10b981;border-color:#10b981;">Accept</button>
        <button type="button" class="btn btn-outline" id="rejectScheduleBtn" style="border-color:#ef4444;color:#ef4444;">Reject</button>
      </div>
      <button type="button" class="btn btn-outline" id="cancelScheduleModal">Close</button>
    </div>
  </div>
</div>

<!-- New Schedule Modal -->
<div id="newScheduleModal" style="display:none;position:fixed;inset:0;z-index:5000;">
  <div data-backdrop style="position:absolute;inset:0;background:rgba(0,0,0,0.45);z-index:0;"></div>
  <div role="dialog" aria-modal="true" style="position:relative;z-index:1;max-width:520px;margin:8vh auto;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.2);overflow:hidden;">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #e5e7eb;background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;">
      <div style="font-weight:600;font-size:1.05rem;">Create New Schedule</div>
      <button type="button" id="closeNewScheduleModal" class="btn btn-outline" style="padding:6px 10px;background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.3);color:#fff;">✕</button>
    </div>
    <form id="newScheduleForm" action="#" method="post">
      <div style="padding:20px;display:grid;gap:16px;">
        <div>
          <label for="schedule_nurse" style="display:block;margin-bottom:6px;font-weight:600;color:#0f172a;font-size:0.9rem;">Nurse</label>
          <select id="schedule_nurse" name="nurse" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';">
            <option value="">Select Nurse</option>
            <!-- Options will be populated dynamically -->
          </select>
        </div>
        
        <!-- Title -->
        <div>
          <label for="schedule_shift" style="display:block;margin-bottom:6px;font-weight:600;color:#0f172a;font-size:0.9rem;">Title</label>
          <input id="schedule_shift" name="shift" type="text" placeholder="e.g., Day Shift" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
        </div>

        <!-- Station -->
        <div>
          <label for="schedule_ward" style="display:block;margin-bottom:6px;font-weight:600;color:#0f172a;font-size:0.9rem;">Station</label>
          <input id="schedule_ward" name="ward" type="text" placeholder="e.g., ICU, Emergency" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
          <div>
            <label for="schedule_date" style="display:block;margin-bottom:6px;font-weight:600;color:#0f172a;font-size:0.9rem;">Date</label>
            <input id="schedule_date" name="date" type="date" required min="<?php echo date('Y-m-d'); ?>" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
          </div>
          <div>
            <label for="schedule_time" style="display:block;margin-bottom:6px;font-weight:600;color:#0f172a;font-size:0.9rem;">Start Time</label>
            <input id="schedule_time" name="time" type="time" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
          </div>
          <div>
            <label for="schedule_end_time" style="display:block;margin-bottom:6px;font-weight:600;color:#0f172a;font-size:0.9rem;">End Time</label>
            <input id="schedule_end_time" name="end_time" type="time" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
          </div>
        </div>
        
        
        <div>
          <label for="schedule_notes" style="display:block;margin-bottom:6px;font-weight:600;color:#0f172a;font-size:0.9rem;">Notes</label>
          <textarea id="schedule_notes" name="notes" rows="3" placeholder="Optional details about the schedule" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;resize:vertical;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';"></textarea>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid #e5e7eb;background:#f9fafb;">
        <button type="button" class="btn btn-outline" id="cancelNewScheduleModal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="submitScheduleBtn" style="background:linear-gradient(135deg,#0a5d39,#10b981);">Create Schedule</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  var modal = document.getElementById('scheduleModal');
  var closeBtn = document.getElementById('closeScheduleModal');
  var cancelBtn = document.getElementById('cancelScheduleModal');
  var backdrop = modal.querySelector('[data-backdrop]');
  var scheduleItems = document.getElementById('scheduleItems');
  
  function open(){
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
  }
  
  function close(){
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }
  
  function formatDate(dateStr) {
    if (!dateStr) return '—';
    try {
      var date = new Date(dateStr);
      return date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
      });
    } catch (e) {
      return dateStr;
    }
  }
  
  function formatTime(timeStr) {
    if (!timeStr) return '—';
    return timeStr.length > 5 ? timeStr.substring(0, 5) : timeStr;
  }
  
  function getStatusBadge(status) {
    var statusColors = {
      'request': '#f59e0b',
      'pending': '#6366f1', 
      'accepted': '#10b981',
      'rejected': '#ef4444'
    };
    var color = statusColors[status] || '#f59e0b';
    return '<span style="background:' + color + ';color:#fff;font-size:0.8rem;padding:4px 8px;border-radius:4px;font-weight:600;">' + status.toUpperCase() + '</span>';
  }
  
  function showScheduleDetails(item) {
    var nurse = item.getAttribute('data-nurse') || '—';
    var date = item.getAttribute('data-date') || '—';
    var time = item.getAttribute('data-time') || '—';
    var shift = item.getAttribute('data-shift') || '—';
    var ward = item.getAttribute('data-ward') || '—';
    var status = item.getAttribute('data-status') || '—';
    var notes = item.getAttribute('data-notes') || '—';
    var email = item.getAttribute('data-nurse-email') || '—';
    var scheduleId = item.getAttribute('data-id') || '';
    
    document.getElementById('modalNurse').textContent = nurse;
    document.getElementById('modalDate').textContent = formatDate(date);
    document.getElementById('modalTime').textContent = formatTime(time);
    document.getElementById('modalShift').textContent = shift ? shift.charAt(0).toUpperCase() + shift.slice(1) : '—';
    document.getElementById('modalWard').textContent = ward || '—';
    document.getElementById('modalStatus').innerHTML = getStatusBadge(status);
    document.getElementById('modalNotes').textContent = notes || 'No notes provided';
    document.getElementById('modalEmail').textContent = email || '—';
    
    // Show/hide action buttons based on status
    var actionButtons = document.getElementById('actionButtons');
    if (status === 'pending' || status === 'request') {
      actionButtons.style.display = 'flex';
      // Store current item reference for actions
      actionButtons.setAttribute('data-current-item', scheduleId);
    } else {
      actionButtons.style.display = 'none';
    }
    
    open();
  }
  
  // Event listeners
  if(closeBtn) closeBtn.addEventListener('click', close);
  if(cancelBtn) cancelBtn.addEventListener('click', close);
  if(backdrop) backdrop.addEventListener('click', close);
  
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape' && modal.style.display === 'block'){
      close();
    }
  });
  
  // Accept/Reject button handlers
  var acceptBtn = document.getElementById('acceptScheduleBtn');
  var rejectBtn = document.getElementById('rejectScheduleBtn');
  
  if(acceptBtn) {
    acceptBtn.addEventListener('click', function(){
      var actionButtons = document.getElementById('actionButtons');
      var scheduleId = actionButtons.getAttribute('data-current-item');
      if(scheduleId) {
        updateScheduleStatus(scheduleId, 'accepted');
      }
    });
  }
  
  if(rejectBtn) {
    rejectBtn.addEventListener('click', function(){
      var actionButtons = document.getElementById('actionButtons');
      var scheduleId = actionButtons.getAttribute('data-current-item');
      if(scheduleId) {
        updateScheduleStatus(scheduleId, 'rejected');
      }
    });
  }
  
  function updateScheduleStatus(scheduleId, newStatus) {
    var confirmMessage = newStatus === 'accepted' ? 
      'Are you sure you want to accept this schedule request?' : 
      'Are you sure you want to reject this schedule request?';
    if(!confirm(confirmMessage)) return;
    fetch('/capstone/schedules/requests.php?id='+encodeURIComponent(scheduleId),{
      method:'PUT',
      headers:{ 'Content-Type':'application/json' },
      body: JSON.stringify({ status: newStatus })
    }).then(function(res){
      if(!res.ok) throw new Error('Failed to update');
      return res.json();
    }).then(function(){
      alert('Schedule request '+newStatus+' successfully!');
      close();
      window.location.reload();
    }).catch(function(err){
      console.error(err);
      alert('Error updating schedule: '+err.message);
    });
  }
  
  // Click handlers for schedule list (item open, edit, delete)
  if(scheduleItems) {
    scheduleItems.addEventListener('click', function(e){
      var editBtn = e.target.closest('[data-action="editSchedule"]');
      var delBtn = e.target.closest('[data-action="deleteSchedule"]');
      var item = e.target.closest('.schedule-item');

      if(editBtn && item){
        e.preventDefault();
        e.stopPropagation();
        openEditFromItem(item);
        return;
      }

      if(delBtn && item){
        e.preventDefault();
        e.stopPropagation();
        var name = item.getAttribute('data-nurse') || 'this schedule';
        if(confirm('Delete '+name+'? This cannot be undone.')){
          item.parentNode.removeChild(item);
          // Toggle empty message if list is empty
          var anyLeft = scheduleItems.querySelector('.schedule-item');
          var emptyMsg = document.getElementById('noScheduleMsg');
          if(!anyLeft && emptyMsg){ emptyMsg.style.display = ''; }
        }
        return;
      }

      if(item){
        showScheduleDetails(item);
      }
    });
  }
})();

// Edit Schedule Modal functionality
(function(){
  var editModal = document.getElementById('editScheduleModal');
  var closeEditBtn = document.getElementById('closeEditScheduleModal');
  var cancelEditBtn = document.getElementById('cancelEditScheduleModal');
  var editBackdrop = editModal ? editModal.querySelector('[data-backdrop]') : null;
  var editForm = document.getElementById('editScheduleForm');

  async function loadEditNurses(selectedId){
    try {
      var response = await fetch('/capstone/templates/supervisor/get_nurses.php');
      if (response.ok) {
        var nurses = await response.json();
        var select = document.getElementById('edit_schedule_nurse');
        if (select) {
          select.innerHTML = '<option value="">Select Nurse</option>';
          nurses.forEach(function(nurse) {
            var option = document.createElement('option');
            option.value = nurse.id;
            option.textContent = nurse.name;
            if(String(nurse.id) === String(selectedId||'')) option.selected = true;
            select.appendChild(option);
          });
        }
      }
    } catch (err) { console.error('Failed to load nurses for edit', err); }
  }

  function open(){ if(editModal){ editModal.style.display='block'; document.body.style.overflow='hidden'; } }
  function close(){ if(editModal){ editModal.style.display='none'; document.body.style.overflow=''; } }

  window.openEditFromItem = function(item){
    if(!item) return;
    var id = item.getAttribute('data-id')||'';
    var nurseId = item.getAttribute('data-nurse-id')||'';
    var shift = item.getAttribute('data-shift')||'';
    var ward = item.getAttribute('data-ward')||'';
    var date = item.getAttribute('data-date')||'';
    var time = item.getAttribute('data-time')||'';
    var endTime = item.getAttribute('data-end-time')||'';
    var notes = item.getAttribute('data-notes')||'';

    document.getElementById('edit_schedule_id').value = id;
    document.getElementById('edit_schedule_shift').value = shift;
    document.getElementById('edit_schedule_ward').value = ward;
    document.getElementById('edit_schedule_date').value = date;
    document.getElementById('edit_schedule_time').value = time;
    document.getElementById('edit_schedule_end_time').value = endTime;
    document.getElementById('edit_schedule_notes').value = notes;
    loadEditNurses(nurseId);
    setupEditValidation();
    open();
  }

  function setupEditValidation(){
    var dateInput = document.getElementById('edit_schedule_date');
    var timeInput = document.getElementById('edit_schedule_time');
    var endInput = document.getElementById('edit_schedule_end_time');
    if(!dateInput || !timeInput || !endInput) return;
    var today = new Date().toISOString().split('T')[0];
    dateInput.min = today;
    dateInput.addEventListener('change', function(){
      var d=new Date(this.value); var t=new Date(); t.setHours(0,0,0,0);
      if(d<t){ alert('Cannot set past date.'); this.value=today; }
    });
    timeInput.addEventListener('change', function(){
      if(endInput.value && this.value && endInput.value < this.value){ alert('End Time must be after Start Time.'); endInput.value=''; }
    });
    endInput.addEventListener('change', function(){
      if(timeInput.value && this.value && this.value < timeInput.value){ alert('End Time must be after Start Time.'); this.value=''; }
    });
  }

  if(closeEditBtn) closeEditBtn.addEventListener('click', close);
  if(cancelEditBtn) cancelEditBtn.addEventListener('click', close);
  if(editBackdrop) editBackdrop.addEventListener('click', close);

  if(editForm){
    editForm.addEventListener('submit', async function(e){
      e.preventDefault();
      var data = {
        id: document.getElementById('edit_schedule_id').value,
        nurse_id: document.getElementById('edit_schedule_nurse').value,
        shift: document.getElementById('edit_schedule_shift').value,
        ward: document.getElementById('edit_schedule_ward').value,
        date: document.getElementById('edit_schedule_date').value,
        time: document.getElementById('edit_schedule_time').value,
        end_time: document.getElementById('edit_schedule_end_time').value,
        notes: document.getElementById('edit_schedule_notes').value
      };
      try{
        var res = await fetch('/capstone/templates/supervisor/update_schedule.php', {
          method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data)
        });
        if(!res.ok) throw new Error('Failed to update schedule');
        // Update DOM row
        var row = document.querySelector('.schedule-item[data-id="'+data.id+'"]');
        if(row){
          row.setAttribute('data-nurse-id', data.nurse_id);
          row.setAttribute('data-shift', data.shift);
          row.setAttribute('data-ward', data.ward);
          row.setAttribute('data-date', data.date);
          row.setAttribute('data-time', data.time);
          row.setAttribute('data-end-time', data.end_time);
          row.setAttribute('data-notes', data.notes);
          // Update visible text values
          var meta = row.querySelector('.appt-meta');
          if(meta){
            var lines = meta.querySelectorAll('div');
            if(lines[0]) lines[0].textContent = (document.getElementById('edit_schedule_nurse').selectedOptions[0]?.textContent)|| lines[0].textContent;
            if(lines[1]) lines[1].textContent = (data.time.slice(0,5)||'') + ' • ' + (data.shift || 'Shift');
            if(lines[2]) lines[2].textContent = data.ward || 'No Unit';
          }
          // Update date badge
          var d = new Date(data.date);
          if(!isNaN(d)){ 
            var mon = d.toLocaleString('en-US', {month:'short'}).toUpperCase();
            row.querySelector('.appt-date div:nth-child(1)').textContent = mon;
            row.querySelector('.appt-date div:nth-child(2)').textContent = d.getDate();
            row.querySelector('.appt-date div:nth-child(3)').textContent = d.getFullYear();
          }
        }
        alert('Schedule updated successfully');
        close();
      }catch(err){
        alert('Error: '+err.message);
      }
    });
  }
})();

// New Schedule Modal functionality
(function(){
  var newModal = document.getElementById('newScheduleModal');
  var openNewBtn = document.getElementById('openScheduleModal');
  var closeNewBtn = document.getElementById('closeNewScheduleModal');
  var cancelNewBtn = document.getElementById('cancelNewScheduleModal');
  var newBackdrop = newModal.querySelector('[data-backdrop]');
  var newForm = document.getElementById('newScheduleForm');
  
  function openNew(){
    newModal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    loadNurses(); // Load nurses when modal opens
    setupDateValidation(); // Setup date validation
  }
  
  function setupDateValidation() {
    var dateInput = document.getElementById('schedule_date');
    var timeInput = document.getElementById('schedule_time');
    var endInput = document.getElementById('schedule_end_time');
    
    if (dateInput && timeInput) {
      // Set minimum date to today
      var today = new Date().toISOString().split('T')[0];
      dateInput.min = today;
      
      // Add event listener for date change
      dateInput.addEventListener('change', function() {
        var selectedDate = new Date(this.value);
        var today = new Date();
        today.setHours(0, 0, 0, 0); // Reset time to start of day
        
        if (selectedDate < today) {
          alert('Cannot schedule for past dates. Please select today or a future date.');
          this.value = today.toISOString().split('T')[0];
          return;
        }
        
        // If selected date is today, set minimum time to current time
        if (selectedDate.getTime() === today.getTime()) {
          var now = new Date();
          var currentTime = now.getHours().toString().padStart(2, '0') + ':' + 
                           now.getMinutes().toString().padStart(2, '0');
          timeInput.min = currentTime;
          if (endInput) endInput.min = timeInput.value || currentTime;
          
          // If current time is already set and it's in the past, clear it
          if (timeInput.value && timeInput.value < currentTime) {
            timeInput.value = '';
            alert('Selected time is in the past. Please select a future time for today.');
          }
          if (endInput && endInput.value && endInput.value < (timeInput.value || currentTime)) {
            endInput.value = '';
          }
        } else {
          // For future dates, remove time restriction
          timeInput.min = '';
          if (endInput) endInput.min = timeInput.value || '';
        }
      });
      
      // Add event listener for time change
      timeInput.addEventListener('change', function() {
        var selectedDate = new Date(dateInput.value);
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // If selected date is today, validate time
        if (selectedDate.getTime() === today.getTime()) {
          var now = new Date();
          var currentTime = now.getHours().toString().padStart(2, '0') + ':' + 
                           now.getMinutes().toString().padStart(2, '0');
          
          if (this.value < currentTime) {
            alert('Cannot schedule for past time. Please select a future time.');
            this.value = '';
          }
        }
        // Update end time min and validate against start time
        if (endInput) {
          endInput.min = this.value || '';
          if (endInput.value && this.value && endInput.value < this.value) {
            alert('End Time must be after Start Time.');
            endInput.value = '';
          }
        }
      });

      // Add event listener for end time change
      if (endInput) {
        endInput.addEventListener('change', function() {
          var start = timeInput.value || '';
          if (start && this.value < start) {
            alert('End Time must be after Start Time.');
            this.value = '';
          }
        });
      }
    }
  }
  
  async function loadNurses() {
    try {
      var response = await fetch('/capstone/templates/supervisor/get_nurses.php');
      if (response.ok) {
        var nurses = await response.json();
        var select = document.getElementById('schedule_nurse');
        if (select) {
          // Clear existing options except the first one
          select.innerHTML = '<option value="">Select Nurse</option>';
          
          // Add nurse options
          nurses.forEach(function(nurse) {
            var option = document.createElement('option');
            option.value = nurse.id;
            option.textContent = nurse.name;
            select.appendChild(option);
          });
        }
      } else {
        console.error('Failed to load nurses');
      }
    } catch (error) {
      console.error('Error loading nurses:', error);
    }
  }
  
  function closeNew(){
    newModal.style.display = 'none';
    document.body.style.overflow = '';
    newForm.reset();
  }
  
  // Event listeners for new schedule modal
  if(openNewBtn) openNewBtn.addEventListener('click', function(e){ e.preventDefault(); openNew(); });
  if(closeNewBtn) closeNewBtn.addEventListener('click', closeNew);
  if(cancelNewBtn) cancelNewBtn.addEventListener('click', closeNew);
  if(newBackdrop) newBackdrop.addEventListener('click', closeNew);
  
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape' && newModal.style.display === 'block'){
      closeNew();
    }
  });
  
  // Form submission
  if(newForm) {
    newForm.addEventListener('submit', async function(e){
      e.preventDefault();
      
      var formData = new FormData(newForm);
      var data = {
        nurse_id: formData.get('nurse'),
        date: formData.get('date'),
        time: formData.get('time'),
        end_time: formData.get('end_time'),
        shift: formData.get('shift'),
        ward: formData.get('ward'),
        notes: formData.get('notes'),
        status: 'accepted' // New schedules are automatically accepted
      };
      
      try {
        var response = await fetch('/capstone/templates/supervisor/create_schedule.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        });
        
        if(response.ok) {
          alert('Schedule created successfully!');
          closeNew();
          window.location.reload();
        } else {
          var error = await response.text();
          throw new Error(error || 'Failed to create schedule');
        }
      } catch(error) {
        console.error('Error creating schedule:', error);
        alert('Error creating schedule: ' + error.message);
      }
    });
  }
})();
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>

