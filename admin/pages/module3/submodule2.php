<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();

$now = date('Y-m-d H:i:s');
$start30 = date('Y-m-d H:i:s', strtotime('-30 days'));

$total30 = 0;
$repeat = 0;
$escalations = 0;
$recentValidated = [];
$recentPayments = [];

$resTotal = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE date_issued >= '$start30'");
if ($resTotal && ($row = $resTotal->fetch_assoc())) {
  $total30 = (int)($row['c'] ?? 0);
}

$resRepeat = $db->query("SELECT COUNT(*) AS c FROM (SELECT vehicle_plate, COUNT(*) AS cnt FROM tickets WHERE date_issued >= '$start30' AND vehicle_plate IS NOT NULL AND vehicle_plate <> '' GROUP BY vehicle_plate HAVING cnt > 1) t");
if ($resRepeat && ($rowR = $resRepeat->fetch_assoc())) {
  $repeat = (int)($rowR['c'] ?? 0);
}

$resEsc = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE date_issued >= '$start30' AND status='Escalated'");
if ($resEsc && ($rowE = $resEsc->fetch_assoc())) {
  $escalations = (int)($rowE['c'] ?? 0);
}

$resVal = $db->query("SELECT ticket_number, vehicle_plate, status, date_issued FROM tickets WHERE status='Validated' ORDER BY date_issued DESC LIMIT 10");
if ($resVal) {
  while ($r = $resVal->fetch_assoc()) {
    $recentValidated[] = $r;
  }
}

$resPay = $db->query("SELECT t.ticket_number, t.vehicle_plate, t.status, p.amount_paid, p.date_paid, p.receipt_ref FROM payment_records p JOIN tickets t ON p.ticket_id = t.ticket_id ORDER BY p.date_paid DESC LIMIT 10");
if ($resPay) {
  while ($p = $resPay->fetch_assoc()) {
    $recentPayments[] = $p;
  }
}
?>

<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
    <div>
      <h1 class="text-2xl font-bold mb-1">Validation, Payment & Compliance</h1>
      <p class="text-sm text-slate-600 dark:text-slate-400">Cross-validate ticket data, record payments, and monitor repeat violations.</p>
    </div>
    <div class="text-xs text-slate-500 dark:text-slate-400">
      Data window: last 30 days<br>
      As of <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($now))); ?>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900">
      <h2 class="text-lg font-semibold mb-3">Validate Ticket</h2>
      <form id="ticket-validate-form" class="space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="relative">
            <label class="block text-xs mb-1 text-slate-500">Ticket number</label>
            <input id="val-ticket-number" name="ticket_number" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="TCK-2026-0001">
            <div id="val-ticket-suggestions" class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow text-xs max-h-48 overflow-y-auto hidden"></div>
          </div>
          <div>
            <label class="block text-xs mb-1 text-slate-500">Vehicle plate</label>
            <input id="val-vehicle-plate" name="vehicle_plate" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="ABC1234">
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm">Validate against system records</button>
          <span class="text-xs text-slate-500">Auto-updates ticket status and franchise/coop when found.</span>
        </div>
      </form>
      <div id="ticket-validate-result" class="mt-3 text-sm text-slate-600 dark:text-slate-300"></div>
    </div>

    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900">
      <h2 class="text-lg font-semibold mb-3">Payment Processing</h2>
      <form id="ticket-payment-form" class="space-y-3">
        <div class="relative">
          <label class="block text-xs mb-1 text-slate-500">Ticket number</label>
          <input id="pay-ticket-number" name="ticket_number" required class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="TCK-2026-0001">
          <div id="pay-ticket-suggestions" class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow text-xs max-h-48 overflow-y-auto hidden"></div>
          <div id="pay-ticket-context" class="mt-1 text-[11px] text-slate-500"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs mb-1 text-slate-500">Amount paid (₱)</label>
            <input id="pay-amount" name="amount_paid" type="number" step="0.01" min="0" required class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="1000.00">
          </div>
          <div>
            <label class="block text-xs mb-1 text-slate-500">Receipt ref</label>
            <input id="pay-receipt" name="receipt_ref" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="OR-123456">
          </div>
          <div class="flex items-end">
            <label class="inline-flex items-center text-xs text-slate-600 dark:text-slate-300">
              <input id="pay-verified" name="verified_by_treasury" type="checkbox" class="mr-2" checked value="1">
              Treasury verified
            </label>
          </div>
        </div>
        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm">Mark as paid</button>
      </form>
      <div id="ticket-payment-result" class="mt-3 text-sm text-slate-600 dark:text-slate-300"></div>
    </div>
  </div>

  <div class="p-4 border rounded-lg dark:border-slate-700 mt-6 bg-white dark:bg-slate-900">
    <h2 class="text-lg font-semibold mb-3">Compliance Summary (Last 30 days)</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="p-3 border rounded dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
        <div class="text-sm text-slate-500">Violations</div>
        <div class="text-2xl font-bold"><?php echo (int)$total30; ?></div>
      </div>
      <div class="p-3 border rounded dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
        <div class="text-sm text-slate-500">Repeat offenders</div>
        <div class="text-2xl font-bold"><?php echo (int)$repeat; ?></div>
      </div>
      <div class="p-3 border rounded dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
        <div class="text-sm text-slate-500">Escalations</div>
        <div class="text-2xl font-bold"><?php echo (int)$escalations; ?></div>
      </div>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
      <button type="button" id="btn-notify-franchise" class="px-3 py-2 border rounded text-sm">Notify franchise office</button>
      <button type="button" id="btn-create-case" class="px-3 py-2 border rounded text-sm">Create compliance case</button>
      <span id="compliance-actions-result" class="text-xs text-slate-500"></span>
    </div>
  </div>

  <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900">
      <h2 class="text-lg font-semibold mb-3">Recent Validations</h2>
      <div class="mb-2">
        <input id="recent-validations-filter" class="w-full px-2 py-1 text-xs border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Filter by ticket or plate">
      </div>
      <div class="bg-slate-50 dark:bg-slate-800 rounded border dark:border-slate-700 max-h-64 overflow-y-auto">
        <table class="w-full text-sm text-left">
          <thead class="text-xs text-slate-500 uppercase bg-slate-100 dark:bg-slate-700 sticky top-0">
            <tr>
              <th class="px-3 py-2">Ticket</th>
              <th class="px-3 py-2">Plate</th>
              <th class="px-3 py-2">Status</th>
              <th class="px-3 py-2">Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentValidated)): ?>
              <tr><td colspan="4" class="px-3 py-2 text-center text-slate-500">No validations yet.</td></tr>
            <?php else: ?>
              <?php foreach ($recentValidated as $v): ?>
                <tr class="border-t border-slate-200 dark:border-slate-700 cursor-pointer tmm-row-val"
                  data-ticket="<?php echo htmlspecialchars($v['ticket_number'] ?? '', ENT_QUOTES); ?>"
                  data-plate="<?php echo htmlspecialchars($v['vehicle_plate'] ?? '', ENT_QUOTES); ?>"
                  data-status="<?php echo htmlspecialchars($v['status'] ?? 'Validated', ENT_QUOTES); ?>">
                  <td class="px-3 py-2">
                    <?php echo htmlspecialchars($v['ticket_number'] ?? ''); ?>
                    <?php if (!empty($v['ticket_number'])): ?>
                      <a href="?page=module3/submodule3&ticket=<?php echo urlencode($v['ticket_number']); ?>" target="_blank" class="ml-2 text-[11px] text-indigo-600 hover:underline">Evidence</a>
                      <a href="?page=module3/submodule3&ticket=<?php echo urlencode($v['ticket_number']); ?>" target="_blank" class="ml-2 text-[11px] text-slate-600 dark:text-slate-300 hover:underline">Details</a>
                    <?php endif; ?>
                  </td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars($v['vehicle_plate'] ?? ''); ?></td>
                  <td class="px-3 py-2 text-xs">
                    <span class="px-2 py-1 rounded bg-blue-100 text-blue-700"><?php echo htmlspecialchars($v['status'] ?? 'Validated'); ?></span>
                  </td>
                  <td class="px-3 py-2 text-xs text-slate-500"><?php echo htmlspecialchars(date('Y-m-d', strtotime($v['date_issued'] ?? ''))); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900">
      <h2 class="text-lg font-semibold mb-3">Recent Payments</h2>
      <div class="mb-2">
        <input id="recent-payments-filter" class="w-full px-2 py-1 text-xs border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Filter by ticket, plate, or OR number">
      </div>
      <div class="bg-slate-50 dark:bg-slate-800 rounded border dark:border-slate-700 max-h-64 overflow-y-auto">
        <table class="w-full text-sm text-left">
          <thead class="text-xs text-slate-500 uppercase bg-slate-100 dark:bg-slate-700 sticky top-0">
            <tr>
              <th class="px-3 py-2">Ticket</th>
              <th class="px-3 py-2">Plate</th>
              <th class="px-3 py-2">Amount</th>
              <th class="px-3 py-2">Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentPayments)): ?>
              <tr><td colspan="4" class="px-3 py-2 text-center text-slate-500">No payments recorded yet.</td></tr>
            <?php else: ?>
              <?php foreach ($recentPayments as $p): ?>
                <tr class="border-t border-slate-200 dark:border-slate-700 cursor-pointer tmm-row-pay"
                  data-ticket="<?php echo htmlspecialchars($p['ticket_number'] ?? '', ENT_QUOTES); ?>"
                  data-plate="<?php echo htmlspecialchars($p['vehicle_plate'] ?? '', ENT_QUOTES); ?>"
                  data-status="<?php echo htmlspecialchars($p['status'] ?? '', ENT_QUOTES); ?>">
                  <td class="px-3 py-2">
                    <?php echo htmlspecialchars($p['ticket_number'] ?? ''); ?>
                    <?php if (!empty($p['ticket_number'])): ?>
                      <a href="?page=module3/submodule3&ticket=<?php echo urlencode($p['ticket_number']); ?>" target="_blank" class="ml-2 text-[11px] text-indigo-600 hover:underline">Evidence</a>
                      <a href="?page=module3/submodule3&ticket=<?php echo urlencode($p['ticket_number']); ?>" target="_blank" class="ml-2 text-[11px] text-slate-600 dark:text-slate-300 hover:underline">Details</a>
                    <?php endif; ?>
                    <?php if (!empty($p['receipt_ref'])): ?>
                      <div class="text-[11px] text-slate-500">OR: <?php echo htmlspecialchars($p['receipt_ref']); ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars($p['vehicle_plate'] ?? ''); ?></td>
                  <td class="px-3 py-2 text-right">₱<?php echo number_format((float)($p['amount_paid'] ?? 0), 2); ?></td>
                  <td class="px-3 py-2 text-xs text-slate-500"><?php echo htmlspecialchars(date('Y-m-d', strtotime($p['date_paid'] ?? ''))); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var validateForm = document.getElementById('ticket-validate-form');
  var validateResult = document.getElementById('ticket-validate-result');
  var paymentForm = document.getElementById('ticket-payment-form');
  var paymentResult = document.getElementById('ticket-payment-result');
  var notifyBtn = document.getElementById('btn-notify-franchise');
  var createCaseBtn = document.getElementById('btn-create-case');
  var complianceMsg = document.getElementById('compliance-actions-result');
  var validationsFilter = document.getElementById('recent-validations-filter');
  var paymentsFilter = document.getElementById('recent-payments-filter');
  var valTicketInput = document.getElementById('val-ticket-number');
  var valPlateInput = document.getElementById('val-vehicle-plate');
  var valTicketSuggestions = document.getElementById('val-ticket-suggestions');
  var valTicketDebounceId = null;
  var valTicketCache = {};
  var payTicketInput = document.getElementById('pay-ticket-number');
  var payTicketSuggestions = document.getElementById('pay-ticket-suggestions');
  var payTicketContext = document.getElementById('pay-ticket-context');
  var payTicketDebounceId = null;
  var valRows = document.querySelectorAll('.tmm-row-val');
  var payRows = document.querySelectorAll('.tmm-row-pay');

  function attachTableFilter(inputEl, tableEl, matchFn) {
    if (!inputEl || !tableEl) return;
    var tbody = tableEl.querySelector('tbody');
    if (!tbody) return;
    inputEl.addEventListener('input', function () {
      var q = inputEl.value.toLowerCase().trim();
      var rows = tbody.querySelectorAll('tr');
      rows.forEach(function (row) {
        var cells = row.querySelectorAll('td');
        if (!cells.length) return;
        if (!q) {
          row.style.display = '';
          return;
        }
        var match = matchFn(row, cells, q);
        row.style.display = match ? '' : 'none';
      });
    });
  }

  function clearValTicketSuggestions() {
    if (!valTicketSuggestions) return;
    valTicketSuggestions.innerHTML = '';
    valTicketSuggestions.classList.add('hidden');
  }

  function renderValTicketSuggestions(items) {
    if (!valTicketSuggestions) return;
    valTicketSuggestions.innerHTML = '';
    if (!items || !items.length) {
      valTicketSuggestions.classList.add('hidden');
      return;
    }
    items.forEach(function (item) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'w-full text-left px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-700 flex flex-col';
      var primary = document.createElement('span');
      primary.className = 'font-medium';
      primary.textContent = item.ticket_number || '';
      var secondary = document.createElement('span');
      secondary.className = 'text-[11px] text-slate-500';
      var plateLabel = item.vehicle_plate || '';
      var statusLabel = item.status || '';
      var parts = [];
      if (plateLabel) parts.push(plateLabel);
      if (statusLabel) parts.push(statusLabel);
      secondary.textContent = parts.join(' • ');
      btn.appendChild(primary);
      if (secondary.textContent) btn.appendChild(secondary);
      btn.addEventListener('mousedown', function (e) {
        e.preventDefault();
        if (valTicketInput) valTicketInput.value = item.ticket_number || '';
        if (valPlateInput) valPlateInput.value = item.vehicle_plate || '';
        if (item.ticket_number) {
          valTicketCache[item.ticket_number] = item;
        }
        clearValTicketSuggestions();
      });
      valTicketSuggestions.appendChild(btn);
    });
    valTicketSuggestions.classList.remove('hidden');
  }

  function clearPayTicketSuggestions() {
    if (!payTicketSuggestions) return;
    payTicketSuggestions.innerHTML = '';
    payTicketSuggestions.classList.add('hidden');
  }

  function setPayTicketContext(item) {
    if (!payTicketContext) return;
    if (!item) {
      payTicketContext.textContent = '';
      return;
    }
    var plate = item.vehicle_plate || '';
    var status = item.status || '';
    var parts = [];
    if (plate) parts.push('Plate ' + plate);
    if (status) parts.push(status);
    payTicketContext.textContent = parts.join(' • ');
  }

  function renderPayTicketSuggestions(items) {
    if (!payTicketSuggestions) return;
    payTicketSuggestions.innerHTML = '';
    if (!items || !items.length) {
      payTicketSuggestions.classList.add('hidden');
      return;
    }
    items.forEach(function (item) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'w-full text-left px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-700 flex flex-col';
      var primary = document.createElement('span');
      primary.className = 'font-medium';
      primary.textContent = item.ticket_number || '';
      var secondary = document.createElement('span');
      secondary.className = 'text-[11px] text-slate-500';
      var plateLabel = item.vehicle_plate || '';
      var statusLabel = item.status || '';
      var parts = [];
      if (plateLabel) parts.push(plateLabel);
      if (statusLabel) parts.push(statusLabel);
      secondary.textContent = parts.join(' • ');
      btn.appendChild(primary);
      if (secondary.textContent) btn.appendChild(secondary);
      btn.addEventListener('mousedown', function (e) {
        e.preventDefault();
        if (payTicketInput) payTicketInput.value = item.ticket_number || '';
        if (item.ticket_number) {
          valTicketCache[item.ticket_number] = item;
        }
        setPayTicketContext(item);
        clearPayTicketSuggestions();
      });
      payTicketSuggestions.appendChild(btn);
    });
    payTicketSuggestions.classList.remove('hidden');
  }

  if (validateForm && validateResult) {
    validateForm.addEventListener('submit', function (e) {
      e.preventDefault();
      validateResult.textContent = 'Validating ticket against PUV and franchise records...';
      validateResult.className = 'mt-3 text-sm text-slate-500';

      var formData = new FormData(validateForm);
      fetch('/tmm/admin/api/tickets/validate.php', {
        method: 'POST',
        body: formData
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.ok) {
            validateResult.textContent = 'Ticket validated. Status: ' + (data.status || 'Validated');
            validateResult.className = 'mt-3 text-sm text-emerald-600';
          } else {
            validateResult.textContent = (data && data.error) ? data.error : 'Validation failed.';
            validateResult.className = 'mt-3 text-sm text-rose-600';
          }
        })
        .catch(function () {
          validateResult.textContent = 'Network error while validating ticket.';
          validateResult.className = 'mt-3 text-sm text-rose-600';
        });
    });
  }

  if (paymentForm && paymentResult) {
    paymentForm.addEventListener('submit', function (e) {
      e.preventDefault();
      paymentResult.textContent = 'Recording payment...';
      paymentResult.className = 'mt-3 text-sm text-slate-500';

      var formData = new FormData(paymentForm);
      var verified = document.getElementById('pay-verified');
      if (verified && verified.checked) {
        formData.set('verified_by_treasury', '1');
      } else {
        formData.delete('verified_by_treasury');
      }

      fetch('/tmm/admin/api/tickets/settle.php', {
        method: 'POST',
        body: formData
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.ok) {
            paymentResult.textContent = 'Payment recorded. Ticket status updated to ' + (data.status || 'Settled') + '.';
            paymentResult.className = 'mt-3 text-sm text-emerald-600';
            paymentForm.reset();
          } else {
            paymentResult.textContent = (data && data.error) ? data.error : 'Failed to record payment.';
            paymentResult.className = 'mt-3 text-sm text-rose-600';
          }
        })
        .catch(function () {
          paymentResult.textContent = 'Network error while recording payment.';
          paymentResult.className = 'mt-3 text-sm text-rose-600';
        });
    });
  }

  if (notifyBtn && complianceMsg) {
    notifyBtn.addEventListener('click', function () {
      complianceMsg.textContent = 'Franchise office would be notified for repeat offenders (demo placeholder).';
    });
  }

  if (createCaseBtn && complianceMsg) {
    createCaseBtn.addEventListener('click', function () {
      complianceMsg.textContent = 'Compliance case would be opened for escalated tickets (demo placeholder).';
    });
  }

  var validationsTable = document.querySelector('.mt-6 grid .p-4:nth-child(1) table');
  var paymentsTable = document.querySelector('.mt-6 grid .p-4:nth-child(2) table');

  attachTableFilter(validationsFilter, validationsTable, function (row, cells, q) {
    var ticket = (cells[0].textContent || '').toLowerCase();
    var plate = (cells[1].textContent || '').toLowerCase();
    return ticket.indexOf(q) !== -1 || plate.indexOf(q) !== -1;
  });

  attachTableFilter(paymentsFilter, paymentsTable, function (row, cells, q) {
    var ticket = (cells[0].textContent || '').toLowerCase();
    var plate = (cells[1].textContent || '').toLowerCase();
    var amount = (cells[2].textContent || '').toLowerCase();
    return ticket.indexOf(q) !== -1 || plate.indexOf(q) !== -1 || amount.indexOf(q) !== -1;
  });

  valRows.forEach(function (row) {
    row.addEventListener('click', function () {
      var t = row.getAttribute('data-ticket') || '';
      var p = row.getAttribute('data-plate') || '';
      var s = row.getAttribute('data-status') || '';
      if (valTicketInput && t) {
        valTicketInput.value = t;
      }
      if (valPlateInput && p) {
        valPlateInput.value = p;
      }
      if (t) {
        valTicketCache[t] = { ticket_number: t, vehicle_plate: p, status: s };
      }
      if (validateForm && validateForm.scrollIntoView) {
        validateForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  payRows.forEach(function (row) {
    row.addEventListener('click', function () {
      var t = row.getAttribute('data-ticket') || '';
      var p = row.getAttribute('data-plate') || '';
      var s = row.getAttribute('data-status') || '';
      if (payTicketInput && t) {
        payTicketInput.value = t;
      }
      if (t) {
        valTicketCache[t] = { ticket_number: t, vehicle_plate: p, status: s };
      }
      if (t || p || s) {
        setPayTicketContext({ ticket_number: t, vehicle_plate: p, status: s });
      }
      if (paymentForm && paymentForm.scrollIntoView) {
        paymentForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  if (valTicketInput) {
    valTicketInput.addEventListener('input', function () {
      var q = valTicketInput.value.trim();
      if (valTicketDebounceId) clearTimeout(valTicketDebounceId);
      if (!q) {
        clearValTicketSuggestions();
        return;
      }
      valTicketDebounceId = setTimeout(function () {
        fetch('/tmm/admin/api/tickets/list.php?q=' + encodeURIComponent(q))
          .then(function (res) { return res.json(); })
          .then(function (data) {
            if (data && Array.isArray(data.items)) {
              var items = data.items.slice(0, 8);
              items.forEach(function (it) {
                if (it.ticket_number) {
                  valTicketCache[it.ticket_number] = it;
                }
              });
              renderValTicketSuggestions(items);
            } else {
              clearValTicketSuggestions();
            }
          })
          .catch(function () {
            clearValTicketSuggestions();
          });
      }, 200);
    });

    valTicketInput.addEventListener('blur', function () {
      var val = valTicketInput.value.trim();
      setTimeout(function () {
        clearValTicketSuggestions();
      }, 150);
      if (!val || !valPlateInput) return;
      if (valTicketCache[val] && valTicketCache[val].vehicle_plate) {
        valPlateInput.value = valTicketCache[val].vehicle_plate;
        return;
      }
      fetch('/tmm/admin/api/tickets/list.php?q=' + encodeURIComponent(val))
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && Array.isArray(data.items)) {
            var items = data.items;
            var match = null;
            for (var i = 0; i < items.length; i++) {
              if ((items[i].ticket_number || '') === val) {
                match = items[i];
                break;
              }
            }
            if (!match && items.length > 0) {
              match = items[0];
            }
            if (match && match.vehicle_plate) {
              valPlateInput.value = match.vehicle_plate;
              valTicketCache[val] = match;
            }
          }
        });
    });
  }

  if (payTicketInput) {
    payTicketInput.addEventListener('input', function () {
      var q = payTicketInput.value.trim();
      if (payTicketDebounceId) clearTimeout(payTicketDebounceId);
      if (!q) {
        clearPayTicketSuggestions();
        setPayTicketContext(null);
        return;
      }
      payTicketDebounceId = setTimeout(function () {
        fetch('/tmm/admin/api/tickets/list.php?q=' + encodeURIComponent(q))
          .then(function (res) { return res.json(); })
          .then(function (data) {
            if (data && Array.isArray(data.items)) {
              var items = data.items.slice(0, 8);
              items.forEach(function (it) {
                if (it.ticket_number) {
                  valTicketCache[it.ticket_number] = it;
                }
              });
              renderPayTicketSuggestions(items);
            } else {
              clearPayTicketSuggestions();
            }
          })
          .catch(function () {
            clearPayTicketSuggestions();
          });
      }, 200);
    });

    payTicketInput.addEventListener('blur', function () {
      var val = payTicketInput.value.trim();
      setTimeout(function () {
        clearPayTicketSuggestions();
      }, 150);
      if (!val) {
        setPayTicketContext(null);
        return;
      }
      if (valTicketCache[val]) {
        setPayTicketContext(valTicketCache[val]);
        return;
      }
      fetch('/tmm/admin/api/tickets/list.php?q=' + encodeURIComponent(val))
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && Array.isArray(data.items)) {
            var items = data.items;
            var match = null;
            for (var i = 0; i < items.length; i++) {
              if ((items[i].ticket_number || '') === val) {
                match = items[i];
                break;
              }
            }
            if (!match && items.length > 0) {
              match = items[0];
            }
            if (match) {
              valTicketCache[val] = match;
              setPayTicketContext(match);
            }
          }
        });
    });
  }
});
</script>
