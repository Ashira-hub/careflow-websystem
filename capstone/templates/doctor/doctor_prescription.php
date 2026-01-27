<?php $page = 'Doctor Prescription';
include __DIR__ . '/../../includes/header.php'; ?>
<?php
require_once __DIR__ . '/../../config/db.php';
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
} catch (Throwable $e) {
  $patientOptions = [];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (($_GET['action'] ?? '') === 'create_lab_test')) {
  header('Content-Type: application/json');
  $input = json_decode(file_get_contents('php://input'), true);
  if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
  }

  $patient = trim((string)($input['patient_name'] ?? ''));
  $testName = trim((string)($input['test_name'] ?? ''));
  $category = trim((string)($input['category'] ?? ''));
  $testDate = trim((string)($input['test_date'] ?? ''));
  $notes = trim((string)($input['notes'] ?? ''));

  $errs = [];
  if ($patient === '') $errs[] = 'Patient is required';
  if ($testName === '') $errs[] = 'Test name is required';
  if ($category === '') $errs[] = 'Category is required';
  if ($testDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $testDate)) $errs[] = 'Valid date is required (YYYY-MM-DD)';
  if ($errs) {
    http_response_code(400);
    echo json_encode(['error' => implode("\n", $errs)]);
    exit;
  }

  try {
    $pdo = get_pdo();
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS lab_tests (
         id SERIAL PRIMARY KEY,
         patient TEXT NOT NULL,
         test_name TEXT NOT NULL,
         category TEXT,
         status TEXT,
         date DATE,
         test_date DATE,
         description TEXT,
         notes TEXT,
         created_by_user_id BIGINT,
         created_at TIMESTAMPTZ DEFAULT now(),
         updated_at TIMESTAMPTZ DEFAULT now()
       )"
    );
    try {
      $pdo->exec("ALTER TABLE lab_tests ADD COLUMN IF NOT EXISTS date DATE");
    } catch (Throwable $_) {
    }
    try {
      $pdo->exec("ALTER TABLE lab_tests ADD COLUMN IF NOT EXISTS test_date DATE");
    } catch (Throwable $_) {
    }
    try {
      $pdo->exec("ALTER TABLE lab_tests ADD COLUMN IF NOT EXISTS notes TEXT");
    } catch (Throwable $_) {
    }
    try {
      $pdo->exec("ALTER TABLE lab_tests ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now()");
    } catch (Throwable $_) {
    }
    try {
      $pdo->exec("ALTER TABLE lab_tests ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT now()");
    } catch (Throwable $_) {
    }
    try {
      $pdo->exec("ALTER TABLE lab_tests ADD COLUMN IF NOT EXISTS created_by_user_id BIGINT");
    } catch (Throwable $_) {
    }

    $colStmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'lab_tests'");
    $colStmt->execute();
    $cols = array_map('strtolower', array_map('strval', array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
    $hasLegacyDate = in_array('date', $cols, true);
    $hasTestDate = in_array('test_date', $cols, true);
    $hasNotes = in_array('notes', $cols, true);
    $hasStatus = in_array('status', $cols, true);
    $hasCreatedById = in_array('created_by_user_id', $cols, true);

    $insCols = ['patient', 'test_name', 'category'];
    $insVals = [':patient', ':test_name', ':category'];
    $params = [
      ':patient' => $patient,
      ':test_name' => $testName,
      ':category' => $category,
    ];
    if ($hasStatus) {
      $insCols[] = 'status';
      $insVals[] = ':status';
      $params[':status'] = 'Pending';
    }
    if ($hasLegacyDate) {
      $insCols[] = 'date';
      $insVals[] = ':legacy_date';
      $params[':legacy_date'] = $testDate;
    }
    if ($hasTestDate) {
      $insCols[] = 'test_date';
      $insVals[] = ':test_date';
      $params[':test_date'] = $testDate;
    }
    if ($hasNotes) {
      $insCols[] = 'notes';
      $insVals[] = ':notes';
      $params[':notes'] = $notes;
    }
    if ($hasCreatedById) {
      $insCols[] = 'created_by_user_id';
      $insVals[] = 'NULL';
    }

    $sql = 'INSERT INTO lab_tests (' . implode(',', $insCols) . ') VALUES (' . implode(',', $insVals) . ') RETURNING id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $newId = $stmt->fetchColumn();

    echo json_encode(['ok' => true, 'data' => ['id' => $newId]]);
    exit;
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create lab test']);
    exit;
  }
}
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
        window.addEventListener('DOMContentLoaded', function() {
          var doctorId = <?php echo isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0; ?>;
          var rxForm = document.querySelector('.rx-form form[data-form="prescription"]');
          var labForm = document.querySelector('.rx-form form[data-form="lab_test"]');
          var rxSection = document.getElementById('rxSection');
          var labSection = document.getElementById('labSection');
          var btnShowRx = document.getElementById('btnShowRx');
          var btnShowLab = document.getElementById('btnShowLab');

          function setActiveTab(which) {
            if (rxSection) rxSection.style.display = which === 'rx' ? '' : 'none';
            if (labSection) labSection.style.display = which === 'lab' ? '' : 'none';
            if (btnShowRx) {
              btnShowRx.style.background = which === 'rx' ? 'linear-gradient(135deg,#0a5d39,#10b981)' : '#e2e8f0';
              btnShowRx.style.color = which === 'rx' ? '#fff' : '#0f172a';
            }
            if (btnShowLab) {
              btnShowLab.style.background = which === 'lab' ? 'linear-gradient(135deg,#0a5d39,#10b981)' : '#e2e8f0';
              btnShowLab.style.color = which === 'lab' ? '#fff' : '#0f172a';
            }
          }

          if (btnShowRx) btnShowRx.addEventListener('click', function() {
            setActiveTab('rx');
          });
          if (btnShowLab) btnShowLab.addEventListener('click', function() {
            setActiveTab('lab');
          });
          setActiveTab('rx');

          var testInput = document.getElementById('lab_test_name');
          var testList = document.getElementById('labTestNameList');
          var testWrap = document.getElementById('labTestNameWrap');
          var testChevron = document.getElementById('labTestNameChevron');
          if (testInput && testList) {
            function isTestListOpen() {
              return testList.style.display === 'block';
            }

            var testOptions = [
              'Complete Blood Count (CBC)',
              'Urinalysis',
              'Fasting Blood Sugar (FBS)',
              'Random Blood Sugar (RBS)',
              'Lipid Profile',
              'Liver Function Test (LFT)',
              'Kidney Function Test (KFT)',
              'Creatinine',
              'Blood Urea Nitrogen (BUN)',
              'Electrolytes (Na/K/Cl)',
              'Hemoglobin A1c (HbA1c)',
              'Pregnancy Test',
              'COVID-19 Antigen',
              'Dengue NS1/IgM/IgG'
            ];

            function escapeHtml(s) {
              return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
            }

            function openTestList() {
              testList.style.display = 'block';
            }

            function closeTestList() {
              testList.style.display = 'none';
            }

            function renderTestList(query) {
              var q = String(query || '').toLowerCase().trim();
              var matches = testOptions.filter(function(t) {
                return q === '' || String(t).toLowerCase().indexOf(q) !== -1;
              });
              if (matches.length === 0) {
                testList.innerHTML = '<div style="padding:10px 12px;color:#64748b;font-size:0.9rem;">No matches</div>';
                return;
              }
              testList.innerHTML = matches.map(function(t) {
                return '<div data-value="' + escapeHtml(t) + '" style="padding:10px 12px;cursor:pointer;border-bottom:1px solid #f1f5f9;">' + escapeHtml(t) + '</div>';
              }).join('');
            }

            renderTestList('');
            closeTestList();

            testInput.addEventListener('focus', function() {
              renderTestList(testInput.value);
              openTestList();
            });

            var testMouseDownWasFocused = false;
            testInput.addEventListener('mousedown', function() {
              testMouseDownWasFocused = document.activeElement === testInput;
            });

            testInput.addEventListener('click', function() {
              renderTestList(testInput.value);
              if (testMouseDownWasFocused && isTestListOpen()) {
                closeTestList();
                return;
              }
              openTestList();
            });

            if (testChevron) {
              testChevron.addEventListener('mousedown', function(e) {
                e.preventDefault();
                renderTestList(testInput.value);
                if (isTestListOpen()) {
                  closeTestList();
                } else {
                  openTestList();
                }
                testInput.focus();
              });
            }

            testInput.addEventListener('input', function() {
              renderTestList(testInput.value);
              openTestList();
            });

            testList.addEventListener('mousedown', function(e) {
              var el = e.target;
              if (!el) return;
              var val = el.getAttribute('data-value');
              if (!val) return;
              e.preventDefault();
              testInput.value = val;
              closeTestList();
            });

            document.addEventListener('click', function(e) {
              if (!testWrap) {
                closeTestList();
                return;
              }
              if (!testWrap.contains(e.target)) {
                closeTestList();
              }
            });
          }

          if (!rxForm) return;
          rxForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            var doctor = (document.getElementById('doctor') || {}).value || '';
            var patient = (document.getElementById('patient') || {}).value || '';
            var medicine = (document.getElementById('medicine') || {}).value || '';
            var qty = (document.getElementById('quantity_prescribed') || {}).value || '';
            var dose = (document.getElementById('dosage_strength') || {}).value || '';
            var desc = (document.getElementById('description') || {}).value || '';

            // Validate required fields
            if (!doctor || !patient || !medicine || !qty || !dose) {
              alert('Please fill in all required fields: Doctor, Patient, Medicine, Quantity, and Dosage.');
              return;
            }

            var title = 'New prescription from ' + doctor;

            try {
              // Save prescription to database
              var dbRes = await fetch('/capstone/prescriptions/create.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                  doctor_name: doctor,
                  patient_name: patient,
                  medicine: medicine,
                  quantity: qty,
                  dosage_strength: dose,
                  description: desc
                })
              });

              if (!dbRes.ok) {
                var dbError = await dbRes.text().catch(function() {
                  return '';
                });
                throw new Error(dbError || 'Failed to save prescription');
              }
              var dbJson = await dbRes.json().catch(function() {
                return null;
              });
              var prescriptionId = dbJson && dbJson.data && dbJson.data.id ? dbJson.data.id : null;

              var parts = [];
              if (patient) parts.push('Patient: ' + patient);
              if (medicine) parts.push('Medicine: ' + medicine + (dose ? (' ' + dose) : ''));
              if (qty) parts.push('Qty: ' + qty);
              if (desc) parts.push(desc);
              if (prescriptionId !== null && prescriptionId !== undefined) {
                parts.push('PrescriptionID: ' + prescriptionId);
              }
              var body = parts.join(' | ');

              // Notify pharmacy, include prescription_id and doctor_id so backend can update DB status and doctor notifications
              var notifRes = await fetch('/capstone/notifications/pharmacy.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                  title: title,
                  body: body,
                  prescription_id: prescriptionId,
                  doctor_id: doctorId
                })
              });

              if (!notifRes.ok) {
                var t = await notifRes.text().catch(function() {
                  return '';
                });
                throw new Error(t || 'Failed to notify pharmacy');
              }

              alert('Prescription submitted successfully. Pharmacy has been notified.');
              rxForm.reset();
              if (document.getElementById('patient')) {
                document.getElementById('patient').focus();
              }
            } catch (err) {
              alert('Error: ' + err.message);
            }
          });

          if (labForm) {
            labForm.addEventListener('submit', async function(e) {
              e.preventDefault();
              var doctor = (document.getElementById('lab_doctor') || {}).value || '';
              var patient = (document.getElementById('lab_patient') || {}).value || '';
              var testName = (document.getElementById('lab_test_name') || {}).value || '';
              var category = (document.getElementById('lab_category') || {}).value || '';
              var testDate = (document.getElementById('lab_test_date') || {}).value || '';
              var notes = (document.getElementById('lab_notes') || {}).value || '';

              if (!doctor || !patient || !testName || !category || !testDate) {
                alert('Please fill in all required fields: Doctor, Patient, Test Name, Category, and Date.');
                return;
              }

              try {
                var createRes = await fetch(window.location.pathname + '?action=create_lab_test', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json'
                  },
                  body: JSON.stringify({
                    doctor_name: doctor,
                    patient_name: patient,
                    test_name: testName,
                    category: category,
                    test_date: testDate,
                    notes: notes
                  })
                });
                if (!createRes.ok) {
                  var t = await createRes.text().catch(function() {
                    return '';
                  });
                  throw new Error(t || 'Failed to create lab test');
                }
                var createJson = await createRes.json().catch(function() {
                  return null;
                });
                var labTestId = createJson && createJson.data && createJson.data.id ? createJson.data.id : null;

                var title = 'New lab test request from ' + doctor;
                var parts = [];
                if (patient) parts.push('Patient: ' + patient);
                if (testName) parts.push('Test: ' + testName);
                if (category) parts.push('Category: ' + category);
                if (testDate) parts.push('Date: ' + testDate);
                if (notes) parts.push(notes);
                if (labTestId !== null && labTestId !== undefined) {
                  parts.push('LabTestID: ' + labTestId);
                }
                var body = parts.join(' | ');

                var notifRes = await fetch('/capstone/notifications/pharmacy.php?role=laboratory', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json'
                  },
                  body: JSON.stringify({
                    title: title,
                    body: body,
                    doctor_id: doctorId
                  })
                });
                if (!notifRes.ok) {
                  var nt = await notifRes.text().catch(function() {
                    return '';
                  });
                  throw new Error(nt || 'Failed to notify laboratory');
                }

                // Best-effort: notify patient (for mobile app unread badge)
                try {
                  var pTitle = 'Laboratory test requested';
                  var pMsgParts = [];
                  if (testName) pMsgParts.push('Test: ' + testName);
                  if (category) pMsgParts.push('Category: ' + category);
                  if (testDate) pMsgParts.push('Date: ' + testDate);
                  if (doctor) pMsgParts.push('Doctor: ' + doctor);
                  if (labTestId !== null && labTestId !== undefined) {
                    pMsgParts.push('LabTestID: ' + labTestId);
                  }
                  var pMsg = pMsgParts.join(' | ');
                  await fetch('/capstone/api/notifications.php', {
                    method: 'POST',
                    headers: {
                      'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                      title: pTitle,
                      message: pMsg,
                      patient_name: patient
                    })
                  });
                } catch (e) {
                  // ignore
                }

                alert('Laboratory test request submitted successfully. Laboratory has been notified.');
                labForm.reset();
                if (document.getElementById('lab_patient')) {
                  document.getElementById('lab_patient').focus();
                }
              } catch (err) {
                alert('Error: ' + err.message);
              }
            });
          }
        });
      </script>
    </div>
  </aside>

  <div class="rx-layout">
    <div style="grid-column:1 / -1;display:flex;gap:10px;margin-bottom:16px;">
      <button type="button" id="btnShowRx" style="flex:1;padding:12px 14px;border-radius:12px;border:1px solid #e2e8f0;font-weight:700;cursor:pointer;background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;">Prescription</button>
      <button type="button" id="btnShowLab" style="flex:1;padding:12px 14px;border-radius:12px;border:1px solid #e2e8f0;font-weight:700;cursor:pointer;background:#e2e8f0;color:#0f172a;">Laboratory Test</button>
    </div>

    <section id="rxSection" class="rx-form" style="grid-column:1 / -1;background:#fff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,0.08);">
      <div style="background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;padding:24px 28px;border-top-left-radius:20px;border-top-right-radius:20px;">
        <h3 style="margin:0;font-size:1.3rem;font-weight:700;color:#fff;">Create Prescription</h3>
        <p style="margin:4px 0 0;opacity:0.9;font-size:0.9rem;">Fill in the details below</p>
      </div>
      <form method="post" action="#" onsubmit="return false;" data-form="prescription">
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
                    <path d="M6 9l6 6 6-6" />
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

    <section id="labSection" class="rx-form" style="grid-column:1 / -1;display:none;background:#fff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,0.08);">
      <div style="background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;padding:24px 28px;border-top-left-radius:20px;border-top-right-radius:20px;">
        <h3 style="margin:0;font-size:1.3rem;font-weight:700;color:#fff;">Request Laboratory Test</h3>
        <p style="margin:4px 0 0;opacity:0.9;font-size:0.9rem;">Fill in the details below</p>
      </div>
      <form method="post" action="#" onsubmit="return false;" data-form="lab_test">
        <div style="padding:28px;display:grid;gap:20px;">
          <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div class="form-field">
              <label for="lab_doctor" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Doctor Name</label>
              <input type="text" id="lab_doctor" name="lab_doctor" value="<?php echo htmlspecialchars($_SESSION['user']['full_name'] ?? ''); ?>" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
            </div>
            <div class="form-field">
              <label for="lab_patient" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Patient Name</label>
              <div style="position:relative;">
                <select id="lab_patient" name="lab_patient" required style="width:100%;padding:12px 40px 12px 16px;border:2px solid #e2e8f0;border-radius:12px;background:#f8fafc;appearance:none;-webkit-appearance:none;-moz-appearance:none;transition:all 0.2s ease;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';">
                  <option value="" <?php echo $selectedPatient === '' ? 'selected' : ''; ?>>Select patient</option>
                  <?php foreach ($patientOptions as $p): $isSel = ($selectedPatient !== '' && strcasecmp($selectedPatient, $p) === 0); ?>
                    <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $isSel ? 'selected' : ''; ?>><?php echo htmlspecialchars($p); ?></option>
                  <?php endforeach; ?>
                </select>
                <div style="position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:#64748b;">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 9l6 6 6-6" />
                  </svg>
                </div>
              </div>
            </div>
          </div>

          <div class="form-field">
            <label for="lab_test_name" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Test Name</label>
            <div id="labTestNameWrap" style="position:relative;">
              <input type="text" id="lab_test_name" name="lab_test_name" placeholder="Select or type test name" required autocomplete="off" style="width:100%;padding:12px 40px 12px 16px;border:2px solid #e2e8f0;border-radius:12px;background:#f8fafc;transition:all 0.2s ease;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
              <div id="labTestNameChevron" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:auto;cursor:default;color:#64748b;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M6 9l6 6 6-6" />
                </svg>
              </div>
              <div id="labTestNameList" style="display:none;position:absolute;left:0;right:0;top:calc(100% + 6px);background:#fff;border:2px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.08);max-height:220px;overflow:auto;z-index:50;"></div>
            </div>
          </div>

          <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div class="form-field">
              <label for="lab_category" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Category</label>
              <div style="position:relative;">
                <select id="lab_category" name="lab_category" required style="width:100%;padding:12px 40px 12px 16px;border:2px solid #e2e8f0;border-radius:12px;background:#f8fafc;appearance:none;-webkit-appearance:none;-moz-appearance:none;transition:all 0.2s ease;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';">
                  <option value="">Select category</option>
                  <option value="Hematology">Hematology</option>
                  <option value="Chemistry">Chemistry</option>
                  <option value="Urinalysis">Urinalysis</option>
                  <option value="Microbiology">Microbiology</option>
                  <option value="Immunology">Immunology</option>
                  <option value="Serology">Serology</option>
                  <option value="Other">Other</option>
                </select>
                <div style="position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:#64748b;">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 9l6 6 6-6" />
                  </svg>
                </div>
              </div>
            </div>
            <div class="form-field">
              <label for="lab_test_date" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Test Date</label>
              <input type="date" id="lab_test_date" name="lab_test_date" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
            </div>
          </div>

          <div class="form-field">
            <label for="lab_notes" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Notes</label>
            <textarea id="lab_notes" name="lab_notes" rows="3" placeholder="Enter notes" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;resize:vertical;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';"></textarea>
          </div>
        </div>
        <div class="rx-actions" style="display:flex;justify-content:center;gap:12px;padding:24px 28px;border-top:1px solid #e5e7eb;background:linear-gradient(135deg,#f8fafc,#f1f5f9);">
          <button class="btn btn-primary" type="submit" style="padding:14px 28px;border-radius:12px;font-weight:700;font-size:1rem;background:linear-gradient(135deg,#0a5d39,#10b981);">Submit Lab Test</button>
        </div>
      </form>
    </section>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>