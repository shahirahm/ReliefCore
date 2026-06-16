<?php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_role(['Donor', 'Admin']);

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    if ($action === 'make_donation') {
        $donation_type = $_POST['donation_type'];

        if ($donation_type === 'Money') {
            $amount = (float)($_POST['amount'] ?? 0);
            if ($amount <= 0) {
                flash_error("Please enter a valid donation amount.");
                redirect_to("donor.php");
            }

            $stmt = $pdo->prepare("
                INSERT INTO donations (donor_id, donation_type, amount, item_id, quantity, payment_method, donation_status)
                VALUES (?, 'Money', ?, NULL, NULL, ?, 'Received')
            ");
            $stmt->execute([$user_id, $amount, $_POST['payment_method'] ?? 'Cash']);

            $donation_id = $pdo->lastInsertId();
            flash_success("Money donation submitted successfully. Donation ID: #DON-" . str_pad($donation_id, 5, '0', STR_PAD_LEFT));
            redirect_to("donor.php");
        }

        if ($donation_type === 'Supply') {
            $item_id = $_POST['item_id'] ?? null;
            $quantity = (int)($_POST['quantity'] ?? 0);

            if (!$item_id || $quantity <= 0) {
                flash_error("Please select a supply item and valid quantity.");
                redirect_to("donor.php");
            }

            $stmt = $pdo->prepare("
                INSERT INTO donations (donor_id, donation_type, amount, item_id, quantity, payment_method, donation_status)
                VALUES (?, 'Supply', NULL, ?, ?, NULL, 'Received')
            ");
            $stmt->execute([$user_id, $item_id, $quantity]);

            $donation_id = $pdo->lastInsertId();
            flash_success("Supply donation submitted successfully. Donation ID: #DON-" . str_pad($donation_id, 5, '0', STR_PAD_LEFT));
            redirect_to("donor.php");
        }
    }

    flash_error("Invalid donation action.");
    redirect_to("donor.php");

} catch (PDOException $e) {
    flash_error("Donation failed: " . $e->getMessage());
    redirect_to("donor.php");
}
?>
