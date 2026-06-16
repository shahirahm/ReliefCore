<?php
$page_title = "Donor | Disaster Relief System";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/functions.php";
require_role(['Donor', 'Admin']);

$user_id = $_SESSION['user_id'];
$is_admin = current_user_role() === 'Admin';

$items = $pdo->query("SELECT item_id, item_name, item_category, unit FROM supply_items ORDER BY item_category, item_name")->fetchAll();

if ($is_admin) {
    $donations = $pdo->query("
        SELECT d.*, u.full_name AS donor_name, u.email AS donor_email, si.item_name, si.item_category, si.unit
        FROM donations d
        LEFT JOIN users u ON d.donor_id = u.user_id
        LEFT JOIN supply_items si ON d.item_id = si.item_id
        ORDER BY d.donation_id DESC
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT d.*, u.full_name AS donor_name, u.email AS donor_email, si.item_name, si.item_category, si.unit
        FROM donations d
        LEFT JOIN users u ON d.donor_id = u.user_id
        LEFT JOIN supply_items si ON d.item_id = si.item_id
        WHERE d.donor_id = ?
        ORDER BY d.donation_id DESC
    ");
    $stmt->execute([$user_id]);
    $donations = $stmt->fetchAll();
}

if ($is_admin) {
    $usage_rows = $pdo->query("
        SELECT du.*, d.donation_type, d.amount, d.quantity AS donated_quantity,
               donor.full_name AS donor_name, rc.camp_name, si.item_name, si.unit
        FROM donation_usage du
        JOIN donations d ON du.donation_id = d.donation_id
        LEFT JOIN users donor ON d.donor_id = donor.user_id
        LEFT JOIN relief_camps rc ON du.camp_id = rc.camp_id
        LEFT JOIN supply_items si ON du.item_id = si.item_id
        ORDER BY du.usage_id DESC
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT du.*, d.donation_type, d.amount, d.quantity AS donated_quantity,
               donor.full_name AS donor_name, rc.camp_name, si.item_name, si.unit
        FROM donation_usage du
        JOIN donations d ON du.donation_id = d.donation_id
        LEFT JOIN users donor ON d.donor_id = donor.user_id
        LEFT JOIN relief_camps rc ON du.camp_id = rc.camp_id
        LEFT JOIN supply_items si ON du.item_id = si.item_id
        WHERE d.donor_id = ?
        ORDER BY du.usage_id DESC
    ");
    $stmt->execute([$user_id]);
    $usage_rows = $stmt->fetchAll();
}

$total_money = 0;
$total_supply = 0;
foreach ($donations as $d) {
    if ($d['donation_type'] === 'Money') $total_money += (float)$d['amount'];
    if ($d['donation_type'] === 'Supply') $total_supply += (int)$d['quantity'];
}
?>
<?php include "../includes/header.php"; ?>

<main class="container dashboard">
  <aside class="sidebar">
    <h3>Donor</h3>
    <a class="active" onclick="showSection('donor-dashboard', this)">Dashboard</a>
    <a onclick="showSection('donor-donate', this)">Donate Money / Supplies</a>
    <a onclick="showSection('donor-history', this)">Donation History</a>
    <a onclick="showSection('donor-usage', this)">Track Donation Usage</a>
    <a onclick="showSection('donor-receipts', this)">Download Receipts</a>
    <a href="chat.php">Chat</a>
  </aside>

  <section class="content">
    <div class="page-title">
      <span class="badge">Donor Dashboard</span>
      <h1>Donation Management</h1>
    </div>

    <section id="donor-dashboard" class="tab-section active">
      <div class="kpi-row">
        <div class="kpi"><span>Total Donations</span><strong><?php echo count($donations); ?></strong></div>
        <div class="kpi"><span>Money Donated</span><strong><?php echo number_format($total_money, 2); ?></strong></div>
        <div class="kpi"><span>Supply Quantity</span><strong><?php echo number_format($total_supply); ?></strong></div>
        <div class="kpi"><span>Usage Records</span><strong><?php echo count($usage_rows); ?></strong></div>
      </div>
      <div class="card">
        <h2>Donation ID Tracking</h2>      </div>
    </section>

    <section id="donor-donate" class="tab-section">
      <form class="card" method="POST" action="process_donor.php">
        <input type="hidden" name="action" value="make_donation">
        <h2>Donate Money or Supplies</h2>

        <div class="form-grid">
          <div>
            <label>Donation Type</label>
            <select name="donation_type" id="donationType" required onchange="toggleDonationFields()">
              <option value="Money">Money</option>
              <option value="Supply">Supply</option>
            </select>
          </div>

          <div class="money-field">
            <label>Amount</label>
            <input type="number" step="0.01" min="1" name="amount" placeholder="Example: 5000">
          </div>

          <div class="money-field">
            <label>Payment Method</label>
            <select name="payment_method">
              <option value="Cash">Cash</option>
              <option value="bKash">bKash</option>
              <option value="Nagad">Nagad</option>
              <option value="Bank Transfer">Bank Transfer</option>
              <option value="Card">Card</option>
            </select>
          </div>

          <div class="supply-field" style="display:none">
            <label>Supply Item</label>
            <select name="item_id">
              <option value="">Select supply item</option>
              <?php foreach($items as $item): ?>
                <option value="<?php echo $item['item_id']; ?>">
                  <?php echo htmlspecialchars($item['item_name']); ?> —
                  <?php echo htmlspecialchars($item['item_category']); ?> /
                  <?php echo htmlspecialchars($item['unit']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="supply-field" style="display:none">
            <label>Quantity</label>
            <input type="number" min="1" name="quantity" placeholder="Example: 100">
          </div>
        </div>

        <br>
        <button class="btn">Submit Donation</button>
      </form>

      <script>
        function toggleDonationFields(){
          const type = document.getElementById('donationType').value;
          document.querySelectorAll('.money-field').forEach(el => el.style.display = type === 'Money' ? 'block' : 'none');
          document.querySelectorAll('.supply-field').forEach(el => el.style.display = type === 'Supply' ? 'block' : 'none');
        }
      </script>
    </section>

    <section id="donor-history" class="tab-section">
      <section class="card">
        <h2>Donation History — Who Donated What</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Donation ID</th><th>Donor</th><th>Type</th><th>Donation Details</th><th>Status</th><th>Date</th><th>Receipt</th></tr>
            </thead>
            <tbody>
              <?php foreach($donations as $d): ?>
                <tr>
                  <td><strong>#DON-<?php echo str_pad($d['donation_id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                  <td><?php echo htmlspecialchars($d['donor_name'] ?? 'Unknown'); ?><br><span class="small"><?php echo htmlspecialchars($d['donor_email'] ?? ''); ?></span></td>
                  <td><?php echo htmlspecialchars($d['donation_type']); ?></td>
                  <td>
                    <?php if($d['donation_type'] === 'Money'): ?>
                      Amount: <?php echo number_format((float)$d['amount'], 2); ?><br>
                      Method: <?php echo htmlspecialchars($d['payment_method'] ?? ''); ?>
                    <?php else: ?>
                      Item: <?php echo htmlspecialchars($d['item_name'] ?? ''); ?><br>
                      Quantity: <?php echo htmlspecialchars($d['quantity'] ?? 0); ?> <?php echo htmlspecialchars($d['unit'] ?? ''); ?>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($d['donation_status']); ?></td>
                  <td><?php echo htmlspecialchars($d['donated_at']); ?></td>
                  <td><a class="btn btn-outline" href="generate_donation_receipt.php?donation_id=<?php echo $d['donation_id']; ?>">Download</a></td>
                </tr>
              <?php endforeach; ?>
              <?php if(count($donations) === 0): ?><tr><td colspan="7">No donations found.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="donor-usage" class="tab-section">
      <section class="card">
        <h2>Track Where Donations Were Used</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Donation ID</th><th>Donor</th><th>Used At Camp</th><th>Used Item / Amount</th><th>Quantity Used</th><th>Used For</th><th>Used Date</th></tr>
            </thead>
            <tbody>
              <?php foreach($usage_rows as $u): ?>
                <tr>
                  <td><strong>#DON-<?php echo str_pad($u['donation_id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                  <td><?php echo htmlspecialchars($u['donor_name'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($u['camp_name'] ?? 'Not assigned'); ?></td>
                  <td>
                    <?php if($u['donation_type'] === 'Money'): ?>
                      Money Donation: <?php echo number_format((float)$u['amount'], 2); ?>
                    <?php else: ?>
                      <?php echo htmlspecialchars($u['item_name'] ?? 'Supply'); ?>
                    <?php endif; ?>
                  </td>
                  <td><?php echo $u['quantity_used'] !== null ? htmlspecialchars($u['quantity_used']) . ' ' . htmlspecialchars($u['unit'] ?? '') : '-'; ?></td>
                  <td><?php echo htmlspecialchars($u['used_for'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($u['used_at']); ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if(count($usage_rows) === 0): ?><tr><td colspan="7">No usage record found yet.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="donor-receipts" class="tab-section">
      <section class="card">
        <h2>Download Donation Receipts</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Donation ID</th><th>Type</th><th>Details</th><th>Date</th><th>Receipt</th></tr></thead>
            <tbody>
              <?php foreach($donations as $d): ?>
                <tr>
                  <td><strong>#DON-<?php echo str_pad($d['donation_id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                  <td><?php echo htmlspecialchars($d['donation_type']); ?></td>
                  <td>
                    <?php if($d['donation_type'] === 'Money'): ?>
                      <?php echo number_format((float)$d['amount'], 2); ?> via <?php echo htmlspecialchars($d['payment_method'] ?? ''); ?>
                    <?php else: ?>
                      <?php echo htmlspecialchars($d['quantity'] ?? 0); ?> <?php echo htmlspecialchars($d['unit'] ?? ''); ?> <?php echo htmlspecialchars($d['item_name'] ?? ''); ?>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($d['donated_at']); ?></td>
                  <td><a class="btn btn-outline" href="generate_donation_receipt.php?donation_id=<?php echo $d['donation_id']; ?>">Download Receipt</a></td>
                </tr>
              <?php endforeach; ?>
              <?php if(count($donations) === 0): ?><tr><td colspan="5">No receipts available.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

  </section>
</main>

<?php include "../includes/footer.php"; ?>
