<?php
$page_title = "Admin | Disaster Relief System";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/functions.php";
require_role(['Admin']);

$roles = $pdo->query("SELECT * FROM roles ORDER BY role_name")->fetchAll();

$pending_users = $pdo->query("
    SELECT u.user_id, u.full_name, u.email, u.phone, r.role_name, u.account_status
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    WHERE u.account_status = 'Pending'
    ORDER BY u.user_id DESC
")->fetchAll();

$all_users = $pdo->query("
    SELECT u.user_id, u.full_name, u.email, u.phone, u.role_id, r.role_name, u.account_status
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
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

$stock_rows = $pdo->query("
    SELECT
        cs.stock_id,
        cs.camp_id,
        cs.item_id,
        rc.camp_name,
        si.item_name,
        si.item_category,
        si.unit,
        cs.quantity,
        cs.minimum_required,
        cs.last_updated,
        CASE
            WHEN cs.minimum_required IS NULL OR cs.minimum_required <= 0 THEN 'No Minimum Set'
            WHEN cs.quantity < cs.minimum_required THEN 'Shortage'
            WHEN cs.quantity <= (cs.minimum_required * 1.25) THEN 'Low Stock'
            ELSE 'OK'
        END AS stock_status,
        (
            SELECT COUNT(*)
            FROM stock_alerts sa
            WHERE sa.camp_id = cs.camp_id
            AND sa.item_id = cs.item_id
            AND sa.alert_status = 'Open'
        ) AS open_alert_count
    FROM camp_stock cs
    JOIN relief_camps rc ON cs.camp_id = rc.camp_id
    JOIN supply_items si ON cs.item_id = si.item_id
    ORDER BY rc.camp_name, si.item_category, si.item_name
")->fetchAll();

$announcements = $pdo->query("SELECT * FROM announcements ORDER BY announcement_id DESC")->fetchAll();

$all_donations_for_usage = $pdo->query("
    SELECT d.donation_id, d.donation_type, d.amount, d.quantity, u.full_name AS donor_name, si.item_name, si.unit, si.item_id
    FROM donations d
    LEFT JOIN users u ON d.donor_id = u.user_id
    LEFT JOIN supply_items si ON d.item_id = si.item_id
    WHERE d.donation_status IN ('Received','Distributed')
    ORDER BY d.donation_id DESC
")->fetchAll();

$camps_for_usage = $pdo->query("SELECT camp_id, camp_name FROM relief_camps ORDER BY camp_name")->fetchAll();

$donation_usage_records = $pdo->query("
    SELECT du.*, d.donation_type, d.amount, donor.full_name AS donor_name, rc.camp_name, si.item_name, si.unit
    FROM donation_usage du
    JOIN donations d ON du.donation_id = d.donation_id
    LEFT JOIN users donor ON d.donor_id = donor.user_id
    LEFT JOIN relief_camps rc ON du.camp_id = rc.camp_id
    LEFT JOIN supply_items si ON du.item_id = si.item_id
    ORDER BY du.usage_id DESC
")->fetchAll();


$pdo->exec("
    CREATE TABLE IF NOT EXISTS affected_user_links (
        link_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        family_id INT,
        chat_allowed TINYINT(1) DEFAULT 0,
        support_note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (family_id) REFERENCES affected_families(family_id) ON DELETE SET NULL
    )
");

$affected_users_for_support = $pdo->query("
    SELECT u.user_id, u.full_name, u.email, u.phone,
           aul.link_id, aul.family_id, aul.chat_allowed, aul.support_note,
           af.head_name, rc.camp_name
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN affected_user_links aul ON u.user_id = aul.user_id
    LEFT JOIN affected_families af ON aul.family_id = af.family_id
    LEFT JOIN relief_camps rc ON af.camp_id = rc.camp_id
    WHERE r.role_name = 'Affected Person'
    ORDER BY u.user_id DESC
")->fetchAll();

$families_for_support = $pdo->query("
    SELECT af.family_id, af.head_name, af.phone, rc.camp_name
    FROM affected_families af
    LEFT JOIN relief_camps rc ON af.camp_id = rc.camp_id
    ORDER BY af.family_id DESC
")->fetchAll();

$affected_help_requests = $pdo->query("
    SELECT hr.*, u.full_name AS affected_name, af.head_name, rc.camp_name
    FROM help_requests hr
    LEFT JOIN users u ON hr.affected_user_id = u.user_id
    LEFT JOIN affected_families af ON hr.family_id = af.family_id
    LEFT JOIN relief_camps rc ON af.camp_id = rc.camp_id
    ORDER BY hr.request_id DESC
")->fetchAll();

?>
<?php include "../includes/header.php"; ?>

<main class="container dashboard">
  <aside class="sidebar">
    <h3>Admin</h3>
    <a class="active" onclick="showSection('admin-dashboard', this)">Dashboard</a>
    <a onclick="showSection('admin-users', this)">Users</a>
    <a onclick="showSection('admin-managers', this)">Camp Managers</a>
    <a onclick="showSection('admin-camps', this)">Relief Camps</a>
    <a onclick="showSection('admin-approval', this)">Account Approval</a>
    <a onclick="showSection('admin-affected-support', this)">Affected Support</a>
    <a onclick="showSection('admin-categories', this)">Disaster Categories</a>
    <a onclick="showSection('admin-locations', this)">Camp Locations</a>
    <a onclick="showSection('admin-stock', this)">Stock Monitor</a>
    <a onclick="showSection('admin-reports', this)">Reports</a>
    <a onclick="showSection('admin-donation-usage', this)">Donation Usage</a>
    <a onclick="showSection('admin-announcements', this)">Announcements</a>
    <a href="chat.php">Chat</a>
  </aside>

  <section class="content">
    <div class="page-title">
      <span class="badge">Admin Dashboard</span>
      <h1>Admin Panel</h1>
    </div>

    <section id="admin-dashboard" class="tab-section active">
      <div class="kpi-row">
        <div class="kpi"><span>Total Users</span><strong><?php echo count_table($pdo, 'users'); ?></strong></div>
        <div class="kpi"><span>Camps</span><strong><?php echo count_table($pdo, 'relief_camps'); ?></strong></div>
        <div class="kpi"><span>Stock Alerts</span><strong><?php echo count_table($pdo, 'stock_alerts'); ?></strong></div>
        <div class="kpi"><span>Reports</span><strong><?php echo count_table($pdo, 'reports'); ?></strong></div>
      </div>

      <div class="card">
        <h2>Emergency Overview</h2>
      </div>
    </section>

    <section id="admin-users" class="tab-section">
      <section class="card">
        <h2>Edit or Remove Users</h2>
        <p class="anchor-note">You can update user details, change role/status, reset password, or remove a user. The logged-in admin cannot delete their own account.</p>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>User Details</th>
                <th>Role / Status</th>
                <th>Optional Password Reset</th>
                <th>Save</th>
                <th>Remove</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($all_users as $u): ?>
                <tr>
                  <form method="POST" action="process_admin.php">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">

                    <td>
                      <label>Name</label>
                      <input name="full_name" value="<?php echo htmlspecialchars($u['full_name']); ?>" required>
                      <br><br>
                      <label>Email</label>
                      <input type="email" name="email" value="<?php echo htmlspecialchars($u['email']); ?>" required>
                      <br><br>
                      <label>Phone</label>
                      <input name="phone" value="<?php echo htmlspecialchars($u['phone'] ?? ''); ?>">
                    </td>

                    <td>
                      <label>Role</label>
                      <select name="role_id" required>
                        <?php foreach($roles as $role): ?>
                          <option value="<?php echo $role['role_id']; ?>" <?php echo $role['role_id'] == $u['role_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role['role_name']); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <br><br>
                      <label>Status</label>
                      <select name="account_status">
                        <option value="Pending" <?php echo $u['account_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Approved" <?php echo $u['account_status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo $u['account_status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                      </select>
                    </td>

                    <td>
                      <label>New Password</label>
                      <input type="password" name="new_password" placeholder="Leave blank to keep old password">
                    </td>

                    <td>
                      <button class="btn btn-success">Update</button>
                    </td>
                  </form>

                  <td>
                    <?php if($u['user_id'] != $_SESSION['user_id']): ?>
                      <form method="POST" action="process_admin.php" onsubmit="return confirm('Remove this user? Related messages may also be removed.');">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                        <button class="btn btn-danger">Delete</button>
                      </form>
                    <?php else: ?>
                      <span class="small">Current admin</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
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
        </div>

        <br>
        <button class="btn">Save Manager</button>
      </form>
    </section>

    <section id="admin-camps" class="tab-section">
      <form class="card" method="POST" action="process_admin.php">
        <input type="hidden" name="action" value="add_camp">
        <h2>Add Relief Camp</h2>

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

          <div><label>Capacity</label><input type="number" name="capacity" min="0" value="0"></div>
          <div><label>Current Population</label><input type="number" name="current_population" min="0" value="0"></div>

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
        <h2>Edit / Remove Relief Camps</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Camp</th>
                <th>Category / Location</th>
                <th>Manager</th>
                <th>Capacity / Population</th>
                <th>Status</th>
                <th>Save</th>
                <th>Remove</th>
              </tr>
            </thead>

            <tbody>
              <?php foreach($camps as $camp): ?>
                <tr>
                  <form method="POST" action="process_admin.php">
                    <input type="hidden" name="action" value="update_camp">
                    <input type="hidden" name="camp_id" value="<?php echo $camp['camp_id']; ?>">

                    <td>
                      <label>Camp Name</label>
                      <input name="camp_name" value="<?php echo htmlspecialchars($camp['camp_name']); ?>" required>
                    </td>

                    <td>
                      <label>Category</label>
                      <select name="category_id">
                        <option value="">None</option>
                        <?php foreach($categories_for_form as $cat): ?>
                          <option value="<?php echo $cat['category_id']; ?>" <?php echo $cat['category_id'] == $camp['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <br><br>
                      <label>Location</label>
                      <select name="location_id">
                        <option value="">None</option>
                        <?php foreach($locations_for_form as $loc): ?>
                          <option value="<?php echo $loc['location_id']; ?>" <?php echo $loc['location_id'] == $camp['location_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc['location_name']); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </td>

                    <td>
                      <label>Manager</label>
                      <select name="manager_id">
                        <option value="">None</option>
                        <?php foreach($managers_for_form as $manager): ?>
                          <option value="<?php echo $manager['user_id']; ?>" <?php echo $manager['user_id'] == $camp['manager_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($manager['full_name']); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </td>

                    <td>
                      <label>Capacity</label>
                      <input type="number" name="capacity" min="0" value="<?php echo htmlspecialchars($camp['capacity']); ?>">
                      <br><br>
                      <label>Current Population</label>
                      <input type="number" name="current_population" min="0" value="<?php echo htmlspecialchars($camp['current_population']); ?>">
                    </td>

                    <td>
                      <select name="status">
                        <option value="Active" <?php echo $camp['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Standby" <?php echo $camp['status'] === 'Standby' ? 'selected' : ''; ?>>Standby</option>
                        <option value="Closed" <?php echo $camp['status'] === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                      </select>
                    </td>

                    <td><button class="btn btn-success">Update</button></td>
                  </form>

                  <td>
                    <form method="POST" action="process_admin.php" onsubmit="return confirm('Delete this relief camp? Related stock, task, family and distribution records may also be removed.');">
                      <input type="hidden" name="action" value="delete_camp">
                      <input type="hidden" name="camp_id" value="<?php echo $camp['camp_id']; ?>">
                      <button class="btn btn-danger">Delete</button>
                    </form>
                  </td>
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


    <section id="admin-affected-support" class="tab-section">
      <section class="card">
        <h2>Affected People Support</h2>
        
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Affected User</th>
                <th>Link to Registered Family</th>
                <th>Camp Support Chat</th>
                <th>Support Note</th>
                <th>Save</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($affected_users_for_support as $au): ?>
                <tr>
                  <form method="POST" action="process_admin.php">
                    <input type="hidden" name="action" value="update_affected_support">
                    <input type="hidden" name="affected_user_id" value="<?php echo $au['user_id']; ?>">

                    <td>
                      <strong><?php echo htmlspecialchars($au['full_name']); ?></strong><br>
                      <span class="small"><?php echo htmlspecialchars($au['email']); ?></span><br>
                      <span class="small"><?php echo htmlspecialchars($au['phone']); ?></span>
                    </td>

                    <td>
                      <select name="family_id">
                        <option value="">Not linked</option>
                        <?php foreach($families_for_support as $fam): ?>
                          <option value="<?php echo $fam['family_id']; ?>" <?php echo $fam['family_id'] == $au['family_id'] ? 'selected' : ''; ?>>
                            Family ID <?php echo $fam['family_id']; ?> —
                            <?php echo htmlspecialchars($fam['head_name']); ?>
                            <?php if(!empty($fam['camp_name'])): ?>
                              — <?php echo htmlspecialchars($fam['camp_name']); ?>
                            <?php endif; ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <?php if(!empty($au['camp_name'])): ?>
                        <br><span class="small">Current Camp: <?php echo htmlspecialchars($au['camp_name']); ?></span>
                      <?php endif; ?>
                    </td>

                    <td>
                      <select name="chat_allowed">
                        <option value="0" <?php echo (int)$au['chat_allowed'] !== 1 ? 'selected' : ''; ?>>Not Allowed</option>
                        <option value="1" <?php echo (int)$au['chat_allowed'] === 1 ? 'selected' : ''; ?>>Allowed</option>
                      </select>
                    </td>

                    <td>
                      <textarea name="support_note" placeholder="Optional note for support team"><?php echo htmlspecialchars($au['support_note'] ?? ''); ?></textarea>
                    </td>

                    <td><button class="btn btn-success">Update</button></td>
                  </form>
                </tr>
              <?php endforeach; ?>

              <?php if(count($affected_users_for_support) === 0): ?>
                <tr><td colspan="5">No affected person account found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="section card">
        <h2>Affected Help Requests</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Requester</th><th>Need</th><th>Urgency</th><th>Details</th><th>Status</th><th>Update</th></tr>
            </thead>
            <tbody>
              <?php foreach($affected_help_requests as $req): ?>
                <tr>
                  <td>
                    <?php echo htmlspecialchars($req['affected_name'] ?? $req['head_name'] ?? 'Unknown'); ?><br>
                    <span class="small"><?php echo htmlspecialchars($req['camp_name'] ?? ''); ?></span>
                  </td>
                  <td><?php echo htmlspecialchars($req['need_type']); ?></td>
                  <td><?php echo htmlspecialchars($req['urgency']); ?></td>
                  <td><?php echo htmlspecialchars($req['details']); ?></td>
                  <form method="POST" action="process_admin.php">
                    <input type="hidden" name="action" value="update_help_request_status">
                    <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                    <td>
                      <select name="request_status">
                        <option value="Submitted" <?php echo $req['request_status']==='Submitted' ? 'selected' : ''; ?>>Submitted</option>
                        <option value="Approved" <?php echo $req['request_status']==='Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="In Progress" <?php echo $req['request_status']==='In Progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="Resolved" <?php echo $req['request_status']==='Resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="Rejected" <?php echo $req['request_status']==='Rejected' ? 'selected' : ''; ?>>Rejected</option>
                      </select>
                    </td>
                    <td><button class="btn btn-success">Save</button></td>
                  </form>
                </tr>
              <?php endforeach; ?>

              <?php if(count($affected_help_requests) === 0): ?>
                <tr><td colspan="6">No help requests found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="admin-categories" class="tab-section">
      <form class="card" method="POST" action="process_admin.php">
        <input type="hidden" name="action" value="add_category">
        <h2>Add Disaster Category</h2>
        <div class="form-grid">
          <div><label>Category Name</label><input name="category_name" required></div>
          <div><label>Description</label><input name="description"></div>
        </div>
        <br>
        <button class="btn">Add Category</button>
      </form>

      <section class="section card">
        <h2>Edit / Remove Disaster Categories</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Category</th><th>Description</th><th>Save</th><th>Remove</th></tr></thead>
            <tbody>
              <?php foreach($categories as $c): ?>
                <tr>
                  <form method="POST" action="process_admin.php">
                    <input type="hidden" name="action" value="update_category">
                    <input type="hidden" name="category_id" value="<?php echo $c['category_id']; ?>">
                    <td><input name="category_name" value="<?php echo htmlspecialchars($c['category_name']); ?>" required></td>
                    <td><input name="description" value="<?php echo htmlspecialchars($c['description'] ?? ''); ?>"></td>
                    <td><button class="btn btn-success">Update</button></td>
                  </form>
                  <td>
                    <form method="POST" action="process_admin.php" onsubmit="return confirm('Delete this disaster category? Camps using it will be kept but category will be removed.');">
                      <input type="hidden" name="action" value="delete_category">
                      <input type="hidden" name="category_id" value="<?php echo $c['category_id']; ?>">
                      <button class="btn btn-danger">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="admin-locations" class="tab-section">
      <form class="card" method="POST" action="process_admin.php">
        <input type="hidden" name="action" value="add_location">
        <h2>Add Camp Location</h2>
        <div class="form-grid">
          <div><label>Location Name</label><input name="location_name" required></div>
          <div><label>District</label><input name="district"></div>
        </div>
        <br>
        <label>Address</label><textarea name="address"></textarea>
        <br>
        <button class="btn">Add Location</button>
      </form>

      <section class="section card">
        <h2>Edit / Remove Camp Locations</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Location</th><th>District</th><th>Address</th><th>Save</th><th>Remove</th></tr></thead>
            <tbody>
              <?php foreach($locations as $l): ?>
                <tr>
                  <form method="POST" action="process_admin.php">
                    <input type="hidden" name="action" value="update_location">
                    <input type="hidden" name="location_id" value="<?php echo $l['location_id']; ?>">
                    <td><input name="location_name" value="<?php echo htmlspecialchars($l['location_name']); ?>" required></td>
                    <td><input name="district" value="<?php echo htmlspecialchars($l['district'] ?? ''); ?>"></td>
                    <td><input name="address" value="<?php echo htmlspecialchars($l['address'] ?? ''); ?>"></td>
                    <td><button class="btn btn-success">Update</button></td>
                  </form>
                  <td>
                    <form method="POST" action="process_admin.php" onsubmit="return confirm('Delete this location? Camps using it will be kept but location will be removed.');">
                      <input type="hidden" name="action" value="delete_location">
                      <input type="hidden" name="location_id" value="<?php echo $l['location_id']; ?>">
                      <button class="btn btn-danger">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="admin-stock" class="tab-section">
      <section class="card">
        <h2>Stock Monitor</h2>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Camp / Item</th>
                <th>Quantity</th>
                <th>Minimum Required</th>
                <th>Status</th>
                <th>Open Alerts</th>
                <th>Last Updated</th>
                <th>Save</th>
                <th>Remove</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($stock_rows as $row): ?>
                <tr>
                  <form method="POST" action="process_admin.php">
                    <input type="hidden" name="action" value="update_stock_admin">
                    <input type="hidden" name="stock_id" value="<?php echo $row['stock_id']; ?>">
                    <input type="hidden" name="camp_id" value="<?php echo $row['camp_id']; ?>">
                    <input type="hidden" name="item_id" value="<?php echo $row['item_id']; ?>">

                    <td>
                      <strong><?php echo htmlspecialchars($row['camp_name']); ?></strong><br>
                      <span class="small"><?php echo htmlspecialchars($row['item_name']); ?> — <?php echo htmlspecialchars($row['item_category']); ?> / <?php echo htmlspecialchars($row['unit']); ?></span>
                    </td>

                    <td><input type="number" name="quantity" value="<?php echo htmlspecialchars($row['quantity']); ?>" min="0"></td>
                    <td><input type="number" name="minimum_required" value="<?php echo htmlspecialchars($row['minimum_required']); ?>" min="0"></td>

                    <td>
                      <?php
                        $status_class = 'ok';
                        if($row['stock_status'] === 'Shortage') $status_class = 'bad';
                        if($row['stock_status'] === 'Low Stock' || $row['stock_status'] === 'No Minimum Set') $status_class = 'pending';
                      ?>
                      <span class="status <?php echo $status_class; ?>"><?php echo htmlspecialchars($row['stock_status']); ?></span>
                    </td>

                    <td><?php echo htmlspecialchars($row['open_alert_count']); ?></td>
                    <td><?php echo htmlspecialchars($row['last_updated']); ?></td>
                    <td><button class="btn btn-success">Update</button></td>
                  </form>

                  <td>
                    <form method="POST" action="process_admin.php" onsubmit="return confirm('Delete this stock record?');">
                      <input type="hidden" name="action" value="delete_stock_admin">
                      <input type="hidden" name="stock_id" value="<?php echo $row['stock_id']; ?>">
                      <input type="hidden" name="camp_id" value="<?php echo $row['camp_id']; ?>">
                      <input type="hidden" name="item_id" value="<?php echo $row['item_id']; ?>">
                      <button class="btn btn-danger">Delete</button>
                    </form>
                  </td>
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
        <a class="btn btn-outline" href="generate_camp_report.php">Download Camp Report PDF</a>
      </div>
    </section>


    <section id="admin-donation-usage" class="tab-section">
      <form class="card" method="POST" action="process_admin.php">
        <input type="hidden" name="action" value="record_donation_usage">

        <h2>Donation Usages</h2>
    
        <div class="form-grid">
          <div>
            <label>Donation ID</label>
            <select name="donation_id" required>
              <option value="">Select donation</option>
              <?php foreach($all_donations_for_usage as $don): ?>
                <option value="<?php echo $don['donation_id']; ?>">
                  #DON-<?php echo str_pad($don['donation_id'], 5, '0', STR_PAD_LEFT); ?> —
                  <?php echo htmlspecialchars($don['donor_name'] ?? 'Unknown'); ?> —
                  <?php echo htmlspecialchars($don['donation_type']); ?>
                  <?php if($don['donation_type'] === 'Money'): ?>
                    <?php echo number_format((float)$don['amount'], 2); ?>
                  <?php else: ?>
                    <?php echo htmlspecialchars($don['item_name'] ?? 'Supply'); ?>
                    <?php echo htmlspecialchars($don['quantity'] ?? 0); ?> <?php echo htmlspecialchars($don['unit'] ?? ''); ?>
                  <?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Used at Camp</label>
            <select name="camp_id">
              <option value="">Select camp</option>
              <?php foreach($camps_for_usage as $camp): ?>
                <option value="<?php echo $camp['camp_id']; ?>"><?php echo htmlspecialchars($camp['camp_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Quantity Used</label>
            <input type="number" name="quantity_used" min="0" placeholder="For supply donation">
          </div>
        </div>

        <br>
        <label>Used For</label>
        <textarea name="used_for" placeholder="Example: Food package distribution to flood-affected families" required></textarea>
        <br>
        <button class="btn">Record Usage</button>
      </form>

      <section class="section card">
        <h2>Donation Usage Records</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Donation ID</th><th>Donor</th><th>Camp</th><th>Item / Amount</th><th>Quantity Used</th><th>Used For</th><th>Date</th></tr>
            </thead>
            <tbody>
              <?php foreach($donation_usage_records as $usage): ?>
                <tr>
                  <td><strong>#DON-<?php echo str_pad($usage['donation_id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                  <td><?php echo htmlspecialchars($usage['donor_name'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($usage['camp_name'] ?? ''); ?></td>
                  <td>
                    <?php if($usage['donation_type'] === 'Money'): ?>
                      Money: <?php echo number_format((float)$usage['amount'], 2); ?>
                    <?php else: ?>
                      <?php echo htmlspecialchars($usage['item_name'] ?? 'Supply'); ?>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($usage['quantity_used'] ?? '-'); ?> <?php echo htmlspecialchars($usage['unit'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($usage['used_for']); ?></td>
                  <td><?php echo htmlspecialchars($usage['used_at']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="admin-announcements" class="tab-section">
      <form class="card" method="POST" action="process_admin.php">
        <input type="hidden" name="action" value="send_announcement">
        <h2>Send Urgent Alert / Announcement</h2>

        <label>Title</label>
        <input name="title" required>
        <br><br>

        <label>Audience</label>
        <select name="audience">
          <option>All</option>
          <option>Camp Managers</option>
          <option>Volunteers</option>
          <option>Donors</option>
          <option>Affected People</option>
        </select>
        <br><br>

        <label>Message</label>
        <textarea name="message" required></textarea>
        <br>

        <button class="btn">Send Alert</button>
      </form>

      <section class="section card">
        <h2>Previous Announcements</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Title</th><th>Audience</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach($announcements as $a): ?>
                <tr>
                  <td><?php echo htmlspecialchars($a['title']); ?></td>
                  <td><?php echo htmlspecialchars($a['audience']); ?></td>
                  <td><?php echo htmlspecialchars($a['created_at']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

  </section>
</main>

<?php include "../includes/footer.php"; ?>
