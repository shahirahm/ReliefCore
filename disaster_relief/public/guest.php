<?php
$page_title = "Guest | Disaster Relief System";
require_once "../config/database.php";
include "../includes/header.php";

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
")->fetchAll();

function guest_stock_status_text($percent) {
    if ($percent === null || $percent === '') return "Not Updated";
    if ($percent < 50) return "Critical";
    if ($percent < 75) return "Low Stock";
    return "Sufficient";
}

function guest_stock_width($percent) {
    if ($percent === null || $percent === '') return 0;
    if ($percent > 100) return 100;
    if ($percent < 0) return 0;
    return $percent;
}
?>
<main>
  <section class="section section-soft" style="padding-top:90px">
    <div class="container">
      <div class="section-heading">
        <div class="section-kicker">Public Access</div>
        <h2>Emergency Information & Active Campaigns</h2>
        <p>Guests can view awareness information, emergency hotlines, active relief campaigns and donation/contact options.</p>
      </div>
      <div class="grid grid-3">
        <div class="card"><div class="card-icon">📢</div><h3>Awareness Information</h3><p>Flood, cyclone, fire, landslide and earthquake safety instructions can be displayed here.</p></div>
        <div class="card"><div class="card-icon">☎️</div><h3>Emergency Hotline</h3><p>999 and official camp support contacts can be shown here.</p></div>
        <div class="card"><div class="card-icon">❤️</div><h3>Donation Access</h3><p>Guests can create donor accounts and submit money or supply donations.</p></div>
      </div>
    </div>
  </section>

  <section class="section" id="active-camps">
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
              $width = guest_stock_width($stock_percent);
              $stock_text = guest_stock_status_text($stock_percent);
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
                  <?php echo $stock_text; ?>
                </strong>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="card">
            <h3>No Active Camps Found</h3>
            <p>Add active relief camps in your database to show them here.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="cta-band">
        <div>
          <h2>Want to help the relief operation?</h2>
          <p>You can donate supplies, donate money, or register as a volunteer.</p>
        </div>
        <div class="hero-actions">
          <a class="btn" href="donor.php">Donate</a>
          <a class="btn btn-outline" href="volunteer.php">Volunteer</a>
        </div>
      </div>
    </div>
  </section>
</main>
<?php include "../includes/footer.php"; ?>
