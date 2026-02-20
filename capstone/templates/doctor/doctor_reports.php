<?php $page = 'Analytics Dashboard';
include __DIR__ . '/../../includes/header.php'; ?>

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

  <div class="content" style="width:100%;max-width:none;margin:0;">
    <?php
    require_once __DIR__ . '/../../config/db.php';
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    $uid = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
    $doctorName = isset($_SESSION['user']['full_name']) ? (string)$_SESSION['user']['full_name'] : '';

    $selectedYear  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
    $selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
    if ($selectedYear < 1970 || $selectedYear > 2100) {
      $selectedYear = (int)date('Y');
    }
    if ($selectedMonth < 1 || $selectedMonth > 12) {
      $selectedMonth = (int)date('n');
    }
    $monthStart = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    $fromDate = $monthStart;
    $toDate = $monthEnd;
    $fromTs = $fromDate . ' 00:00:00';
    $toTsExclusive = date('Y-m-d', strtotime($toDate . ' +1 day')) . ' 00:00:00';

    $prevTs = mktime(0, 0, 0, $selectedMonth - 1, 1, $selectedYear);
    $nextTs = mktime(0, 0, 0, $selectedMonth + 1, 1, $selectedYear);
    $prevMonth = (int)date('n', $prevTs);
    $prevYear = (int)date('Y', $prevTs);
    $nextMonth = (int)date('n', $nextTs);
    $nextYear = (int)date('Y', $nextTs);

    $prevMonthStart = sprintf('%04d-%02d-01', $prevYear, $prevMonth);
    $prevMonthEnd = date('Y-m-t', strtotime($prevMonthStart));
    $prevFromDate = $prevMonthStart;
    $prevToDate = $prevMonthEnd;
    $prevFromTs = $prevFromDate . ' 00:00:00';
    $prevToTsExclusive = date('Y-m-d', strtotime($prevToDate . ' +1 day')) . ' 00:00:00';

    $kpiPatients = 0;
    $kpiApptsTotal = 0;
    $kpiApptsDone = 0;
    $kpiApptsPending = 0;
    $kpiRxTotal = 0;
    $kpiLabReqTotal = 0;
    $prevKpiPatients = 0;
    $prevKpiApptsTotal = 0;
    $prevKpiApptsDone = 0;
    $prevKpiRxTotal = 0;
    $prevKpiLabReqTotal = 0;
    $rxStatusCounts = [];
    $dailyAppts = [];
    $dailyRx = [];
    $topPatients = [];
    $topMeds = [];
    $reportError = '';

    function dr_escape($v)
    {
      return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }

    $daysInMonth = (int)date('t', strtotime($monthStart));
    for ($d = 1; $d <= $daysInMonth; $d++) {
      $key = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $d);
      $dailyAppts[$key] = 0;
      $dailyRx[$key] = 0;
    }

    try {
      $pdo = get_pdo();

      // Appointments
      $stmtAp = $pdo->prepare('SELECT patient, "date"::date AS d, COALESCE(done, false) AS done FROM appointments WHERE created_by_user_id = :uid AND "date"::date >= :from_d AND "date"::date <= :to_d');
      $stmtAp->execute([
        ':uid' => $uid,
        ':from_d' => $fromDate,
        ':to_d' => $toDate,
      ]);
      $apRows = $stmtAp->fetchAll(PDO::FETCH_ASSOC) ?: [];
      $patientCounts = [];
      $patientSet = [];
      $prevPatientSet = [];
      foreach ($apRows as $r) {
        $kpiApptsTotal++;
        $isDone = (bool)($r['done'] ?? false);
        if ($isDone) {
          $kpiApptsDone++;
        } else {
          $kpiApptsPending++;
        }
        $p = trim((string)($r['patient'] ?? ''));
        if ($p !== '') {
          $patientSet[$p] = true;
          $patientCounts[$p] = ($patientCounts[$p] ?? 0) + 1;
        }
        $dd = isset($r['d']) ? substr((string)$r['d'], 0, 10) : '';
        if ($dd !== '' && array_key_exists($dd, $dailyAppts)) {
          $dailyAppts[$dd] = (int)$dailyAppts[$dd] + 1;
        }
      }

      $stmtApPrev = $pdo->prepare('SELECT patient, COALESCE(done, false) AS done FROM appointments WHERE created_by_user_id = :uid AND "date"::date >= :from_d AND "date"::date <= :to_d');
      $stmtApPrev->execute([
        ':uid' => $uid,
        ':from_d' => $prevFromDate,
        ':to_d' => $prevToDate,
      ]);
      $apPrevRows = $stmtApPrev->fetchAll(PDO::FETCH_ASSOC) ?: [];
      foreach ($apPrevRows as $r) {
        $prevKpiApptsTotal++;
        $isDone = (bool)($r['done'] ?? false);
        if ($isDone) {
          $prevKpiApptsDone++;
        }
        $p = trim((string)($r['patient'] ?? ''));
        if ($p !== '') {
          $prevPatientSet[$p] = true;
        }
      }

      // Prescriptions (discover columns/ownership)
      $colStmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'prescription'");
      $colStmt->execute();
      $rxCols = array_map('strtolower', array_map('strval', array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
      $rxHasOwner = in_array('created_by_user_id', $rxCols, true);
      $rxHasStatus = in_array('status', $rxCols, true);

      if ($rxHasOwner) {
        $sqlRx = 'SELECT patient_name, medicine, created_at, ' . ($rxHasStatus ? 'status' : "'' AS status") . ' FROM prescription WHERE created_by_user_id = :uid AND created_at >= :from_ts AND created_at < :to_ts';
        $stmtRx = $pdo->prepare($sqlRx);
        $stmtRx->execute([
          ':uid' => $uid,
          ':from_ts' => $fromTs,
          ':to_ts' => $toTsExclusive,
        ]);
      } else {
        $sqlRx = 'SELECT patient_name, medicine, created_at, ' . ($rxHasStatus ? 'status' : "'' AS status") . ' FROM prescription WHERE doctor_name = :doctor_name AND created_at >= :from_ts AND created_at < :to_ts';
        $stmtRx = $pdo->prepare($sqlRx);
        $stmtRx->execute([
          ':doctor_name' => $doctorName,
          ':from_ts' => $fromTs,
          ':to_ts' => $toTsExclusive,
        ]);
      }

      $rxRows = $stmtRx->fetchAll(PDO::FETCH_ASSOC) ?: [];
      $medCounts = [];
      foreach ($rxRows as $r) {
        $kpiRxTotal++;
        $p = trim((string)($r['patient_name'] ?? ''));
        if ($p !== '') {
          $patientSet[$p] = true;
          $patientCounts[$p] = ($patientCounts[$p] ?? 0) + 1;
        }
        $m = trim((string)($r['medicine'] ?? ''));
        if ($m !== '') {
          $medCounts[$m] = ($medCounts[$m] ?? 0) + 1;
        }
        if ($rxHasStatus) {
          $s = trim((string)($r['status'] ?? ''));
          if ($s === '') $s = 'Unknown';
          $rxStatusCounts[$s] = ($rxStatusCounts[$s] ?? 0) + 1;
        }
        $createdAt = (string)($r['created_at'] ?? '');
        $dd = $createdAt !== '' ? substr($createdAt, 0, 10) : '';
        if ($dd !== '' && array_key_exists($dd, $dailyRx)) {
          $dailyRx[$dd] = (int)$dailyRx[$dd] + 1;
        }
      }

      if ($rxHasOwner) {
        $sqlRxPrev = 'SELECT patient_name FROM prescription WHERE created_by_user_id = :uid AND created_at >= :from_ts AND created_at < :to_ts';
        $stmtRxPrev = $pdo->prepare($sqlRxPrev);
        $stmtRxPrev->execute([
          ':uid' => $uid,
          ':from_ts' => $prevFromTs,
          ':to_ts' => $prevToTsExclusive,
        ]);
      } else {
        $sqlRxPrev = 'SELECT patient_name FROM prescription WHERE doctor_name = :doctor_name AND created_at >= :from_ts AND created_at < :to_ts';
        $stmtRxPrev = $pdo->prepare($sqlRxPrev);
        $stmtRxPrev->execute([
          ':doctor_name' => $doctorName,
          ':from_ts' => $prevFromTs,
          ':to_ts' => $prevToTsExclusive,
        ]);
      }
      $rxPrevRows = $stmtRxPrev->fetchAll(PDO::FETCH_ASSOC) ?: [];
      foreach ($rxPrevRows as $r) {
        $prevKpiRxTotal++;
        $p = trim((string)($r['patient_name'] ?? ''));
        if ($p !== '') {
          $prevPatientSet[$p] = true;
        }
      }

      // Lab Requests Sent (from notifications table; best-effort)
      try {
        $stmtLab = $pdo->prepare("SELECT COUNT(*)::int AS c FROM notifications WHERE role = 'laboratory' AND doctor_id = :uid AND time >= :from_ts::timestamptz AND time < :to_ts::timestamptz");
        $stmtLab->execute([
          ':uid' => $uid,
          ':from_ts' => $fromTs,
          ':to_ts' => $toTsExclusive,
        ]);
        $kpiLabReqTotal = (int)($stmtLab->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
      } catch (Throwable $e) {
        $kpiLabReqTotal = 0;
      }

      try {
        $stmtLabPrev = $pdo->prepare("SELECT COUNT(*)::int AS c FROM notifications WHERE role = 'laboratory' AND doctor_id = :uid AND time >= :from_ts::timestamptz AND time < :to_ts::timestamptz");
        $stmtLabPrev->execute([
          ':uid' => $uid,
          ':from_ts' => $prevFromTs,
          ':to_ts' => $prevToTsExclusive,
        ]);
        $prevKpiLabReqTotal = (int)($stmtLabPrev->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
      } catch (Throwable $e) {
        $prevKpiLabReqTotal = 0;
      }

      $kpiPatients = count($patientSet);
      $prevKpiPatients = isset($prevPatientSet) && is_array($prevPatientSet) ? count($prevPatientSet) : 0;

      arsort($patientCounts);
      foreach (array_slice($patientCounts, 0, 10, true) as $name => $cnt) {
        $topPatients[] = ['name' => $name, 'cnt' => (int)$cnt];
      }

      arsort($medCounts);
      foreach (array_slice($medCounts, 0, 10, true) as $name => $cnt) {
        $topMeds[] = ['name' => $name, 'cnt' => (int)$cnt];
      }
    } catch (Throwable $e) {
      $reportError = 'Unable to load analytics right now.';
    }

    $maxDaily = 0;
    foreach ($dailyAppts as $k => $v) {
      $maxDaily = max($maxDaily, (int)$v);
    }
    if ($maxDaily <= 0) $maxDaily = 1;

    $deltaPatients = (int)$kpiPatients - (int)$prevKpiPatients;
    $deltaAppts = (int)$kpiApptsTotal - (int)$prevKpiApptsTotal;
    $deltaRx = (int)$kpiRxTotal - (int)$prevKpiRxTotal;
    $deltaLab = (int)$kpiLabReqTotal - (int)$prevKpiLabReqTotal;

    function dr_delta_badge($delta)
    {
      $d = (int)$delta;
      $isPos = $d >= 0;
      $color = $isPos ? '#16a34a' : '#dc2626';
      $sign = $isPos ? '+' : '';
      return '<span style="display:inline-flex;align-items:center;gap:6px;color:' . $color . ';font-weight:800;font-size:0.9rem;">' . ($isPos ? '↗' : '↘') . ' ' . $sign . $d . '</span>';
    }
    ?>

    <style>
      .ad-page {
        background: #f8fafc;
      }

      .ad-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
      }

      .ad-title {
        margin: 0;
        font-size: 28px;
        font-weight: 800;
        color: #0f172a;
      }

      .ad-subtitle {
        margin-top: 6px;
        color: #64748b;
        font-weight: 500;
      }

      .ad-month {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 12px 14px;
        box-shadow: 0 2px 10px rgba(2, 6, 23, 0.04);
      }

      .ad-month a {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        text-decoration: none;
        color: #0f172a;
        border: 1px solid #e5e7eb;
        background: #fff;
      }

      .ad-month a:hover {
        background: #f8fafc;
      }

      .ad-month-label {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 800;
        color: #0f172a;
        min-width: 160px;
        justify-content: center;
      }

      .ad-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 18px;
        margin-bottom: 18px;
      }

      .ad-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 18px;
        box-shadow: 0 2px 10px rgba(2, 6, 23, 0.04);
        position: relative;
      }

      .ad-card-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
      }

      .ad-icon {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .ad-label {
        margin-top: 14px;
        color: #334155;
        font-weight: 800;
      }

      .ad-value {
        margin-top: 6px;
        font-size: 38px;
        font-weight: 900;
        color: #0f172a;
        line-height: 1;
      }

      .ad-meta {
        margin-top: 6px;
        color: #64748b;
        font-size: 0.9rem;
      }

      .ad-chart {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 18px;
        box-shadow: 0 2px 10px rgba(2, 6, 23, 0.04);
      }

      .ad-chart-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
      }

      .ad-chart-title {
        margin: 0;
        font-weight: 900;
        color: #0f172a;
        font-size: 1.05rem;
      }

      .ad-chart-sub {
        margin-top: 4px;
        color: #64748b;
        font-weight: 600;
        font-size: 0.9rem;
      }

      .ad-bars {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        height: 190px;
        padding: 12px 8px;
        border-radius: 14px;
        background: #ffffff;
      }

      .ad-bar {
        flex: 1;
        min-width: 6px;
        border-radius: 8px 8px 0 0;
        background: #e9d5ff;
      }

      @media (max-width: 900px) {
        .ad-header {
          flex-direction: column;
          align-items: stretch;
        }

        .ad-grid {
          grid-template-columns: 1fr;
        }

        .ad-month-label {
          min-width: 120px;
        }
      }
    </style>

    <div class="ad-page">
      <div class="ad-header">
        <div>
          <h2 class="ad-title">Analytics Dashboard</h2>
          <div class="ad-subtitle">Track your practice performance</div>
          <?php if ($reportError !== ''): ?>
            <div class="muted" style="margin-top:10px;color:#b91c1c;"><?php echo dr_escape($reportError); ?></div>
          <?php endif; ?>
        </div>
        <div class="ad-month" aria-label="Month selector">
          <a href="?month=<?php echo (int)$prevMonth; ?>&year=<?php echo (int)$prevYear; ?>" aria-label="Previous month">&#10094;</a>
          <div class="ad-month-label">
            <span style="width:32px;height:32px;border-radius:10px;background:#f3e8ff;display:flex;align-items:center;justify-content:center;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
            </span>
            <span><?php echo dr_escape(date('F Y', strtotime($monthStart))); ?></span>
          </div>
          <a href="?month=<?php echo (int)$nextMonth; ?>&year=<?php echo (int)$nextYear; ?>" aria-label="Next month">&#10095;</a>
        </div>
      </div>

      <div class="ad-grid">
        <div class="ad-card">
          <div class="ad-card-top">
            <div class="ad-icon" style="background:#dbeafe;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
            </div>
            <div><?php echo dr_delta_badge($deltaPatients); ?></div>
          </div>
          <div class="ad-label">Unique Patients</div>
          <div class="ad-value"><?php echo (int)$kpiPatients; ?></div>
          <div class="ad-meta">This month</div>
        </div>

        <div class="ad-card">
          <div class="ad-card-top">
            <div class="ad-icon" style="background:#f3e8ff;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
            </div>
            <div><?php echo dr_delta_badge($deltaAppts); ?></div>
          </div>
          <div class="ad-label">Total Appointments</div>
          <div class="ad-value"><?php echo (int)$kpiApptsTotal; ?></div>
          <div class="ad-meta"><?php echo (int)$kpiApptsDone; ?> completed</div>
        </div>

        <div class="ad-card">
          <div class="ad-card-top">
            <div class="ad-icon" style="background:#dcfce7;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
              </svg>
            </div>
            <div><?php echo dr_delta_badge($deltaRx); ?></div>
          </div>
          <div class="ad-label">Prescriptions</div>
          <div class="ad-value"><?php echo (int)$kpiRxTotal; ?></div>
          <div class="ad-meta">Created</div>
        </div>

        <div class="ad-card">
          <div class="ad-card-top">
            <div class="ad-icon" style="background:#ffedd5;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 12h4l2-5 4 10 2-5h4"></path>
              </svg>
            </div>
            <div><?php echo dr_delta_badge($deltaLab); ?></div>
          </div>
          <div class="ad-label">Lab Requests</div>
          <div class="ad-value"><?php echo (int)$kpiLabReqTotal; ?></div>
          <div class="ad-meta">Sent</div>
        </div>
      </div>

      <div class="ad-chart">
        <div class="ad-chart-head">
          <div>
            <h3 class="ad-chart-title">Daily Appointments</h3>
            <div class="ad-chart-sub">Total: <?php echo (int)$kpiApptsTotal; ?> appointments</div>
          </div>
          <div style="width:36px;height:36px;border-radius:12px;background:#f3e8ff;display:flex;align-items:center;justify-content:center;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
          </div>
        </div>
        <?php
        $chartLabels = [];
        $chartDaily = [];
        $chartCum = [];
        $running = 0;
        foreach ($dailyAppts as $day => $cnt) {
          $chartLabels[] = substr((string)$day, -2);
          $val = (int)$cnt;
          $chartDaily[] = $val;
          $running += $val;
          $chartCum[] = $running;
        }
        ?>
        <div class="ad-bars" style="height:260px;">
          <canvas id="doctorDailyApptsChart" aria-label="Daily appointments chart" role="img"></canvas>
        </div>
        <script>
          (function() {
            var el = document.getElementById('doctorDailyApptsChart');
            if (!el || !window.Chart) return;
            var labels = <?php echo json_encode($chartLabels); ?>;
            var daily = <?php echo json_encode($chartDaily); ?>;
            var cum = <?php echo json_encode($chartCum); ?>;
            new Chart(el, {
              data: {
                labels: labels,
                datasets: [{
                  type: 'bar',
                  label: 'Units Sold',
                  data: daily,
                  backgroundColor: '#2563eb',
                  borderRadius: 6,
                  yAxisID: 'y'
                }, {
                  type: 'line',
                  label: 'Total Transaction',
                  data: cum,
                  borderColor: '#f97316',
                  backgroundColor: 'rgba(249, 115, 22, 0.15)',
                  tension: 0.35,
                  pointRadius: 3,
                  pointHoverRadius: 4,
                  yAxisID: 'y1'
                }]
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                  legend: {
                    position: 'bottom'
                  }
                },
                scales: {
                  y: {
                    beginAtZero: true,
                    title: {
                      display: true,
                      text: 'Units Sold'
                    }
                  },
                  y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: {
                      drawOnChartArea: false
                    },
                    title: {
                      display: true,
                      text: 'Total Transactions'
                    }
                  }
                }
              }
            });
          })();
        </script>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>