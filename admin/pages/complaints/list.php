<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Citizen Complaints</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Review and manage complaints submitted by commuters. AI categorization assists in prioritization.</p>
  <?php require_once __DIR__ . '/../../includes/db.php'; $db = db(); ?>

  <!-- Filters -->
  <form class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6" method="GET">
    <input type="hidden" name="page" value="complaints/list">
    <input name="q" class="col-span-1 px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Reference or Plate Search" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
    
    <select name="status" class="col-span-1 px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
      <option value="">All Statuses</option>
      <option value="Submitted" <?php echo (($_GET['status'] ?? '')==='Submitted')?'selected':''; ?>>Submitted</option>
      <option value="Under Review" <?php echo (($_GET['status'] ?? '')==='Under Review')?'selected':''; ?>>Under Review</option>
      <option value="Resolved" <?php echo (($_GET['status'] ?? '')==='Resolved')?'selected':''; ?>>Resolved</option>
    </select>
    
    <button class="px-4 py-2 bg-[#4CAF50] text-white rounded-lg w-full md:w-auto">Filter</button>
  </form>

  <!-- Table -->
  <div class="overflow-x-auto rounded-xl ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 shadow-sm">
    <table class="min-w-full text-sm">
      <thead class="hidden md:table-header-group bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
        <tr class="text-left text-slate-700 dark:text-slate-200">
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Ref No.</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Plate</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Reported Issue</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">AI Category</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Evidence</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Status</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Date</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider text-center">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
        <?php
          $q = trim($_GET['q'] ?? '');
          $status = trim($_GET['status'] ?? '');
          
          $sql = "SELECT * FROM complaints";
          $conds = [];
          $params = [];
          $types = '';
          
          if ($q !== '') { 
              $conds[] = "(reference_number LIKE ? OR vehicle_plate LIKE ?)"; 
              $params[] = "%$q%"; 
              $params[] = "%$q%"; 
              $types .= 'ss'; 
          }
          
          if ($status !== '') { 
              $conds[] = "status=?"; 
              $params[] = $status; 
              $types .= 's'; 
          }
          
          if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
          $sql .= " ORDER BY created_at DESC";
          
          if ($params) { 
              $stmt = $db->prepare($sql); 
              $stmt->bind_param($types, ...$params); 
              $stmt->execute(); 
              $res = $stmt->get_result(); 
          } else { 
              $res = $db->query($sql); 
          }
          
          while ($row = $res->fetch_assoc()):
        ?>
        <tr class="grid grid-cols-1 md:table-row gap-2 md:gap-0 p-2 md:p-0 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors duration-150">
          <td class="py-3 px-4 font-mono text-slate-500"><?php echo htmlspecialchars($row['reference_number']); ?></td>
          <td class="py-3 px-4 font-bold"><?php echo htmlspecialchars($row['vehicle_plate'] ?: 'N/A'); ?></td>
          <td class="py-3 px-4">
              <div class="font-medium"><?php echo htmlspecialchars($row['complaint_type']); ?></div>
              <div class="text-xs text-slate-500 truncate max-w-xs"><?php echo htmlspecialchars($row['description']); ?></div>
          </td>
          <td class="py-3 px-4">
              <?php if($row['ai_category']): ?>
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                      <i data-lucide="brain-circuit" class="w-3 h-3 mr-1"></i>
                      <?php echo htmlspecialchars($row['ai_category']); ?>
                  </span>
              <?php else: ?>
                  <span class="text-slate-400">-</span>
              <?php endif; ?>
          </td>
          <td class="py-3 px-4">
              <?php if($row['media_path']): ?>
                  <a href="../citizen/commuter/<?php echo htmlspecialchars($row['media_path']); ?>" target="_blank" class="text-blue-600 hover:underline text-xs flex items-center">
                      <i data-lucide="image" class="w-4 h-4 mr-1"></i> View
                  </a>
              <?php else: ?>
                  <span class="text-slate-400 text-xs">No Media</span>
              <?php endif; ?>
          </td>
          <td class="py-3 px-4">
              <?php 
                  $statusColor = 'bg-slate-100 text-slate-700';
                  if ($row['status'] === 'Submitted') $statusColor = 'bg-blue-100 text-blue-700';
                  if ($row['status'] === 'Under Review') $statusColor = 'bg-yellow-100 text-yellow-700';
                  if ($row['status'] === 'Resolved') $statusColor = 'bg-green-100 text-green-700';
              ?>
              <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $statusColor; ?>">
                  <?php echo htmlspecialchars($row['status']); ?>
              </span>
          </td>
          <td class="py-3 px-4 text-slate-500 text-xs">
              <?php echo date('M j, Y H:i', strtotime($row['created_at'])); ?>
          </td>
          <td class="py-3 px-4 text-center">
             <button onclick="updateStatus(<?php echo $row['id']; ?>, 'Under Review')" class="text-yellow-600 hover:text-yellow-800 text-xs mr-2" title="Mark Under Review">Review</button>
             <button onclick="updateStatus(<?php echo $row['id']; ?>, 'Resolved')" class="text-green-600 hover:text-green-800 text-xs" title="Mark Resolved">Resolve</button>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
async function updateStatus(id, status) {
    if(!confirm('Update status to ' + status + '?')) return;
    
    try {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('status', status);
        
        const res = await fetch('api/complaints/update_status.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await res.json();
        
        if(data.success) {
            window.location.reload();
        } else {
            alert('Failed to update: ' + data.message);
        }
    } catch(e) {
        console.error(e);
        alert('Error updating status');
    }
}
</script>
