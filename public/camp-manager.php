<?php
$page_title = "Camp Manager | Disaster Relief System";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/functions.php";
require_role(['Camp Manager', 'Admin']);

$user_id = $_SESSION['user_id'];
$is_admin = current_user_role() === 'Admin';


if ($is_admin) {
    $assigned_camps = $pdo->query("
        SELECT rc.*, dc.category_name, cl.location_name
        FROM relief_camps rc
        LEFT JOIN disaster_categories dc ON rc.category_id = dc.category_id
        LEFT JOIN camp_locations cl ON rc.location_id = cl.location_id
        ORDER BY rc.camp_id DESC
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT rc.*, dc.category_name, cl.location_name
        FROM relief_camps rc
        LEFT JOIN disaster_categories dc ON rc.category_id = dc.category_id
        LEFT JOIN camp_locations cl ON rc.location_id = cl.location_id
        WHERE rc.manager_id = ?
        ORDER BY rc.camp_id DESC
    ");
    $stmt->execute([$user_id]);
    $assigned_camps = $stmt->fetchAll();
}

$selected_camp_id = $_GET['camp_id'] ?? ($assigned_camps[0]['camp_id'] ?? null);

$can_manage_selected_camp = false;
foreach ($assigned_camps as $camp) {
    if ($selected_camp_id == $camp['camp_id']) {
        $can_manage_selected_camp = true;
        $selected_camp = $camp;
        break;
    }
}

if (!$selected_camp_id || !$can_manage_selected_camp) {
    $selected_camp = null;
}

$items = $pdo->query("SELECT item_id, item_name, item_category, unit FROM supply_items ORDER BY item_category, item_name")->fetchAll();

$volunteers = $pdo->query("
    SELECT u.user_id, u.full_name
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    WHERE r.role_name='Volunteer' AND u.account_status='Approved'
    ORDER BY u.full_name
")->fetchAll();

$families = [];
$family_members = [];
$stock_rows = [];
$tasks = [];
$logs = [];

if ($selected_camp_id && $can_manage_selected_camp) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM affected_families
        WHERE camp_id = ?
        ORDER BY family_id DESC
    ");
    $stmt->execute([$selected_camp_id]);
    $families = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT fm.*, af.head_name
        FROM family_members fm
        JOIN affected_families af ON fm.family_id = af.family_id
        WHERE af.camp_id = ?
        ORDER BY fm.member_id DESC
    ");
    $stmt->execute([$selected_camp_id]);
    $family_members = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT
            cs.stock_id,
            cs.quantity,
            cs.minimum_required,
            cs.last_updated,
            si.item_id,
            si.item_name,
            si.item_category,
            si.unit,
            CASE
                WHEN cs.minimum_required IS NULL OR cs.minimum_required <= 0 THEN 'No Minimum Set'
                WHEN cs.quantity < cs.minimum_required THEN 'Shortage'
                WHEN cs.quantity <= (cs.minimum_required * 1.25) THEN 'Low Stock'
                ELSE 'OK'
            END AS stock_status
        FROM camp_stock cs
        JOIN supply_items si ON cs.item_id = si.item_id
        WHERE cs.camp_id = ?
        ORDER BY si.item_category, si.item_name
    ");
    $stmt->execute([$selected_camp_id]);
    $stock_rows = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT vt.*, u.full_name AS volunteer_name
        FROM volunteer_tasks vt
        LEFT JOIN users u ON vt.volunteer_id = u.user_id
        WHERE vt.camp_id = ?
        ORDER BY vt.task_id DESC
    ");
    $stmt->execute([$selected_camp_id]);
    $tasks = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT ad.*, af.head_name, si.item_name, si.unit, u.full_name AS distributed_by_name
        FROM aid_distribution ad
        JOIN affected_families af ON ad.family_id = af.family_id
        JOIN supply_items si ON ad.item_id = si.item_id
        LEFT JOIN users u ON ad.distributed_by = u.user_id
        WHERE ad.camp_id = ?
        ORDER BY ad.distribution_id DESC
    ");
    $stmt->execute([$selected_camp_id]);
    $logs = $stmt->fetchAll();
}

$total_members = 0;
foreach ($families as $f) {
    $total_members += (int)$f['total_members'];
}
?>
<?php include "../includes/header.php"; ?>

<main class="container dashboard">
  <aside class="sidebar">
    <h3>Camp Manager</h3>
    <a class="active" onclick="showSection('cm-dashboard', this)">Assigned Camp</a>
    <a onclick="showSection('cm-families', this)">Register Families</a>
    <a onclick="showSection('cm-individuals', this)">Add Individuals</a>
    <a onclick="showSection('cm-stock', this)">Food / Medicine / Shelter Stock</a>
    <a onclick="showSection('cm-tasks', this)">Assign Volunteers</a>
    <a onclick="showSection('cm-distribution', this)">Aid Distribution Logs</a>
    <a onclick="showSection('cm-report', this)">Camp Summary Report</a>
    <a href="chat.php">Chat</a>
  </aside>

  <section class="content">
    <div class="page-title">
      <span class="badge">Camp Manager Dashboard</span>
      <h1>Assigned Relief Camp Management</h1>
    </div>

    <?php if(count($assigned_camps) > 1): ?>
      <form class="card" method="GET" action="camp-manager.php" style="margin-bottom:20px">
        <label>Select Assigned Camp</label>
        <select name="camp_id" onchange="this.form.submit()">
          <?php foreach($assigned_camps as $camp): ?>
            <option value="<?php echo $camp['camp_id']; ?>" <?php echo $selected_camp_id == $camp['camp_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($camp['camp_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>

    <?php if(!$selected_camp): ?>
      <section class="card">
        <h2>No Assigned Relief Camp Found</h2>
        <p>You do not currently have a camp assigned. Ask the admin to assign you as manager from <strong>Admin > Relief Camps</strong>.</p>
      </section>
    <?php else: ?>

    <section id="cm-dashboard" class="tab-section active">
      <div class="kpi-row">
        <div class="kpi"><span>Camp Status</span><strong><?php echo htmlspecialchars($selected_camp['status']); ?></strong></div>
        <div class="kpi"><span>Registered Families</span><strong><?php echo count($families); ?></strong></div>
        <div class="kpi"><span>Registered People</span><strong><?php echo number_format($total_members); ?></strong></div>
        <div class="kpi"><span>Volunteer Tasks</span><strong><?php echo count($tasks); ?></strong></div>
      </div>

      <div class="grid grid-2">
        <div class="card">
          <h2><?php echo htmlspecialchars($selected_camp['camp_name']); ?></h2>
          <p><strong>Disaster:</strong> <?php echo htmlspecialchars($selected_camp['category_name'] ?? 'Not set'); ?></p>
          <p><strong>Location:</strong> <?php echo htmlspecialchars($selected_camp['location_name'] ?? 'Not set'); ?></p>
          <p><strong>Capacity:</strong> <?php echo htmlspecialchars($selected_camp['capacity']); ?></p>
          <p><strong>Current Population:</strong> <?php echo htmlspecialchars($selected_camp['current_population']); ?></p>
        </div>

        <div class="card">
          <h2>Camp Manager Actions</h2>
          <a class="btn btn-outline" href="chat.php">Open Chat</a>
        </div>
      </div>
    </section>

    <section id="cm-families" class="tab-section">
      <form class="card" method="POST" action="process_camp.php">
        <input type="hidden" name="action" value="register_family">
        <input type="hidden" name="camp_id" value="<?php echo $selected_camp_id; ?>">

        <h2>Register Affected Family</h2>
        <p class="anchor-note">This family will be registered under your assigned camp only.</p>

        <div class="form-grid">
          <div><label>Head of Family</label><input name="head_name" required></div>
          <div><label>Phone</label><input name="phone"></div>
          <div><label>Total Members</label><input type="number" name="total_members" min="1" value="1"></div>
          <div><label>Registration Date</label><input type="date" name="registration_date" value="<?php echo date('Y-m-d'); ?>"></div>
        </div>

        <br>
        <label>Address / Details</label>
        <textarea name="address"></textarea>
        <br>
        <button class="btn">Register Family</button>
      </form>

      <section class="section card">
        <h2>Registered Families</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Head Name</th><th>Phone</th><th>Members</th><th>Address</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
              <?php foreach($families as $family): ?>
                <tr>
                  <td><?php echo htmlspecialchars($family['head_name']); ?></td>
                  <td><?php echo htmlspecialchars($family['phone']); ?></td>
                  <td><?php echo htmlspecialchars($family['total_members']); ?></td>
                  <td><?php echo htmlspecialchars($family['address']); ?></td>
                  <td><?php echo htmlspecialchars($family['status']); ?></td>
                  <td><?php echo htmlspecialchars($family['registration_date']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="cm-individuals" class="tab-section">
      <form class="card" method="POST" action="process_camp.php">
        <input type="hidden" name="action" value="add_member">
        <input type="hidden" name="camp_id" value="<?php echo $selected_camp_id; ?>">

        <h2>Add Family Member / Individual</h2>
        <p class="anchor-note">Select a registered family and add individual member details.</p>

        <label>Family</label>
        <select name="family_id" required>
          <option value="">Select family</option>
          <?php foreach($families as $family): ?>
            <option value="<?php echo $family['family_id']; ?>">
              <?php echo htmlspecialchars($family['head_name']); ?> — Family ID <?php echo $family['family_id']; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <br><br>

        <div class="form-grid">
          <div><label>Member Name</label><input name="member_name" required></div>
          <div><label>Age</label><input type="number" name="age" min="0"></div>
          <div>
            <label>Gender</label>
            <select name="gender">
              <option>Male</option>
              <option>Female</option>
              <option>Other</option>
            </select>
          </div>
          <div><label>Relation to Head</label><input name="relation_to_head"></div>
        </div>

        <br>
        <label>Health Note</label>
        <textarea name="health_note" placeholder="Optional medical/special need note"></textarea>
        <br>
        <button class="btn">Add Individual</button>
      </form>

      <section class="section card">
        <h2>Registered Individuals</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Name</th><th>Family Head</th><th>Age</th><th>Gender</th><th>Relation</th><th>Health Note</th></tr></thead>
            <tbody>
              <?php foreach($family_members as $member): ?>
                <tr>
                  <td><?php echo htmlspecialchars($member['member_name']); ?></td>
                  <td><?php echo htmlspecialchars($member['head_name']); ?></td>
                  <td><?php echo htmlspecialchars($member['age']); ?></td>
                  <td><?php echo htmlspecialchars($member['gender']); ?></td>
                  <td><?php echo htmlspecialchars($member['relation_to_head']); ?></td>
                  <td><?php echo htmlspecialchars($member['health_note']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="cm-stock" class="tab-section">
      <form class="card" method="POST" action="process_camp.php">
        <input type="hidden" name="action" value="update_stock">
        <input type="hidden" name="camp_id" value="<?php echo $selected_camp_id; ?>">

        <h2>Update Food, Medicine and Shelter Stock</h2>
        <p class="anchor-note">Choose an item, update current quantity and minimum required level. Shortage alerts will be synced automatically.</p>

        <div class="form-grid">
          <div>
            <label>Supply Item</label>
            <select name="item_id" required>
              <option value="">Select item</option>
              <?php foreach($items as $item): ?>
                <?php if(in_array($item['item_category'], ['Food', 'Medicine', 'Shelter', 'Water', 'Clothing', 'Other'])): ?>
                  <option value="<?php echo $item['item_id']; ?>">
                    <?php echo htmlspecialchars($item['item_name']); ?> — <?php echo htmlspecialchars($item['item_category']); ?>
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>

          <div><label>Current Quantity</label><input type="number" name="quantity" min="0" required></div>
          <div><label>Minimum Required</label><input type="number" name="minimum_required" min="0" required></div>
        </div>

        <br>
        <button class="btn">Update Stock</button>
      </form>

      <section class="section card">
        <h2>Current Camp Stock</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Item</th><th>Category</th><th>Quantity</th><th>Minimum Required</th><th>Status</th><th>Last Updated</th></tr></thead>
            <tbody>
              <?php foreach($stock_rows as $row): ?>
                <?php
                  $status_class = 'ok';
                  if($row['stock_status'] === 'Shortage') $status_class = 'bad';
                  if($row['stock_status'] === 'Low Stock' || $row['stock_status'] === 'No Minimum Set') $status_class = 'pending';
                ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                  <td><?php echo htmlspecialchars($row['item_category']); ?></td>
                  <td><?php echo htmlspecialchars($row['quantity']); ?> <?php echo htmlspecialchars($row['unit']); ?></td>
                  <td><?php echo htmlspecialchars($row['minimum_required']); ?> <?php echo htmlspecialchars($row['unit']); ?></td>
                  <td><span class="status <?php echo $status_class; ?>"><?php echo htmlspecialchars($row['stock_status']); ?></span></td>
                  <td><?php echo htmlspecialchars($row['last_updated']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="cm-tasks" class="tab-section">
      <form class="card" method="POST" action="process_camp.php">
        <input type="hidden" name="action" value="assign_task">
        <input type="hidden" name="camp_id" value="<?php echo $selected_camp_id; ?>">

        <h2>Assign Volunteers to Tasks</h2>

        <div class="form-grid">
          <div>
            <label>Volunteer</label>
            <select name="volunteer_id" required>
              <option value="">Select volunteer</option>
              <?php foreach($volunteers as $volunteer): ?>
                <option value="<?php echo $volunteer['user_id']; ?>"><?php echo htmlspecialchars($volunteer['full_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div><label>Due Date</label><input type="date" name="due_date"></div>
        </div>

        <br>
        <label>Task Title</label>
        <input name="task_title" required>
        <br><br>

        <label>Task Description</label>
        <textarea name="task_description"></textarea>
        <br>

        <button class="btn">Assign Task</button>
      </form>

      <section class="section card">
        <h2>Assigned Volunteer Tasks</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Task</th><th>Volunteer</th><th>Status</th><th>Due Date</th><th>Description</th></tr></thead>
            <tbody>
              <?php foreach($tasks as $task): ?>
                <tr>
                  <td><?php echo htmlspecialchars($task['task_title']); ?></td>
                  <td><?php echo htmlspecialchars($task['volunteer_name'] ?? 'Unassigned'); ?></td>
                  <td><?php echo htmlspecialchars($task['task_status']); ?></td>
                  <td><?php echo htmlspecialchars($task['due_date']); ?></td>
                  <td><?php echo htmlspecialchars($task['task_description']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="cm-distribution" class="tab-section">
      <form class="card" method="POST" action="process_camp.php">
        <input type="hidden" name="action" value="record_distribution">
        <input type="hidden" name="camp_id" value="<?php echo $selected_camp_id; ?>">

        <h2>Record Aid Distribution</h2>
        <p class="anchor-note">Recording distribution will also subtract the distributed quantity from camp stock if the item exists in stock.</p>

        <div class="form-grid">
          <div>
            <label>Family</label>
            <select name="family_id" required>
              <option value="">Select family</option>
              <?php foreach($families as $family): ?>
                <option value="<?php echo $family['family_id']; ?>">
                  <?php echo htmlspecialchars($family['head_name']); ?> — Family ID <?php echo $family['family_id']; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Item</label>
            <select name="item_id" required>
              <option value="">Select item</option>
              <?php foreach($items as $item): ?>
                <option value="<?php echo $item['item_id']; ?>">
                  <?php echo htmlspecialchars($item['item_name']); ?> — <?php echo htmlspecialchars($item['unit']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div><label>Quantity</label><input type="number" name="quantity" min="1" required></div>
          <div><label>Distribution Date</label><input type="date" name="distribution_date" value="<?php echo date('Y-m-d'); ?>"></div>
        </div>

        <br>
        <label>Note</label>
        <textarea name="note"></textarea>
        <br>

        <button class="btn">Record Distribution</button>
      </form>

      <section class="section card">
        <h2>Aid Distribution Logs</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Date</th><th>Family</th><th>Item</th><th>Quantity</th><th>Recorded By</th><th>Note</th></tr></thead>
            <tbody>
              <?php foreach($logs as $log): ?>
                <tr>
                  <td><?php echo htmlspecialchars($log['distribution_date']); ?></td>
                  <td><?php echo htmlspecialchars($log['head_name']); ?></td>
                  <td><?php echo htmlspecialchars($log['item_name']); ?></td>
                  <td><?php echo htmlspecialchars($log['quantity']); ?> <?php echo htmlspecialchars($log['unit']); ?></td>
                  <td><?php echo htmlspecialchars($log['distributed_by_name'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($log['note']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="cm-report" class="tab-section">
      <div class="card">
        <h2>Generate Camp-wise Summary Report</h2>
        <p>This report includes assigned camp information, registered families, individuals, stock and aid distribution summary.</p>
        <a class="btn btn-outline" href="generate_camp_report.php?camp_id=<?php echo $selected_camp_id; ?>">Download Camp Summary PDF</a>
      </div>
    </section>

    <?php endif; ?>
  </section>
</main>

<?php include "../includes/footer.php"; ?>
