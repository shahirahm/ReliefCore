<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../libs/fpdf/fpdf.php";

require_role(['Donor', 'Admin']);

$user_id = $_SESSION['user_id'];
$is_admin = current_user_role() === 'Admin';
$donation_id = $_GET['donation_id'] ?? null;

if (!$donation_id) die("Donation ID missing.");

if ($is_admin) {
    $stmt = $pdo->prepare("
        SELECT d.*, u.full_name AS donor_name, u.email AS donor_email, u.phone AS donor_phone,
               si.item_name, si.item_category, si.unit
        FROM donations d
        LEFT JOIN users u ON d.donor_id = u.user_id
        LEFT JOIN supply_items si ON d.item_id = si.item_id
        WHERE d.donation_id = ?
    ");
    $stmt->execute([$donation_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT d.*, u.full_name AS donor_name, u.email AS donor_email, u.phone AS donor_phone,
               si.item_name, si.item_category, si.unit
        FROM donations d
        LEFT JOIN users u ON d.donor_id = u.user_id
        LEFT JOIN supply_items si ON d.item_id = si.item_id
        WHERE d.donation_id = ? AND d.donor_id = ?
    ");
    $stmt->execute([$donation_id, $user_id]);
}
$donation = $stmt->fetch();

if (!$donation) die("Donation not found or permission denied.");

$pdf = new FPDF();
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 18);
$pdf->Cell(0, 12, 'Donation Receipt', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 8, 'Disaster Relief Network', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Receipt Information', 0, 1);
$pdf->SetFont('Arial', '', 10);

$pdf->Cell(55, 8, 'Donation ID:', 1);
$pdf->Cell(0, 8, '#DON-' . str_pad($donation['donation_id'], 5, '0', STR_PAD_LEFT), 1, 1);

$pdf->Cell(55, 8, 'Donor Name:', 1);
$pdf->Cell(0, 8, $donation['donor_name'] ?? 'Unknown', 1, 1);

$pdf->Cell(55, 8, 'Email:', 1);
$pdf->Cell(0, 8, $donation['donor_email'] ?? '', 1, 1);

$pdf->Cell(55, 8, 'Phone:', 1);
$pdf->Cell(0, 8, $donation['donor_phone'] ?? '', 1, 1);

$pdf->Cell(55, 8, 'Donation Type:', 1);
$pdf->Cell(0, 8, $donation['donation_type'], 1, 1);

if ($donation['donation_type'] === 'Money') {
    $pdf->Cell(55, 8, 'Amount:', 1);
    $pdf->Cell(0, 8, number_format((float)$donation['amount'], 2), 1, 1);
    $pdf->Cell(55, 8, 'Payment Method:', 1);
    $pdf->Cell(0, 8, $donation['payment_method'] ?? '', 1, 1);
} else {
    $pdf->Cell(55, 8, 'Supply Item:', 1);
    $pdf->Cell(0, 8, ($donation['item_name'] ?? 'Supply') . ' - ' . ($donation['item_category'] ?? ''), 1, 1);
    $pdf->Cell(55, 8, 'Quantity:', 1);
    $pdf->Cell(0, 8, ($donation['quantity'] ?? 0) . ' ' . ($donation['unit'] ?? ''), 1, 1);
}

$pdf->Cell(55, 8, 'Status:', 1);
$pdf->Cell(0, 8, $donation['donation_status'], 1, 1);

$pdf->Cell(55, 8, 'Donation Date:', 1);
$pdf->Cell(0, 8, $donation['donated_at'], 1, 1);

$pdf->Ln(10);
$pdf->MultiCell(0, 7, 'Thank you for supporting disaster relief activities. This receipt confirms that your donation was recorded in the Disaster Relief Network system.');

$pdf->Ln(15);
$pdf->Cell(0, 8, 'Authorized Signature: ___________________________', 0, 1);

$pdf->Output('D', 'donation_receipt_DON_' . str_pad($donation['donation_id'], 5, '0', STR_PAD_LEFT) . '.pdf');
exit;
?>
