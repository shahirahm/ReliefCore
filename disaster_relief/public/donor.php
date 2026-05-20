<?php
$page_title = "Donor | Disaster Relief System";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/functions.php";

if (!is_logged_in()) {
    header("Location: login.php?error=Please login or register as donor first");
    exit;
}

$items = $pdo->query("SELECT item_id, item_name FROM supply_items ORDER BY item_name")->fetchAll();
$donor_id = $_SESSION['user_id'];
$history = $pdo->prepare("SELECT d.*, si.item_name FROM donations d LEFT JOIN supply_items si ON d.item_id=si.item_id WHERE d.donor_id=? ORDER BY donation_id DESC");
$history->execute([$donor_id]);
$history = $history->fetchAll();

include "../includes/header.php";
?>
<main class="container dashboard">
  <aside class="sidebar">
    <h3>Donor</h3>
    <a class="active" onclick="showSection('donor-dashboard', this)">Dashboard</a>
    <a onclick="showSection('donor-money', this)">Donate Money</a>
    <a onclick="showSection('donor-supply', this)">Donate Supplies</a>
    <a onclick="showSection('donor-history', this)">Donation History</a>
    <a onclick="showSection('donor-usage', this)">Usage Tracking</a>
    <a onclick="showSection('donor-receipt', this)">Receipts</a>
    <a href="chat.php">Chat</a>
  </aside>

  <section class="content">
    <div class="page-title"><span class="badge">Donor Dashboard</span><h1>Donor Panel</h1></div>

    <section id="donor-dashboard" class="tab-section active">
      <div class="kpi-row">
        <div class="kpi"><span>Total Records</span><strong><?php echo count($history); ?></strong></div>
        <div class="kpi"><span>Money Donations</span><strong><?php echo count(array_filter($history, fn($d)=>$d['donation_type']=='Money')); ?></strong></div>
        <div class="kpi"><span>Supply Donations</span><strong><?php echo count(array_filter($history, fn($d)=>$d['donation_type']=='Supply')); ?></strong></div>
        <div class="kpi"><span>Receipts</span><strong><?php echo count($history); ?></strong></div>
      </div>
    </section>

    <section id="donor-money" class="tab-section">
      <form class="card" method="POST" action="process_donor.php">
        <input type="hidden" name="action" value="donate_money">
        <h2>Donate Money</h2>
        <label>Amount</label><input type="number" name="amount" required><br><br>
        <label>Payment Method</label><select name="payment_method"><option>bKash</option><option>Nagad</option><option>Bank</option><option>Cash</option></select><br><br>
        <button class="btn">Donate</button>
      </form>
    </section>

    <section id="donor-supply" class="tab-section">
      <form class="card" method="POST" action="process_donor.php">
        <input type="hidden" name="action" value="donate_supply">
        <h2>Donate Supplies</h2>
        <label>Item</label><select name="item_id"><?php foreach($items as $i): ?><option value="<?php echo $i['item_id']; ?>"><?php echo htmlspecialchars($i['item_name']); ?></option><?php endforeach; ?></select><br><br>
        <label>Quantity</label><input type="number" name="quantity" required><br><br>
        <button class="btn">Submit Supply</button>
      </form>
    </section>

    <section id="donor-history" class="tab-section">
      <section class="card">
        <h2>Donation History</h2>
        <div class="table-wrap">
          <table><thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Item</th><th>Quantity</th><th>Status</th></tr></thead><tbody>
            <?php foreach($history as $d): ?>
              <tr>
                <td><?php echo htmlspecialchars($d['donated_at']); ?></td>
                <td><?php echo htmlspecialchars($d['donation_type']); ?></td>
                <td><?php echo htmlspecialchars($d['amount'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($d['item_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($d['quantity'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($d['donation_status']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody></table>
        </div>
      </section>
    </section>

    <section id="donor-usage" class="tab-section">
      <div class="card">
        <h2>Track Where Donated Items Were Used</h2>
        <p>This section can show donation usage after admin or camp manager links donations to camps.</p>
      </div>
    </section>

    <section id="donor-receipt" class="tab-section">
      <div class="card">
        <h2>Download Donation Receipts</h2>
        <p>Receipt PDF generation can be connected with FPDF/TCPDF later.</p>
        <button class="btn btn-outline" onclick="showAlert('Receipt download demo triggered.')">Download Receipt Demo</button>
      </div>
    </section>
  </section>
</main>
<?php include "../includes/footer.php"; ?>
