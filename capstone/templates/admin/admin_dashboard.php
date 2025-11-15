<?php
 $page='Admin Dashboard';
 require_once __DIR__.'/../../config/db.php';
 $totalUsers = 0;
 $activeUsers = 0;
 try {
   $pdo = get_pdo();
   $stmt = $pdo->query("SELECT COUNT(*) FROM users");
   $totalUsers = (int)$stmt->fetchColumn();
   // Check if users.active column exists; if yes, count active=TRUE
   $c = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name='users' AND column_name='active'");
   $c->execute();
   if ($c->fetchColumn()) {
     $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE active = TRUE");
     $activeUsers = (int)$stmt->fetchColumn();
   } else {
     // Fallback: show total as active if no active column is present
     $activeUsers = $totalUsers;
   }
 } catch (Throwable $e) {
   // leave defaults
 }
 include __DIR__.'/../../includes/header.php';
?>

<div class="layout-sidebar full-bleed" style="padding: 24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a class="active" href="/capstone/templates/admin/admin_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/admin/admin_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/admin/admin_users.php"><img src="/capstone/assets/img/appointment.png" alt="Users" style="width:18px;height:18px;object-fit:contain;"> Users</a></li>
          <li><a href="/capstone/templates/admin/admin_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
          <li><a href="/capstone/templates/admin/admin_settings.php"><img src="/capstone/assets/img/setting.png" alt="Settings" style="width:18px;height:18px;object-fit:contain;"> Settings</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div>
    <section id="home" class="dashboard-hero" style="padding-top:10px;">
      <h1 class="dashboard-title">Admin Dashboard</h1>
      <div class="stat-cards" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="card"><h4>Users</h4><div class="muted-small">Total users</div><div class="stat-value"><?php echo number_format($totalUsers); ?></div></div>
        <div class="card"><h4>Active Users</h4><div class="muted-small">Active flag</div><div class="stat-value"><?php echo number_format($activeUsers); ?></div></div>
      </div>

      <div class="card">
        <h3 style="margin:0 0 16px;color:#0a5d39;">Quick Actions</h3>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
          <a href="/capstone/templates/admin/admin_users.php" class="btn btn-outline" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;text-align:center;border-radius:12px;text-decoration:none;transition:all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='none'">
            <img src="/capstone/assets/img/user.png" alt="Manage Users" style="width:28px;height:28px;object-fit:contain;margin-bottom:8px;" />
            <div style="font-weight:600;color:#0f172a;">Manage Users</div>
          </a>
          <a href="/capstone/templates/admin/admin_reports.php" class="btn btn-outline" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;text-align:center;border-radius:12px;text-decoration:none;transition:all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='none'">
            <img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:28px;height:28px;object-fit:contain;margin-bottom:8px;" />
            <div style="font-weight:600;color:#0f172a;">Reports</div>
          </a>
          <a href="/capstone/templates/admin/admin_settings.php" class="btn btn-outline" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;text-align:center;border-radius:12px;text-decoration:none;transition:all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='none'">
            <img src="/capstone/assets/img/setting.png" alt="Settings" style="width:28px;height:28px;object-fit:contain;margin-bottom:8px;" />
            <div style="font-weight:600;color:#0f172a;">Settings</div>
          </a>
        </div>
      </div>
    </section>
  </div>
</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>

