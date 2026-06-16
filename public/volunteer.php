<?php
$page_title = "Volunteer | Disaster Relief System";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/functions.php";
require_role(['Volunteer', 'Admin']);

$user_id = $_SESSION['user_id'];
$is_admin = current_user_role() === 'Admin';


$pdo->exec("
    CREATE TABLE IF NOT EXISTS volunteer_deliveries (
        delivery_id INT AUTO_INCREMENT PRIMARY KEY,
        volunteer_id INT,
        camp_id INT,
        item_id INT,
        quantity INT NOT NULL,
        delivered_to VARCHAR(160),
        delivery_note TEXT,
        delivery_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (volunteer_id) REFERENCES users(user_id) ON DELETE SET NULL,
        FOREIGN KEY (camp_id) REFERENCES relief_camps(camp_id) ON DELETE SET NULL,
        FOREIGN KEY (item_id) REFERENCES supply_items(item_id) ON DELETE SET NULL
    )
");

if ($is_admin) {
    $tasks_stmt = $pdo->query("
        SELECT vt.*, rc.camp_name, u.full_name AS volunteer_name
        FROM volunteer_tasks vt
        LEFT JOIN relief_camps rc ON vt.camp_id = rc.camp_id
        LEFT JOIN users u ON vt.volunteer_id = u.user_id
        ORDER BY vt.task_id DESC
    ");
    $tasks = $tasks_stmt->fetchAll();
} else {
    $tasks_stmt = $pdo->prepare("
        SELECT vt.*, rc.camp_name, u.full_name AS volunteer_name
        FROM volunteer_tasks vt
        LEFT JOIN relief_camps rc ON vt.camp_id = rc.camp_id
        LEFT JOIN users u ON vt.volunteer_id = u.user_id
        WHERE vt.volunteer_id = ?
        ORDER BY vt.task_id DESC
    ");
    $tasks_stmt->execute([$user_id]);
    $tasks = $tasks_stmt->fetchAll();
}


if ($is_admin) {
    $assigned_camps = $pdo->query("
        SELECT DISTINCT rc.camp_id, rc.camp_name
        FROM relief_camps rc
        ORDER BY rc.camp_name
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT DISTINCT rc.camp_id, rc.camp_name
        FROM volunteer_tasks vt
        JOIN relief_camps rc ON vt.camp_id = rc.camp_id
        WHERE vt.volunteer_id = ?
        ORDER BY rc.camp_name
    ");
    $stmt->execute([$user_id]);
    $assigned_camps = $stmt->fetchAll();
}

if ($is_admin) {
    $stock_items = $pdo->query("
        SELECT cs.stock_id, cs.camp_id, cs.item_id, cs.quantity, rc.camp_name, si.item_name, si.item_category, si.unit
        FROM camp_stock cs
        JOIN relief_camps rc ON cs.camp_id = rc.camp_id
        JOIN supply_items si ON cs.item_id = si.item_id
        WHERE cs.quantity > 0
        ORDER BY rc.camp_name, si.item_category, si.item_name
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT DISTINCT cs.stock_id, cs.camp_id, cs.item_id, cs.quantity, rc.camp_name, si.item_name, si.item_category, si.unit
        FROM volunteer_tasks vt
        JOIN relief_camps rc ON vt.camp_id = rc.camp_id
        JOIN camp_stock cs ON rc.camp_id = cs.camp_id
        JOIN supply_items si ON cs.item_id = si.item_id
        WHERE vt.volunteer_id = ?
        AND cs.quantity > 0
        ORDER BY rc.camp_name, si.item_category, si.item_name
    ");
    $stmt->execute([$user_id]);
    $stock_items = $stmt->fetchAll();
}

if ($is_admin) {
    $deliveries = $pdo->query("
        SELECT vd.*, rc.camp_name, si.item_name, si.unit, u.full_name AS volunteer_name
        FROM volunteer_deliveries vd
        LEFT JOIN relief_camps rc ON vd.camp_id = rc.camp_id
        LEFT JOIN supply_items si ON vd.item_id = si.item_id
        LEFT JOIN users u ON vd.volunteer_id = u.user_id
        ORDER BY vd.delivery_id DESC
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT vd.*, rc.camp_name, si.item_name, si.unit, u.full_name AS volunteer_name
        FROM volunteer_deliveries vd
        LEFT JOIN relief_camps rc ON vd.camp_id = rc.camp_id
        LEFT JOIN supply_items si ON vd.item_id = si.item_id
        LEFT JOIN users u ON vd.volunteer_id = u.user_id
        WHERE vd.volunteer_id = ?
        ORDER BY vd.delivery_id DESC
    ");
    $stmt->execute([$user_id]);
    $deliveries = $stmt->fetchAll();
}

if ($is_admin) {
    $field_issues = $pdo->query("
        SELECT *
        FROM help_requests
        WHERE affected_user_id IS NULL
        ORDER BY request_id DESC
    ")->fetchAll();
} else {
   
    $stmt = $pdo->prepare("
        SELECT *
        FROM help_requests
        WHERE affected_user_id IS NULL
        AND details LIKE ?
        ORDER BY request_id DESC
    ");
    $stmt->execute(['%Volunteer ID: ' . $user_id . '%']);
    $field_issues = $stmt->fetchAll();
}

$pending_count = count(array_filter($tasks, fn($t) => $t['task_status'] === 'Pending'));
$progress_count = count(array_filter($tasks, fn($t) => $t['task_status'] === 'In Progress'));
$completed_count = count(array_filter($tasks, fn($t) => $t['task_status'] === 'Completed'));
?>
<?php include "../includes/header.php"; ?>

<main class="container dashboard">
  <aside class="sidebar">
    <h3>Volunteer</h3>
    <a class="active" onclick="showSection('vol-dashboard', this)">Dashboard</a>
    <a onclick="showSection('vol-tasks', this)">Relief Tasks</a>
    <a onclick="showSection('vol-status', this)">Update Task Status</a>
    <a onclick="showSection('vol-delivery', this)">Delivered Supplies</a>
    <a onclick="showSection('vol-issues', this)">Field Issues</a>
    <a href="chat.php">Chat</a>
  </aside>

  <section class="content">
    <div class="page-title">
      <span class="badge">Volunteer Dashboard</span>
      <h1>Volunteer Relief Task Panel</h1>
      <p>View assigned relief tasks, update completion status, record delivered supplies, report urgent field issues and chat with camp managers.</p>
    </div>

    <section id="vol-dashboard" class="tab-section active">
      <div class="kpi-row">
        <div class="kpi"><span>Assigned Tasks</span><strong><?php echo count($tasks); ?></strong></div>
        <div class="kpi"><span>Pending</span><strong><?php echo $pending_count; ?></strong></div>
        <div class="kpi"><span>In Progress</span><strong><?php echo $progress_count; ?></strong></div>
        <div class="kpi"><span>Completed</span><strong><?php echo $completed_count; ?></strong></div>
      </div>

      <div class="grid grid-2">
        <div class="card">
          <h2>Volunteer Responsibilities</h2>
          <p>You can only record delivered supplies from stock that already exists in your assigned camp stock. If a task is marked Completed, it stays completed.</p>
        </div>
        <div class="card">
          <h2>Communication</h2>
          <p>Use chat to communicate with camp managers and admin.</p>
          <a class="btn btn-outline" href="chat.php">Open Chat</a>
        </div>
      </div>
    </section>

    <section id="vol-tasks" class="tab-section">
      <section class="card">
        <h2>View Assigned Relief Tasks</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Task</th>
                <th>Camp</th>
                <th>Status</th>
                <th>Due Date</th>
                <th>Description</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($tasks as $task): ?>
                <tr>
                  <td><?php echo htmlspecialchars($task['task_title']); ?></td>
                  <td><?php echo htmlspecialchars($task['camp_name'] ?? 'Not assigned'); ?></td>
                  <td>
                    <?php
                      $status_class = 'pending';
                      if($task['task_status'] === 'Completed') $status_class = 'ok';
                      if($task['task_status'] === 'In Progress') $status_class = 'pending';
                    ?>
                    <span class="status <?php echo $status_class; ?>"><?php echo htmlspecialchars($task['task_status']); ?></span>
                  </td>
                  <td><?php echo htmlspecialchars($task['due_date'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($task['task_description'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>

              <?php if(count($tasks) === 0): ?>
                <tr><td colspan="5">No relief tasks assigned yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="vol-status" class="tab-section">
      <section class="card">
        <h2>Update Task Completion Status</h2>
        <p class="anchor-note">Once a task is completed, it is locked as completed and cannot be changed again by the volunteer.</p>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Task</th>
                <th>Camp</th>
                <th>Current Status</th>
                <th>Update Status</th>
                <th>Save</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($tasks as $task): ?>
                <tr>
                  <td><?php echo htmlspecialchars($task['task_title']); ?></td>
                  <td><?php echo htmlspecialchars($task['camp_name'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($task['task_status']); ?></td>
                  <?php if($task['task_status'] === 'Completed'): ?>
                    <td><span class="status ok">Completed - Locked</span></td>
                    <td><button class="btn btn-outline" disabled>Already Completed</button></td>
                  <?php else: ?>
                    <form method="POST" action="process_volunteer.php">
                      <input type="hidden" name="action" value="update_task_status">
                      <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                      <td>
                        <select name="task_status" required>
                          <option value="Pending" <?php echo $task['task_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                          <option value="In Progress" <?php echo $task['task_status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                          <option value="Completed">Completed</option>
                        </select>
                      </td>
                      <td><button class="btn btn-success">Update</button></td>
                    </form>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>

              <?php if(count($tasks) === 0): ?>
                <tr><td colspan="5">No tasks available for status update.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="vol-delivery" class="tab-section">
      <form class="card" method="POST" action="process_volunteer.php">
        <input type="hidden" name="action" value="record_delivery">

        <h2>Record Delivered Supplies</h2>
        <p class="anchor-note">Only items already present in camp stock are available here. Delivery quantity cannot exceed available stock.</p>

        <div class="form-grid">
          <div>
            <label>Available Camp Stock</label>
            <select name="stock_id" required>
              <option value="">Select stock item</option>
              <?php foreach($stock_items as $stock): ?>
                <option value="<?php echo $stock['stock_id']; ?>">
                  <?php echo htmlspecialchars($stock['camp_name']); ?> —
                  <?php echo htmlspecialchars($stock['item_name']); ?>
                  (Available: <?php echo htmlspecialchars($stock['quantity']); ?> <?php echo htmlspecialchars($stock['unit']); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Quantity Delivered</label>
            <input type="number" name="quantity" min="1" required>
          </div>

          <div>
            <label>Delivered To / Area</label>
            <input name="delivered_to" placeholder="Example: Block A families / Field point">
          </div>

          <div>
            <label>Delivery Date</label>
            <input type="date" name="delivery_date" value="<?php echo date('Y-m-d'); ?>">
          </div>
        </div>

        <br>
        <label>Delivery Note</label>
        <textarea name="delivery_note"></textarea>
        <br>
        <button class="btn">Record Delivery</button>
      </form>

      <section class="section card">
        <h2>Delivered Supplies History</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Camp</th>
                <th>Item</th>
                <th>Quantity</th>
                <th>Delivered To</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($deliveries as $delivery): ?>
                <tr>
                  <td><?php echo htmlspecialchars($delivery['delivery_date']); ?></td>
                  <td><?php echo htmlspecialchars($delivery['camp_name']); ?></td>
                  <td><?php echo htmlspecialchars($delivery['item_name']); ?></td>
                  <td><?php echo htmlspecialchars($delivery['quantity']); ?> <?php echo htmlspecialchars($delivery['unit']); ?></td>
                  <td><?php echo htmlspecialchars($delivery['delivered_to']); ?></td>
                  <td><?php echo htmlspecialchars($delivery['delivery_note']); ?></td>
                </tr>
              <?php endforeach; ?>

              <?php if(count($deliveries) === 0): ?>
                <tr><td colspan="6">No delivered supplies recorded yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="vol-issues" class="tab-section">
      <form class="card" method="POST" action="process_volunteer.php">
        <input type="hidden" name="action" value="report_field_issue">

        <h2>Report Urgent Field Issue</h2>
        <p class="anchor-note">Report shortages, blocked access, medical needs, rescue needs or other field problems to camp managers/admin.</p>

        <div class="form-grid">
          <div>
            <label>Related Camp</label>
            <select name="camp_id">
              <option value="">Select camp if applicable</option>
              <?php foreach($assigned_camps as $camp): ?>
                <option value="<?php echo $camp['camp_id']; ?>"><?php echo htmlspecialchars($camp['camp_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Need Type</label>
            <select name="need_type" required>
              <option>Food</option>
              <option>Medicine</option>
              <option>Shelter</option>
              <option>Rescue</option>
              <option>Other</option>
            </select>
          </div>

          <div>
            <label>Urgency</label>
            <select name="urgency" required>
              <option>Normal</option>
              <option>Urgent</option>
              <option>Critical</option>
            </select>
          </div>
        </div>

        <br>
        <label>Issue Details</label>
        <textarea name="details" required></textarea>
        <br>
        <button class="btn btn-danger">Report Field Issue</button>
      </form>

      <section class="section card">
        <h2>My Reported Field Issues</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Need</th><th>Urgency</th><th>Details</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach($field_issues as $issue): ?>
                <tr>
                  <td><?php echo htmlspecialchars($issue['need_type']); ?></td>
                  <td><?php echo htmlspecialchars($issue['urgency']); ?></td>
                  <td><?php echo htmlspecialchars($issue['details']); ?></td>
                  <td><?php echo htmlspecialchars($issue['request_status']); ?></td>
                  <td><?php echo htmlspecialchars($issue['created_at']); ?></td>
                </tr>
              <?php endforeach; ?>

              <?php if(count($field_issues) === 0): ?>
                <tr><td colspan="5">No field issues reported yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

  </section>
</main>

<?php include "../includes/footer.php"; ?>
