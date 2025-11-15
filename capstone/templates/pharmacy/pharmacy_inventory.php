<?php
 $page='Pharmacy Inventory';
 require_once __DIR__.'/../../config/db.php';
 if (session_status() === PHP_SESSION_NONE) { session_start(); }

 $inventoryItems = [];
 $inventoryError = '';

 if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   try {
     $pdo = get_pdo();
     $mode = isset($_POST['mode']) && $_POST['mode'] === 'update' ? 'update' : 'add';
     $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

     $generic   = trim($_POST['generic_name'] ?? '');
     $brand     = trim($_POST['brand_name'] ?? '');
     $category  = trim($_POST['category'] ?? '');
     $dosage    = trim($_POST['dosage_type'] ?? '');
     $strength  = trim($_POST['strength'] ?? '');
     $unit      = trim($_POST['unit'] ?? '');
     $expires   = trim($_POST['expiration_date'] ?? '');
     $stockRaw  = trim($_POST['stock'] ?? '0');
     $desc      = trim($_POST['description'] ?? '');

     $errors = [];
     if ($generic === '') { $errors[] = 'Generic name is required.'; }
     if ($stockRaw === '' || !is_numeric($stockRaw) || (int)$stockRaw < 0) { $errors[] = 'Stock must be a non-negative number.'; }
     $stock = (int)$stockRaw;
     if ($expires !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires)) {
       $errors[] = 'Expiration date must be in YYYY-MM-DD format.';
     }

     if ($errors) {
       $_SESSION['flash_error'] = implode("\n", $errors);
     } else {
       if ($mode === 'update' && $id > 0) {
         $sql = 'UPDATE inventory
                   SET generic_name = :generic_name,
                       brand_name   = :brand_name,
                       category     = :category,
                       dosage_type  = :dosage_type,
                       strength     = :strength,
                       unit         = :unit,
                       expiration_date = :expiration_date,
                       stock        = :stock,
                       description  = :description
                 WHERE id = :id';
         $stmt = $pdo->prepare($sql);
         $stmt->execute([
           ':generic_name'    => $generic,
           ':brand_name'      => $brand,
           ':category'        => $category,
           ':dosage_type'     => $dosage,
           ':strength'        => $strength,
           ':unit'            => $unit,
           ':expiration_date' => $expires !== '' ? $expires : null,
           ':stock'           => $stock,
           ':description'     => $desc,
           ':id'              => $id,
         ]);
         $_SESSION['flash_success'] = 'Inventory item updated.';
       } else {
         $sql = 'INSERT INTO inventory (generic_name, brand_name, category, dosage_type, strength, unit, expiration_date, stock, description, created_at)
                 VALUES (:generic_name, :brand_name, :category, :dosage_type, :strength, :unit, :expiration_date, :stock, :description, NOW())';
         $stmt = $pdo->prepare($sql);
         $stmt->execute([
           ':generic_name'    => $generic,
           ':brand_name'      => $brand,
           ':category'        => $category,
           ':dosage_type'     => $dosage,
           ':strength'        => $strength,
           ':unit'            => $unit,
           ':expiration_date' => $expires !== '' ? $expires : null,
           ':stock'           => $stock,
           ':description'     => $desc,
         ]);
         $_SESSION['flash_success'] = 'Inventory item added.';
       }
     }
   } catch (Throwable $e) {
     $_SESSION['flash_error'] = 'Failed to save inventory: ' . $e->getMessage();
   }

   header('Location: /capstone/templates/pharmacy/pharmacy_inventory.php');
   exit;
 }

 try {
   $pdo = get_pdo();
   $stmt = $pdo->query('SELECT id, generic_name, brand_name, category, dosage_type, strength, unit, expiration_date, description, created_at, stock FROM inventory ORDER BY generic_name ASC');
   $inventoryItems = $stmt->fetchAll();
 } catch (Throwable $e) {
   $inventoryError = 'Unable to load inventory at the moment.';
 }
 function pi_escape($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
 include __DIR__.'/../../includes/header.php';
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/pharmacy/pharmacy_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/appointment.png" alt="Inventory" style="width:18px;height:18px;object-fit:contain;"> Inventory</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_prescription.php"><img src="/capstone/assets/img/prescription.png" alt="Prescription" style="width:18px;height:18px;object-fit:contain;"> Prescription</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_medicine.php"><img src="/capstone/assets/img/drug.png" alt="Medicine" style="width:18px;height:18px;object-fit:contain;"> Medicine</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div>
    <section class="card" style="margin-bottom:16px;">
      <h2 class="dashboard-title" style="margin:0;">Inventory</h2>
      <div class="grid-2" style="display:grid;grid-template-columns:1fr auto;align-items:end;gap:12px;margin-top:8px;">
        <div class="form-field">
          <label for="piSearchInput">Search</label>
          <input type="text" id="piSearchInput" placeholder="Name or code" />
        </div>
        <div class="form-field" style="width:180px;min-width:160px;">
          <label for="piCategoryFilter">Category</label>
          <select id="piCategoryFilter" style="width:100%">
            <option value="">All</option>
            <option value="Tablets">Tablets</option>
            <option value="Capsules">Capsules</option>
            <option value="Liquids">Liquids</option>
          </select>
        </div>
      </div>
    </section>

    <section class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <h3 style="margin:0;">Stock List</h3>
        <div class="rx-actions"><a class="btn btn-primary" href="#" id="piAddBtn">+ Add</a></div>
      </div>
      <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-error" style="margin-top:12px;">
          <?php echo nl2br(pi_escape($_SESSION['flash_error'])); unset($_SESSION['flash_error']); ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert" style="margin-top:12px;border-color:#bbf7d0;background:#ecfdf5;color:#065f46;">
          <?php echo nl2br(pi_escape($_SESSION['flash_success'])); unset($_SESSION['flash_success']); ?>
        </div>
      <?php endif; ?>
      <?php if ($inventoryError !== ''): ?>
        <div class="muted" style="padding:12px 0;"><?php echo pi_escape($inventoryError); ?></div>
      <?php else: ?>
        <div class="table-responsive">
          <table>
            <thead><tr><th>ID</th><th>Generic Name</th><th>Brand Name</th><th>Category</th><th>Dosage Type</th><th>Strength</th><th>Unit</th><th>Expires</th><th>Stock</th><th>Description</th><th></th></tr></thead>
            <tbody>
              <?php if ($inventoryItems): ?>
                <?php foreach ($inventoryItems as $item): ?>
                  <tr data-description="<?php echo pi_escape($item['description'] ?? ''); ?>">
                    <td><?php echo pi_escape($item['id'] ?? ''); ?></td>
                    <td><?php echo pi_escape($item['generic_name'] ?? ''); ?></td>
                    <td><?php echo pi_escape($item['brand_name'] ?? ''); ?></td>
                    <td><?php echo pi_escape($item['category'] ?? ''); ?></td>
                    <td><?php echo pi_escape($item['dosage_type'] ?? ''); ?></td>
                    <td><?php echo pi_escape($item['strength'] ?? ''); ?></td>
                    <td><?php echo pi_escape($item['unit'] ?? ''); ?></td>
                    <td><?php echo pi_escape($item['expiration_date'] ?? ''); ?></td>
                    <td><?php echo pi_escape($item['stock'] ?? ''); ?></td>
                    <td><?php echo pi_escape($item['description'] ?? ''); ?></td>
                    <td><a class="btn btn-outline" href="#" data-action="piUpdate" data-id="<?php echo pi_escape($item['id'] ?? ''); ?>">Update</a></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="12" class="muted" style="text-align:center;">No inventory records found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div id="piPager" style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:10px;border-top:1px solid #e5e7eb;padding-top:10px;font-size:0.85rem;">
          <button type="button" id="piPrevPage" class="pager-pill" style="min-width:64px;height:32px;padding:6px 14px;border-radius:999px;border:1px solid #16a34a;background:#fff;color:#16a34a;font-weight:500;font-size:.9rem;">Prev</button>
          <button type="button" id="piPageIndicator" class="pager-pill-active" style="min-width:32px;height:32px;padding:6px 12px;border-radius:999px;border:none;background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;font-weight:600;font-size:.9rem;box-shadow:0 8px 18px rgba(16,185,129,0.45);">1</button>
          <button type="button" id="piNextPage" class="pager-pill" style="min-width:64px;height:32px;padding:6px 14px;border-radius:999px;border:1px solid #16a34a;background:#fff;color:#16a34a;font-weight:500;font-size:.9rem;">Next</button>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<div id="piModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:80;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#fff;border-radius:12px;max-width:520px;width:100%;padding:20px;position:relative;box-shadow:0 20px 45px rgba(0,0,0,.18);overflow:auto;max-height:90vh;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="margin:0;" id="piModalTitle">Add Inventory Item</h3>
      <button type="button" class="btn btn-outline" id="piCloseBtn">Close</button>
    </div>
    <form id="piForm" data-mode="add" method="post" action="/capstone/templates/pharmacy/pharmacy_inventory.php">
      <input type="hidden" name="mode" id="pi_mode" value="add">
      <input type="hidden" name="id" id="pi_id" value="">
      <div class="form-field"><label for="pi_generic_name">Generic Name</label><input type="text" id="pi_generic_name" name="generic_name" required></div>
      <div class="form-field"><label for="pi_brand_name">Brand Name</label><input type="text" id="pi_brand_name" name="brand_name"></div>
      <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-field"><label for="pi_category">Category</label><input type="text" id="pi_category" name="category"></div>
        <div class="form-field"><label for="pi_dosage_type">Dosage Type</label><input type="text" id="pi_dosage_type" name="dosage_type"></div>
      </div>
      <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-field"><label for="pi_strength">Strength</label><input type="text" id="pi_strength" name="strength"></div>
        <div class="form-field"><label for="pi_unit">Unit</label><input type="text" id="pi_unit" name="unit"></div>
      </div>
      <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-field"><label for="pi_expiration_date">Expiration Date</label><input type="date" id="pi_expiration_date" name="expiration_date"></div>
        <div class="form-field"><label for="pi_stock">Stock</label><input type="number" id="pi_stock" name="stock" min="0"></div>
      </div>
      <div class="form-field"><label for="pi_description">Description</label><textarea id="pi_description" name="description" rows="3"></textarea></div>
      <div style="display:flex;justify-content:flex-end;margin-top:16px;gap:10px;">
        <button type="button" class="btn btn-outline" id="piCancelBtn">Cancel</button>
        <button type="submit" class="btn btn-primary" id="piSubmitBtn">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
  (function(){
    var addBtn = document.getElementById('piAddBtn');
    var modal = document.getElementById('piModal');
    if(!addBtn || !modal) return;
    var closeBtn = document.getElementById('piCloseBtn');
    var cancelBtn = document.getElementById('piCancelBtn');
    var form = document.getElementById('piForm');
    var title = document.getElementById('piModalTitle');
    var submitBtn = document.getElementById('piSubmitBtn');
    function openModal(e){ if(e) e.preventDefault(); modal.style.display='flex'; }
    function closeModal(e){ if(e) e.preventDefault(); modal.style.display='none'; }
    addBtn.addEventListener('click', openModal);
    // Let browser submit form to server; no JS alert.
    if(closeBtn) closeBtn.addEventListener('click', closeModal);
    if(cancelBtn) cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e){ if(e.target===modal) closeModal(); });

    var updateButtons = document.querySelectorAll('[data-action="piUpdate"]');
    function fillFormFromRow(row){
      if(!form) return;
      form.reset();
      var cells = row.querySelectorAll('td');
      form.elements['generic_name'].value = cells[1] ? cells[1].textContent.trim() : '';
      form.elements['brand_name'].value = cells[2] ? cells[2].textContent.trim() : '';
      form.elements['category'].value = cells[3] ? cells[3].textContent.trim() : '';
      form.elements['dosage_type'].value = cells[4] ? cells[4].textContent.trim() : '';
      form.elements['strength'].value = cells[5] ? cells[5].textContent.trim() : '';
      form.elements['unit'].value = cells[6] ? cells[6].textContent.trim() : '';
      form.elements['expiration_date'].value = cells[7] ? cells[7].textContent.trim() : '';
      form.elements['stock'].value = cells[8] ? cells[8].textContent.trim() : '';
      form.elements['description'].value = cells[9] ? cells[9].textContent.trim() : '';
    }
    updateButtons.forEach(function(btn){
      btn.addEventListener('click', function(e){
        e.preventDefault();
        if(form){
          form.setAttribute('data-mode','update');
          document.getElementById('pi_mode').value = 'update';
          document.getElementById('pi_id').value = btn.getAttribute('data-id') || '';
        }
        if(title){ title.textContent = 'Update Inventory Item'; }
        if(submitBtn){ submitBtn.textContent = 'Update'; }
        var row = btn.closest('tr');
        if(row){ fillFormFromRow(row); }
        openModal();
      });
    });
    if(form){
      form.addEventListener('reset', function(){
        if(document.getElementById('pi_id')) document.getElementById('pi_id').value = '';
        form.setAttribute('data-mode','add');
        if(document.getElementById('pi_mode')) document.getElementById('pi_mode').value = 'add';
      });
    }
    if(addBtn){
      addBtn.addEventListener('click', function(){
        if(form){
          form.reset();
          form.setAttribute('data-mode','add');
          if(document.getElementById('pi_mode')) document.getElementById('pi_mode').value = 'add';
          if(document.getElementById('pi_id')) document.getElementById('pi_id').value = '';
        }
        if(title){ title.textContent = 'Add Inventory Item'; }
        if(submitBtn){ submitBtn.textContent = 'Save'; }
      });
    }

    // Client-side pagination and filtering for inventory table (similar to lab_orders.php)
    var searchInput = document.getElementById('piSearchInput');
    var categoryFilter = document.getElementById('piCategoryFilter');
    var pager = document.getElementById('piPager');
    var prevBtnPager = document.getElementById('piPrevPage');
    var nextBtnPager = document.getElementById('piNextPage');
    var pageIndicator = document.getElementById('piPageIndicator');
    var pageSize = 8;
    var currentPage = 1;
    var originalRows = [];

    function initializeRows(){
      var table = modal.parentNode ? modal.parentNode.parentNode.querySelector('table') : null;
      if(!table){
        table = document.querySelector('section.card table');
      }
      if(!table) return;
      var tbody = table.querySelector('tbody');
      if(!tbody) return;
      originalRows = Array.from(tbody.querySelectorAll('tr'))
        .filter(function(row){ return !row.classList.contains('pi-empty-row'); })
        .map(function(row){
          var cells = row.querySelectorAll('td');
          return {
            element: row,
            id: cells[0] ? cells[0].textContent.trim() : '',
            generic: cells[1] ? cells[1].textContent.trim() : '',
            brand: cells[2] ? cells[2].textContent.trim() : '',
            category: cells[3] ? cells[3].textContent.trim() : '',
            dosage: cells[4] ? cells[4].textContent.trim() : '',
            strength: cells[5] ? cells[5].textContent.trim() : '',
            unit: cells[6] ? cells[6].textContent.trim() : '',
            expires: cells[7] ? cells[7].textContent.trim() : '',
            stock: cells[8] ? cells[8].textContent.trim() : '',
            description: cells[9] ? cells[9].textContent.trim() : ''
          };
        });
      currentPage = 1;
      renderPage();
    }

    function getFilteredRows(){
      var searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
      var selectedCategory = categoryFilter ? categoryFilter.value : '';
      return originalRows.filter(function(r){
        var matchesSearch = !searchTerm ||
          r.generic.toLowerCase().includes(searchTerm) ||
          r.brand.toLowerCase().includes(searchTerm) ||
          r.category.toLowerCase().includes(searchTerm) ||
          r.description.toLowerCase().includes(searchTerm);
        var matchesCategory = !selectedCategory || r.category === selectedCategory;
        return matchesSearch && matchesCategory;
      });
    }

    function renderPage(){
      var table = document.querySelector('section.card table');
      if(!table) return;
      var tbody = table.querySelector('tbody');
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
        noRow.className = 'pi-empty-row';
        noRow.innerHTML = '<td colspan="12" class="muted" style="text-align:center;">No inventory records found.</td>';
        tbody.appendChild(noRow);
      } else {
        filtered.slice(start, end).forEach(function(r){ tbody.appendChild(r.element); });
      }

      if(pageIndicator){
        pageIndicator.textContent = 'Page ' + currentPage + ' / ' + totalPages;
      }
      if(prevBtnPager){ prevBtnPager.disabled = (currentPage <= 1 || total === 0); }
      if(nextBtnPager){ nextBtnPager.disabled = (currentPage >= totalPages || total === 0); }
    }

    function filterRows(){
      currentPage = 1;
      renderPage();
    }

    if(searchInput){
      searchInput.addEventListener('input', function(){
        var self = this;
        clearTimeout(self._searchTimeout);
        self._searchTimeout = setTimeout(filterRows, 250);
      });
    }
    if(categoryFilter){
      categoryFilter.addEventListener('change', filterRows);
    }
    if(prevBtnPager){
      prevBtnPager.addEventListener('click', function(){
        if(currentPage > 1){ currentPage--; renderPage(); }
      });
    }
    if(nextBtnPager){
      nextBtnPager.addEventListener('click', function(){
        currentPage++; renderPage();
      });
    }

    // Initialize cache from server-rendered rows
    initializeRows();
  })();
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>

