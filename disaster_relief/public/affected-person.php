<?php
$page_title = "Affected Person | Disaster Relief System";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_login();

$user_id = $_SESSION['user_id'];
$requests = $pdo->prepare("SELECT * FROM help_requests WHERE affected_user_id=? ORDER BY request_id DESC");
$requests->execute([$user_id]);
$requests = $requests->fetchAll();

include "../includes/header.php";
?>
<main class="container dashboard">
  <aside class="sidebar">
    <h3>Affected Person</h3>
    <a class="active" onclick="showSection('ap-dashboard', this)">Dashboard</a>
    <a onclick="showSection('ap-request', this)">Submit Help Request</a>
    <a onclick="showSection('ap-status', this)">View Relief Status</a>
    <a onclick="showSection('ap-camp', this)">Assigned Camp</a>
    <a onclick="showSection('ap-support', this)">Support Information</a>
    <a href="chat.php">Chat</a>
  </aside>

  <section class="content">
    <div class="page-title"><span class="badge">Affected Person Dashboard</span><h1>Affected Person Panel</h1></div>

    <section id="ap-dashboard" class="tab-section active">
      <div class="kpi-row">
        <div class="kpi"><span>My Requests</span><strong><?php echo count($requests); ?></strong></div>
        <div class="kpi"><span>Submitted</span><strong><?php echo count(array_filter($requests, fn($r)=>$r['request_status']=='Submitted')); ?></strong></div>
        <div class="kpi"><span>Resolved</span><strong><?php echo count(array_filter($requests, fn($r)=>$r['request_status']=='Resolved')); ?></strong></div>
        <div class="kpi"><span>Support Info</span><strong>--</strong></div>
      </div>
    </section>

    <section id="ap-request" class="tab-section">
      <form class="card" method="POST" action="process_affected.php">
        <h2>Submit Help Request</h2>
        <label>Need Type</label><select name="need_type"><option>Food</option><option>Medicine</option><option>Shelter</option><option>Rescue</option><option>Other</option></select><br><br>
        <label>Urgency</label><select name="urgency"><option>Normal</option><option>Urgent</option><option>Critical</option></select><br><br>
        <label>Details</label><textarea name="details" required></textarea><br>
        <button class="btn">Submit Request</button>
      </form>
    </section>

    <section id="ap-status" class="tab-section">
      <section class="card">
        <h2>My Relief Status</h2>
        <div class="table-wrap">
          <table><thead><tr><th>Need</th><th>Urgency</th><th>Details</th><th>Status</th><th>Date</th></tr></thead><tbody>
            <?php foreach($requests as $r): ?>
              <tr><td><?php echo htmlspecialchars($r['need_type']); ?></td><td><?php echo htmlspecialchars($r['urgency']); ?></td><td><?php echo htmlspecialchars($r['details']); ?></td><td><?php echo htmlspecialchars($r['request_status']); ?></td><td><?php echo htmlspecialchars($r['created_at']); ?></td></tr>
            <?php endforeach; ?>
          </tbody></table>
        </div>
      </section>
    </section>

    <section id="ap-camp" class="tab-section">
      <div class="card">
        <h2>Assigned Camp</h2>
        <p>Assigned camp details can be shown here after linking the logged-in affected person to a family record.</p>
      </div>
    </section>

    <section id="ap-support" class="tab-section">
      <div class="card">
        <h2>Support Information</h2>
        <p>Food, medicine, shelter and camp support details can be displayed here.</p>
      </div>
    </section>
  </section>
</main>
<?php include "../includes/footer.php"; ?>
