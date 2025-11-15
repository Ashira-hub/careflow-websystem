<?php $page='Admin Notifications'; include __DIR__.'/../../includes/header.php'; ?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/admin/admin_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/admin/admin_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/admin/admin_users.php"><img src="/capstone/assets/img/appointment.png" alt="Users" style="width:18px;height:18px;object-fit:contain;"> Users</a></li>
          <li><a href="/capstone/templates/admin/admin_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
          <li><a href="/capstone/templates/admin/admin_settings.php"><img src="/capstone/assets/img/setting.png" alt="Settings" style="width:18px;height:18px;object-fit:contain;"> Settings</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div class="content" style="max-width:1200px;margin:0 auto;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
      <h3 style="margin:0;">Notifications</h3>
      <div style="display:flex;gap:8px;align-items:center;">
        <button class="btn btn-outline" id="nfClearAll" title="Clear all">Clear all</button>
      </div>
    </div>

    <div id="nfEmpty" class="muted" style="display:none;padding:12px 0;">You're all caught up. No notifications.</div>

    <div id="nfList" style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;"></div>
  </div>
</div>

<!-- Notification Detail Modal -->
<div id="notificationModal" style="display:none;position:fixed;inset:0;z-index:1000;overflow-y:auto;">
  <div data-backdrop style="position:absolute;inset:0;background:rgba(0,0,0,0.5);"></div>
  <div role="dialog" aria-modal="true" style="position:relative;max-width:600px;margin:5vh auto;background:#fff;border-radius:20px;box-shadow:0 25px 50px rgba(0,0,0,0.25);overflow:hidden;">
    <div style="background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;padding:24px 28px;border-radius:20px 20px 0 0;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h3 style="margin:0;font-size:1.3rem;font-weight:700;">Notification Details</h3>
          <p style="margin:4px 0 0;opacity:0.9;font-size:0.9rem;">View full notification information</p>
        </div>
        <button type="button" id="closeNotificationModal" style="background:rgba(255,255,255,0.2);border:none;color:#fff;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.3)'" onmouseout="this.style.backgroundColor='rgba(255,255,255,0.2)'"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
      </div>
    </div>
    <div style="padding:28px;">
      <div id="notificationContent"></div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:12px;padding:20px 28px;border-top:1px solid #e5e7eb;background:linear-gradient(135deg,#f8fafc,#f1f5f9);">
      <button type="button" class="btn btn-outline" id="closeNotificationModalBtn" style="padding:10px 20px;border-radius:10px;font-weight:600;">Close</button>
      <button class="btn btn-primary" id="deleteNotificationBtn" style="padding:10px 20px;border-radius:10px;font-weight:600;background:linear-gradient(135deg,#ef4444,#dc2626);">Delete</button>
    </div>
  </div>
</div>

<script>
(function(){
  var notifications = [];
  var list = document.getElementById('nfList');
  var empty = document.getElementById('nfEmpty');
  var btnClearAll = document.getElementById('nfClearAll');

  // Modal elements
  var modal = document.getElementById('notificationModal');
  var modalContent = document.getElementById('notificationContent');
  var closeModalBtn = document.getElementById('closeNotificationModal');
  var closeModalBtn2 = document.getElementById('closeNotificationModalBtn');
  var deleteModalBtn = document.getElementById('deleteNotificationBtn');
  var backdrop = modal ? modal.querySelector('[data-backdrop]') : null;
  var currentNotificationId = null;

  function escapeHtml(s){ return (s||'').replace(/[&<>\"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;'}[c]; }); }

  async function load(){
    try{
      var res = await fetch('/capstone/notifications/pharmacy.php?role=admin', { cache: 'no-store' });
      if(!res.ok) throw new Error('Failed to load notifications');
      var data = await res.json();
      var raw = Array.isArray(data.items)?data.items:[];
      // Only include notifications explicitly for admin
      notifications = raw
        .filter(function(n){
          var r = (String(n.role||n.target_role||'').toLowerCase());
          return r === 'admin';
        })
        .map(function(n){ n.read=!!n.read; return n; });
      render();
    }catch(err){
      list.innerHTML = '<div class="muted" style="padding:12px;">'+escapeHtml(err.message)+'</div>';
      empty.style.display='none';
    }
  }

  function render(){
    list.innerHTML = '';
    if(notifications.length === 0){ empty.style.display = ''; return; } else { empty.style.display = 'none'; }
    var items = notifications.slice().sort(function(a,b){
      var ta = String(b.time||'');
      var tb = String(a.time||'');
      var cmp = ta.localeCompare(tb);
      if(cmp !== 0) return cmp;
      return (b.id||0) - (a.id||0);
    });
    items.forEach(function(n){
      var row = document.createElement('div');
      row.className = 'nf-item';
      row.setAttribute('data-id', n.id);
      row.setAttribute('style','display:flex;gap:12px;align-items:flex-start;padding:12px 14px;border-bottom:1px solid #e5e7eb;cursor:pointer;transition:background-color 0.2s ease;'+(n.read?'background:#fafafa;':''));
      row.innerHTML = '\n        <div style="width:32px;min-width:32px;height:32px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;">🔔</div>\n        <div style="flex:1;min-width:0;">\n          <div style="display:flex;align-items:center;gap:8px;">\n            <div style="font-weight:600;">'+escapeHtml(n.title)+'</div>\n            '+(n.read?'':'<span style="background:#ef4444;color:#fff;border-radius:999px;padding:1px 6px;font-size:.72rem;">NEW</span>')+'\n            <div class="muted-small" style="margin-left:auto;">'+escapeHtml(n.time)+'</div>\n          </div>\n          <div class="muted" style="margin-top:2px;">'+escapeHtml(n.body)+'</div>\n        </div>\n        <div style="display:flex;align-items:center;justify-content:center;width:40px;height:40px;">\n          <button class="nf-delete" data-action="delete" style="width:32px;height:32px;border:none;border-radius:8px;background:#fef2f2;color:#ef4444;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.2s ease;" onmouseover="this.style.backgroundColor=\'#fee2e2\'" onmouseout="this.style.backgroundColor=\'#fef2f2\'" title="Delete notification">\n            <svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\">\n              <path d=\"M3 6h18\"/>\n              <path d=\"M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6\"/>\n              <path d=\"M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2\"/>\n            </svg>\n          </button>\n        </div>';
      list.appendChild(row);
    });
  }

  function openModal(notificationId){
    var notification = notifications.find(function(n){ return n.id === notificationId; });
    if(!notification) return;
    currentNotificationId = notificationId;
    notification.read = true; // mark read
    modalContent.innerHTML = '<div style="display:grid;gap:20px;">'
      +'<div style="display:flex;align-items:center;gap:12px;padding:16px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;">'
        +'<div style="width:40px;height:40px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;font-size:20px;">🔔</div>'
        +'<div style="flex:1;">'
          +'<div style="font-weight:700;color:#0f172a;font-size:1.1rem;margin-bottom:4px;">'+escapeHtml(notification.title)+'</div>'
          +'<div style="color:#64748b;font-size:0.9rem;">'+escapeHtml(notification.time)+'</div>'
        +'</div>'
        +(notification.read ? '' : '<span style="background:#ef4444;color:#fff;border-radius:999px;padding:4px 8px;font-size:0.75rem;font-weight:600;">NEW</span>')
      +'</div>'
      +'<div style="padding:20px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;">'
        +'<h4 style="margin:0 0 12px;color:#0f172a;font-size:1rem;font-weight:600;">Message Details</h4>'
        +'<div style="color:#374151;line-height:1.6;font-size:0.95rem;">'+escapeHtml(notification.body)+'</div>'
      +'</div>'
    +'</div>';
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    render();
  }

  function closeModal(){ modal.style.display='none'; document.body.style.overflow=''; currentNotificationId=null; }

  if(btnClearAll){
    btnClearAll.addEventListener('click', async function(){
      try{
        var res = await fetch('/capstone/notifications/pharmacy.php?role=admin', { method:'DELETE' });
        if(!res.ok) throw new Error('Failed to clear');
        notifications = [];
        render();
      }catch(err){ alert('Error: '+err.message); }
    });
  }

  // Row interactions
  if(list){
    list.addEventListener('click', function(e){
      var btn = e.target.closest('button');
      var row = e.target.closest('.nf-item');
      if(!row) return;
      var id = parseInt(row.getAttribute('data-id'),10);
      var idx = notifications.findIndex(function(n){ return n.id === id; });
      if(idx === -1) return;
      if(btn && btn.getAttribute('data-action') === 'delete'){
        e.stopPropagation();
        if(!confirm('Delete this notification?')) return;
        notifications.splice(idx,1);
        render();
        return;
      }
      openModal(id);
    });

    list.addEventListener('mouseenter', function(e){ var row=e.target.closest('.nf-item'); if(row) row.style.backgroundColor='#f8fafc'; });
    list.addEventListener('mouseleave', function(e){ var row=e.target.closest('.nf-item'); if(row){ var id=parseInt(row.getAttribute('data-id'),10); var n=notifications.find(function(x){return x.id===id;}); row.style.backgroundColor = n && n.read ? '#fafafa' : ''; } });
  }

  if(closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
  if(closeModalBtn2) closeModalBtn2.addEventListener('click', closeModal);
  if(backdrop) backdrop.addEventListener('click', closeModal);
  if(deleteModalBtn){
    deleteModalBtn.addEventListener('click', function(){
      if(currentNotificationId){
        var idx = notifications.findIndex(function(n){ return n.id === currentNotificationId; });
        if(idx !== -1){ if(!confirm('Delete this notification?')) return; notifications.splice(idx,1); render(); }
        closeModal();
      }
    });
  }

  load();
})();
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>

