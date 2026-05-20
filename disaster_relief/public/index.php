<?php
$page_title = "Home | Disaster Relief System";
require_once "../config/database.php";
require_once "../includes/functions.php";
include "../includes/header.php";

$active_camps = $pdo->query("
    SELECT COUNT(*) AS total
    FROM relief_camps
    WHERE status IS NULL OR status = '' OR LOWER(status) IN ('active', 'standby', 'ongoing')
")->fetch()['total'] ?? 0;

$total_families = $pdo->query("
    SELECT COUNT(*) AS total
    FROM affected_families
")->fetch()['total'] ?? 0;

$total_people = $pdo->query("
    SELECT COALESCE(SUM(total_members), 0) AS total
    FROM affected_families
")->fetch()['total'] ?? 0;

$total_volunteers = $pdo->query("
    SELECT COUNT(*) AS total
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    WHERE r.role_name = 'Volunteer'
    AND u.account_status = 'Approved'
")->fetch()['total'] ?? 0;

$total_money_donated = $pdo->query("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM donations
    WHERE donation_type = 'Money'
")->fetch()['total'] ?? 0;

$total_supply_donations = $pdo->query("
    SELECT COUNT(*) AS total
    FROM donations
    WHERE donation_type = 'Supply'
")->fetch()['total'] ?? 0;

$open_stock_alerts = $pdo->query("
    SELECT COUNT(*) AS total
    FROM stock_alerts
    WHERE alert_status = 'Open'
")->fetch()['total'] ?? 0;


$campaigns = $pdo->query("
    SELECT
        rc.camp_id,
        rc.camp_name,
        rc.current_population,
        rc.status,
        dc.category_name,
        cl.location_name,
        COUNT(DISTINCT af.family_id) AS families_served,
        COALESCE(SUM(cs.quantity), 0) AS total_stock_quantity,
        COALESCE(SUM(cs.minimum_required), 0) AS total_minimum_required,
        CASE
            WHEN COALESCE(SUM(cs.minimum_required), 0) > 0
            THEN ROUND((SUM(cs.quantity) / SUM(cs.minimum_required)) * 100)
            ELSE NULL
        END AS stock_percent
    FROM relief_camps rc
    LEFT JOIN disaster_categories dc ON rc.category_id = dc.category_id
    LEFT JOIN camp_locations cl ON rc.location_id = cl.location_id
    LEFT JOIN affected_families af ON rc.camp_id = af.camp_id
    LEFT JOIN camp_stock cs ON rc.camp_id = cs.camp_id
    WHERE rc.status IS NULL OR rc.status = '' OR LOWER(rc.status) IN ('active', 'standby', 'ongoing')
    GROUP BY rc.camp_id, rc.camp_name, rc.current_population, rc.status, dc.category_name, cl.location_name
    ORDER BY rc.camp_id DESC
    LIMIT 3
")->fetchAll();

$live_situations = $pdo->query("
    SELECT
        rc.camp_name,
        rc.status,
        dc.category_name,
        cl.location_name,
        COUNT(sa.alert_id) AS open_alerts
    FROM relief_camps rc
    LEFT JOIN disaster_categories dc ON rc.category_id = dc.category_id
    LEFT JOIN camp_locations cl ON rc.location_id = cl.location_id
    LEFT JOIN stock_alerts sa ON rc.camp_id = sa.camp_id AND sa.alert_status = 'Open'
    GROUP BY rc.camp_id, rc.camp_name, rc.status, dc.category_name, cl.location_name
    ORDER BY open_alerts DESC, rc.camp_id DESC
    LIMIT 4
")->fetchAll();

function stock_status_text($percent) {
    if ($percent === null || $percent === '') return "Not Updated";
    if ($percent < 50) return "Critical";
    if ($percent < 75) return "Low Stock";
    return "Sufficient";
}

function stock_status_class($percent) {
    if ($percent === null || $percent === '') return "risk-monitor";
    if ($percent < 50) return "risk-high";
    if ($percent < 75) return "risk-warning";
    return "risk-ok";
}

function progress_width($percent) {
    if ($percent === null || $percent === '') return 0;
    if ($percent > 100) return 100;
    if ($percent < 0) return 0;
    return $percent;
}
?>
<main>
  <section class="emergency-hero" id="home">
    <div class="container hero-grid">
      <div class="hero-copy">
        <span class="badge">An Integrated Disaster Coordination Platform</span>
        <h1>In Every Disaster,<br><span class="red-text">We Stand</span><br>Together for Humanity</h1>
        <p>One unified system for camp management, volunteer coordination, donation tracking, and rapid aid delivery to affected families, all in real time.</p>
        <div class="hero-actions">
          <a class="btn" href="affected-person.php">Apply for Help</a>
          <a class="btn btn-outline" href="donor.php">Donate Now</a>
        </div>
      </div>

      <div class="live-card">
        <h3><span class="live-dot"></span> Live Situation</h3>

        <?php if(count($live_situations) > 0): ?>
          <?php foreach($live_situations as $situation): ?>
            <?php
              $alert_count = (int)$situation['open_alerts'];
              $risk_text = $alert_count > 0 ? "Stock Alert" : $situation['status'];
              $risk_class = $alert_count > 0 ? "risk-high" : "risk-ok";
            ?>
            <div class="situation-row">
              <span>
                <?php echo htmlspecialchars($situation['category_name'] ?? 'Relief'); ?>
                —
                <?php echo htmlspecialchars($situation['location_name'] ?? $situation['camp_name']); ?>
              </span>
              <span class="risk-pill <?php echo $risk_class; ?>">
                <?php echo htmlspecialchars($risk_text); ?>
              </span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="situation-row">
            <span>No active camp records found</span>
            <span class="risk-pill risk-monitor">No Data</span>
          </div>
        <?php endif; ?>

        <br>
        <a class="btn" href="admin.php" style="width:100%">View Full Dashboard →</a>
      </div>
    </div>

    <div class="hero-stats">
      <div class="container stats-grid">
        <div class="stat">
          <strong><?php echo number_format($active_camps); ?></strong>
          <span>Active Relief Camps</span>
        </div>
        <div class="stat">
          <strong><?php echo number_format($total_families); ?></strong>
          <span>Registered Families</span>
        </div>
        <div class="stat">
          <strong><?php echo number_format($total_people); ?></strong>
          <span>People Supported</span>
        </div>
        <div class="stat">
          <strong><?php echo number_format($total_volunteers); ?></strong>
          <span>Approved Volunteers</span>
        </div>
      </div>
    </div>
  </section>

  <section class="section" id="about">
    <div class="container">
      <div class="section-heading">
        <div class="section-kicker">About The System</div>
        <h2>Built for fast, organized and transparent disaster response</h2>
        <p>ReliefConnect connects administrators, camp managers, volunteers, donors and affected people in one web-based platform so aid can move faster and records remain clear.</p>
      </div>
      <div class="grid grid-3">
        <div class="card"><div class="card-icon">🏕️</div><h3>Camp Management</h3><p>Register affected families, add family members, update camp stock and maintain distribution logs.</p></div>
        <div class="card"><div class="card-icon">🚨</div><h3>Emergency Alerts</h3><p>Admin can send urgent announcements and monitor real-time stock shortage warnings.</p></div>
        <div class="card"><div class="card-icon">🤝</div><h3>Volunteer Coordination</h3><p>Assign relief tasks, update completion status and record urgent field issues.</p></div>
      </div>
    </div>
  </section>

  <section class="section section-soft" id="roles">
    <div class="container">
      <div class="section-heading">
        <div class="section-kicker">Role Based Access</div>
        <h2>Separate dashboards for every stakeholder</h2>
      </div>
      <div class="grid grid-3">
        <a class="card role-card" href="admin.php"><div class="card-icon">🛡️</div><h3>Admin</h3><p>Approve accounts, manage camps, monitor stocks, generate reports and send alerts.</p></a>
        <a class="card role-card" href="camp-manager.php"><div class="card-icon">🏕️</div><h3>Camp Manager</h3><p>Manage families, stock, volunteers, aid distribution and camp-wise summaries.</p></a>
        <a class="card role-card" href="volunteer.php"><div class="card-icon">🧑‍🚒</div><h3>Volunteer</h3><p>View assigned tasks, update status, record delivered supplies and report field issues.</p></a>
        <a class="card role-card" href="donor.php"><div class="card-icon">📦</div><h3>Donor</h3><p>Donate money or supplies, view donation history, usage tracking and receipts.</p></a>
        <a class="card role-card" href="affected-person.php"><div class="card-icon">🧑‍👩‍👧</div><h3>Affected People</h3><p>Submit help requests, view relief status and assigned support information.</p></a>
        <a class="card role-card" href="guest.php"><div class="card-icon">🌐</div><h3>Guest</h3><p>View awareness information, emergency hotline, active campaigns and contact details.</p></a>
      </div>
    </div>
  </section>

  <section class="section" id="features">
    <div class="container">
      <div class="section-heading">
        <div class="section-kicker">Current Database Summary</div>
      </div>
      <div class="grid grid-3">
        <div class="card"><div class="card-icon">💰</div><h3>Money Donations</h3><p>Total recorded money donation: <strong><?php echo number_format((float)$total_money_donated, 2); ?> TK</strong></p></div>
        <div class="card"><div class="card-icon">📦</div><h3>Supply Donations</h3><p>Total supply donation records: <strong><?php echo number_format($total_supply_donations); ?></strong></p></div>
        <div class="card"><div class="card-icon">🚨</div><h3>Open Stock Alerts</h3><p>Current unresolved stock alerts: <strong><?php echo number_format($open_stock_alerts); ?></strong></p></div>
      </div>
    </div>
  </section>

  <section class="section section-soft" id="active-camps">
    <div class="container">
      <div class="section-heading">
        <div class="section-kicker">Ongoing Operations</div>
        <h2>Active Relief Camps</h2>
      </div>

      <div class="grid grid-3">
        <?php if(count($campaigns) > 0): ?>
          <?php foreach($campaigns as $camp): ?>
            <?php
              $stock_percent = $camp['stock_percent'];
              $width = progress_width($stock_percent);
              $stock_text = stock_status_text($stock_percent);
              $stock_class = stock_status_class($stock_percent);
            ?>
            <div class="card camp-card">
              <div class="camp-img"></div>
              <div class="camp-body">
                <span class="tag"><?php echo htmlspecialchars($camp['category_name'] ?? 'Relief'); ?></span>
                <h3 style="margin-top:16px"><?php echo htmlspecialchars($camp['camp_name']); ?></h3>
                <p>
                  📍 <?php echo htmlspecialchars($camp['location_name'] ?? 'Location not set'); ?>
                  · <?php echo number_format((int)$camp['families_served']); ?> families registered
                  · <?php echo number_format((int)$camp['current_population']); ?> current population
                </p>
                <div class="progress"><span style="width:<?php echo $width; ?>%"></span></div>
                <strong class="small">
                  STOCK LEVEL:
                  <?php echo $stock_percent === null ? "Not Updated" : number_format((float)$stock_percent) . "%"; ?>
                  —
                  <span class="<?php echo $stock_class; ?>"><?php echo $stock_text; ?></span>
                </strong>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="card">
            <h3>No Active Camps Found</h3>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="section" id="donate">
    <div class="container">
      <div class="cta-band">
        <div>
          <h2>Every donation can become immediate relief.</h2>
          <p>Track donated money and supplies from submission to usage through the system.</p>
        </div>
        <div class="hero-actions">
          <a class="btn" href="donor.php">Donate Now</a>
          <a class="btn btn-outline" href="volunteer.php">Join as Volunteer</a>
        </div>
      </div>
    </div>
  </section>
</main>
<?php include "../includes/footer.php"; ?>
