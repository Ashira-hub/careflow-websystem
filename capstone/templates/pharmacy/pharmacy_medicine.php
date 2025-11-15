<?php
$page='Pharmacy Medicine';
require_once __DIR__.'/../../config/db.php';
$medicineItems = [];
$medicineError = '';
try {
  $pdo = get_pdo();
  $stmt = $pdo->query('SELECT id, generic_name, brand_name, category, dosage_type, strength, unit, expiration_date, description, stock FROM inventory ORDER BY brand_name ASC NULLS LAST');
  $medicineItems = $stmt->fetchAll();
} catch (Throwable $e) {
  $medicineError = 'Unable to load medicines at the moment.';
}
function pm_escape($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
include __DIR__.'/../../includes/header.php';
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/pharmacy/pharmacy_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_inventory.php"><img src="/capstone/assets/img/appointment.png" alt="Inventory" style="width:18px;height:18px;object-fit:contain;"> Inventory</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_prescription.php"><img src="/capstone/assets/img/prescription.png" alt="Prescription" style="width:18px;height:18px;object-fit:contain;"> Prescription</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/drug.png" alt="Medicine" style="width:18px;height:18px;object-fit:contain;"> Medicine</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div>
    <section class="card" style="margin-bottom:16px;">
      <h2 class="dashboard-title" style="margin:0;">Medicine List</h2>
      <div class="grid-2" style="display:grid;grid-template-columns:1fr auto;align-items:end;gap:12px;margin-top:8px;">
        <div class="form-field"><label>Search</label><input type="text" id="searchInput" placeholder="Name or code" /></div>
        <div class="form-field" style="width:180px;min-width:160px;"><label>Category</label><select id="categoryFilter" style="width:100%"><option value="">All</option></select></div>
      </div>
    </section>

    <section class="card">
      <h3 style="margin:0;">Medicines</h3>
      <?php if ($medicineError !== ''): ?>
        <div class="muted" style="padding:12px 0;"><?php echo pm_escape($medicineError); ?></div>
      <?php else: ?>
        <div class="table-responsive">
          <table>
            <thead><tr><th>ID</th><th>Brand Name</th><th>Generic Name</th><th>Category</th><th>Dosage Type</th><th>Strength</th><th>Unit</th><th>Stock</th><th>Expires</th><th>Description</th></tr></thead>
            <tbody>
              <?php if ($medicineItems): ?>
                <?php foreach ($medicineItems as $med): ?>
                  <tr>
                    <td><?php echo pm_escape($med['id'] ?? ''); ?></td>
                    <td><?php echo pm_escape($med['brand_name'] ?? ''); ?></td>
                    <td><?php echo pm_escape($med['generic_name'] ?? ''); ?></td>
                    <td><?php echo pm_escape($med['category'] ?? ''); ?></td>
                    <td><?php echo pm_escape($med['dosage_type'] ?? ''); ?></td>
                    <td><?php echo pm_escape($med['strength'] ?? ''); ?></td>
                    <td><?php echo pm_escape($med['unit'] ?? ''); ?></td>
                    <td><?php echo pm_escape($med['stock'] ?? ''); ?></td>
                    <td><?php echo pm_escape($med['expiration_date'] ?? ''); ?></td>
                    <td><?php echo pm_escape($med['description'] ?? ''); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="10" class="muted" style="text-align:center;">No medicines found in inventory.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div id="pmPager" style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:10px;border-top:1px solid #e5e7eb;padding-top:10px;font-size:0.85rem;">
          <button type="button" id="pmPrevPage" class="pager-pill" style="min-width:64px;height:32px;padding:6px 14px;border-radius:999px;border:1px solid #16a34a;background:#fff;color:#16a34a;font-weight:500;font-size:.9rem;">Prev</button>
          <button type="button" id="pmPageIndicator" class="pager-pill-active" style="min-width:32px;height:32px;padding:6px 12px;border-radius:999px;border:none;background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;font-weight:600;font-size:.9rem;box-shadow:0 8px 18px rgba(16,185,129,0.45);">1</button>
          <button type="button" id="pmNextPage" class="pager-pill" style="min-width:64px;height:32px;padding:6px 14px;border-radius:999px;border:1px solid #16a34a;background:#fff;color:#16a34a;font-weight:500;font-size:.9rem;">Next</button>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<script>
(function(){
  var searchInput = document.getElementById('searchInput');
  var categoryFilter = document.getElementById('categoryFilter');
  var table = document.querySelector('section.card table');
  var tableBody = table ? table.querySelector('tbody') : null;
  var pager = document.getElementById('pmPager');
  var prevBtn = document.getElementById('pmPrevPage');
  var nextBtn = document.getElementById('pmNextPage');
  var pageIndicator = document.getElementById('pmPageIndicator');
  var originalRows = [];
  var pageSize = 8;
  var currentPage = 1;
  
  // Store original rows data
  function initializeRows(){
    if(!tableBody) return;
    originalRows = Array.from(tableBody.querySelectorAll('tr'))
      .filter(function(row){ return !row.classList.contains('pm-empty-row'); })
      .map(function(row){
        var cells = row.querySelectorAll('td');
        return {
          element: row,
          id: cells[0] ? cells[0].textContent.trim() : '',
          brandName: cells[1] ? cells[1].textContent.trim() : '',
          genericName: cells[2] ? cells[2].textContent.trim() : '',
          category: cells[3] ? cells[3].textContent.trim() : '',
          dosageType: cells[4] ? cells[4].textContent.trim() : '',
          strength: cells[5] ? cells[5].textContent.trim() : '',
          unit: cells[6] ? cells[6].textContent.trim() : '',
          stock: cells[7] ? cells[7].textContent.trim() : '',
          expires: cells[8] ? cells[8].textContent.trim() : '',
          description: cells[9] ? cells[9].textContent.trim() : ''
        };
      });
    
    populateFilterOptions();
    currentPage = 1;
    renderPage();
  }
  
  function populateFilterOptions(){
    if(originalRows.length === 0 || !categoryFilter) return;
    var categories = [...new Set(originalRows.map(function(row){
      return row.category;
    }).filter(function(category){
      return category && category.trim() !== '';
    }))].sort();
    categoryFilter.innerHTML = '<option value="">All</option>';
    categories.forEach(function(category){
      var option = document.createElement('option');
      option.value = category;
      option.textContent = category;
      categoryFilter.appendChild(option);
    });
  }

  function getFilteredRows(){
    var searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
    var selectedCategory = categoryFilter ? categoryFilter.value : '';
    return originalRows.filter(function(row){
      var matchesSearch = !searchTerm ||
        row.brandName.toLowerCase().includes(searchTerm) ||
        row.genericName.toLowerCase().includes(searchTerm) ||
        row.id.toLowerCase().includes(searchTerm) ||
        row.description.toLowerCase().includes(searchTerm);
      var matchesCategory = !selectedCategory || row.category === selectedCategory;
      return matchesSearch && matchesCategory;
    });
  }

  function renderPage(){
    if(!tableBody) return;
    var filtered = getFilteredRows();
    var total = filtered.length;
    var totalPages = Math.max(1, Math.ceil(total / pageSize));
    if(currentPage > totalPages) currentPage = totalPages;
    var start = (currentPage - 1) * pageSize;
    var end = start + pageSize;

    tableBody.innerHTML = '';

    if(total === 0){
      var noRow = document.createElement('tr');
      noRow.className = 'pm-empty-row';
      noRow.innerHTML = '<td colspan="10" class="muted" style="text-align:center;">No medicines found matching the filters.</td>';
      tableBody.appendChild(noRow);
    } else {
      filtered.slice(start, end).forEach(function(row){
        tableBody.appendChild(row.element);
      });
    }

    if(pageIndicator){
      pageIndicator.textContent = 'Page ' + currentPage + ' / ' + totalPages;
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
      var self = this;
      clearTimeout(self._pmSearchTimeout);
      self._pmSearchTimeout = setTimeout(filterRows, 300);
    });
  }
  
  if(categoryFilter){
    categoryFilter.addEventListener('change', filterRows);
  }

  if(prevBtn){
    prevBtn.addEventListener('click', function(){
      if(currentPage > 1){ currentPage--; renderPage(); }
    });
  }

  if(nextBtn){
    nextBtn.addEventListener('click', function(){
      currentPage++; renderPage();
    });
  }
  
  // Initialize on page load
  initializeRows();
})();
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>

