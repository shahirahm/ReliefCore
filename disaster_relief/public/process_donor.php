<?php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_login();

$action = $_POST['action'] ?? '';

try {
    if ($action === 'donate_money') {
        $stmt = $pdo->prepare("INSERT INTO donations (donor_id, donation_type, amount, payment_method, donation_status) VALUES (?, 'Money', ?, ?, 'Pending')");
        $stmt->execute([$_SESSION['user_id'], $_POST['amount'], $_POST['payment_method']]);
        flash_success("Money donation submitted successfully.");
    }

    if ($action === 'donate_supply') {
        $stmt = $pdo->prepare("INSERT INTO donations (donor_id, donation_type, item_id, quantity, donation_status) VALUES (?, 'Supply', ?, ?, 'Pending')");
        $stmt->execute([$_SESSION['user_id'], $_POST['item_id'], $_POST['quantity']]);
        flash_success("Supply donation submitted successfully.");
    }
} catch (PDOException $e) {
    flash_error("Donation failed: " . $e->getMessage());
}

redirect_to("donor.php");
?>