<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();

$tickets = [];
$res = $db->query("SELECT ticket_number, violation_code, vehicle_plate, issued_by, status FROM tickets ORDER BY date_issued DESC LIMIT 20");
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $tickets[] = $row;
  }
}
?>

<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Violation Logging & Ticket Processing</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">On-site and automated violation recording, ticket generation, evidence attachment, and initial case creation.</p>

  <div class="p-4 border rounded-lg dark:border-slate-700 mb-6">
    <h2 class="text-lg font-semibold mb-3">Create Ticket</h2>
    <form id="create-ticket-form" class="grid grid-cols-1 md:grid-cols-3 gap-4" enctype="multipart/form-data">
      <select id="violation-select" name="violation_code" required class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
        <option value="">Violation code</option>
      </select>
      <div class="relative">
        <input id="ticket-plate-input" name="plate_number" required class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 w-full" placeholder="Vehicle plate">
        <div id="ticket-plate-suggestions" class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow text-sm max-h-48 overflow-y-auto hidden"></div>
      </div>
      <input id="ticket-driver-input" name="driver_name" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Driver/Operator name">
      <input name="location" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Location">
      <input type="datetime-local" name="issued_at" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
      <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm mb-1">Photo</label>
          <input type="file" name="photo" accept="image/*" class="w-full text-sm">
        </div>
        <div>
          <label class="block text-sm mb-1">Video</label>
          <input type="file" name="video" accept="video/*" class="w-full text-sm">
        </div>
        <div>
          <label class="block text-sm mb-1">Notes</label>
          <input name="notes" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Additional info">
        </div>
      </div>
      <div class="md:col-span-3 flex items-center justify-between text-sm text-slate-600 dark:text-slate-400">
        <span id="violation-summary" class="mr-2"></span>
        <span id="violation-fine-preview" class="font-semibold"></span>
      </div>
      <button type="submit" class="md:col-span-3 px-4 py-2 bg-[#4CAF50] text-white rounded">Generate Ticket</button>
    </form>
    <div id="ticket-message" class="mt-3 text-sm"></div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left text-slate-600 dark:text-slate-300">
          <th class="py-2 px-3">Ticket #</th>
          <th class="py-2 px-3">Violation</th>
          <th class="py-2 px-3">Plate</th>
          <th class="py-2 px-3">Issued By</th>
          <th class="py-2 px-3">Status</th>
          <th class="py-2 px-3">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y dark:divide-slate-700">
        <?php if (empty($tickets)): ?>
          <tr>
            <td colspan="6" class="py-4 px-3 text-center text-slate-500">No tickets have been logged yet.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($tickets as $t): ?>
            <?php
              $status = $t['status'] ?? 'Pending';
              $badgeClass = 'bg-amber-100 text-amber-700';
              if ($status === 'Validated') $badgeClass = 'bg-blue-100 text-blue-700';
              elseif ($status === 'Settled') $badgeClass = 'bg-emerald-100 text-emerald-700';
              elseif ($status === 'Escalated') $badgeClass = 'bg-rose-100 text-rose-700';
            ?>
            <tr>
              <td class="py-2 px-3"><?php echo htmlspecialchars($t['ticket_number']); ?></td>
              <td class="py-2 px-3"><?php echo htmlspecialchars($t['violation_code']); ?></td>
              <td class="py-2 px-3"><?php echo htmlspecialchars($t['vehicle_plate']); ?></td>
              <td class="py-2 px-3"><?php echo htmlspecialchars($t['issued_by'] ?: '—'); ?></td>
              <td class="py-2 px-3">
                <span class="px-2 py-1 rounded text-xs font-medium <?php echo $badgeClass; ?>">
                  <?php echo htmlspecialchars($status); ?>
                </span>
              </td>
              <td class="py-2 px-3 space-x-2">
                <a href="?page=module3/submodule3&q=<?php echo urlencode($t['ticket_number']); ?>" class="px-2 py-1 border rounded text-xs">Open</a>
                <button type="button"
                  class="px-2 py-1 border rounded text-xs"
                  onclick="TMMViewEvidence && TMMViewEvidence.open('<?php echo htmlspecialchars($t['ticket_number']); ?>');">
                  View Evidence
                </button>
                <button type="button"
                  class="px-2 py-1 border rounded text-xs"
                  onclick="TMMUploadEvidence && TMMUploadEvidence.open('<?php echo htmlspecialchars($t['ticket_number']); ?>');">
                  Upload Evidence
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('create-ticket-form');
  var msg = document.getElementById('ticket-message');
  var violationSelect = document.getElementById('violation-select');
  var violationSummary = document.getElementById('violation-summary');
  var violationFinePreview = document.getElementById('violation-fine-preview');
  var plateInput = document.getElementById('ticket-plate-input');
  var driverInput = document.getElementById('ticket-driver-input');
  var suggestionsBox = document.getElementById('ticket-plate-suggestions');
  var plateDebounceId = null;
  var violationMap = {};
  var TMMUploadEvidence = (function () {
    var dlg = null;
    function open(ticketNo) {
      if (!dlg) {
        dlg = document.createElement('div');
        dlg.className = 'fixed inset-0 bg-black/30 flex items-center justify-center z-50';
        dlg.innerHTML = '<div class="bg-white dark:bg-slate-900 rounded-lg p-4 w-[95%] max-w-md border dark:border-slate-700">' +
          '<div class="text-lg font-semibold mb-2">Upload Evidence</div>' +
          '<form id="tmm-ev-form" class="space-y-3">' +
          '  <div class="text-sm text-slate-600 dark:text-slate-300">Attach additional photo/video/pdf evidence to the selected ticket.</div>' +
          '  <input type="hidden" name="ticket_number" id="tmm-ev-ticket">' +
          '  <input type="file" name="evidence" id="tmm-ev-file" class="w-full text-sm">' +
          '  <div class="flex items-center justify-end gap-2">' +
          '    <button type="button" id="tmm-ev-cancel" class="px-3 py-1.5 border rounded text-sm">Cancel</button>' +
          '    <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white rounded text-sm">Upload</button>' +
          '  </div>' +
          '</form>' +
          '<div id="tmm-ev-msg" class="mt-2 text-xs"></div>' +
          '</div>';
        document.body.appendChild(dlg);
        dlg.addEventListener('click', function (e) {
          if (e.target === dlg) close();
        });
        var formEl = dlg.querySelector('#tmm-ev-form');
        var msgEl = dlg.querySelector('#tmm-ev-msg');
        var cancelBtn = dlg.querySelector('#tmm-ev-cancel');
        cancelBtn.addEventListener('click', function () { close(); });
        formEl.addEventListener('submit', function (e) {
          e.preventDefault();
          msgEl.textContent = 'Uploading...';
          msgEl.className = 'mt-2 text-xs text-slate-500';
          var fd = new FormData(formEl);
          fetch('/tmm/admin/api/tickets/evidence_upload.php', { method: 'POST', body: fd })
            .then(function (res) { return res.json(); })
            .then(function (data) {
              if (data && data.ok) {
                msgEl.textContent = 'Uploaded successfully.';
                msgEl.className = 'mt-2 text-xs text-emerald-600';
                setTimeout(function () { close(); window.location.reload(); }, 600);
              } else {
                msgEl.textContent = (data && data.error) ? data.error : 'Upload failed.';
                msgEl.className = 'mt-2 text-xs text-rose-600';
              }
            })
            .catch(function () {
              msgEl.textContent = 'Network error.';
              msgEl.className = 'mt-2 text-xs text-rose-600';
            });
        });
      }
      var ticketEl = dlg.querySelector('#tmm-ev-ticket');
      var fileEl = dlg.querySelector('#tmm-ev-file');
      var msgEl2 = dlg.querySelector('#tmm-ev-msg');
      if (ticketEl) ticketEl.value = ticketNo || '';
      if (fileEl) fileEl.value = '';
      if (msgEl2) { msgEl2.textContent = ''; msgEl2.className = 'mt-2 text-xs'; }
      dlg.style.display = 'flex';
    }
    function close() {
      if (dlg) dlg.style.display = 'none';
    }
    return { open: open, close: close };
  })();
  window.TMMUploadEvidence = TMMUploadEvidence;

  var TMMViewEvidence = (function () {
    var dlg = null;
    function open(ticketNo) {
      if (!dlg) {
        dlg = document.createElement('div');
        dlg.className = 'fixed inset-0 bg-black/30 flex items-center justify-center z-50';
        dlg.innerHTML = '' +
          '<div class="bg-white dark:bg-slate-900 rounded-lg p-4 w-[95%] max-w-3xl border dark:border-slate-700">' +
          '  <div class="flex items-center justify-between mb-2">' +
          '    <div class="text-lg font-semibold">Evidence for Ticket</div>' +
          '    <button type="button" id="tmm-ev-view-close" class="px-3 py-1.5 border rounded text-sm">Close</button>' +
          '  </div>' +
          '  <div id="tmm-ev-view-content" class="space-y-3 text-sm text-slate-600 dark:text-slate-300">' +
          '    <div>Loading evidence...</div>' +
          '  </div>' +
          '</div>';
        document.body.appendChild(dlg);
        dlg.addEventListener('click', function (e) {
          if (e.target === dlg) close();
        });
        var closeBtn = dlg.querySelector('#tmm-ev-view-close');
        if (closeBtn) {
          closeBtn.addEventListener('click', function () {
            close();
          });
        }
      }
      if (!ticketNo) {
        return;
      }
      var contentEl = dlg.querySelector('#tmm-ev-view-content');
      if (contentEl) {
        contentEl.innerHTML = '<div class="text-sm text-slate-600 dark:text-slate-300">Loading evidence...</div>';
      }
      fetch('/tmm/admin/api/tickets/get_evidence.php?ticket=' + encodeURIComponent(ticketNo))
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!contentEl) return;
          if (!data || !data.ok) {
            contentEl.innerHTML = '<div class="text-sm text-rose-600">Failed to load evidence.</div>';
            return;
          }
          var items = Array.isArray(data.evidence) ? data.evidence : [];
          if (!items.length) {
            contentEl.innerHTML = '<div class="text-sm text-slate-500">No evidence files have been uploaded for this ticket.</div>';
            return;
          }
          var html = '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';
          items.forEach(function (ev) {
            var url = ev.url || '';
            var type = ev.file_type || 'file';
            var ts = ev.timestamp || '';
            var dateLabel = '';
            if (ts) {
              var d = new Date(ts);
              if (!isNaN(d.getTime())) {
                dateLabel = d.toLocaleDateString();
              } else {
                dateLabel = ts;
              }
            }
            html += '<div class="border rounded-lg overflow-hidden bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700">';
            html += '<div class="aspect-video bg-slate-200 dark:bg-slate-900 flex items-center justify-center">';
            if (url && (type === 'photo' || type === 'image')) {
              html += '<img src="' + url + '" alt="Evidence" class="w-full h-full object-cover">';
            } else if (url && type === 'video') {
              html += '<video src="' + url + '" controls class="w-full h-full"></video>';
            } else if (url && type === 'pdf') {
              html += '<a href="' + url + '" target="_blank" class="text-xs px-3 py-2 rounded bg-white dark:bg-slate-800 border dark:border-slate-700">Open PDF</a>';
            } else if (url) {
              html += '<a href="' + url + '" target="_blank" class="text-xs px-3 py-2 rounded bg-white dark:bg-slate-800 border dark:border-slate-700">Open file</a>';
            } else {
              html += '<div class="text-xs text-slate-500 px-3">Missing file path</div>';
            }
            html += '</div>';
            html += '<div class="p-2 text-xs text-slate-600 dark:text-slate-300 flex items-center justify-between">';
            html += '<span class="uppercase tracking-wide">' + type + '</span>';
            html += '<span class="text-slate-400">' + (dateLabel || '') + '</span>';
            html += '</div>';
            html += '</div>';
          });
          html += '</div>';
          contentEl.innerHTML = html;
        })
        .catch(function () {
          if (contentEl) {
            contentEl.innerHTML = '<div class="text-sm text-rose-600">Failed to load evidence.</div>';
          }
        });
      dlg.style.display = 'flex';
    }
    function close() {
      if (dlg) dlg.style.display = 'none';
    }
    return { open: open, close: close };
  })();
  window.TMMViewEvidence = TMMViewEvidence;

  function renderViolationSummary(code) {
    if (!code || !violationMap[code]) {
      if (violationSummary) violationSummary.textContent = '';
      if (violationFinePreview) violationFinePreview.textContent = '';
      return;
    }
    var item = violationMap[code];
    var desc = item.description || '';
    var fine = item.fine_amount !== undefined && item.fine_amount !== null ? Number(item.fine_amount) : 0;
    if (violationSummary) {
      violationSummary.textContent = code + (desc ? ' – ' + desc : '');
    }
    if (violationFinePreview) {
      violationFinePreview.textContent = 'Fine: ₱' + fine.toFixed(2);
    }
  }

  if (violationSelect) {
    fetch('/tmm/admin/api/tickets/violation_types.php')
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data || !Array.isArray(data.items)) return;
        var items = data.items;
        items.forEach(function (item) {
          var code = item.violation_code || '';
          if (!code) return;
          violationMap[code] = item;
          var opt = document.createElement('option');
          opt.value = code;
          var label = code;
          if (item.description) {
            label += ' – ' + item.description;
          }
          opt.textContent = label;
          violationSelect.appendChild(opt);
        });
      })
      .catch(function () {
        // Leave fallback select state if API fails
      });

    violationSelect.addEventListener('change', function () {
      renderViolationSummary(violationSelect.value);
    });
  }
  if (!form) return;

  function clearPlateSuggestions() {
    if (!suggestionsBox) return;
    suggestionsBox.innerHTML = '';
    suggestionsBox.classList.add('hidden');
  }

  function renderPlateSuggestions(items) {
    if (!suggestionsBox) return;
    suggestionsBox.innerHTML = '';
    if (!items || items.length === 0) {
      suggestionsBox.classList.add('hidden');
      return;
    }
    items.forEach(function (item) {
      var entry = document.createElement('button');
      entry.type = 'button';
      entry.className = 'w-full text-left px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-700 flex flex-col';
      var primary = document.createElement('span');
      primary.className = 'font-medium';
      primary.textContent = item.plate_number || '';
      var secondary = document.createElement('span');
      secondary.className = 'text-xs text-slate-500';
      var nameLabel = item.operator_name || '';
      var coopLabel = item.coop_name || '';
      if (nameLabel && coopLabel) secondary.textContent = nameLabel + ' • ' + coopLabel;
      else if (nameLabel) secondary.textContent = nameLabel;
      else if (coopLabel) secondary.textContent = coopLabel;
      entry.appendChild(primary);
      if (secondary.textContent) entry.appendChild(secondary);
      entry.addEventListener('mousedown', function (e) {
        e.preventDefault();
        if (plateInput) plateInput.value = item.plate_number || '';
        if (driverInput) driverInput.value = item.operator_name || '';
        clearPlateSuggestions();
      });
      suggestionsBox.appendChild(entry);
    });
    suggestionsBox.classList.remove('hidden');
  }

  if (plateInput && suggestionsBox) {
    plateInput.addEventListener('input', function () {
      var q = plateInput.value.trim();
      if (plateDebounceId) clearTimeout(plateDebounceId);
      if (q.length === 0) {
        clearPlateSuggestions();
        return;
      }
      plateDebounceId = setTimeout(function () {
        fetch('/tmm/admin/api/module1/list_vehicles.php?q=' + encodeURIComponent(q))
          .then(function (res) { return res.json(); })
          .then(function (data) {
            if (data && data.ok && Array.isArray(data.data)) {
              renderPlateSuggestions(data.data.slice(0, 8));
            } else {
              clearPlateSuggestions();
            }
          })
          .catch(function () {
            clearPlateSuggestions();
          });
      }, 200);
    });
    plateInput.addEventListener('blur', function () {
      var val = plateInput.value.trim();
      if (val) {
        fetch('/tmm/admin/api/module1/list_vehicles.php?q=' + encodeURIComponent(val))
          .then(function (res) { return res.json(); })
          .then(function (data) {
            if (data && data.ok && Array.isArray(data.data)) {
              var upper = val.toUpperCase();
              var match = null;
              for (var i = 0; i < data.data.length; i++) {
                var p = (data.data[i].plate_number || '').toUpperCase();
                if (p === upper) {
                  match = data.data[i];
                  break;
                }
              }
          if (!match && data.data.length > 0) {
            match = data.data[0];
          }
          if (match && driverInput) {
            var opName = (match.operator_name || '').trim();
            if (opName !== '') {
              driverInput.value = opName;
            }
          }
            }
          })
          .finally(function () {
            setTimeout(clearPlateSuggestions, 150);
          });
      } else {
        setTimeout(clearPlateSuggestions, 150);
      }
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    msg.textContent = 'Generating ticket...';
    msg.className = 'mt-3 text-sm text-slate-500';

    var formData = new FormData(form);

    fetch('/tmm/admin/api/traffic/create_ticket.php', {
      method: 'POST',
      body: formData
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data && data.ok) {
          msg.textContent = 'Ticket ' + (data.ticket_number || '') + ' created. Fine: ₱' + (data.fine !== undefined ? Number(data.fine).toFixed(2) : '0.00');
          msg.className = 'mt-3 text-sm text-emerald-600';
          form.reset();
          setTimeout(function () { window.location.reload(); }, 800);
        } else {
          msg.textContent = (data && data.error) ? data.error : 'Failed to create ticket.';
          msg.className = 'mt-3 text-sm text-rose-600';
        }
      })
      .catch(function () {
        msg.textContent = 'Network error while creating ticket.';
        msg.className = 'mt-3 text-sm text-rose-600';
      });
  });
});
</script>
