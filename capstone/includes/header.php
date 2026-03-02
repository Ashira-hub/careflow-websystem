<?php
if (!isset($page)) {
  $page = '';
}
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
// Load avatar_uri for header profile icon on every request so it reflects across devices
try {
  if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = [];
  }
  if (!empty($_SESSION['user']['id'])) {
    require_once __DIR__ . '/../config/db.php';
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT avatar_uri, last_edited FROM profile WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $_SESSION['user']['id']]);
    $row = $stmt->fetch();
    if ($row) {
      $_SESSION['user']['avatar_uri'] = isset($row['avatar_uri']) ? (string)$row['avatar_uri'] : '';
      $_SESSION['user']['avatar_last_edited'] = isset($row['last_edited']) ? (string)$row['last_edited'] : '';
    } else {
      $_SESSION['user']['avatar_uri'] = '';
      $_SESSION['user']['avatar_last_edited'] = '';
    }
  }
} catch (Throwable $e) { /* ignore avatar load errors */
}
// Session-scoped activity log for doctor pages (for dashboard Recent Activity)
try {
  $uri = strtolower((string)($_SERVER['REQUEST_URI'] ?? ''));
  $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
  $isDoctorPath = strpos($uri, '/templates/doctor/') !== false;
  $isDoctorDashboard = strpos($uri, '/templates/doctor/doctor_dashboard.php') !== false;
  if ($isDoctorPath && !$isDoctorDashboard && $method === 'GET') {
    if (!isset($_SESSION['session_started_at'])) {
      $_SESSION['session_started_at'] = date('Y-m-d H:i:s');
    }
    if (!isset($_SESSION['doctor_activity']) || !is_array($_SESSION['doctor_activity'])) {
      $_SESSION['doctor_activity'] = [];
    }
    $title = 'Viewed ' . ($page !== '' ? (string)$page : 'Doctor Page');
    $ts = date('Y-m-d H:i:s');
    $meta = substr($ts, 0, 16);
    $body = $page !== '' ? (string)$page : (string)$uri;
    $_SESSION['doctor_activity'][] = ['title' => $title, 'meta' => $meta, 'body' => $body, 'ts' => $ts];
    if (count($_SESSION['doctor_activity']) > 50) {
      $_SESSION['doctor_activity'] = array_slice($_SESSION['doctor_activity'], -50);
    }
  }
  // Session-scoped activity log for laboratory pages (for lab dashboard Recent Activity)
  $isLabPath = strpos($uri, '/templates/laboratory/') !== false;
  $isLabDashboard = strpos($uri, '/templates/laboratory/lab_dashboard.php') !== false;
  if ($isLabPath && !$isLabDashboard && $method === 'GET') {
    if (!isset($_SESSION['session_started_at']) || $_SESSION['session_started_at'] === '') {
      $_SESSION['session_started_at'] = date('Y-m-d H:i:s');
    }
    if (!isset($_SESSION['lab_activity']) || !is_array($_SESSION['lab_activity'])) {
      $_SESSION['lab_activity'] = [];
    }
    $title = 'Viewed ' . ($page !== '' ? (string)$page : 'Lab Page');
    $ts = date('Y-m-d H:i:s');
    $meta = substr($ts, 0, 16);
    $body = $page !== '' ? (string)$page : (string)$uri;
    $_SESSION['lab_activity'][] = ['title' => $title, 'meta' => $meta, 'body' => $body, 'ts' => $ts];
    if (count($_SESSION['lab_activity']) > 50) {
      $_SESSION['lab_activity'] = array_slice($_SESSION['lab_activity'], -50);
    }
  }
  // Session-scoped activity log for pharmacy pages (for pharmacy dashboard Recent Activity)
  $isPharmPath = strpos($uri, '/templates/pharmacy/') !== false;
  $isPharmDashboard = strpos($uri, '/templates/pharmacy/pharmacy_dashboard.php') !== false;
  if ($isPharmPath && !$isPharmDashboard && $method === 'GET') {
    if (!isset($_SESSION['session_started_at']) || $_SESSION['session_started_at'] === '') {
      $_SESSION['session_started_at'] = date('Y-m-d H:i:s');
    }
    if (!isset($_SESSION['pharmacy_activity']) || !is_array($_SESSION['pharmacy_activity'])) {
      $_SESSION['pharmacy_activity'] = [];
    }
    $title = 'Viewed ' . ($page !== '' ? (string)$page : 'Pharmacy Page');
    $ts = date('Y-m-d H:i:s');
    $meta = substr($ts, 0, 16);
    $body = $page !== '' ? (string)$page : (string)$uri;
    $_SESSION['pharmacy_activity'][] = ['title' => $title, 'meta' => $meta, 'body' => $body, 'ts' => $ts];
    if (count($_SESSION['pharmacy_activity']) > 50) {
      $_SESSION['pharmacy_activity'] = array_slice($_SESSION['pharmacy_activity'], -50);
    }
  }
} catch (Throwable $e) { /* ignore logging errors */
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CareFlow<?php echo $page ? ' - ' . htmlspecialchars($page) : ''; ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/capstone/assets/css/style.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <?php if (strpos($page, 'Doctor Dashboard') !== false): ?>
    <link rel="stylesheet" href="/capstone/assets/css/doctor-dashboard.css" />
    <!-- try sanako ni -->
  <?php endif; ?>
  <style>
    /* Minimal dark theme overrides for Admin pages */
    .dark body {
      background: #0b1220;
      color: #e5e7eb;
    }

    .dark .card,
    .dark section.card,
    .dark .stat-card {
      background: #111827 !important;
      border-color: #1f2937 !important;
      color: #e5e7eb !important;
    }

    .dark .sidebar,
    .dark .sidepanel {
      background: #0b1220;
      border-color: #1f2937;
    }

    .dark .nav-list a {
      color: #e5e7eb;
    }

    .dark .nav-list a.active {
      color: #10b981;
    }

    .dark .dashboard-title,
    .dark h1,
    .dark h2,
    .dark h3,
    .dark h4 {
      color: #e5e7eb;
    }

    .dark .muted,
    .dark .muted-small {
      color: #9ca3af !important;
    }

    .dark input,
    .dark select,
    .dark textarea {
      background: #0f172a !important;
      border-color: #1f2937 !important;
      color: #e5e7eb !important;
    }

    .dark table {
      color: #e5e7eb;
    }

    .dark table thead tr {
      background: #0f172a;
    }

    .dark table tr {
      border-color: #1f2937;
    }

    .dark .btn.btn-outline {
      border-color: #374151;
      color: #e5e7eb;
    }

    .dark .btn.btn-outline:hover {
      background: #111827;
    }

    .dark .alert {
      background: #0f172a !important;
      border-color: #1f2937 !important;
      color: #e5e7eb !important;
    }
  </style>
  <script>
    (function() {
      try {
        var p = (location.pathname || '').toLowerCase();
        if (p.indexOf('/admin/') !== -1) {
          if (localStorage.getItem('adminDarkMode') === '1') {
            document.documentElement.classList.add('dark');
          } else {
            document.documentElement.classList.remove('dark');
          }
        }
      } catch (e) {}
    })();
  </script>
</head>

<body>
  <header class="site-header">
    <div class="container nav">
      <a class="brand" href="/capstone/index.php">
        <img src="/capstone/assets/img/careflow_logo (2).png" alt="CareFlow Logo" style="height:100px;width:auto;display:block;">
      </a>
      <nav>
        <ul class="menu">
          <li><a class="<?php echo $page === 'Home' ? 'active' : ''; ?>" href="/capstone/index.php">Home</a></li>
          <li><a class="<?php echo $page === 'About' ? 'active' : ''; ?>" href="/capstone/about.php">About</a></li>
          <li><a class="<?php echo $page === 'Contact' ? 'active' : ''; ?>" href="/capstone/contact.php">Contact</a></li>
        </ul>
      </nav>
      <div class="actions" style="display:flex;align-items:center;gap:24px;">
        <?php if (!empty($_SESSION['user'])): ?>
          <!-- Notifications -->
          <div class="notif" style="position:relative;">
            <?php
            $roleStr = strtolower((string)($_SESSION['user']['role'] ?? ''));
            $uriStr = strtolower((string)($_SERVER['REQUEST_URI'] ?? ''));
            if (strpos($roleStr, 'nurse') !== false) {
              $notifHref = '/capstone/templates/nurse/nurse_notification.php';
            } elseif (strpos($roleStr, 'pharm') !== false) {
              $notifHref = '/capstone/templates/pharmacy/pharmacy_notification.php';
            } elseif (strpos($roleStr, 'labor') !== false) {
              $notifHref = '/capstone/templates/laboratory/laboratory_notification.php';
            } elseif (strpos($roleStr, 'admin') !== false) {
              $notifHref = '/capstone/templates/admin/admin_notification.php';
            } elseif (strpos($roleStr, 'super') !== false) {
              $notifHref = '/capstone/templates/supervisor/supervisor_notifcation.php';
            } elseif (strpos($roleStr, 'doctor') !== false) {
              $notifHref = '/capstone/templates/doctor/doctor_notifcation.php';
            } else {
              if (strpos($uriStr, '/supervisor/') !== false) {
                $notifHref = '/capstone/templates/supervisor/supervisor_notifcation.php';
              } elseif (strpos($uriStr, '/pharmacy/') !== false) {
                $notifHref = '/capstone/templates/pharmacy/pharmacy_notification.php';
              } elseif (strpos($uriStr, '/nurse/') !== false) {
                $notifHref = '/capstone/templates/nurse/nurse_notification.php';
              } elseif (strpos($uriStr, '/laboratory/') !== false) {
                $notifHref = '/capstone/templates/laboratory/laboratory_notification.php';
              } elseif (strpos($uriStr, '/admin/') !== false) {
                $notifHref = '/capstone/templates/admin/admin_notification.php';
              } else {
                $notifHref = '/capstone/templates/doctor/doctor_notifcation.php';
              }
            }
            ?>
            <a href="<?php echo $notifHref; ?>" class="btn btn-ghost" title="Notifications" style="position:relative;display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border:none;border-radius:12px;background:transparent;transition:all 0.2s ease;">
              <img src="/capstone/assets/img/notification.png" alt="Notifications" style="width:36px;height:36px;object-fit:contain;display:block;" />
              <span id="notifBadge" style="display:none;position:absolute;top:8px;right:8px;background:#ef4444;color:#fff;border-radius:50%;padding:2px 4px;font-size:.65rem;line-height:1;height:18px;min-width:18px;text-align:center;font-weight:700;border:2px solid #fff;">0</span>
            </a>
          </div>

          <!-- Profile Section -->
          <div class="profile" style="position:relative;">
            <div style="display:flex;align-items:center;gap:16px;">
              <!-- Profile Avatar -->
              <div style="position:relative;">
                <a href="/capstone/templates/doctor/doctor_profile.php" class="btn btn-ghost" style="display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border:none;border-radius:50%;background:#ffffff;transition:all 0.3s ease;overflow:hidden;box-shadow:0 0 0 3px #ffffff, 0 0 0 5px #0a5d39, 0 8px 18px rgba(0,0,0,0.10);padding:0;line-height:0;">
                  <?php $hdrAvatar = trim((string)($_SESSION['user']['avatar_uri'] ?? '')); ?>
                  <?php if ($hdrAvatar !== ''): ?>
                    <?php $hdrV = isset($_SESSION['user']['avatar_last_edited']) && $_SESSION['user']['avatar_last_edited'] !== '' ? ('?v=' . urlencode((string)$_SESSION['user']['avatar_last_edited'])) : ''; ?>
                    <img src="<?php echo htmlspecialchars($hdrAvatar . $hdrV); ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;display:block;filter:none;border-radius:50%;border:1px solid #e5e7eb;" />
                  <?php else: ?>
                    <img src="/capstone/assets/img/user.png" alt="Profile" style="width:100%;height:100%;object-fit:cover;display:block;border-radius:50%;filter:none;border:1px solid #e5e7eb;" />
                  <?php endif; ?>
                </a>
              </div>

              <!-- User Info -->
              <div style="text-align:left;min-width:0;">
                <div style="font-weight:700;color:#0f172a;font-size:0.95rem;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px;">
                  <?php echo htmlspecialchars($_SESSION['user']['full_name'] ?? 'User'); ?>
                </div>
                <div style="color:#64748b;font-size:0.8rem;line-height:1.2;text-transform:capitalize;white-space:nowrap;font-weight:500;">
                  <?php echo htmlspecialchars($_SESSION['user']['role'] ?? 'user'); ?>
                </div>
              </div>

              <!-- Dropdown Button -->
              <button class="btn btn-ghost" id="profileDropdownBtn" style="display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border:none;border-radius:10px;background:transparent;transition:all 0.2s ease;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#64748b;">
                  <path d="M6 9l6 6 6-6" />
                </svg>
              </button>
            </div>

            <!-- Dropdown Menu -->
            <div id="profileDropdown" style="position:absolute;right:0;top:100%;background:#fff;border:2px solid #e5e7eb;border-radius:20px;box-shadow:0 25px 50px rgba(0,0,0,0.2);min-width:220px;display:none;z-index:20;margin-top:16px;overflow:hidden;backdrop-filter:blur(20px);">
              <div style="padding:12px 0;">
                <div style="padding:16px 20px;border-bottom:2px solid #f1f5f9;background:linear-gradient(135deg,#f8fafc,#f1f5f9);">
                  <div style="font-size:0.85rem;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Account Menu</div>
                </div>
                <a href="/capstone/templates/doctor/doctor_profile.php" style="display:flex;align-items:center;gap:16px;padding:16px 20px;color:#0f172a;text-decoration:none;transition:all 0.2s ease;font-weight:600;font-size:0.95rem;border-bottom:1px solid #f1f5f9;">
                  <div style="width:18px;height:18px;display:flex;align-items:center;justify-content:center;background:transparent;border-radius:0;">
                    <img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;border-radius:0!important;" />
                  </div>
                  <span>Profile Settings</span>
                </a>
                <a href="/capstone/logout.php" style="display:flex;align-items:center;gap:16px;padding:16px 20px;color:#0f172a;text-decoration:none;transition:all 0.2s ease;font-weight:600;font-size:0.95rem;">
                  <div style="width:18px;height:18px;display:flex;align-items:center;justify-content:center;background:transparent;border-radius:0;">
                    <img src="/capstone/assets/img/logout.png" alt="Sign Out" style="width:18px;height:18px;object-fit:contain;border-radius:0!important;filter: invert(23%) sepia(95%) saturate(7428%) hue-rotate(357deg) brightness(93%) contrast(112%);" />
                  </div>
                  <span>Sign Out</span>
                </a>
              </div>
            </div>
          </div>
        <?php else: ?>
          <a class="btn btn-outline" href="/capstone/login.php" style="padding:10px 20px;border-radius:10px;font-weight:600;">Login</a>
          <a class="btn btn-primary" href="/capstone/register.php" style="padding:10px 20px;border-radius:10px;font-weight:600;background:linear-gradient(135deg,#0a5d39,#10b981);">Register</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <script>
    (function() {
      // Welcome back toast
      <?php if (!empty($_SESSION['flash_welcome'])): ?>
        var toast = document.createElement('div');
        toast.textContent = <?php echo json_encode((string)$_SESSION['flash_welcome']); ?>;
        toast.setAttribute('role', 'status');
        toast.style.position = 'fixed';
        toast.style.top = '20px';
        toast.style.right = '20px';
        toast.style.zIndex = '9999';
        toast.style.background = '#0a5d39';
        toast.style.color = '#fff';
        toast.style.padding = '12px 16px';
        toast.style.borderRadius = '12px';
        toast.style.boxShadow = '0 10px 30px rgba(0,0,0,0.2)';
        toast.style.fontWeight = '700';
        toast.style.opacity = '0';
        toast.style.transition = 'opacity .25s ease, transform .25s ease';
        toast.style.transform = 'translateY(-8px)';
        document.addEventListener('DOMContentLoaded', function() {
          document.body.appendChild(toast);
          requestAnimationFrame(function() {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
          });
          setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-8px)';
            setTimeout(function() {
              if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
              }
            }, 300);
          }, 2800);
        });
      <?php $_SESSION['flash_welcome'] = null;
        unset($_SESSION['flash_welcome']);
      endif; ?>
      // Simple notification badge update (no dropdown functionality)
      var nBadge = document.getElementById('notifBadge');
      var doctorId = <?php echo isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0; ?>;

      function detectRole() {
        var p = (location.pathname || '').toLowerCase();
        if (p.indexOf('/doctor/') !== -1) return 'doctor';
        if (p.indexOf('/nurse/') !== -1) return 'nurse';
        if (p.indexOf('/pharmacy/') !== -1) return 'pharmacy';
        if (p.indexOf('/supervisor/') !== -1) return 'supervisor';
        return '';
      }

      async function updateNotificationBadge() {
        try {
          var role = detectRole();
          if (!role || !nBadge) return;

          var res;
          if (role === 'doctor') {
            res = await fetch('https://backend-careflow.vercel.app/api/notifications', {
              method: 'GET',
              cache: 'no-store',
              headers: {
                'X-User-Id': String(doctorId || ''),
                'Content-Type': 'application/json'
              }
            });
          } else {
            var endpoint = role === 'supervisor' ?
              '/capstone/notifications/supervisor.php' :
              '/capstone/notifications/pharmacy.php?role=' + encodeURIComponent(role) + (role === 'doctor' && doctorId ? ('&doctor_id=' + encodeURIComponent(doctorId)) : '');
            res = await fetch(endpoint);
          }
          if (!res.ok) return;

          var data = await res.json();
          var notifications = [];
          if (Array.isArray(data)) {
            notifications = data;
          } else if (data && Array.isArray(data.items)) {
            notifications = data.items;
          } else if (data && Array.isArray(data.notifications)) {
            notifications = data.notifications;
          }
          var unreadCount = notifications.filter(function(n) {
            var isRead = (n && (n.read != null ? n.read : (n.is_read != null ? n.is_read : n.isRead))) != null ? (n.read != null ? n.read : (n.is_read != null ? n.is_read : n.isRead)) : false;
            return !isRead;
          }).length;

          if (unreadCount > 0) {
            nBadge.textContent = unreadCount;
            nBadge.style.display = 'inline-block';
          } else {
            nBadge.style.display = 'none';
          }
        } catch (err) {
          // Silent fail
        }
      }

      // Poll due reminders to push notifications for doctors
      async function pollDueReminders() {
        try {
          var role = detectRole();
          if (role !== 'doctor') return;
          // This triggers moving due reminders into the doctor-specific notifications
          var url = '/capstone/appointments/reminders.php?role=doctor&due=1' + (doctorId ? ('&doctor_id=' + encodeURIComponent(doctorId)) : '');
          await fetch(url, {
            cache: 'no-store'
          });
        } catch (e) {
          /* ignore */
        }
      }

      // Profile dropdown functionality
      var profileBtn = document.getElementById('profileDropdownBtn');
      var profileDropdown = document.getElementById('profileDropdown');

      if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          profileDropdown.style.display = profileDropdown.style.display === 'block' ? 'none' : 'block';
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
          if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
            profileDropdown.style.display = 'none';
          }
        });

        // Add hover effect to logout link
        var logoutLink = profileDropdown.querySelector('a');
        if (logoutLink) {
          logoutLink.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f1f5f9';
          });
          logoutLink.addEventListener('mouseleave', function() {
            this.style.backgroundColor = 'transparent';
          });
        }

        // Add hover effects to notification button
        var notifBtn = document.querySelector('.notif a');
        if (notifBtn) {
          notifBtn.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f1f5f9';
            this.style.transform = 'scale(1.05)';
          });
          notifBtn.addEventListener('mouseleave', function() {
            this.style.backgroundColor = 'transparent';
            this.style.transform = 'scale(1)';
          });
        }

        // Add hover effects to profile section (optional hover highlight)
        var profileSection = document.querySelector('.profile > div');
        if (profileSection) {
          profileSection.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8fafc';
          });
          profileSection.addEventListener('mouseleave', function() {
            this.style.backgroundColor = 'transparent';
          });
        }

        // No hover style changes for profile icon to preserve border/rings

        // Add hover effects to dropdown button
        if (profileBtn) {
          profileBtn.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f1f5f9';
            this.style.transform = 'scale(1.05)';
          });
          profileBtn.addEventListener('mouseleave', function() {
            this.style.backgroundColor = 'transparent';
            this.style.transform = 'scale(1)';
          });
        }

        // Add hover effects to dropdown menu items
        var dropdownLinks = document.querySelectorAll('#profileDropdown a');
        dropdownLinks.forEach(function(link) {
          link.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8fafc';
            this.style.transform = 'translateX(4px)';
          });
          link.addEventListener('mouseleave', function() {
            this.style.backgroundColor = 'transparent';
            this.style.transform = 'translateX(0)';
          });
        });
      }

      // Update badge on load and more frequently (every 15s)
      updateNotificationBadge();
      setInterval(updateNotificationBadge, 15000);
      // Also poll reminders periodically on doctor pages (every 15s)
      pollDueReminders();
      setInterval(pollDueReminders, 15000);
    })();
  </script>
  <main>