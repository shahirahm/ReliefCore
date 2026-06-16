<?php
$page_title = "Affected Person | Disaster Relief System";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/functions.php";
require_login();

$user_id = $_SESSION['user_id'];

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

/*
  Auto-link affected person to registered family by phone number.
  Admin can also link manually from Admin > Affected Support.
*/
$stmt = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE user_id=?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM affected_user_links WHERE user_id=?");
$stmt->execute([$user_id]);
$link = $stmt->fetch();

if (!$link && !empty($current_user['phone'])) {
    $stmt = $pdo->prepare("SELECT family_id FROM affected_families WHERE phone=? ORDER BY family_id DESC LIMIT 1");
    $stmt->execute([$current_user['phone']]);
    $matched_family = $stmt->fetch();

    if ($matched_family) {
        $stmt = $pdo->prepare("INSERT INTO affected_user_links (user_id, family_id, chat_allowed, support_note) VALUES (?, ?, 0, 'Auto-linked by matching phone number. Camp support chat requires admin approval.')");
        $stmt->execute([$user_id, $matched_family['family_id']]);

        $stmt = $pdo->prepare("SELECT * FROM affected_user_links WHERE user_id=?");
        $stmt->execute([$user_id]);
        $link = $stmt->fetch();
    }
}

$family = null;
$assigned_camp = null;
$camp_manager = null;
$members = [];
$distribution_logs = [];

if ($link && !empty($link['family_id'])) {
    $stmt = $pdo->prepare("
        SELECT af.*, rc.camp_name, rc.status AS camp_status, rc.capacity, rc.current_population,
               dc.category_name, cl.location_name, cl.district, cl.address AS location_address,
               rc.manager_id
        FROM affected_families af
        LEFT JOIN relief_camps rc ON af.camp_id = rc.camp_id
        LEFT JOIN disaster_categories dc ON rc.category_id = dc.category_id
        LEFT JOIN camp_locations cl ON rc.location_id = cl.location_id
        WHERE af.family_id = ?
    ");
    $stmt->execute([$link['family_id']]);
    $family = $stmt->fetch();

    if ($family) {
        $assigned_camp = $family;

        if (!empty($family['manager_id'])) {
            $stmt = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE user_id=?");
            $stmt->execute([$family['manager_id']]);
            $camp_manager = $stmt->fetch();
        }

        $stmt = $pdo->prepare("SELECT * FROM family_members WHERE family_id=? ORDER BY member_id DESC");
        $stmt->execute([$family['family_id']]);
        $members = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT ad.*, si.item_name, si.unit, u.full_name AS distributed_by_name
            FROM aid_distribution ad
            JOIN supply_items si ON ad.item_id = si.item_id
            LEFT JOIN users u ON ad.distributed_by = u.user_id
            WHERE ad.family_id = ?
            ORDER BY ad.distribution_id DESC
        ");
        $stmt->execute([$family['family_id']]);
        $distribution_logs = $stmt->fetchAll();
    }
}

if ($family) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM help_requests
        WHERE affected_user_id = ?
        OR family_id = ?
        ORDER BY request_id DESC
    ");
    $stmt->execute([$user_id, $family['family_id']]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM help_requests WHERE affected_user_id=? ORDER BY request_id DESC");
    $stmt->execute([$user_id]);
}
$requests = $stmt->fetchAll();

$submitted = count(array_filter($requests, fn($r)=>$r['request_status']=='Submitted'));
$active = count(array_filter($requests, fn($r)=>in_array($r['request_status'], ['Approved','In Progress'])));
$resolved = count(array_filter($requests, fn($r)=>$r['request_status']=='Resolved'));

include "../includes/header.php";
?>

<main class="container dashboard">
  <aside class="sidebar">
    <h3>Affected Person</h3>
    <a class="active" onclick="showSection('ap-dashboard', this)">Dashboard</a>
    <a onclick="showSection('ap-request', this)">Submit Help Request</a>
    <a onclick="showSection('ap-status', this)">Relief Status</a>
    <a onclick="showSection('ap-camp', this)">Assigned Camp</a>
    <a onclick="showSection('ap-support', this)">Support Information</a>
    <a href="chat.php">Chat</a>
  </aside>

  <section class="content">
    <div class="page-title">
      <span class="badge">Affected Person Dashboard</span>
      <h1>Affected Person Support Panel</h1>
      <p>Submit help requests, view relief status, assigned camp details and support information.</p>
    </div>

    <section id="ap-dashboard" class="tab-section active">
      <div class="kpi-row">
        <div class="kpi"><span>My Requests</span><strong><?php echo count($requests); ?></strong></div>
        <div class="kpi"><span>Submitted</span><strong><?php echo $submitted; ?></strong></div>
        <div class="kpi"><span>Active Support</span><strong><?php echo $active; ?></strong></div>
        <div class="kpi"><span>Resolved</span><strong><?php echo $resolved; ?></strong></div>
      </div>

      <div class="grid grid-2">
        <div class="card">
          <h2>Registration Status</h2>
          <?php if($family): ?>
            <p><strong>Registered Family:</strong> <?php echo htmlspecialchars($family['head_name']); ?></p>
            <p><strong>Family Status:</strong> <?php echo htmlspecialchars($family['status']); ?></p>
            <p><strong>Total Members:</strong> <?php echo htmlspecialchars($family['total_members']); ?></p>
          <?php else: ?>
            <p>You are not linked to a registered affected family yet.</p>
            <p class="anchor-note">If your phone number matches a registered family phone, the system links automatically. Otherwise admin/camp support must link your account.</p>
          <?php endif; ?>
        </div>

        <div class="card">
          <h2>Camp Support Chat</h2>
          <?php if($link && (int)$link['chat_allowed'] === 1): ?>
            <p><span class="status ok">Allowed</span></p>
            <a class="btn btn-outline" href="chat.php">Open Chat</a>
          <?php else: ?>
            <p><span class="status pending">Not Allowed Yet</span></p>
            <p class="anchor-note">Camp support chat must be allowed by admin.</p>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section id="ap-request" class="tab-section">
      <form class="card" method="POST" action="process_affected.php">
        <input type="hidden" name="action" value="submit_help_request">
        <h2>Submit Help Request</h2>

        <div class="form-grid">
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
        <label>Details</label>
        <textarea name="details" required placeholder="Describe what help is needed, location details, number of people, health concerns etc."></textarea>
        <br>

        <button class="btn">Submit Request</button>
      </form>
    </section>

    <section id="ap-status" class="tab-section">
      <section class="card">
        <h2>My Relief Status</h2>
        <p class="anchor-note">This shows help request status and aid distribution history if your account is linked to a registered family.</p>

        <h3>Help Requests</h3>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Need</th><th>Urgency</th><th>Details</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach($requests as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['need_type']); ?></td>
                  <td><?php echo htmlspecialchars($r['urgency']); ?></td>
                  <td><?php echo htmlspecialchars($r['details']); ?></td>
                  <td><?php echo htmlspecialchars($r['request_status']); ?></td>
                  <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if(count($requests) === 0): ?>
                <tr><td colspan="5">No help requests submitted yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <br>
        <h3>Aid Received / Distribution Logs</h3>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Date</th><th>Item</th><th>Quantity</th><th>Distributed By</th><th>Note</th></tr></thead>
            <tbody>
              <?php foreach($distribution_logs as $log): ?>
                <tr>
                  <td><?php echo htmlspecialchars($log['distribution_date']); ?></td>
                  <td><?php echo htmlspecialchars($log['item_name']); ?></td>
                  <td><?php echo htmlspecialchars($log['quantity']); ?> <?php echo htmlspecialchars($log['unit']); ?></td>
                  <td><?php echo htmlspecialchars($log['distributed_by_name'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($log['note']); ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if(count($distribution_logs) === 0): ?>
                <tr><td colspan="5">No aid distribution record found yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="ap-camp" class="tab-section">
      <div class="card">
        <h2>Assigned Camp</h2>
        <?php if($assigned_camp): ?>
          <p><strong>Camp:</strong> <?php echo htmlspecialchars($assigned_camp['camp_name'] ?? ''); ?></p>
          <p><strong>Camp Status:</strong> <?php echo htmlspecialchars($assigned_camp['camp_status'] ?? ''); ?></p>
          <p><strong>Disaster:</strong> <?php echo htmlspecialchars($assigned_camp['category_name'] ?? ''); ?></p>
          <p><strong>Location:</strong> <?php echo htmlspecialchars($assigned_camp['location_name'] ?? ''); ?>, <?php echo htmlspecialchars($assigned_camp['district'] ?? ''); ?></p>
          <p><strong>Address:</strong> <?php echo htmlspecialchars($assigned_camp['location_address'] ?? ''); ?></p>
          <p><strong>Camp Population:</strong> <?php echo htmlspecialchars($assigned_camp['current_population'] ?? ''); ?> / <?php echo htmlspecialchars($assigned_camp['capacity'] ?? ''); ?></p>
        <?php else: ?>
          <p>No assigned camp found yet.</p>
        <?php endif; ?>
      </div>

      <?php if($family): ?>
      <section class="section card">
        <h2>Registered Family / Individual Members</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Name</th><th>Age</th><th>Gender</th><th>Relation</th><th>Health Note</th></tr></thead>
            <tbody>
              <?php foreach($members as $m): ?>
                <tr>
                  <td><?php echo htmlspecialchars($m['member_name']); ?></td>
                  <td><?php echo htmlspecialchars($m['age']); ?></td>
                  <td><?php echo htmlspecialchars($m['gender']); ?></td>
                  <td><?php echo htmlspecialchars($m['relation_to_head']); ?></td>
                  <td><?php echo htmlspecialchars($m['health_note']); ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if(count($members) === 0): ?>
                <tr><td colspan="5">No individual members added yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>
    </section>

    <section id="ap-support" class="tab-section">
      <div class="card">
        <h2>Support Information</h2>
        <?php if($camp_manager): ?>
          <p><strong>Camp Manager:</strong> <?php echo htmlspecialchars($camp_manager['full_name']); ?></p>
          <p><strong>Email:</strong> <?php echo htmlspecialchars($camp_manager['email']); ?></p>
          <p><strong>Phone:</strong> <?php echo htmlspecialchars($camp_manager['phone']); ?></p>
        <?php else: ?>
          <p>No camp support contact assigned yet.</p>
        <?php endif; ?>

        <p><strong>Support Chat:</strong>
          <?php if($link && (int)$link['chat_allowed'] === 1): ?>
            <span class="status ok">Allowed</span>
          <?php else: ?>
            <span class="status pending">Not allowed yet</span>
          <?php endif; ?>
        </p>

        <?php if($link && !empty($link['support_note'])): ?>
          <p><strong>Support Note:</strong> <?php echo htmlspecialchars($link['support_note']); ?></p>
        <?php endif; ?>

        <p class="anchor-note">For emergency assistance, submit a help request with Critical urgency.</p>
      </div>
    </section>
  </section>
</main>

<?php include "../includes/footer.php"; ?>
