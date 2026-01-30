<?php $page = 'Doctor Notifications';
include __DIR__ . '/../../includes/header.php'; ?>

<div class="layout-sidebar full-bleed" style="padding:24px 20px;">
  <aside class="sidebar">
    <div class="sidepanel">
      <nav>
        <ul class="nav-list">
          <li><a href="/capstone/templates/doctor/doctor_dashboard.php"><img src="/capstone/assets/img/home (2).png" alt="Home" style="width:18px;height:18px;object-fit:contain;"> Home</a></li>
          <li><a href="/capstone/templates/doctor/doctor_profile.php"><img src="/capstone/assets/img/user.png" alt="Profile" style="width:18px;height:18px;object-fit:contain;"> Profile</a></li>
          <li><a href="/capstone/templates/doctor/doctor_appointment.php"><img src="/capstone/assets/img/appointment.png" alt="Appointment" style="width:18px;height:18px;object-fit:contain;"> Appointment</a></li>
          <li><a href="/capstone/templates/doctor/doctor_prescription.php"><img src="/capstone/assets/img/prescription.png" alt="Prescription" style="width:18px;height:18px;object-fit:contain;"> Prescription</a></li>
          <li><a href="/capstone/templates/doctor/doctor_records.php"><img src="/capstone/assets/img/medical-file.png" alt="Patient Record" style="width:18px;height:18px;object-fit:contain;"> Patient Record</a></li>
          <li><a href="/capstone/templates/doctor/doctor_reports.php"><img src="/capstone/assets/img/bar-chart.png" alt="Reports" style="width:18px;height:18px;object-fit:contain;"> Reports</a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <div class="content" style="max-width:1200px;margin:0 auto;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
      <h3 style="margin:0;">Notifications</h3>
      <div style="display:flex;gap:8px;align-items:center;">
        <button class="btn btn-outline" id="nfRefresh" title="Refresh">Refresh</button>
        <button class="btn btn-outline" id="nfClearAll" title="Clear all">Clear all</button>
      </div>
    </div>

    <div id="nfEmpty" class="muted" style="display:none;padding:12px 0;">You're all caught up. No notifications.</div>

    <div id="nfList" style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
      <!-- Items injected by JS -->
    </div>
  </div>
</div>

<!-- Notification Detail Modal -->
<div id="notificationModal" style="display:none;position:fixed;inset:0;z-index:1000;overflow-y:auto;">
  <div data-backdrop style="position:absolute;inset:0;background:rgba(0,0,0,0.5);"></div>
  <div role="dialog" aria-modal="true" style="position:relative;max-width:600px;margin:5vh auto;background:#fff;border-radius:20px;box-shadow:0 25px 50px rgba(0,0,0,0.25);overflow:hidden;">
    <!-- Modal Header -->
    <div style="background:linear-gradient(135deg,#0a5d39,#10b981);color:#fff;padding:24px 28px;border-radius:20px 20px 0 0;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h3 style="margin:0;font-size:1.3rem;font-weight:700;">Notification Details</h3>
          <p style="margin:4px 0 0;opacity:0.9;font-size:0.9rem;">View full notification information</p>
        </div>
        <button type="button" id="closeNotificationModal" style="background:rgba(255,255,255,0.2);border:none;color:#fff;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.3)'" onmouseout="this.style.backgroundColor='rgba(255,255,255,0.2)'">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6L6 18M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Modal Content -->
    <div style="padding:28px;">
      <div id="notificationContent">
        <!-- Content will be populated by JavaScript -->
      </div>
    </div>

    <!-- Modal Footer -->
    <div style="display:flex;justify-content:flex-end;gap:12px;padding:20px 28px;border-top:1px solid #e5e7eb;background:linear-gradient(135deg,#f8fafc,#f1f5f9);">
      <button type="button" class="btn btn-outline" id="closeNotificationModalBtn" style="padding:10px 20px;border-radius:10px;font-weight:600;">Close</button>
      <button class="btn btn-primary" id="deleteNotificationBtn" style="padding:10px 20px;border-radius:10px;font-weight:600;background:linear-gradient(135deg,#ef4444,#dc2626);">Delete</button>
    </div>
  </div>
</div>

<script>
  (function() {
    // Backend-powered notifications
    var notifications = [];

    var list = document.getElementById('nfList');
    var empty = document.getElementById('nfEmpty');
    var btnRefresh = document.getElementById('nfRefresh');
    var btnClearAll = document.getElementById('nfClearAll');

    // Modal elements
    var modal = document.getElementById('notificationModal');
    var modalContent = document.getElementById('notificationContent');
    var closeModalBtn = document.getElementById('closeNotificationModal');
    var closeModalBtn2 = document.getElementById('closeNotificationModalBtn');
    var deleteModalBtn = document.getElementById('deleteNotificationBtn');
    var backdrop = modal ? modal.querySelector('[data-backdrop]') : null;
    var currentNotificationId = null;

    var doctorId = <?php echo isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0; ?>;
    var doctorName = <?php echo json_encode((string)($_SESSION['user']['full_name'] ?? 'Doctor')); ?>;

    function getAcceptedIds() {
      try {
        var raw = localStorage.getItem('doctorAcceptedAppointmentNotifIds');
        var arr = JSON.parse(raw || '[]');
        return Array.isArray(arr) ? arr.map(String) : [];
      } catch (e) {
        return [];
      }
    }

    function addAcceptedId(id) {
      try {
        var ids = getAcceptedIds();
        var sid = String(id || '');
        if (!sid) return;
        if (ids.indexOf(sid) === -1) ids.push(sid);
        localStorage.setItem('doctorAcceptedAppointmentNotifIds', JSON.stringify(ids));
      } catch (e) {}
    }

    function isAppointmentRequest(n) {
      var t = String((n && n.title) || '').toLowerCase();
      var b = String((n && n.body) || '').toLowerCase();
      if (t.indexOf('appointment') !== -1 && t.indexOf('request') !== -1) return true;
      if (b.indexOf('appointment request') !== -1) return true;
      if (b.indexOf('requested an appointment') !== -1) return true;
      return false;
    }

    function parseAppointmentDetails(text) {
      var out = {
        patient: '',
        date: '',
        time: '',
        notes: ''
      };
      var s = String(text || '');

      var mPatient = s.match(/patient\s*:\s*([^|\n]+)/i);
      if (mPatient && mPatient[1]) out.patient = mPatient[1].trim();

      if (!out.patient) {
        var mFrom = s.match(/\bappointment\s+request\b.*?\bfrom\s+(.+?)\s+for\b/i);
        if (mFrom && mFrom[1]) out.patient = mFrom[1].trim();
      }

      if (!out.patient) {
        var mRem = s.match(/reminder\s*:\s*(.*?)\s+appointment\s+on\s+/i);
        if (mRem && mRem[1]) out.patient = mRem[1].trim();
      }

      var mDate = s.match(/(\d{4}-\d{2}-\d{2})/);
      if (mDate && mDate[1]) out.date = mDate[1];

      var mTime12 = s.match(/\b(\d{1,2}:\d{2})(?::\d{2})?\s*(AM|PM)\b/i);
      if (mTime12 && mTime12[1]) {
        var hm = mTime12[1];
        var ampm = String(mTime12[2] || '').toUpperCase();
        var parts = hm.split(':');
        var hh = parseInt(parts[0], 10);
        var mm = parts[1];
        if (!isNaN(hh)) {
          if (ampm === 'PM' && hh < 12) hh += 12;
          if (ampm === 'AM' && hh === 12) hh = 0;
          out.time = String(hh).padStart(2, '0') + ':' + mm + ':00';
        }
      } else {
        var mTime = s.match(/\b(\d{1,2}:\d{2}(?::\d{2})?)\b/);
        if (mTime && mTime[1]) {
          out.time = mTime[1];
          if (out.time.length === 5) out.time = out.time + ':00';
        }
      }

      var mNotes = s.match(/notes\s*:\s*([^\n]+)/i);
      if (mNotes && mNotes[1]) out.notes = mNotes[1].trim();

      if (!out.notes) {
        var mReason = s.match(/reason\s*:\s*([^\n]+)/i);
        if (mReason && mReason[1]) out.notes = mReason[1].trim();
      }

      return out;
    }

    async function load() {
      try {
        var url = 'https://backend-careflow.vercel.app/api/notifications';
        var res = await fetch(url, {
          method: 'GET',
          cache: 'no-store',
          headers: {
            'X-User-Id': String(doctorId || ''),
            'Content-Type': 'application/json'
          }
        });
        if (!res.ok) throw new Error('Failed to load notifications');
        var data = await res.json();
        var prevScroll = list ? list.scrollTop : 0;

        var rawItems = [];
        if (Array.isArray(data)) {
          rawItems = data;
        } else if (data && Array.isArray(data.items)) {
          rawItems = data.items;
        } else if (data && Array.isArray(data.notifications)) {
          rawItems = data.notifications;
        }

        var acceptedIds = getAcceptedIds();
        if (acceptedIds.length) {
          rawItems = rawItems.filter(function(n) {
            var idVal = (n && (n.id != null ? n.id : n.notification_id)) != null ? (n.id != null ? n.id : n.notification_id) : '';
            return acceptedIds.indexOf(String(idVal)) === -1;
          });
        }

        notifications = rawItems.map(function(n, idx) {
          var idVal = (n && (n.id != null ? n.id : n.notification_id)) != null ? (n.id != null ? n.id : n.notification_id) : (idx + 1);
          var titleVal = (n && (n.title != null ? n.title : n.subject)) != null ? (n.title != null ? n.title : n.subject) : 'Notification';
          var bodyVal = (n && (n.body != null ? n.body : (n.message != null ? n.message : n.description))) != null ? (n.body != null ? n.body : (n.message != null ? n.message : n.description)) : '';
          var timeVal = (n && (n.time != null ? n.time : (n.created_at != null ? n.created_at : n.createdAt))) != null ? (n.time != null ? n.time : (n.created_at != null ? n.created_at : n.createdAt)) : '';
          var readVal = (n && (n.read != null ? n.read : (n.is_read != null ? n.is_read : n.isRead))) != null ? (n.read != null ? n.read : (n.is_read != null ? n.is_read : n.isRead)) : false;
          var patientNameVal = (n && (
            n.patient_name != null ? n.patient_name :
            (n.patient != null ? n.patient :
              (n.full_name != null ? n.full_name :
                (n.sender_name != null ? n.sender_name :
                  (n.sender != null ? n.sender :
                    (n.patientName != null ? n.patientName : null)
                  )
                )
              )
            )
          ));
          return {
            id: String(idVal),
            title: String(titleVal || ''),
            body: String(bodyVal || ''),
            time: String(timeVal || ''),
            read: !!readVal,
            patientName: patientNameVal != null ? String(patientNameVal) : ''
          };
        });
        render();
        if (list) list.scrollTop = prevScroll;
      } catch (err) {
        list.innerHTML = '<div class="muted" style="padding:12px;">' + escapeHtml(err.message) + '</div>';
        empty.style.display = 'none';
      }
    }

    function render() {
      list.innerHTML = '';
      if (notifications.length === 0) {
        empty.style.display = '';
        return;
      } else {
        empty.style.display = 'none';
      }
      var items = notifications.slice().sort(function(a, b) {
        var ta = String(b.time || '');
        var tb = String(a.time || '');
        var cmp = ta.localeCompare(tb);
        if (cmp !== 0) return cmp;
        return String(b.id || '').localeCompare(String(a.id || ''));
      });
      items.forEach(function(n) {
        var row = document.createElement('div');
        row.className = 'nf-item';
        row.setAttribute('data-id', n.id);
        row.setAttribute('style', 'display:flex;gap:12px;align-items:flex-start;padding:12px 14px;border-bottom:1px solid #e5e7eb;cursor:pointer;transition:background-color 0.2s ease;' + (n.read ? 'background:#fafafa;' : ''));
        row.innerHTML = '\n        <div style="width:32px;min-width:32px;height:32px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;">🔔</div>\n        <div style="flex:1;min-width:0;">\n          <div style="display:flex;align-items:center;gap:8px;">\n            <div style="font-weight:600;">' + escapeHtml(n.title) + '</div>\n            ' + (n.read ? '' : '<span style="background:#ef4444;color:#fff;border-radius:999px;padding:1px 6px;font-size:.72rem;">NEW</span>') + '\n            <div class="muted-small" style="margin-left:auto;">' + escapeHtml(n.time) + '</div>\n          </div>\n          <div class="muted" style="margin-top:2px;">' + escapeHtml(n.body) + '</div>\n        </div>\n        <div style="display:flex;align-items:center;justify-content:center;width:40px;height:40px;">\n          <button class="nf-delete" data-action="delete" style="width:32px;height:32px;border:none;border-radius:8px;background:#fef2f2;color:#ef4444;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.2s ease;" onmouseover="this.style.backgroundColor=\'#fee2e2\'" onmouseout="this.style.backgroundColor=\'#fef2f2\'" title="Delete notification">\n            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">\n              <path d="M3 6h18"/>\n              <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>\n              <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>\n            </svg>\n          </button>\n        </div>';
        list.appendChild(row);
      });
    }

    function escapeHtml(s) {
      return (s || '').replace(/[&<>"]/g, function(c) {
        return {
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;'
        } [c];
      });
    }

    // Modal functions
    function openModal(notificationId) {
      var notification = notifications.find(function(n) {
        return String(n.id) === String(notificationId);
      });
      if (!notification) return;

      currentNotificationId = notificationId;

      // Mark as read when opened
      notification.read = true;

      var apptControls = '';
      if (isAppointmentRequest(notification)) {
        var ap = parseAppointmentDetails(notification.body);
        var prefillPatient = (notification.patientName || ap.patient || '').trim();
        apptControls = '<div style="padding:20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">' +
          '<h4 style="margin:0 0 12px;color:#0f172a;font-size:1rem;font-weight:700;">Appointment Request Details</h4>' +
          '<div style="display:grid;gap:10px;">' +
          '<div><div style="color:#64748b;font-size:0.85rem;margin-bottom:4px;">Patient</div><input id="apptReqPatient" type="text" value="' + escapeHtml(prefillPatient) + '" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;" /></div>' +
          '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">' +
          '<div><div style="color:#64748b;font-size:0.85rem;margin-bottom:4px;">Date</div><input id="apptReqDate" type="date" value="' + escapeHtml(ap.date) + '" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;" /></div>' +
          '<div><div style="color:#64748b;font-size:0.85rem;margin-bottom:4px;">Time</div><input id="apptReqTime" type="time" value="' + escapeHtml((ap.time || '').substring(0, 5)) + '" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;" /></div>' +
          '</div>' +
          '<div><div style="color:#64748b;font-size:0.85rem;margin-bottom:4px;">Notes (optional)</div><textarea id="apptReqNotes" rows="2" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;resize:vertical;">' + escapeHtml(ap.notes) + '</textarea></div>' +
          '<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:6px;">' +
          '<button type="button" class="btn btn-primary" id="acceptAppointmentBtn" style="padding:10px 16px;border-radius:10px;font-weight:700;background:linear-gradient(135deg,#0a5d39,#10b981);">Accept</button>' +
          '</div>' +
          '</div>' +
          '</div>';
      }

      // Populate modal content
      modalContent.innerHTML = '<div style="display:grid;gap:20px;">' +
        '<div style="display:flex;align-items:center;gap:12px;padding:16px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;">' +
        '<div style="width:40px;height:40px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;font-size:20px;">🔔</div>' +
        '<div style="flex:1;">' +
        '<div style="font-weight:700;color:#0f172a;font-size:1.1rem;margin-bottom:4px;">' + escapeHtml(notification.title) + '</div>' +
        '<div style="color:#64748b;font-size:0.9rem;">' + escapeHtml(notification.time) + '</div>' +
        '</div>' +
        (notification.read ? '' : '<span style="background:#ef4444;color:#fff;border-radius:999px;padding:4px 8px;font-size:0.75rem;font-weight:600;">NEW</span>') +
        '</div>' +
        '<div style="padding:20px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;">' +
        '<h4 style="margin:0 0 12px;color:#0f172a;font-size:1rem;font-weight:600;">Message Details</h4>' +
        '<div style="color:#374151;line-height:1.6;font-size:0.95rem;">' + escapeHtml(notification.body) + '</div>' +
        '</div>' +

        apptControls +

        '</div>';

      modal.style.display = 'block';
      document.body.style.overflow = 'hidden';
      render(); // Re-render to update read status
    }

    function closeModal() {
      modal.style.display = 'none';
      document.body.style.overflow = '';
      currentNotificationId = null;
    }

    // Clear all
    btnClearAll.addEventListener('click', async function() {
      try {
        notifications = [];
        render();
      } catch (err) {
        alert('Error: ' + err.message);
      }
    });

    if (btnRefresh) {
      btnRefresh.addEventListener('click', function() {
        load();
      });
    }

    // Row actions
    list.addEventListener('click', function(e) {
      var btn = e.target.closest('button');
      var row = e.target.closest('.nf-item');
      if (!row) return;

      var id = String(row.getAttribute('data-id') || '');
      var idx = notifications.findIndex(function(n) {
        return String(n.id) === id;
      });
      if (idx === -1) return;

      // Handle delete button click
      if (btn && btn.getAttribute('data-action') === 'delete') {
        e.stopPropagation(); // Prevent row click
        if (!confirm('Delete this notification?')) return;
        notifications.splice(idx, 1);
        render();
        return;
      }

      // Handle row click (open modal)
      if (!btn || btn.getAttribute('data-action') !== 'delete') {
        openModal(id);
      }
    });

    // Add hover effects to notification rows
    list.addEventListener('mouseenter', function(e) {
      var row = e.target.closest('.nf-item');
      if (row) row.style.backgroundColor = '#f8fafc';
    });

    list.addEventListener('mouseleave', function(e) {
      var row = e.target.closest('.nf-item');
      if (row) {
        var id = String(row.getAttribute('data-id') || '');
        var notification = notifications.find(function(n) {
          return String(n.id) === id;
        });
        row.style.backgroundColor = notification && notification.read ? '#fafafa' : '';
      }
    });

    // Modal event listeners
    if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
    if (closeModalBtn2) closeModalBtn2.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', closeModal);

    async function acceptAppointmentFromModal() {
      if (!currentNotificationId) return;
      var notification = notifications.find(function(n) {
        return String(n.id) === String(currentNotificationId);
      });
      if (!notification) return;

      var patient = (document.getElementById('apptReqPatient') || {}).value || '';
      var date = (document.getElementById('apptReqDate') || {}).value || '';
      var time = (document.getElementById('apptReqTime') || {}).value || '';
      var notes = (document.getElementById('apptReqNotes') || {}).value || '';

      patient = String(patient).trim();
      date = String(date).trim();
      time = String(time).trim();
      notes = String(notes).trim();

      if (!patient || !date || !time) {
        alert('Please fill Patient, Date, and Time before accepting.');
        return;
      }

      if (!confirm('Accept this appointment request?')) return;

      var btn = document.getElementById('acceptAppointmentBtn');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Accepting...';
        btn.style.opacity = '0.7';
      }

      try {
        var res = await fetch('/capstone/appointments/create.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            patient: patient,
            date: date,
            time: time,
            notes: notes,
            done: false,
            createdByName: doctorName
          })
        });
        if (!res.ok) {
          var t = await res.text().catch(function() {
            return '';
          });
          throw new Error(t || 'Failed to create appointment');
        }

        try {
          await fetch('/capstone/api/notifications.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              patient_name: patient,
              title: 'Appointment accepted',
              message: 'Your appointment request has been accepted by ' + doctorName + ' on ' + date + ' ' + time + '.'
            })
          });
        } catch (e) {}

        addAcceptedId(currentNotificationId);
        var idx = notifications.findIndex(function(n) {
          return String(n.id) === String(currentNotificationId);
        });
        if (idx !== -1) {
          notifications.splice(idx, 1);
        }
        render();
        closeModal();

        alert('Appointment accepted. It should now appear in your Appointment List.');
      } catch (err) {
        alert('Error: ' + err.message);
        if (btn) {
          btn.disabled = false;
          btn.textContent = 'Accept';
          btn.style.opacity = '';
        }
      }
    }

    if (modalContent) {
      modalContent.addEventListener('click', function(e) {
        var btn = e.target.closest('#acceptAppointmentBtn');
        if (!btn) return;
        e.preventDefault();
        acceptAppointmentFromModal();
      });
    }

    if (deleteModalBtn) {
      deleteModalBtn.addEventListener('click', function() {
        if (currentNotificationId) {
          var idx = notifications.findIndex(function(n) {
            return String(n.id) === String(currentNotificationId);
          });
          if (idx !== -1) {
            if (!confirm('Delete this notification?')) return;
            notifications.splice(idx, 1);
            render();
            closeModal();
          }
        }
      });
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && modal && modal.style.display === 'block') {
        closeModal();
      }
    });

    load();
    // Auto-refresh every 10 seconds
    setInterval(load, 10000);
  })();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>