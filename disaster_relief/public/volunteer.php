<?php
$page_title = "Volunteer | Disaster Relief System";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/functions.php";
require_role(['Volunteer', 'Admin']);

$user_id = $_SESSION['user_id'];
$tasks = $pdo->prepare("
    SELECT vt.*, rc.camp_name 
    FROM volunteer_tasks vt
    LEFT JOIN relief_camps rc ON vt.camp_id=rc.camp_id
    WHERE vt.volunteer_id=? OR ? IN (SELECT user_id FROM users u JOIN roles r ON u.role_id=r.role_id WHERE r.role_name='Admin')
    ORDER BY vt.task_id DESC
");
$tasks->execute([$user_id, $user_id]);
$tasks = $tasks->fetchAll();

include "../includes/header.php";
?>
<main class="container dashboard">
  <aside class="sidebar">
    <h3>Volunteer</h3>
    <a class="active" onclick="showSection('vol-dashboard', this)">Dashboard</a>
    <a onclick="showSection('vol-tasks', this)">Assigned Tasks</a>
    <a onclick="showSection('vol-status', this)">Update Task Status</a>
    <a onclick="showSection('vol-delivery', this)">Record Delivered Supplies</a>
    <a onclick="showSection('vol-issue', this)">Report Urgent Issue</a>
    <a href="chat.php">Chat</a>
  </aside>

  <section class="content">
    <div class="page-title"><span class="badge">Volunteer Dashboard</span><h1>Volunteer Panel</h1></div>

    <section id="vol-dashboard" class="tab-section active">
      <div class="kpi-row">
        <div class="kpi"><span>Assigned Tasks</span><strong><?php echo count($tasks); ?></strong></div>
        <div class="kpi"><span>Pending</span><strong><?php echo count(array_filter($tasks, fn($t)=>$t['task_status']=='Pending')); ?></strong></div>
        <div class="kpi"><span>Completed</span><strong><?php echo count(array_filter($tasks, fn($t)=>$t['task_status']=='Completed')); ?></strong></div>
        <div class="kpi"><span>Reports</span><strong><?php echo count_table($pdo, 'help_requests'); ?></strong></div>
      </div>
    </section>

    <section id="vol-tasks" class="tab-section">
      <section class="card">
        <h2>Assigned Tasks</h2>
        <div class="table-wrap">
          <table><thead><tr><th>Task</th><th>Camp</th><th>Status</th><th>Due Date</th></tr></thead><tbody>
            <?php foreach($tasks as $t): ?>
              <tr><td><?php echo htmlspecialchars($t['task_title']); ?></td><td><?php echo htmlspecialchars($t['camp_name'] ?? ''); ?></td><td><?php echo htmlspecialchars($t['task_status']); ?></td><td><?php echo htmlspecialchars($t['due_date'] ?? ''); ?></td></tr>
            <?php endforeach; ?>
          </tbody></table>
        </div>
      </section>
    </section>

    <section id="vol-status" class="tab-section">
      <form class="card" method="POST" action="process_volunteer.php">
        <input type="hidden" name="action" value="update_task">
        <h2>Update Task Completion Status</h2>
        <label>Assigned Task</label>
        <select name="task_id"><?php foreach($tasks as $t): ?><option value="<?php echo $t['task_id']; ?>"><?php echo htmlspecialchars($t['task_title']); ?></option><?php endforeach; ?></select><br><br>
        <label>Status</label><select name="task_status"><option>Pending</option><option>In Progress</option><option>Completed</option></select><br><br>
        <button class="btn">Update</button>
      </form>
    </section>

    <section id="vol-delivery" class="tab-section">
      <form class="card" method="POST" onsubmit="fakeSubmit(event,'Delivered supplies recorded demo. Add database table if required.')">
        <h2>Record Delivered Supplies</h2>
        <div class="form-grid">
          <div><label>Supply Type</label><input required></div>
          <div><label>Quantity</label><input type="number" required></div>
          <div><label>Delivered To</label><input></div>
          <div><label>Date</label><input type="date"></div>
        </div><br>
        <button class="btn">Record Delivery</button>
      </form>
    </section>

    <section id="vol-issue" class="tab-section">
      <form class="card" method="POST" action="process_volunteer.php">
        <input type="hidden" name="action" value="report_issue">
        <h2>Report Urgent Field Issue</h2>
        <label>Need Type</label><select name="need_type"><option>Food</option><option>Medicine</option><option>Shelter</option><option>Rescue</option><option>Other</option></select><br><br>
        <label>Urgency</label><select name="urgency"><option>Normal</option><option>Urgent</option><option>Critical</option></select><br><br>
        <label>Details</label><textarea name="details" required></textarea><br>
        <button class="btn btn-danger">Report Issue</button>
      </form>
    </section>
  </section>
</main>
<?php include "../includes/footer.php"; ?>
