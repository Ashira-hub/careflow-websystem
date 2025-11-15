<?php $page='Admin Settings'; include __DIR__.'/../../includes/header.php'; ?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/admin/admin_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/admin/admin_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/admin/admin_users.php"><img src="/capstone/assets/img/appointment.png" alt="Users" style="width:18px;height:18px;object-fit:contain;"> Users</a></li>
          <li><a href="/capstone/templates/admin/admin_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
          <li><a class="active" href="#"><img src="/capstone/assets/img/setting.png" alt="Settings" style="width:18px;height:18px;object-fit:contain;"> Settings</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div>
    <section class="card" style="margin-bottom:16px;">
      <h2 class="dashboard-title" style="margin:0;">Settings</h2>
    </section>

    <section class="card" style="margin-bottom:16px;">
      <h3 style="margin:0 0 16px;color:#0f172a;font-size:1.2rem;">Profile &amp; Appearance</h3>
      <div style="display:flex;flex-direction:column;gap:12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:16px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;">
          <div>
            <h4 style="margin:0;color:#0f172a;font-size:1rem;">Edit Profile</h4>
            <p style="margin:4px 0 0;color:#64748b;font-size:0.9rem;">Update your personal information</p>
          </div>
          <a href="/capstone/templates/admin/admin_profile.php" class="btn btn-outline" aria-label="Open profile settings" style="width:36px;height:36px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;font-weight:700;">&gt;</a>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:16px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;">
          <div>
            <h4 style="margin:0;color:#0f172a;font-size:1rem;">Notifications</h4>
            <p style="margin:4px 0 0;color:#64748b;font-size:0.9rem;">Receive updates and alerts</p>
          </div>
          <a href="/capstone/templates/admin/admin_notification.php" class="btn btn-outline" aria-label="Open notifications settings" style="width:36px;height:36px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;font-weight:700;">
            &gt;
          </a>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:16px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;">
          <div>
            <h4 style="margin:0;color:#0f172a;font-size:1rem;">Dark Mode</h4>
            <p style="margin:4px 0 0;color:#64748b;font-size:0.9rem;">Switch to dark theme for better viewing</p>
          </div>
          <label class="toggle-switch" style="position:relative;display:inline-block;width:50px;height:28px;">
            <input type="checkbox" id="darkMode" style="opacity:0;width:0;height:0;" />
            <span class="toggle-slider" style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background-color:#94a3b8;transition:0.3s;border-radius:28px;"></span>
          </label>
        </div>
      </div>
    </section>

    

    
  </div>
</div>

<!-- Toggle Switch Styles -->
<style>
  .toggle-switch input:checked + .toggle-slider {
    background-color: #10b981;
  }
  .toggle-switch input:not(:checked) + .toggle-slider {
    background-color: #94a3b8;
  }
  .toggle-slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
  }
  .toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(22px);
  }
</style>

<script>
(function(){
  // Wire Admin Dark Mode toggle to localStorage and reload across /admin/ pages
  var darkToggle = document.getElementById('darkMode');
  if(darkToggle){
    try{
      // Initialize from stored preference
      var isOn = localStorage.getItem('adminDarkMode') === '1';
      darkToggle.checked = isOn;
      // Persist on change and reload to apply in header
      darkToggle.addEventListener('change', function(){
        if(this.checked){
          localStorage.setItem('adminDarkMode','1');
        } else {
          localStorage.removeItem('adminDarkMode');
        }
        location.reload();
      });
    }catch(e){ /* no-op */ }
  }
})();
</script>

