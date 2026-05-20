<?php
$page_title = "Admin | Disaster Relief System";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/functions.php";
require_role(['Admin']);

$pending_users = $pdo->query("
    SELECT u.user_id, u.full_name, u.email, r.role_name, u.account_status
    FROM users u JOIN roles r ON u.role_id = r.role_id
    WHERE u.account_status = 'Pending'
    ORDER BY u.user_id DESC
")->fetchAll();

$stock_rows = $pdo->query("SELECT * FROM v_stock_shortage")->fetchAll();

$all_users = $pdo->query("
    SELECT u.user_id, u.full_name, u.email, r.role_name, u.account_status
    FROM users u JOIN roles r ON u.role_id = r.role_id
    ORDER BY u.user_id DESC
")->fetchAll();

$categories = $pdo->query("SELECT * FROM disaster_categories ORDER BY category_id DESC")->fetchAll();
$locations = $pdo->query("SELECT * FROM camp_locations ORDER BY location_id DESC")->fetchAll();

$categories_for_form = $pdo->query("SELECT category_id, category_name FROM disaster_categories ORDER BY category_name")->fetchAll();
$locations_for_form = $pdo->query("SELECT location_id, location_name FROM camp_locations ORDER BY location_name")->fetchAll();

$managers_for_form = $pdo->query("
    SELECT u.user_id, u.full_name
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    WHERE r.role_name = 'Camp Manager' AND u.account_status = 'Approved'
    ORDER BY u.full_name
")->fetchAll();

$camps = $pdo->query("
    SELECT rc.*, dc.category_name, cl.location_name, u.full_name AS manager_name
    FROM relief_camps rc
    LEFT JOIN disaster_categories dc ON rc.category_id = dc.category_id
    LEFT JOIN camp_locations cl ON rc.location_id = cl.location_id
    LEFT JOIN users u ON rc.manager_id = u.user_id
    ORDER BY rc.camp_id DESC
")->fetchAll();


$announcements = $pdo->query("SELECT * FROM announcements ORDER BY announcement_id DESC")->fetchAll();
?>
<?php include "../includes/header.php"; ?>
<main class="container dashboard">
  <aside class="sidebar">
    <h3>Admin</h3>
    <a class="active" onclick="showSection('admin-dashboard', this)">Dashboard</a>
    <a onclick="showSection('admin-managers', this)">Camp Managers</a>
    <a onclick="showSection('admin-camps', this)">Relief Camps</a>
    <a onclick="showSection('admin-approval', this)">Account Approval</a>
    <a onclick="showSection('admin-categories', this)">Disaster Categories</a>
    <a onclick="showSection('admin-locations', this)">Camp Locations</a>
    <a onclick="showSection('admin-stock', this)">Stock Monitor</a>
    <a onclick="showSection('admin-reports', this)">Reports</a>
    <a onclick="showSection('admin-announcements', this)">Announcements</a>
    <a href="chat.php">Chat</a>
  </aside>

  <section class="content">
    <div class="page-title"><span class="badge">Admin Dashboard</span><h1>Admin Panel</h1></div>

    <section id="admin-dashboard" class="tab-section active">
      <div class="kpi-row">
        <div class="kpi"><span>Total Users</span><strong><?php echo count_table($pdo, 'users'); ?></strong></div>
        <div class="kpi"><span>Camps</span><strong><?php echo count_table($pdo, 'relief_camps'); ?></strong></div>
        <div class="kpi"><span>Stock Alerts</span><strong><?php echo count_table($pdo, 'stock_alerts'); ?></strong></div>
        <div class="kpi"><span>Reports</span><strong><?php echo count_table($pdo, 'reports'); ?></strong></div>
      </div>
      <div class="card">
        <h2>Emergency Overview</h2>
        <p>This dashboard summarizes users, camps, stock alerts and reports from the database.</p>
      </div>
    </section>

    <section id="admin-managers" class="tab-section">
      <form class="card" method="POST" action="process_admin.php">
        <input type="hidden" name="action" value="add_camp_manager">
        <h2>Add Camp Manager</h2>
        <p class="anchor-note">Create an approved camp manager account directly.</p>
        <div class="form-grid">
          <div><label>Name</label><input name="full_name" required></div>
          <div><label>Email</label><input type="email" name="email" required></div>
          <div><label>Phone</label><input name="phone"></div>
          <div><label>Password</label><input type="password" name="password" required></div>
        </div><br>
        <button class="btn">Save Manager</button>
      </form>

      <section class="section card">
        <h2>All Users</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach($all_users as $u): ?>
              <tr>
                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                <td><?php echo htmlspecialchars($u['role_name']); ?></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td><?php echo htmlspecialchars($u['account_status']); ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>


    <section id="admin-camps" class="tab-section">
      <form class="card" method="POST" action="process_admin.php">
        <input type="hidden" name="action" value="add_camp">
        <h2>Add Relief Camp</h2>
        <p class="anchor-note">Use this form to add camps properly so they appear on the homepage and guest page.</p>

        <div class="form-grid">
          <div>
            <label>Camp Name</label>
            <input name="camp_name" required>
          </div>

          <div>
            <label>Disaster Category</label>
            <select name="category_id">
              <option value="">Select category</option>
              <?php foreach($categories_for_form as $cat): ?>
                <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Location</label>
            <select name="location_id">
              <option value="">Select location</option>
              <?php foreach($locations_for_form as $loc): ?>
                <option value="<?php echo $loc['location_id']; ?>"><?php echo htmlspecialchars($loc['location_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Camp Manager</label>
            <select name="manager_id">
              <option value="">Select manager</option>
              <?php foreach($managers_for_form as $manager): ?>
                <option value="<?php echo $manager['user_id']; ?>"><?php echo htmlspecialchars($manager['full_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Capacity</label>
            <input type="number" name="capacity" min="0" value="0">
          </div>

          <div>
            <label>Current Population</label>
            <input type="number" name="current_population" min="0" value="0">
          </div>

          <div>
            <label>Status</label>
            <select name="status">
              <option value="Active">Active</option>
              <option value="Standby">Standby</option>
              <option value="Closed">Closed</option>
            </select>
          </div>
        </div>

        <br>
        <button class="btn">Add Camp</button>
      </form>

      <section class="section card">
        <h2>Existing Relief Camps</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Camp</th>
                <th>Category</th>
                <th>Location</th>
                <th>Manager</th>
                <th>Capacity</th>
                <th>Population</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($camps as $camp): ?>
                <tr>
                  <td><?php echo htmlspecialchars($camp['camp_name']); ?></td>
                  <td><?php echo htmlspecialchars($camp['category_name'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($camp['location_name'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($camp['manager_name'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($camp['capacity']); ?></td>
                  <td><?php echo htmlspecialchars($camp['current_population']); ?></td>
                  <td><span class="status ok"><?php echo htmlspecialchars($camp['status']); ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="admin-approval" class="tab-section">
      <section class="card">
        <h2>Pending Account Approvals</h2>
        <p class="anchor-note">Approve or reject newly registered accounts.</p>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach($pending_users as $u): ?>
              <tr>
                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                <td><?php echo htmlspecialchars($u['role_name']); ?></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td><span class="status pending"><?php echo htmlspecialchars($u['account_status']); ?></span></td>
                <td>
                  <form method="POST" action="process_admin.php" style="display:inline">
                    <input type="hidden" name="action" value="approve_user">
                    <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                    <button class="btn btn-success">Approve</button>
                  </form>
                  <form method="POST" action="process_admin.php" style="display:inline">
                    <input type="hidden" name="action" value="reject_user">
                    <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                    <button class="btn btn-danger">Reject</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="admin-categories" class="tab-section">
      <form class="card" method="POST" action="process_admin.php">
        <input type="hidden" name="action" value="add_category">
        <h2>Manage Disaster Categories</h2>
        <div class="form-grid">
          <div><label>Category Name</label><input name="category_name" required></div>
          <div><label>Description</label><input name="description"></div>
        </div><br>
        <button class="btn">Add Category</button>
      </form>

      <section class="section card">
        <h2>Existing Categories</h2>
        <div class="table-wrap"><table><thead><tr><th>ID</th><th>Category</th><th>Description</th></tr></thead><tbody>
        <?php foreach($categories as $c): ?>
          <tr><td><?php echo $c['category_id']; ?></td><td><?php echo htmlspecialchars($c['category_name']); ?></td><td><?php echo htmlspecialchars($c['description']); ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      </section>
    </section>

    <section id="admin-locations" class="tab-section">
      <form class="card" method="POST" action="process_admin.php">
        <input type="hidden" name="action" value="add_location">
        <h2>Manage Camp Locations</h2>
        <div class="form-grid">
          <div><label>Location Name</label><input name="location_name" required></div>
          <div><label>District</label><input name="district"></div>
        </div><br>
        <label>Address</label><textarea name="address"></textarea><br>
        <button class="btn">Add Location</button>
      </form>

      <section class="section card">
        <h2>Existing Locations</h2>
        <div class="table-wrap"><table><thead><tr><th>ID</th><th>Location</th><th>District</th><th>Address</th></tr></thead><tbody>
        <?php foreach($locations as $l): ?>
          <tr><td><?php echo $l['location_id']; ?></td><td><?php echo htmlspecialchars($l['location_name']); ?></td><td><?php echo htmlspecialchars($l['district']); ?></td><td><?php echo htmlspecialchars($l['address']); ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      </section>
    </section>

    <section id="admin-stock" class="tab-section">
      <section class="card">
        <h2>Supply Stock Across Camps</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Camp</th><th>Item</th><th>Quantity</th><th>Minimum Required</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach($stock_rows as $row): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['camp_name']); ?></td>
                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                <td><?php echo htmlspecialchars($row['minimum_required']); ?></td>
                <td><span class="status <?php echo $row['stock_status']=='Shortage' ? 'bad' : 'ok'; ?>"><?php echo htmlspecialchars($row['stock_status']); ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="admin-reports" class="tab-section">

  <div class="card">

    <h2>Reports</h2>

    <p>Download camp summary and aid distribution reports as PDF.</p>

    <a class="btn btn-outline" href="generate_camp_report.php">

      Download Camp Report PDF

    </a>

  </div>

</section>
    <section id="admin-announcements" class="tab-section">
      <form class="card" method="POST" action="process_admin.php">
        <input type="hidden" name="action" value="send_announcement">
        <h2>Send Urgent Alert / Announcement</h2>
        <label>Title</label><input name="title" required><br><br>
        <label>Audience</label>
        <select name="audience">
          <option>All</option><option>Camp Managers</option><option>Volunteers</option><option>Donors</option><option>Affected People</option>
        </select><br><br>
        <label>Message</label><textarea name="message" required></textarea><br>
        <button class="btn">Send Alert</button>
      </form>

      <section class="section card">
        <h2>Previous Announcements</h2>
        <div class="table-wrap"><table><thead><tr><th>Title</th><th>Audience</th><th>Date</th></tr></thead><tbody>
        <?php foreach($announcements as $a): ?>
          <tr><td><?php echo htmlspecialchars($a['title']); ?></td><td><?php echo htmlspecialchars($a['audience']); ?></td><td><?php echo htmlspecialchars($a['created_at']); ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      </section>
    </section>
  </section>
</main>
<?php include "../includes/footer.php"; ?>
