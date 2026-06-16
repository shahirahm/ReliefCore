<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../libs/fpdf/fpdf.php";

require_role(['Admin', 'Camp Manager']);

$user_id = $_SESSION['user_id'];
$is_admin = current_user_role() === 'Admin';
$camp_id = $_GET['camp_id'] ?? null;

$params = [];
$where = "";

if ($camp_id) {
    if ($is_admin) {
        $where = "WHERE rc.camp_id = ?";
        $params[] = $camp_id;
    } else {
        $where = "WHERE rc.camp_id = ? AND rc.manager_id = ?";
        $params[] = $camp_id;
        $params[] = $user_id;
    }
} else {
    if (!$is_admin) {
        $where = "WHERE rc.manager_id = ?";
        $params[] = $user_id;
    }
}

$stmt = $pdo->prepare("
    SELECT
        rc.camp_id,
        rc.camp_name,
        rc.capacity,
        rc.current_population,
        rc.status,
        dc.category_name,
        cl.location_name,
        cl.district
    FROM relief_camps rc
    LEFT JOIN disaster_categories dc ON rc.category_id = dc.category_id
    LEFT JOIN camp_locations cl ON rc.location_id = cl.location_id
    $where
    ORDER BY rc.camp_id DESC
");
$stmt->execute($params);
$camps = $stmt->fetchAll();

if (!$camps) {
    die("No camp data found or you do not have permission to access this report.");
}

$pdf = new FPDF();
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Camp Summary Report', 0, 1, 'C');

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 8, 'Generated on: ' . date('d M Y, h:i A'), 0, 1);
$pdf->Ln(4);

foreach ($camps as $camp) {
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, 'Camp: ' . $camp['camp_name'], 0, 1);

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 7, 'Disaster Category: ' . ($camp['category_name'] ?? 'Not set'), 0, 1);
    $pdf->Cell(0, 7, 'Location: ' . ($camp['location_name'] ?? 'Not set') . ' ' . ($camp['district'] ?? ''), 0, 1);
    $pdf->Cell(0, 7, 'Capacity: ' . $camp['capacity'] . ' | Current Population: ' . $camp['current_population'] . ' | Status: ' . $camp['status'], 0, 1);
    $pdf->Ln(3);

    $family_stmt = $pdo->prepare("SELECT COUNT(*) AS families, COALESCE(SUM(total_members),0) AS members FROM affected_families WHERE camp_id=?");
    $family_stmt->execute([$camp['camp_id']]);
    $family_summary = $family_stmt->fetch();

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'Family Summary', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 7, 'Registered Families: ' . $family_summary['families'] . ' | Registered People: ' . $family_summary['members'], 0, 1);
    $pdf->Ln(2);

    $stock_stmt = $pdo->prepare("
        SELECT si.item_name, si.item_category, si.unit, cs.quantity, cs.minimum_required
        FROM camp_stock cs
        JOIN supply_items si ON cs.item_id = si.item_id
        WHERE cs.camp_id=?
        ORDER BY si.item_category, si.item_name
    ");
    $stock_stmt->execute([$camp['camp_id']]);
    $stocks = $stock_stmt->fetchAll();

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'Stock Summary', 0, 1);

    if ($stocks) {
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(55, 7, 'Item', 1);
        $pdf->Cell(35, 7, 'Category', 1);
        $pdf->Cell(30, 7, 'Quantity', 1);
        $pdf->Cell(35, 7, 'Minimum', 1);
        $pdf->Cell(35, 7, 'Status', 1);
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 8);
        foreach ($stocks as $stock) {
            $status = 'OK';
            if ($stock['minimum_required'] <= 0) $status = 'No Minimum';
            else if ($stock['quantity'] < $stock['minimum_required']) $status = 'Shortage';
            else if ($stock['quantity'] <= ($stock['minimum_required'] * 1.25)) $status = 'Low Stock';

            $pdf->Cell(55, 7, substr($stock['item_name'], 0, 28), 1);
            $pdf->Cell(35, 7, $stock['item_category'], 1);
            $pdf->Cell(30, 7, $stock['quantity'] . ' ' . $stock['unit'], 1);
            $pdf->Cell(35, 7, $stock['minimum_required'] . ' ' . $stock['unit'], 1);
            $pdf->Cell(35, 7, $status, 1);
            $pdf->Ln();
        }
    } else {
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 7, 'No stock records found.', 0, 1);
    }

    $pdf->Ln(5);

    $dist_stmt = $pdo->prepare("
        SELECT COUNT(*) AS total_logs, COALESCE(SUM(quantity),0) AS total_quantity
        FROM aid_distribution
        WHERE camp_id=?
    ");
    $dist_stmt->execute([$camp['camp_id']]);
    $dist = $dist_stmt->fetch();

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'Aid Distribution Summary', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 7, 'Distribution Logs: ' . $dist['total_logs'] . ' | Total Quantity Distributed: ' . $dist['total_quantity'], 0, 1);

    $pdf->Ln(8);
}

$pdf->Output('D', 'camp_summary_report.pdf');
exit;
?>
