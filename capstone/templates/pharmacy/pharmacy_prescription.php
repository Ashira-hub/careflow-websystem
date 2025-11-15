<?php $page='Pharmacy Prescription'; include __DIR__.'/../../includes/header.php'; ?>
<style>
  /* Scoped styling for this page only */
  .rx-layout { width:100%; }
  .rx-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; width:100%; max-width:none; }
  .rx-controls { display:flex; justify-content:space-between; align-items:center; margin:8px 0 12px; }
  .rx-table-wrap { width:100%; overflow:auto; }
  .rx-table { width:100%; min-width:760px; border-collapse:separate; border-spacing:0; }
  .rx-table thead th { position:sticky; top:0; background:#f8fafc; text-align:left; font-weight:600; padding:10px 12px; border-bottom:1px solid #e5e7eb; z-index:1; }
  .rx-table td { padding:10px 12px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
  .rx-table tr:hover { background:#f9fafb; }
  .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:.72rem; line-height:18px; }
  .badge-new { background:#ef4444; color:#fff; }
  .badge-accepted { background:#0ea5e9; color:#fff; }
  .badge-rejected { background:#6b7280; color:#fff; }
  .badge-dispensed { background:#10b981; color:#fff; }
  .badge-acknowledged { background:#6366f1; color:#fff; }
  .badge-done { background:#22c55e; color:#fff; }
  .rx-modal-card { background:#fff; border-radius:12px; box-shadow:0 20px 40px rgba(0,0,0,.18); max-width:640px; width:100%; padding:20px; position:relative; }
  .rx-modal-card h3 { margin:0; }
  .rx-form-fields { display:grid; gap:12px; margin-top:16px; margin-bottom:18px; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); }
  .rx-form-fields .form-field { display:flex; flex-direction:column; gap:6px; }
  .rx-form-fields label { font-size:.78rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:.04em; }
  .rx-form-fields input,
  .rx-form-fields textarea { padding:10px 12px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; font:inherit; color:#1f2937; }
  .rx-form-fields textarea { resize:vertical; min-height:88px; }
  .rx-modal-footer { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
  .rx-modal-actions { display:flex; gap:8px; flex-wrap:wrap; }
  @media (max-width:520px){ .rx-modal-card { padding:16px; } }
  @media (max-width: 720px){ .rx-table thead { display:none; } .rx-table tr{ display:block; padding:10px 0; } .rx-table td{ display:block; padding:6px 0; } }
</style>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/pharmacy/pharmacy_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_inventory.php"><img src="/capstone/assets/img/appointment.png" alt="Inventory" style="width:18px;height:18px;object-fit:contain;"> Inventory</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/prescription.png" alt="Prescription" style="width:18px;height:18px;object-fit:contain;"> Prescription</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_medicine.php"><img src="/capstone/assets/img/drug.png" alt="Medicine" style="width:18px;height:18px;object-fit:contain;"> Medicine</a></li>
          <li><a href="/capstone/templates/pharmacy/pharmacy_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div class="rx-layout">
    <section class="rx-form rx-card">
      <h3>List of Prescriptions</h3>
      <p class="muted-small">Verify prescription details, prepare medicine, then mark as Prepared to notify nursing.</p>
      <div class="rx-table-wrap">
      <table class="rx-table">
        <thead><tr><th>Date</th><th>Time</th><th>Patient</th><th>Medicine</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody id="rxTblBody"></tbody>
      </table>
      </div>
      <div id="rxPager" style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:10px;border-top:1px solid #e5e7eb;padding-top:10px;font-size:0.85rem;">
        <button type="button" id="rxPrevPage" class="pager-pill" style="min-width:64px;height:32px;padding:6px 14px;border-radius:999px;border:1px solid #16a34a;background:#fff;color:#16a34a;font-weight:500;font-size:.9rem;">Prev</button>
        <button type="button" id="rxPageIndicator" class="pager-pill-active" style="min-width:32px;height:32px;padding:6px 12px;border-radius:999px;border:none;background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;font-weight:600;font-size:.9rem;box-shadow:0 8px 18px rgba(16,185,129,0.45);">1</button>
        <button type="button" id="rxNextPage" class="pager-pill" style="min-width:64px;height:32px;padding:6px 14px;border-radius:999px;border:1px solid #16a34a;background:#fff;color:#16a34a;font-weight:500;font-size:.9rem;">Next</button>
      </div>
    </section>


<!-- Details Modal -->
<div id="rxModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:50;align-items:center;justify-content:center;padding:16px;">
  <div class="rx-modal-card" role="dialog" aria-modal="true">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
      <div>
        <h3 id="rxMTitle">Prescription Details</h3>
        <div id="rxMTime" class="muted-small" style="margin-top:4px;"></div>
      </div>
      <button class="btn btn-outline" id="rxCloseTop" type="button" aria-label="Close" style="padding:6px 10px;">✕</button>
    </div>
    <div class="muted-small" style="margin-top:8px;">Status: <span id="rxMStatus" class="badge badge-new">New</span></div>
    <form id="rxModalForm" class="rx-form-fields">
      <input type="hidden" id="rxMId" />
      <div class="form-field">
        <label for="rxMPatient">Patient</label>
        <input id="rxMPatient" type="text" readonly />
      </div>
      <div class="form-field">
        <label for="rxMMedicine">Medicine</label>
        <input id="rxMMedicine" type="text" readonly />
      </div>
      <div class="form-field">
        <label for="rxMQty">Quantity</label>
        <input id="rxMQty" type="text" readonly />
      </div>
      <div class="form-field" style="grid-column:1/-1;">
        <label for="rxMNotes">Instructions / Notes</label>
        <textarea id="rxMNotes" readonly></textarea>
      </div>
    </form>
    <div class="rx-modal-footer">
      <div class="muted-small">Review the prescription information before updating its status.</div>
      <div class="rx-modal-actions">
        <button class="btn" id="rxAccept" type="button">Accept</button>
        <button class="btn btn-outline" id="rxReject" type="button">Reject</button>
        <button class="btn" id="rxDispense" type="button" style="display:none;">Dispense</button>
        <button class="btn btn-outline" id="rxClose" type="button">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var tbody = document.getElementById('rxTblBody');
  var modal = document.getElementById('rxModal');
  var mTitle = document.getElementById('rxMTitle');
  var mTime = document.getElementById('rxMTime');
  var mStatus = document.getElementById('rxMStatus');
  var form = document.getElementById('rxModalForm');
  var fId = document.getElementById('rxMId');
  var fPatient = document.getElementById('rxMPatient');
  var fMedicine = document.getElementById('rxMMedicine');
  var fQty = document.getElementById('rxMQty');
  var fNotes = document.getElementById('rxMNotes');
  var bAccept = document.getElementById('rxAccept');
  var bReject = document.getElementById('rxReject');
  var bClose = document.getElementById('rxClose');
  var bCloseTop = document.getElementById('rxCloseTop');
  var bDispense = document.getElementById('rxDispense');
  var items = [];
  var selectedId = null;
  var pageSize = 8;
  var currentPage = 1;
  var pager = document.getElementById('rxPager');
  var prevBtn = document.getElementById('rxPrevPage');
  var nextBtn = document.getElementById('rxNextPage');
  var pageIndicator = document.getElementById('rxPageIndicator');
  function escapeHtml(s){ return (s||'').replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
  if(form){ form.addEventListener('submit', function(e){ e.preventDefault(); }); }
  function currentTime(){
    try { return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
    catch(e){ return new Date().toISOString().slice(11,16); }
  }
  function currentDate(){
    try { return new Date().toLocaleDateString([], { year:'numeric', month:'2-digit', day:'2-digit' }); }
    catch(e){ return new Date().toISOString().slice(0,10); }
  }
  function formatDateTime(value){
    if(!value) return '—';
    var parsed = Date.parse(value);
    if(!isNaN(parsed)){
      try {
        return new Date(parsed).toLocaleString([], { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
      } catch (err) {
        return new Date(parsed).toISOString().slice(0,16).replace('T',' ');
      }
    }
    return value;
  }
  var STATUS_META = {
    'new': { text: 'New', className: 'badge badge-new' },
    'accepted': { text: 'Accepted', className: 'badge badge-accepted' },
    'rejected': { text: 'Rejected', className: 'badge badge-rejected' },
    'dispensed': { text: 'Dispensed', className: 'badge badge-dispensed' },
    'acknowledged': { text: 'Acknowledged', className: 'badge badge-acknowledged' },
    'done': { text: 'Done', className: 'badge badge-done' }
  };
  function statusMeta(status){
    var key = (status || 'new').toLowerCase();
    return STATUS_META[key] || STATUS_META['new'];
  }
  function renderStatusBadge(status){
    var meta = statusMeta(status);
    return '<span class="'+meta.className+'">'+escapeHtml(meta.text)+'</span>';
  }
  function applyStatusToElement(el, status){
    if(!el) return;
    var meta = statusMeta(status);
    el.className = meta.className;
    el.textContent = meta.text;
  }
  function parseFields(n){
    var patient='', med='', qty='', notes='';
    var body = n.body||'';
    var extras = [];
    body.split('|').forEach(function(part){
      var raw = part.trim();
      var lower = raw.toLowerCase();
      if(lower.startsWith('patient:')){
        patient = raw.split(':').slice(1).join(':').trim();
      } else if(lower.startsWith('medicine:')){
        med = raw.split(':').slice(1).join(':').trim();
      } else if(lower.startsWith('qty:')){
        qty = raw.split(':').slice(1).join(':').trim();
      } else if(lower.startsWith('notes:')){
        notes = raw.split(':').slice(1).join(':').trim();
      } else if(raw !== ''){
        extras.push(raw);
      }
    });
    if(!notes && extras.length){ notes = extras.join(' | '); }
    return { patient: patient, medicine: med, qty: qty, notes: notes };
  }
  function statusBadge(n){
    return renderStatusBadge(n.status);
  }
  function actionBtn(n){
    return '<button class="btn btn-outline" data-action="view" data-id="'+n.id+'">View</button>';
  }

  function renderPage(){
    tbody.innerHTML = '';
    if(!Array.isArray(items) || items.length === 0){
      tbody.innerHTML = '<tr><td colspan="5" class="muted" style="text-align:center;">No prescriptions received.</td></tr>';
      if(pageIndicator){ pageIndicator.textContent = 'Page 1 / 1'; }
      if(prevBtn){ prevBtn.disabled = true; }
      if(nextBtn){ nextBtn.disabled = true; }
      return;
    }

    var ordered = items.slice().reverse();
    var total = ordered.length;
    var totalPages = Math.max(1, Math.ceil(total / pageSize));
    if(currentPage > totalPages) currentPage = totalPages;
    var start = (currentPage - 1) * pageSize;
    var end = start + pageSize;

    ordered.slice(start, end).forEach(function(n){
      var f = parseFields(n);
      var tr = document.createElement('tr');
      tr.setAttribute('data-id', n.id);
      tr.innerHTML = '<td>'+escapeHtml(currentDate())+'</td>'+
                     '<td>'+escapeHtml(currentTime())+'</td>'+
                     '<td>'+escapeHtml(f.patient||'')+'</td>'+
                     '<td>'+escapeHtml(f.medicine||'')+'</td>'+
                     '<td>'+statusBadge(n)+'</td>'+
                     '<td style="text-align:right;">'+actionBtn(n)+'</td>';
      tbody.appendChild(tr);
    });

    if(pageIndicator){
      pageIndicator.textContent = 'Page ' + currentPage + ' / ' + totalPages;
    }
    if(prevBtn){ prevBtn.disabled = (currentPage <= 1 || total === 0); }
    if(nextBtn){ nextBtn.disabled = (currentPage >= totalPages || total === 0); }
  }

  async function load(){
    try{
      var res = await fetch('/capstone/notifications/pharmacy.php');
      if(!res.ok) throw new Error('Failed to load');
      var data = await res.json();
      items = Array.isArray(data.items)?data.items:[];
      items = items.filter(function(n){
        var st = (n.status || '').toLowerCase();
        return st !== 'acknowledged' && st !== 'done';
      });
      currentPage = 1;
      renderPage();
    }catch(err){
      tbody.innerHTML = '<tr><td colspan="5" class="muted" style="text-align:center;">'+escapeHtml(err.message)+'</td></tr>';
      if(pageIndicator){ pageIndicator.textContent = 'Page 1 / 1'; }
      if(prevBtn){ prevBtn.disabled = true; }
      if(nextBtn){ nextBtn.disabled = true; }
    }
  }
  function updateModalButtons(s){
    var st = (s||'new').toLowerCase();
    if(st==='new'){
      bAccept.style.display = '';
      bReject.style.display = '';
      bDispense.style.display = 'none';
    } else if(st==='accepted'){
      bAccept.style.display = 'none';
      bReject.style.display = 'none';
      bDispense.style.display = '';
    } else {
      bAccept.style.display = 'none';
      bReject.style.display = 'none';
      bDispense.style.display = 'none';
    }
  }
  function openModal(item){
    selectedId = item.id;
    mTitle.textContent = item.title||'Prescription';
    mTime.textContent = formatDateTime(item.time);
    if(fId){ fId.value = item.id; }
    var fields = parseFields(item);
    if(fPatient){ fPatient.value = fields.patient || ''; }
    if(fMedicine){ fMedicine.value = fields.medicine || ''; }
    if(fQty){ fQty.value = fields.qty || ''; }
    if(fNotes){ fNotes.value = fields.notes || item.body || ''; }
    applyStatusToElement(mStatus, item.status);
    modal.style.display = 'flex';
    updateModalButtons(item.status);
  }
  function closeModal(){ modal.style.display = 'none'; }
  if(bClose){ bClose.addEventListener('click', closeModal); }
  if(bCloseTop){ bCloseTop.addEventListener('click', closeModal); }
  modal.addEventListener('click', function(e){ if(e.target===modal) closeModal(); });
  tbody.addEventListener('click', function(e){
    var btn = e.target.closest('button[data-action="view"]');
    var row = e.target.closest('tr');
    var id = null;
    if(btn){ id = parseInt(btn.getAttribute('data-id'),10); }
    else if(row){ id = parseInt(row.getAttribute('data-id'),10); }
    if(!id) return;
    var item = items.find(function(x){ return x.id===id; });
    if(item) openModal(item);
  });
  async function putStatus(id, status){
    var res = await fetch('/capstone/notifications/pharmacy.php?id='+encodeURIComponent(id),{
      method:'PUT', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify({ status: status, read: true })
    });
    if(!res.ok){ throw new Error('Failed to update'); }
    return res.json();
  }
  async function handleStatus(newStatus){
    if(!selectedId) return;
    try{
      var updated = await putStatus(selectedId, newStatus);
      // Update local cache
      var idx = items.findIndex(function(x){ return x.id===selectedId; });
      if(idx>-1){ items[idx] = updated; }
      // Reflect in modal
      applyStatusToElement(mStatus, updated.status);
      updateModalButtons(updated.status);
      // Re-render table
      load();
    }catch(err){ alert('Error: '+err.message); }
  }
  if(bAccept){ bAccept.addEventListener('click', function(){ handleStatus('accepted'); }); }
  if(bReject){ bReject.addEventListener('click', function(){ handleStatus('rejected'); }); }
  if(bDispense){ bDispense.addEventListener('click', function(){ handleStatus('dispensed'); }); }

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

  load();
})();
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>

