<?php
$page_title = "Camp Manager | Disaster Relief System";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/functions.php";
require_role(['Camp Manager', 'Admin']);

$camps = $pdo->query("SELECT camp_id, camp_name FROM relief_camps ORDER BY camp_name")->fetchAll();
$items = $pdo->query("SELECT item_id, item_name FROM supply_items ORDER BY item_name")->fetchAll();
$families = $pdo->query("SELECT family_id, head_name FROM affected_families ORDER BY family_id DESC")->fetchAll();
$volunteers = $pdo->query("SELECT u.user_id, u.full_name FROM users u JOIN roles r ON u.role_id=r.role_id WHERE r.role_name='Volunteer' AND u.account_status='Approved'")->fetchAll();
$logs = $pdo->query("
    SELECT ad.distribution_date, af.head_name, si.item_name, ad.quantity
    FROM aid_distribution ad
    JOIN affected_families af ON ad.family_id=af.family_id
    JOIN supply_items si ON ad.item_id=si.item_id
    ORDER BY ad.distribution_id DESC
")->fetchAll();
include "../includes/header.php";
?>
<main class="container dashboard">
  <aside class="sidebar">
    <h3>Camp Manager</h3>
    <a class="active" onclick="showSection('cm-dashboard', this)">Dashboard</a>
    <a onclick="showSection('cm-families', this)">Register Family</a>
    <a onclick="showSection('cm-members', this)">Add Family Member</a>
    <a onclick="showSection('cm-stock', this)">Update Stock</a>
    <a onclick="showSection('cm-tasks', this)">Assign Volunteers</a>
    <a onclick="showSection('cm-distribution', this)">Distribution Logs</a>
    <a onclick="showSection('cm-report', this)">Camp Report</a>
    <a href="chat.php">Chat</a>
  </aside>

  <section class="content">
    <div class="page-title"><span class="badge">Camp Manager Dashboard</span><h1>Camp Manager Panel</h1></div>

    <section id="cm-dashboard" class="tab-section active">
      <div class="kpi-row">
        <div class="kpi"><span>Families</span><strong><?php echo count_table($pdo, 'affected_families'); ?></strong></div>
        <div class="kpi"><span>Tasks</span><strong><?php echo count_table($pdo, 'volunteer_tasks'); ?></strong></div>
        <div class="kpi"><span>Distributions</span><strong><?php echo count_table($pdo, 'aid_distribution'); ?></strong></div>
        <div class="kpi"><span>Help Requests</span><strong><?php echo count_table($pdo, 'help_requests'); ?></strong></div>
      </div>
      <div class="card"><h2>Camp Summary</h2><p>Use the sidebar to manage families, members, stocks, volunteers and reports.</p></div>
    </section>

    <section id="cm-families" class="tab-section">
      <form class="card" method="POST" action="process_camp.php">
        <input type="hidden" name="action" value="register_family">
        <h2>Register Affected Family</h2>
        <label>Camp</label><select name="camp_id"><?php foreach($camps as $c): ?><option value="<?php echo $c['camp_id']; ?>"><?php echo htmlspecialchars($c['camp_name']); ?></option><?php endforeach; ?></select><br><br>
        <div class="form-grid">
          <div><label>Head of Family</label><input name="head_name" required></div>
          <div><label>Phone</label><input name="phone"></div>
          <div><label>Total Members</label><input type="number" name="total_members"></div>
          <div><label>Registration Date</label><input type="date" name="registration_date"></div>
        </div><br>
        <label>Address</label><textarea name="address"></textarea><br>
        <button class="btn">Register Family</button>
      </form>
    </section>

    <section id="cm-members" class="tab-section">
      <form class="card" method="POST" action="process_camp.php">
        <input type="hidden" name="action" value="add_member">
        <h2>Add Family Member</h2>
        <label>Family</label><select name="family_id"><?php foreach($families as $f): ?><option value="<?php echo $f['family_id']; ?>"><?php echo htmlspecialchars($f['head_name']); ?></option><?php endforeach; ?></select><br><br>
        <div class="form-grid">
          <div><label>Member Name</label><input name="member_name" required></div>
          <div><label>Age</label><input type="number" name="age"></div>
          <div><label>Gender</label><select name="gender"><option>Male</option><option>Female</option><option>Other</option></select></div>
          <div><label>Relation</label><input name="relation_to_head"></div>
        </div><br>
        <button class="btn">Add Member</button>
      </form>
    </section>

    <section id="cm-stock" class="tab-section">
      <form class="card" method="POST" action="process_camp.php">
        <input type="hidden" name="action" value="update_stock">
        <h2>Update Food, Medicine and Shelter Stock</h2>
        <label>Camp</label><select name="camp_id"><?php foreach($camps as $c): ?><option value="<?php echo $c['camp_id']; ?>"><?php echo htmlspecialchars($c['camp_name']); ?></option><?php endforeach; ?></select><br><br>
        <label>Item</label><select name="item_id"><?php foreach($items as $i): ?><option value="<?php echo $i['item_id']; ?>"><?php echo htmlspecialchars($i['item_name']); ?></option><?php endforeach; ?></select><br><br>
        <div class="form-grid">
          <div><label>Quantity</label><input type="number" name="quantity" required></div>
          <div><label>Minimum Required</label><input type="number" name="minimum_required" required></div>
        </div><br>
        <button class="btn">Update Stock</button>
      </form>
    </section>

    <section id="cm-tasks" class="tab-section">
      <form class="card" method="POST" action="process_camp.php">
        <input type="hidden" name="action" value="assign_task">
        <h2>Assign Volunteers to Tasks</h2>
        <label>Camp</label><select name="camp_id"><?php foreach($camps as $c): ?><option value="<?php echo $c['camp_id']; ?>"><?php echo htmlspecialchars($c['camp_name']); ?></option><?php endforeach; ?></select><br><br>
        <label>Volunteer</label><select name="volunteer_id"><?php foreach($volunteers as $v): ?><option value="<?php echo $v['user_id']; ?>"><?php echo htmlspecialchars($v['full_name']); ?></option><?php endforeach; ?></select><br><br>
        <label>Task Title</label><input name="task_title" required><br><br>
        <label>Task Description</label><textarea name="task_description"></textarea><br>
        <button class="btn">Assign Task</button>
      </form>
    </section>

    <section id="cm-distribution" class="tab-section">
      <section class="card">
        <h2>Aid Distribution Logs</h2>
        <div class="table-wrap">
          <table><thead><tr><th>Date</th><th>Family</th><th>Item</th><th>Quantity</th></tr></thead><tbody>
            <?php foreach($logs as $log): ?>
              <tr><td><?php echo htmlspecialchars($log['distribution_date']); ?></td><td><?php echo htmlspecialchars($log['head_name']); ?></td><td><?php echo htmlspecialchars($log['item_name']); ?></td><td><?php echo htmlspecialchars($log['quantity']); ?></td></tr>
            <?php endforeach; ?>
          </tbody></table>
        </div>
      </section>
    </section>

    <section id="cm-report" class="tab-section">
      <div class="card">
        <h2>Camp-wise Summary Report</h2>
        <p>This section is for camp-wise PDF report generation. For a course project, keep it as a report preview or connect FPDF/TCPDF later.</p>
        <button class="btn btn-outline" onclick="showAlert('Camp PDF report demo triggered.')">Generate Camp PDF Demo</button>
      </div>
    </section>
  </section>
</main>
<?php include "../includes/footer.php"; ?>
