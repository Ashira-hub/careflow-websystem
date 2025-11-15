<?php
 $page='Lab Results';
 require_once __DIR__.'/../../config/db.php';
 if (session_status() === PHP_SESSION_NONE) { session_start(); }
 // Basic no-cache headers to avoid stale records
 header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
 header('Cache-Control: post-check=0, pre-check=0', false);
 header('Pragma: no-cache');

 $records = [];
 try {
   $pdo = get_pdo();
   // Discover columns so we can optionally filter by creator and pick date safely
   $colStmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'lab_tests'");
   $colStmt->execute();
   $cols = array_map('strtolower', array_map('strval', array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));
   $hasCreatedById = in_array('created_by_user_id', $cols, true);
   $hasLegacyDate  = in_array('date', $cols, true);

   // Build base SELECT; use legacy date column if present, else created_at::date
   $dateExpr = $hasLegacyDate ? 'date::date' : 'created_at::date';
   if ($hasCreatedById && !empty($_SESSION['user']['id'])) {
     $stmt = $pdo->prepare("SELECT id, patient, test_name, status, $dateExpr AS test_date, notes FROM lab_tests WHERE created_by_user_id = :uid OR created_by_user_id IS NULL ORDER BY $dateExpr DESC, id DESC");
     $stmt->execute([':uid'=>(int)$_SESSION['user']['id']]);
     $records = $stmt->fetchAll();
   } else {
     $stmt = $pdo->query("SELECT id, patient, test_name, status, $dateExpr AS test_date, notes FROM lab_tests ORDER BY $dateExpr DESC, id DESC");
     $records = $stmt->fetchAll();
   }
 } catch (Throwable $e) {
   $_SESSION['flash_error'] = 'Failed to load laboratory records: ' . $e->getMessage();
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
          <li><a href="/capstone/templates/laboratory/lab_orders.php"><img src="/capstone/assets/img/appointment.png" alt="Orders" style="width:18px;height:18px;object-fit:contain;"> Laboratory</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/prescription.png" alt="Results" style="width:18px;height:18px;object-fit:contain;"> Lab Records</a></li>
          <li><a href="/capstone/templates/laboratory/lab_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div>
    <section class="card" style="margin-bottom:16px;">
      <h2 class="dashboard-title" style="margin:0;">Laboratory Records</h2>
      <div style="display:grid;grid-template-columns:1fr auto;gap:12px;margin-top:8px;">
        <div class="form-field">
          <label>Search</label>
          <input type="text" id="searchInput" placeholder="Patient/Test" />
        </div>
        <div class="form-field">
          <label>Filter</label>
          <select id="statusFilter">
            <option value="">All</option>
            <option value="Pending">Pending</option>
            <option value="In progress">In Progress</option>
            <option value="Completed">Completed</option>
          </select>
        </div>
      </div>
    </section>

    <section class="card">
      <h3 style="margin-top:0;">Laboratory Records</h3>
      <table>
        <thead><tr><th>Patient</th><th>Latest Date</th><th>Action</th></tr></thead>
        <tbody>
          <?php if (!$records): ?>
            <tr><td colspan="3" class="muted" style="text-align:center;">No laboratory records yet.</td></tr>
          <?php else: ?>
            <?php
              // Group tests by patient and compute latest date + aggregate tests
              $grouped = [];
              foreach ($records as $r) {
                $pKey = strtolower(trim((string)$r['patient']));
                if (!isset($grouped[$pKey])) {
                  $grouped[$pKey] = [
                    'name' => $r['patient'],
                    'latest_date' => $r['test_date'],
                    'latest_status' => $r['status'],
                    'rows' => []
                  ];
                }
                $grouped[$pKey]['rows'][] = $r;
                // Track latest test by date (strings are in Y-m-d)
                if ((string)$r['test_date'] > (string)$grouped[$pKey]['latest_date']) {
                  $grouped[$pKey]['latest_date'] = $r['test_date'];
                  $grouped[$pKey]['latest_status'] = $r['status'];
                }
              }
            ?>
            <?php foreach ($grouped as $g): ?>
              <?php
                $testsForSearch = [];
                $compact = [];
                foreach ($g['rows'] as $row) {
                  $testsForSearch[] = $row['test_name'];
                  $compact[] = [
                    'test_name' => $row['test_name'],
                    'test_date' => $row['test_date'],
                    'status'    => $row['status'],
                    'notes'     => $row['notes'],
                  ];
                }
                $testsAttr = htmlspecialchars(implode(', ', array_unique($testsForSearch)), ENT_QUOTES, 'UTF-8');
                $recordsJson = htmlspecialchars(json_encode($compact), ENT_QUOTES, 'UTF-8');
              ?>
              <tr data-patient="<?php echo htmlspecialchars($g['name']); ?>" data-tests="<?php echo $testsAttr; ?>">
                <td style="font-weight:600;"><?php echo htmlspecialchars($g['name']); ?></td>
                <td><?php echo htmlspecialchars($g['latest_date']); ?></td>
                <td>
                  <button class="btn btn-outline view-record-btn"
                    data-patient="<?php echo htmlspecialchars($g['name']); ?>"
                    data-latest-date="<?php echo htmlspecialchars($g['latest_date']); ?>"
                    data-status="<?php echo htmlspecialchars($g['latest_status']); ?>"
                    data-records='<?php echo $recordsJson; ?>'
                    style="padding:4px 8px;font-size:0.8rem;">View</button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </section>
  </div>
</div>

<!-- View Record Modal -->
<div id="viewRecordModal" style="display:none;position:fixed;inset:0;z-index:1000;">
  <div data-backdrop style="position:absolute;inset:0;background:rgba(0,0,0,0.5);"></div>
  <div role="dialog" aria-modal="true" style="position:relative;max-width:600px;margin:2vh auto;background:#fff;border-radius:20px;box-shadow:0 25px 50px rgba(0,0,0,0.25);overflow:hidden;min-height:fit-content;">
    <!-- Modal Header -->
    <div style="background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;padding:24px 28px;border-radius:20px 20px 0 0;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h3 style="margin:0;font-size:1.3rem;font-weight:700;">Laboratory Record Details</h3>
          <p style="margin:4px 0 0;opacity:0.9;font-size:0.9rem;">Complete test information</p>
        </div>
        <button type="button" id="closeViewModal" style="background:rgba(255,255,255,0.2);border:none;color:#fff;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.3)'" onmouseout="this.style.backgroundColor='rgba(255,255,255,0.2)'">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6L6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>
    
    <!-- Modal Body -->
    <div style="padding:28px;">
      <div style="display:grid;gap:20px;">
        <!-- Patient -->
        <div style="border-bottom:1px solid #e5e7eb;padding-bottom:12px;">
          <div style="color:#64748b;font-size:0.9rem;margin-bottom:4px;">Patient</div>
          <div id="viewPatient" style="color:#0f172a;font-weight:600;font-size:1rem;">—</div>
        </div>
        
        <!-- Test -->
        <div style="border-bottom:1px solid #e5e7eb;padding-bottom:12px;">
          <div style="color:#64748b;font-size:0.9rem;margin-bottom:4px;">Test</div>
          <div id="viewTest" style="color:#0f172a;font-weight:600;font-size:1rem;">—</div>
        </div>
        
        <!-- Date -->
        <div style="border-bottom:1px solid #e5e7eb;padding-bottom:12px;">
          <div style="color:#64748b;font-size:0.9rem;margin-bottom:4px;">Date</div>
          <div id="viewDate" style="color:#0f172a;font-weight:600;font-size:1rem;">—</div>
        </div>
        
        <!-- Result -->
        <div style="border-bottom:1px solid #e5e7eb;padding-bottom:12px;">
          <div style="color:#64748b;font-size:0.9rem;margin-bottom:4px;">Result</div>
          <div id="viewResult" style="color:#0f172a;font-weight:600;font-size:1rem;">—</div>
        </div>
        
        <!-- Test Results (list of all tests for this patient) -->
        <div>
          <div style="color:#64748b;font-size:0.9rem;margin-bottom:4px;">Test Results</div>
          <div id="viewNotes" style="color:#0f172a;font-size:0.95rem;line-height:1.5;">—</div>
        </div>
      </div>
    </div>
    
    <!-- Modal Footer -->
    <div style="display:flex;justify-content:flex-end;gap:12px;padding:20px 28px;border-top:1px solid #e5e7eb;background:linear-gradient(135deg,#f8fafc,#f1f5f9);">
      <button type="button" class="btn btn-primary" id="closeViewModalBtn" style="padding:10px 20px;border-radius:10px;font-weight:600;background:linear-gradient(135deg,#0a5d39,#10b981);">Close</button>
    </div>
  </div>
</div>

<script>
(function(){
  // Search and Filter functionality
  var searchInput = document.getElementById('searchInput');
  var statusFilter = document.getElementById('statusFilter');
  var originalRows = [];
  
  // Store original rows data (including status via data-status on the View button)
  function initializeRows(){
    var tbody = document.querySelector('tbody');
    if(tbody){
      originalRows = Array.from(tbody.querySelectorAll('tr')).map(function(row){
        var cells = row.querySelectorAll('td');
        var viewBtn = row.querySelector('.view-record-btn');
        var testsAttr = row.getAttribute('data-tests') || '';
        return {
          element: row,
          patient: cells[0] ? cells[0].textContent.trim() : '',
          // For grouped view we keep a concatenated tests string for searching
          tests: testsAttr.toLowerCase(),
          latestDate: cells[1] ? cells[1].textContent.trim() : '',
          status: viewBtn ? (viewBtn.getAttribute('data-status') || '').trim() : ''
        };
      });
    }
  }
  
  function filterRows(){
    var tbody = document.querySelector('tbody');
    if(!tbody || originalRows.length === 0) return;
    
    var searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
    var selectedStatus = statusFilter ? statusFilter.value : '';
    
    // Clear table body
    tbody.innerHTML = '';
    
    var filteredRows = originalRows.filter(function(row){
      // Search filter
      var matchesSearch = !searchTerm || 
        row.patient.toLowerCase().includes(searchTerm) ||
        row.latestDate.toLowerCase().includes(searchTerm) ||
        (row.tests || '').includes(searchTerm);
      
      var matchesStatus = !selectedStatus || (row.status || '').toLowerCase() === selectedStatus.toLowerCase();
      
      return matchesSearch && matchesStatus;
    });
    
    // Show filtered results
    if(filteredRows.length === 0){
      var noResultsRow = document.createElement('tr');
      noResultsRow.innerHTML = '<td colspan="4" class="muted" style="text-align:center;">No laboratory records found matching the filters.</td>';
      tbody.appendChild(noResultsRow);
    } else {
      filteredRows.forEach(function(row){
        tbody.appendChild(row.element);
      });
    }
  }
  
  // Event listeners for search and filter
  if(searchInput){
    searchInput.addEventListener('input', function(){
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(filterRows, 300); // Debounce search
    });
  }
  
  if(statusFilter){
    statusFilter.addEventListener('change', filterRows);
  }
  
  // View Record Modal functionality
  var viewModal = document.getElementById('viewRecordModal');
  var closeViewModal = document.getElementById('closeViewModal');
  var closeViewModalBtn = document.getElementById('closeViewModalBtn');
  var backdrop = viewModal ? viewModal.querySelector('[data-backdrop]') : null;
  
  function openViewModal(recordData){
    if(viewModal){
      viewModal.style.display = 'block';
      document.body.style.overflow = 'hidden';
      
      // Populate modal with record data
      document.getElementById('viewPatient').textContent = recordData.patient || '—';
      document.getElementById('viewDate').textContent = recordData.latestDate || '—';
      document.getElementById('viewResult').textContent = recordData.status || '—';

      // Build a readable list of all tests for this patient
      var notesEl = document.getElementById('viewNotes');
      var testsHtml = '\u2014';
      if (Array.isArray(recordData.records) && recordData.records.length > 0) {
        var rowsHtml = recordData.records.map(function(r){
          var testName = r.test_name || '';
          var testDate = r.test_date || '';
          var status   = r.status || '';
          return '<li><strong>' +
                   (testName ? testName.replace(/</g,'&lt;').replace(/>/g,'&gt;') : 'Test') +
                 '</strong>' +
                 (testDate ? ' <span style="color:#64748b;">[' + testDate.replace(/</g,'&lt;').replace(/>/g,'&gt;') + ']</span>' : '') +
                 (status ? ' <span>' + status.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</span>' : '') +
                 '</li>';
        }).join('');
        testsHtml = '<ul style="padding-left:18px;margin:0;list-style:disc;">' + rowsHtml + '</ul>';
        // Show the most recent test in the "Test" field for quick reference
        var latest = recordData.records[0];
        document.getElementById('viewTest').textContent = (latest && latest.test_name) ? latest.test_name : '\u2014';
      } else {
        document.getElementById('viewTest').textContent = '\u2014';
      }
      if (notesEl){ notesEl.innerHTML = testsHtml; }
    }
  }
  
  function closeViewModalFunc(){
    if(viewModal){
      viewModal.style.display = 'none';
      document.body.style.overflow = '';
    }
  }
  
  // Handle view buttons
  document.addEventListener('click', function(e){
    if(e.target.classList.contains('view-record-btn')){
      e.preventDefault();
      var recordsRaw = e.target.getAttribute('data-records') || '[]';
      var records;
      try { records = JSON.parse(recordsRaw); } catch(_) { records = []; }
      var recordData = {
        patient: e.target.getAttribute('data-patient'),
        latestDate: e.target.getAttribute('data-latest-date'),
        status: e.target.getAttribute('data-status'),
        records: records
      };
      openViewModal(recordData);
    }
  });
  
  // Handle modal close
  if(closeViewModal){ closeViewModal.addEventListener('click', closeViewModalFunc); }
  if(closeViewModalBtn){ closeViewModalBtn.addEventListener('click', closeViewModalFunc); }
  if(backdrop){ backdrop.addEventListener('click', closeViewModalFunc); }
  
  // Close modal on Escape key
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape' && viewModal && viewModal.style.display === 'block'){
      closeViewModalFunc();
    }
  });
  
  // Initialize on page load
  initializeRows();
})();
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>

