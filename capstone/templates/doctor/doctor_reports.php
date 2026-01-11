<?php $page = 'Doctor Reports';
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

  <div>
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

    $kpiPatients = 0;
    $kpiApptsTotal = 0;
    $kpiApptsDone = 0;
    $kpiApptsPending = 0;
    $kpiRxTotal = 0;
    $kpiLabReqTotal = 0;
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

      $kpiPatients = count($patientSet);

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
    foreach ($dailyRx as $k => $v) {
      $maxDaily = max($maxDaily, (int)$v);
    }
    if ($maxDaily <= 0) $maxDaily = 1;
    ?>

    <section class="card" style="margin-bottom:16px;">
      <h2 class="dashboard-title" style="margin:0;">Analytics Reports</h2>
      <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
        <a href="?month=<?php echo (int)$prevMonth; ?>&year=<?php echo (int)$prevYear; ?>" aria-label="Previous month" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:1px solid #0a5d39;border-radius:8px;color:#0a5d39;background:#fff;text-decoration:none;">
          &#10094;
        </a>
        <span style="color:#0a5d39;font-weight:700;"><?php echo dr_escape(date('F Y', strtotime($monthStart))); ?></span>
        <a href="?month=<?php echo (int)$nextMonth; ?>&year=<?php echo (int)$nextYear; ?>" aria-label="Next month" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:1px solid #0a5d39;border-radius:8px;color:#0a5d39;background:#fff;text-decoration:none;">
          &#10095;
        </a>
        <span class="muted" style="color:#64748b;"><?php echo dr_escape($toDate); ?></span>
      </div>
      <?php if ($reportError !== ''): ?>
        <div class="muted" style="margin-top:10px;color:#b91c1c;"><?php echo dr_escape($reportError); ?></div>
      <?php endif; ?>
    </section>

    <section style="margin-bottom:16px;">
      <div class="stat-cards" style="display:grid;grid-template-columns:repeat(5,1fr);gap:16px;">
        <div class="stat-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
          <h4 style="margin:0 0 8px;color:#0f172a;font-size:1rem;font-weight:600;">Patients</h4>
          <div class="muted-small" style="color:#64748b;font-size:0.85rem;margin-bottom:8px;">Unique this month</div>
          <div class="stat-value" style="font-size:2rem;font-weight:700;color:#0a5d39;"><?php echo (int)$kpiPatients; ?></div>
        </div>
        <div class="stat-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
          <h4 style="margin:0 0 8px;color:#0f172a;font-size:1rem;font-weight:600;">Appointments</h4>
          <div class="muted-small" style="color:#64748b;font-size:0.85rem;margin-bottom:8px;">Total</div>
          <div class="stat-value" style="font-size:2rem;font-weight:700;color:#0a5d39;"><?php echo (int)$kpiApptsTotal; ?></div>
        </div>
        <div class="stat-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
          <h4 style="margin:0 0 8px;color:#0f172a;font-size:1rem;font-weight:600;">Appointments</h4>
          <div class="muted-small" style="color:#64748b;font-size:0.85rem;margin-bottom:8px;">Completed</div>
          <div class="stat-value" style="font-size:2rem;font-weight:700;color:#0a5d39;"><?php echo (int)$kpiApptsDone; ?></div>
        </div>
        <div class="stat-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
          <h4 style="margin:0 0 8px;color:#0f172a;font-size:1rem;font-weight:600;">Prescriptions</h4>
          <div class="muted-small" style="color:#64748b;font-size:0.85rem;margin-bottom:8px;">Created</div>
          <div class="stat-value" style="font-size:2rem;font-weight:700;color:#0a5d39;"><?php echo (int)$kpiRxTotal; ?></div>
        </div>
        <div class="stat-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
          <h4 style="margin:0 0 8px;color:#0f172a;font-size:1rem;font-weight:600;">Lab Requests</h4>
          <div class="muted-small" style="color:#64748b;font-size:0.85rem;margin-bottom:8px;">Sent</div>
          <div class="stat-value" style="font-size:2rem;font-weight:700;color:#0a5d39;"><?php echo (int)$kpiLabReqTotal; ?></div>
        </div>
      </div>
    </section>

    <section style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
      <div class="card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
        <h3 style="margin:0 0 14px;color:#0f172a;font-size:1.1rem;font-weight:600;">Daily Activity (Appointments)</h3>
        <div style="display:flex;align-items:flex-end;gap:4px;height:120px;padding:8px 4px;border:1px solid #f1f5f9;border-radius:12px;background:#fbfdff;">
          <?php foreach ($dailyAppts as $day => $cnt): $h = max(6, (int)round(((int)$cnt / $maxDaily) * 100)); ?>
            <div title="<?php echo dr_escape($day); ?>: <?php echo (int)$cnt; ?>" style="flex:1;min-width:2px;height:<?php echo (int)$h; ?>%;background:linear-gradient(135deg,#0a5d39,#10b981);border-radius:6px 6px 0 0;"></div>
          <?php endforeach; ?>
        </div>
        <div class="muted-small" style="margin-top:8px;color:#64748b;font-size:0.85rem;">Hover bars for counts.</div>
      </div>

      <div class="card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
        <h3 style="margin:0 0 14px;color:#0f172a;font-size:1.1rem;font-weight:600;">Daily Activity (Prescriptions)</h3>
        <div style="display:flex;align-items:flex-end;gap:4px;height:120px;padding:8px 4px;border:1px solid #f1f5f9;border-radius:12px;background:#fbfdff;">
          <?php foreach ($dailyRx as $day => $cnt): $h = max(6, (int)round(((int)$cnt / $maxDaily) * 100)); ?>
            <div title="<?php echo dr_escape($day); ?>: <?php echo (int)$cnt; ?>" style="flex:1;min-width:2px;height:<?php echo (int)$h; ?>%;background:linear-gradient(135deg,#0a5d39,#10b981);border-radius:6px 6px 0 0;"></div>
          <?php endforeach; ?>
        </div>
        <div class="muted-small" style="margin-top:8px;color:#64748b;font-size:0.85rem;">Hover bars for counts.</div>
      </div>
    </section>

    <section style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
      <div class="card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
        <h3 style="margin:0 0 16px;color:#0f172a;font-size:1.1rem;font-weight:600;">Top Patients</h3>
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="border-bottom:1px solid #e5e7eb;">
              <th style="padding:8px 0;text-align:left;font-weight:600;color:#0f172a;font-size:0.9rem;">Patient</th>
              <th style="padding:8px 0;text-align:right;font-weight:600;color:#0f172a;font-size:0.9rem;">Count</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($topPatients)): ?>
              <tr>
                <td colspan="2" class="muted" style="text-align:center;padding:16px;color:#64748b;">No data for this month.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($topPatients as $row): ?>
                <tr style="border-bottom:1px solid #f1f5f9;">
                  <td style="padding:8px 0;color:#0f172a;font-size:0.9rem;"><?php echo dr_escape($row['name'] ?? ''); ?></td>
                  <td style="padding:8px 0;text-align:right;color:#64748b;font-size:0.9rem;font-weight:600;"><?php echo (int)($row['cnt'] ?? 0); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
        <h3 style="margin:0 0 16px;color:#0f172a;font-size:1.1rem;font-weight:600;">Top Medicines</h3>
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="border-bottom:1px solid #e5e7eb;">
              <th style="padding:8px 0;text-align:left;font-weight:600;color:#0f172a;font-size:0.9rem;">Medicine</th>
              <th style="padding:8px 0;text-align:right;font-weight:600;color:#0f172a;font-size:0.9rem;">Count</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($topMeds)): ?>
              <tr>
                <td colspan="2" class="muted" style="text-align:center;padding:16px;color:#64748b;">No prescriptions for this month.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($topMeds as $row): ?>
                <tr style="border-bottom:1px solid #f1f5f9;">
                  <td style="padding:8px 0;color:#0f172a;font-size:0.9rem;"><?php echo dr_escape($row['name'] ?? ''); ?></td>
                  <td style="padding:8px 0;text-align:right;color:#64748b;font-size:0.9rem;font-weight:600;"><?php echo (int)($row['cnt'] ?? 0); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <?php if (!empty($rxStatusCounts)): ?>
      <section class="card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;box-shadow:0 2px 4px rgba(0,0,0,0.05);margin-bottom:16px;">
        <h3 style="margin:0 0 16px;color:#0f172a;font-size:1.1rem;font-weight:600;">Prescription Status Breakdown</h3>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
          <?php foreach ($rxStatusCounts as $st => $cnt): ?>
            <div style="border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;background:#f8fafc;">
              <div style="font-weight:700;color:#0f172a;"><?php echo dr_escape($st); ?></div>
              <div class="muted" style="color:#64748b;font-size:0.9rem;"><?php echo (int)$cnt; ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>