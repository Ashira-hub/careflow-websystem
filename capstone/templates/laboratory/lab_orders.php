<?php
 $page='Lab Orders';
 require_once __DIR__.'/../../config/db.php';
 if (session_status() === PHP_SESSION_NONE) { session_start(); }
 // Ensure no stale cache
 header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
 header('Cache-Control: post-check=0, pre-check=0', false);
 header('Pragma: no-cache');

 $tests = [];
 try {
   $pdo = get_pdo();
   // Create lab_tests table if missing; then ensure columns exist where possible
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS lab_tests (
       id SERIAL PRIMARY KEY,
       patient TEXT NOT NULL,
       test_name TEXT NOT NULL,
       category TEXT,
       status TEXT,
       test_date DATE NOT NULL,
       description TEXT,
       notes TEXT,
       created_at TIMESTAMPTZ DEFAULT now(),
       updated_at TIMESTAMPTZ DEFAULT now()
     )"
  );
  // Keep schema flexible for older installs
  try { $pdo->exec("ALTER TABLE lab_tests ADD COLUMN IF NOT EXISTS notes TEXT"); } catch (Throwable $_) {}
  // Some older schemas may lack created_at / updated_at which are used in queries
  try { $pdo->exec("ALTER TABLE lab_tests ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now()"); } catch (Throwable $_) {}
  try { $pdo->exec("ALTER TABLE lab_tests ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT now()"); } catch (Throwable $_) {}
  // Discover current columns
  $colStmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'lab_tests'");
  $colStmt->execute();
  $cols = array_map('strtolower', array_map('strval', array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
  $hasUserId      = in_array('user_id', $cols, true);             // existing but no longer written
  $hasLegacyDate  = in_array('date', $cols, true);                // primary storage for test date
  $hasCreatedById = in_array('created_by_user_id', $cols, true);  // primary storage for creator id

   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     if (empty($_SESSION['user']['id'])) {
       $_SESSION['flash_error'] = 'You must be logged in to manage lab tests.';
       header('Location: /capstone/templates/laboratory/lab_orders.php');
       exit;
     }
     $action = trim($_POST['action'] ?? '');
     $uid = (int)$_SESSION['user']['id'];
     if ($action === 'add' || $action === 'update') {
       $testId = (int)($_POST['test_id'] ?? 0);
       $testName = trim($_POST['test_name'] ?? '');
       $patient  = trim($_POST['patient'] ?? '');
       $category = trim($_POST['category'] ?? '');
       $status   = trim($_POST['status'] ?? '');
       $testDate = trim($_POST['test_date'] ?? '');
       $notes    = trim($_POST['notes'] ?? '');
       $description = $testName; // reuse test name for description column
       $errs = [];
       if ($testName === '') $errs[] = 'Test name is required';
       if ($patient === '')  $errs[] = 'Patient is required';
       if ($category === '') $errs[] = 'Category is required';
       if ($status === '')   $errs[] = 'Status is required';
       if ($testDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $testDate)) $errs[] = 'Valid date is required (YYYY-MM-DD)';
       $allowedStatus = ['Pending','In Progress','Completed','Cancelled'];
       if (!in_array($status, $allowedStatus, true)) $errs[] = 'Invalid status value';
       if ($errs) {
         $_SESSION['flash_error'] = implode("\n", $errs);
       } else {
         if ($action === 'add') {
           // Build INSERT dynamically; store test date in legacy `date` and creator in `created_by_user_id`
           $insCols = ['patient','test_name','category','status','notes'];
           $insVals = [':patient',':test_name',':category',':status',':notes'];
           $params  = [
             ':patient'=>$patient,
             ':test_name'=>$testName,
             ':category'=>$category,
             ':status'=>$status,
             ':notes'=>$notes,
           ];
           if ($hasLegacyDate) {
             // Store test date in legacy `date` column
             $insCols[] = 'date';
             $insVals[] = ':legacy_date';
             $params[':legacy_date'] = $testDate; // store exactly what was selected in the form
           }
           if ($hasCreatedById) {
             $insCols[] = 'created_by_user_id';
             $insVals[] = ':uid';
             $params[':uid'] = $uid;
           }
           $sql = 'INSERT INTO lab_tests (' . implode(',', $insCols) . ') VALUES (' . implode(',', $insVals) . ')';
           $stmt = $pdo->prepare($sql);
           $stmt->execute($params);
           $_SESSION['flash_success'] = 'Laboratory test added successfully!';
         } else {
           // Build UPDATE dynamically
           $setParts = [
             'patient = :patient',
             'test_name = :test_name',
             'category = :category',
             'status = :status',
             'notes = :notes',
             'updated_at = now()'
           ];
           if ($hasLegacyDate) {
             $setParts[] = 'date = :legacy_date';
           }
           if ($hasCreatedById) {
             $setParts[] = 'created_by_user_id = COALESCE(created_by_user_id, :uid)';
           }
           $sql = 'UPDATE lab_tests SET ' . implode(', ', $setParts) . ' WHERE id = :id';
           $stmt = $pdo->prepare($sql);
           $params = [
             ':id'=>$testId,
             ':patient'=>$patient,
             ':test_name'=>$testName,
             ':category'=>$category,
             ':status'=>$status,
             ':legacy_date'=>$testDate,
             ':notes'=>$notes,
           ];
           if ($hasUserId || $hasCreatedById) {
             $params[':uid'] = $uid;
           }
           $stmt->execute($params);
           $_SESSION['flash_success'] = 'Laboratory test updated successfully!';
         }
       }
       header('Location: /capstone/templates/laboratory/lab_orders.php');
       exit;
     } elseif ($action === 'delete') {
       $testId = (int)($_POST['test_id'] ?? 0);
       if ($testId > 0) {
         $stmt = $pdo->prepare('DELETE FROM lab_tests WHERE id = :id');
         $stmt->execute([':id'=>$testId]);
         $_SESSION['flash_success'] = 'Laboratory test deleted successfully!';
       }
       header('Location: /capstone/templates/laboratory/lab_orders.php');
       exit;
     }
   }

   // Load tests; filter by creator when possible (created_by_user_id is now the primary owner field)
  // Cast legacy `date` to DATE so COALESCE has matching types even if the column is TEXT
  // Use test_name as the description shown in the UI; do not rely on the `description` column anymore
  if ($hasCreatedById && !empty($_SESSION['user']['id'])) {
    $stmt = $pdo->prepare('SELECT id, patient, test_name, category, status, COALESCE(date::date, created_at::date) AS test_date, test_name AS description, notes FROM lab_tests WHERE created_by_user_id = :uid OR created_by_user_id IS NULL ORDER BY COALESCE(date::date, created_at::date) DESC, id DESC');
    $stmt->execute([':uid'=>(int)$_SESSION['user']['id']]);
    $tests = $stmt->fetchAll();
  } else {
    $stmt = $pdo->query('SELECT id, patient, test_name, category, status, COALESCE(date::date, created_at::date) AS test_date, test_name AS description, notes FROM lab_tests ORDER BY COALESCE(date::date, created_at::date) DESC, id DESC');
    $tests = $stmt->fetchAll();
  }
 } catch (Throwable $e) {
   $_SESSION['flash_error'] = 'Failed to load lab tests: ' . $e->getMessage();
 }

 include __DIR__.'/../../includes/header.php';
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/laboratory/lab_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/laboratory/lab_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/appointment.png" alt="Orders" style="width:18px;height:18px;object-fit:contain;"> Laboratory</a></li>
          <li><a href="/capstone/templates/laboratory/lab_results.php"><img src="/capstone/assets/img/prescription.png" alt="Results" style="width:18px;height:18px;object-fit:contain;"> Lab Records</a></li>
          <li><a href="/capstone/templates/laboratory/lab_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div>
    <section class="card" style="margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <h2 class="dashboard-title" style="margin:0;">Laboratory</h2>
        <button id="addTestBtn" class="btn btn-primary" style="padding:8px 16px;border-radius:8px;font-weight:600;background:linear-gradient(135deg,#0a5d39,#10b981);box-shadow:0 4px 12px rgba(10,93,57,0.3);transition:all 0.2s ease;" onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 16px rgba(10,93,57,0.4)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 12px rgba(10,93,57,0.3)'">+ Add Test</button>
      </div>
      <div style="display:grid;grid-template-columns:1fr auto;gap:12px;margin-top:8px;">
        <div class="form-field">
          <label>Search</label>
          <input type="text" id="searchInput" placeholder="Patient/Test" />
        </div>
        <div class="form-field">
          <label>Filter</label>
          <select id="categoryFilter">
            <option value="">All</option>
            <option value="Hematology">Hematology</option>
            <option value="Chemistry">Chemistry</option>
            <option value="Microbiology">Microbiology</option>
            <option value="Immunology">Immunology</option>
            <option value="Pathology">Pathology</option>
            <option value="Urinalysis">Urinalysis</option>
            <option value="Blood Bank">Blood Bank</option>
          </select>
        </div>
      </div>
    </section>

    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert alert-error" style="margin:12px 0;">&nbsp;<?php echo nl2br(htmlspecialchars($_SESSION['flash_error'])); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="alert" style="margin:12px 0;border-color:#bbf7d0;background:#ecfdf5;color:#065f46;">&nbsp;<?php echo nl2br(htmlspecialchars($_SESSION['flash_success'])); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>

    <section class="card">
      <h3 style="margin-top:0;">Laboratory Tests</h3>
      <table>
        <thead><tr><th>Patient</th><th>Test</th><th>Category</th><th>Date</th><th>Description</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php if (!$tests): ?>
            <tr id="emptyRow"><td colspan="7" class="muted" style="text-align:center;">No laboratory tests yet.</td></tr>
          <?php else: ?>
            <?php foreach ($tests as $t): ?>
              <tr data-id="<?php echo (int)$t['id']; ?>">
                <td><?php echo htmlspecialchars($t['patient']); ?></td>
                <td><?php echo htmlspecialchars($t['test_name']); ?></td>
                <td><?php echo htmlspecialchars($t['category']); ?></td>
                <td><?php echo htmlspecialchars($t['test_date']); ?></td>
                <td><?php echo htmlspecialchars($t['description']); ?></td>
                <td><span class="badge"><?php echo htmlspecialchars($t['status']); ?></span></td>
                <td>
                  <div style="display:flex;gap:8px;">
                    <button class="btn btn-outline update-test-btn" data-id="<?php echo (int)$t['id']; ?>" data-patient="<?php echo htmlspecialchars($t['patient']); ?>" data-test="<?php echo htmlspecialchars($t['test_name']); ?>" data-category="<?php echo htmlspecialchars($t['category']); ?>" data-date="<?php echo htmlspecialchars($t['test_date']); ?>" data-description="<?php echo htmlspecialchars($t['description']); ?>" data-status="<?php echo htmlspecialchars($t['status']); ?>" data-notes="<?php echo htmlspecialchars($t['notes']); ?>" style="padding:4px 8px;font-size:0.8rem;">Update</button>
                    <button class="btn btn-outline delete-test-btn" data-id="<?php echo (int)$t['id']; ?>" data-patient="<?php echo htmlspecialchars($t['patient']); ?>" data-test="<?php echo htmlspecialchars($t['test_name']); ?>" style="padding:4px 8px;font-size:0.8rem;color:#dc2626;border-color:#dc2626;" onmouseover="this.style.backgroundColor='#dc2626';this.style.color='#fff';" onmouseout="this.style.backgroundColor='transparent';this.style.color='#dc2626';">Delete</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
      <!-- Pager styled as pill controls (Prev, page, Next) -->
      <div id="labPager" style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:10px;border-top:1px solid #e5e7eb;padding-top:10px;font-size:0.85rem;">
        <button type="button" id="labPrevPage" class="pager-pill" style="min-width:64px;height:32px;padding:6px 14px;border-radius:999px;border:1px solid #16a34a;background:#fff;color:#16a34a;font-weight:500;font-size:.9rem;">Prev</button>
        <button type="button" id="labPageIndicator" class="pager-pill-active" style="min-width:32px;height:32px;padding:6px 12px;border-radius:999px;border:none;background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;font-weight:600;font-size:.9rem;box-shadow:0 8px 18px rgba(16,185,129,0.45);">1</button>
        <button type="button" id="labNextPage" class="pager-pill" style="min-width:64px;height:32px;padding:6px 14px;border-radius:999px;border:1px solid #16a34a;background:#fff;color:#16a34a;font-weight:500;font-size:.9rem;">Next</button>
      </div>
    </section>
  </div>
</div>

<!-- Add Test Modal -->
<div id="addTestModal" style="display:none;position:fixed;inset:0;z-index:1000;">
  <div data-backdrop style="position:absolute;inset:0;background:rgba(0,0,0,0.5);"></div>
  <div role="dialog" aria-modal="true" style="position:relative;max-width:600px;margin:2vh auto;background:#fff;border-radius:20px;box-shadow:0 25px 50px rgba(0,0,0,0.25);overflow:hidden;min-height:fit-content;">
    <!-- Modal Header -->
    <div style="background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;padding:24px 28px;border-radius:20px 20px 0 0;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h3 id="modalTitle" style="margin:0;font-size:1.3rem;font-weight:700;">Add Laboratory Test</h3>
          <p id="modalSubtitle" style="margin:4px 0 0;opacity:0.9;font-size:0.9rem;">Create a new laboratory test order</p>
        </div>
        <button type="button" id="closeAddTestModal" style="background:rgba(255,255,255,0.2);border:none;color:#fff;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.3)'" onmouseout="this.style.backgroundColor='rgba(255,255,255,0.2)'">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6L6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>
    
    <form id="addTestForm" method="post" action="/capstone/templates/laboratory/lab_orders.php" style="margin:0;">
      <div style="padding:28px;max-height:70vh;overflow-y:auto;scrollbar-width:thin;scrollbar-color:#0a5d39 #f1f5f9;" class="scrollable-content">
        <!-- Hidden field for test ID -->
        <input type="hidden" id="test_id" name="test_id" value="" />
        <input type="hidden" id="form_action" name="action" value="add" />
        
        <!-- Form Fields -->
        <div style="display:grid;gap:20px;">
          <!-- Test Name -->
          <div class="form-field">
            <label for="test_name" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Test Name *</label>
            <input type="text" id="test_name" name="test_name" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" placeholder="e.g., Complete Blood Count, Urinalysis" />
          </div>
          
          <!-- Patient -->
          <div class="form-field">
            <label for="patient" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Patient *</label>
            <input type="text" id="patient" name="patient" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" placeholder="Patient name" />
          </div>
          
          <!-- Category -->
          <div class="form-field">
            <label for="category" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Category *</label>
            <select id="category" name="category" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';">
              <option value="">Select Category</option>
              <option value="Hematology">Hematology</option>
              <option value="Chemistry">Chemistry</option>
              <option value="Microbiology">Microbiology</option>
              <option value="Immunology">Immunology</option>
              <option value="Pathology">Pathology</option>
              <option value="Urinalysis">Urinalysis</option>
              <option value="Blood Bank">Blood Bank</option>
            </select>
          </div>
          
          <!-- Status -->
          <div class="form-field">
            <label for="status" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Status *</label>
            <select id="status" name="status" required style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';">
              <option value="">Select Status</option>
              <option value="Pending">Pending</option>
              <option value="In Progress">In Progress</option>
              <option value="Completed">Completed</option>
              <option value="Cancelled">Cancelled</option>
            </select>
          </div>
          
          <!-- Date -->
          <div class="form-field">
            <label for="test_date" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Date *</label>
            <input type="date" id="test_date" name="test_date" required min="<?php echo date('Y-m-d'); ?>" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" />
          </div>
          
          <!-- Notes (Optional) -->
          <div class="form-field">
            <label for="notes" style="display:block;margin-bottom:8px;font-weight:600;color:#0f172a;font-size:0.9rem;">Notes (Optional)</label>
            <textarea id="notes" name="notes" rows="3" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:0.95rem;transition:all 0.2s ease;background:#f8fafc;resize:vertical;" onfocus="this.style.borderColor='#0a5d39';this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';" placeholder="Additional notes or special instructions..."></textarea>
          </div>
        </div>
      </div>
      
      <!-- Modal Footer -->
      <div style="display:flex;justify-content:flex-end;gap:12px;padding:20px 28px;border-top:1px solid #e5e7eb;background:linear-gradient(135deg,#f8fafc,#f1f5f9);">
        <button type="button" class="btn btn-outline" id="cancelAddTestModal" style="padding:10px 20px;border-radius:10px;font-weight:600;">Cancel</button>
        <button class="btn btn-primary" type="submit" style="padding:10px 20px;border-radius:10px;font-weight:600;background:linear-gradient(135deg,#0a5d39,#10b981);">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  var addTestBtn = document.getElementById('addTestBtn');
  var modal = document.getElementById('addTestModal');
  var closeBtn = document.getElementById('closeAddTestModal');
  var cancelBtn = document.getElementById('cancelAddTestModal');
  var backdrop = modal ? modal.querySelector('[data-backdrop]') : null;
  var form = document.getElementById('addTestForm');
  var modalTitle = document.getElementById('modalTitle');
  var modalSubtitle = document.getElementById('modalSubtitle');
  var isUpdateMode = false;
  
  function openForAdd(){ 
    if(modal){ 
      modal.style.display = 'block'; 
      document.body.style.overflow='hidden'; 
      // Set default date to today
      var today = new Date().toISOString().split('T')[0];
      document.getElementById('test_date').value = today;
      
      // Set modal title for add
      modalTitle.textContent = 'Add Laboratory Test';
      modalSubtitle.textContent = 'Create a new laboratory test order';
      isUpdateMode = false;
      
      // Clear hidden ID field
      document.getElementById('test_id').value = '';
    } 
  }
  
  function openForUpdate(testData){ 
    if(modal){ 
      modal.style.display = 'block'; 
      document.body.style.overflow='hidden'; 
      
      // Set modal title for update
      modalTitle.textContent = 'Update Laboratory Test';
      modalSubtitle.textContent = 'Edit laboratory test information';
      isUpdateMode = true;
      
      // Populate form fields with existing data
      document.getElementById('test_id').value = testData.id;
      document.getElementById('test_name').value = testData.test;
      document.getElementById('patient').value = testData.patient;
      document.getElementById('category').value = testData.category;
      document.getElementById('status').value = testData.status;
      document.getElementById('test_date').value = testData.date;
      document.getElementById('notes').value = testData.notes || '';
    } 
  }
  
  function close(){ 
    if(modal){ 
      modal.style.display = 'none'; 
      document.body.style.overflow=''; 
      // Reset form
      if(form) form.reset();
      isUpdateMode = false;
    } 
  }
  
  // Function to add new test to table
  function addTestToTable(testData){
    var tbody = document.querySelector('tbody');
    if(tbody){
      var emptyRow = document.getElementById('emptyRow');
      if (emptyRow) { emptyRow.remove(); }
      var newRow = document.createElement('tr');
      newRow.setAttribute('data-id', String(testData.id));
      newRow.innerHTML = 
        '<td>' + testData.patient + '</td>' +
        '<td>' + testData.test + '</td>' +
        '<td>' + testData.category + '</td>' +
        '<td>' + testData.date + '</td>' +
        '<td>' + testData.description + '</td>' +
        '<td><span class="badge">' + testData.status + '</span></td>' +
        '<td><div style="display:flex;gap:8px;">' +
          '<button class="btn btn-outline update-test-btn" data-id="' + testData.id + '" data-patient="' + testData.patient + '" data-test="' + testData.test + '" data-category="' + testData.category + '" data-date="' + testData.date + '" data-description="' + testData.description + '" data-status="' + testData.status + '" data-notes="' + (testData.notes || '') + '" style="padding:4px 8px;font-size:0.8rem;">Update</button>' +
          '<button class="btn btn-outline delete-test-btn" data-id="' + testData.id + '" data-patient="' + testData.patient + '" data-test="' + testData.test + '" style="padding:4px 8px;font-size:0.8rem;color:#dc2626;border-color:#dc2626;" onmouseover="this.style.backgroundColor=\'#dc2626\';this.style.color=\'#fff\';" onmouseout="this.style.backgroundColor=\'transparent\';this.style.color=\'#dc2626\';">Delete</button>' +
        '</div></td>';
      tbody.appendChild(newRow);
      
      // Add to originalRows array for filtering
      originalRows.push({
        id: String(testData.id),
        element: newRow,
        patient: testData.patient,
        test: testData.test,
        category: testData.category,
        date: testData.date,
        description: testData.description,
        status: testData.status
      });
    }
  }
  
  if(addTestBtn){ addTestBtn.addEventListener('click', function(e){ e.preventDefault(); openForAdd(); }); }
  if(closeBtn){ closeBtn.addEventListener('click', function(){ close(); }); }
  if(cancelBtn){ cancelBtn.addEventListener('click', function(){ close(); }); }
  if(backdrop){ backdrop.addEventListener('click', function(){ close(); }); }
  
  // Handle update buttons
  document.addEventListener('click', function(e){
    if(e.target.classList.contains('update-test-btn')){
      e.preventDefault();
      var testData = {
        id: e.target.getAttribute('data-id'),
        test: e.target.getAttribute('data-test'),
        patient: e.target.getAttribute('data-patient'),
        category: e.target.getAttribute('data-category'),
        status: e.target.getAttribute('data-status'),
        date: e.target.getAttribute('data-date'),
        notes: e.target.getAttribute('data-notes')
      };
      openForUpdate(testData);
    }
  });
  
  // Handle delete buttons
  document.addEventListener('click', function(e){
    if(e.target.classList.contains('delete-test-btn')){
      e.preventDefault();
      var testId = e.target.getAttribute('data-id');
      var patient = e.target.getAttribute('data-patient');
      var test = e.target.getAttribute('data-test');
      
      // Show confirmation dialog
      var confirmMessage = 'Are you sure you want to delete the laboratory test for ' + patient + ' (' + test + ')?\n\nThis action cannot be undone.';
      if(confirm(confirmMessage)){
        // Submit a small POST form to delete on server and refresh
        var f = document.createElement('form');
        f.method = 'POST';
        f.action = '/capstone/templates/laboratory/lab_orders.php';
        var a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='delete'; f.appendChild(a);
        var i = document.createElement('input'); i.type='hidden'; i.name='test_id'; i.value=String(testId); f.appendChild(i);
        document.body.appendChild(f);
        f.submit();
      }
    }
  });
  
  document.addEventListener('keydown', function(e){ 
    if(e.key === 'Escape' && modal && modal.style.display === 'block'){ 
      close(); 
    } 
  });

  // Form submission
  if(form){
    form.addEventListener('submit', function(ev){
      // Let the server handle validation and persistence, but ensure action is set
      // Get form data
      var testId = document.getElementById('test_id').value;
      var actionField = document.getElementById('form_action');
      actionField.value = (isUpdateMode ? 'update' : 'add');
      var testName = document.getElementById('test_name').value.trim();
      var patient = document.getElementById('patient').value.trim();
      var category = document.getElementById('category').value;
      var status = document.getElementById('status').value;
      var testDate = document.getElementById('test_date').value;
      var notes = document.getElementById('notes').value.trim();
      // Let the browser submit the form to PHP for DB persistence
    });
  }
  
  // Search and Filter functionality
  var searchInput = document.getElementById('searchInput');
  var categoryFilter = document.getElementById('categoryFilter');
  var originalRows = [];
  var pageSize = 8;
  var currentPage = 1;
  var pagerInfo = document.getElementById('labPagerInfo');
  var pagerIndicator = document.getElementById('labPageIndicator');
  var prevBtn = document.getElementById('labPrevPage');
  var nextBtn = document.getElementById('labNextPage');
  
  // Store original rows data from server-rendered table
  function initializeRows(){
    var tbody = document.querySelector('tbody');
    if(!tbody) return;
    originalRows = Array.from(tbody.querySelectorAll('tr'))
      .filter(function(row){ return row.id !== 'emptyRow'; })
      .map(function(row){
        var cells = row.querySelectorAll('td');
        return {
          element: row,
          patient: cells[0] ? cells[0].textContent.trim() : '',
          test: cells[1] ? cells[1].textContent.trim() : '',
          category: cells[2] ? cells[2].textContent.trim() : '',
          date: cells[3] ? cells[3].textContent.trim() : '',
          description: cells[4] ? cells[4].textContent.trim() : '',
          status: cells[5] ? (cells[5].querySelector('.badge') ? cells[5].querySelector('.badge').textContent.trim() : '') : ''
        };
      });
    currentPage = 1;
    renderPage();
  }

  function getFilteredRows(){
    var searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
    var selectedCategory = categoryFilter ? categoryFilter.value : '';
    return originalRows.filter(function(r){
      var matchesSearch = !searchTerm || r.patient.toLowerCase().includes(searchTerm) || r.test.toLowerCase().includes(searchTerm) || r.category.toLowerCase().includes(searchTerm) || r.description.toLowerCase().includes(searchTerm);
      var matchesCategory = !selectedCategory || r.category === selectedCategory;
      return matchesSearch && matchesCategory;
    });
  }

  function renderPage(){
    var tbody = document.querySelector('tbody');
    if(!tbody) return;
    var filtered = getFilteredRows();
    var total = filtered.length;
    var totalPages = Math.max(1, Math.ceil(total / pageSize));
    if(currentPage > totalPages) currentPage = totalPages;
    var start = (currentPage - 1) * pageSize;
    var end = start + pageSize;

    tbody.innerHTML = '';

    if(total === 0){
      var noRow = document.createElement('tr');
      noRow.innerHTML = '<td colspan="7" class="muted" style="text-align:center;">No laboratory tests found matching the filters.</td>';
      tbody.appendChild(noRow);
    } else {
      filtered.slice(start, end).forEach(function(r){ tbody.appendChild(r.element); });
    }

    if(pagerInfo){
      pagerInfo.textContent = 'Showing ' + (total === 0 ? 0 : (start + 1)) + '-' + (Math.min(end, total)) + ' of ' + total + ' tests';
    }
    if(pagerIndicator){
      pagerIndicator.textContent = 'Page ' + currentPage + ' / ' + totalPages;
    }
    if(prevBtn){ prevBtn.disabled = (currentPage <= 1 || total === 0); }
    if(nextBtn){ nextBtn.disabled = (currentPage >= totalPages || total === 0); }
  }

  function filterRows(){
    currentPage = 1;
    renderPage();
  }

  // Event listeners
  if(searchInput){
    searchInput.addEventListener('input', function(){
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(filterRows, 250);
    });
  }
  if(categoryFilter){ categoryFilter.addEventListener('change', filterRows); }
  if(prevBtn){
    prevBtn.addEventListener('click', function(){ if(currentPage > 1){ currentPage--; renderPage(); } });
  }
  if(nextBtn){
    nextBtn.addEventListener('click', function(){ currentPage++; renderPage(); });
  }
  // Initialize cache from server-rendered rows
  initializeRows();
})();
</script>

<style>
/* Custom scrollbar styling for the modal */
.scrollable-content::-webkit-scrollbar {
  width: 8px;
}

.scrollable-content::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 4px;
}

.scrollable-content::-webkit-scrollbar-thumb {
  background: #0a5d39;
  border-radius: 4px;
  transition: background 0.2s ease;
}

.scrollable-content::-webkit-scrollbar-thumb:hover {
  background: #059669;
}

/* Ensure modal is fully scrollable on mobile */
@media (max-height: 600px) {
  #addTestModal {
    padding: 10px;
  }
  
  #addTestModal > div[role="dialog"] {
    margin: 10px auto;
    max-height: 95vh;
  }
  
  .scrollable-content {
    max-height: 60vh !important;
  }
}

@media (max-width: 768px) {
  #addTestModal > div[role="dialog"] {
    margin: 10px;
    max-width: calc(100% - 20px);
  }
}
</style>

<?php include __DIR__.'/../../includes/footer.php'; ?>

