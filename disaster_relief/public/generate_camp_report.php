<?php
error_reporting(E_ALL);

ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../db/libs/fpdf/fpdf.php";

require_role(['Admin', 'Camp Manager']);

$pdf = new FPDF();
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Camp Summary Report', 0, 1, 'C');

$pdf->Ln(5);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 8, 'Generated on: ' . date('d M Y, h:i A'), 0, 1);

$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(45, 8, 'Camp Name', 1);
$pdf->Cell(35, 8, 'Category', 1);
$pdf->Cell(35, 8, 'Location', 1);
$pdf->Cell(25, 8, 'Capacity', 1);
$pdf->Cell(30, 8, 'Population', 1);
$pdf->Cell(20, 8, 'Status', 1);
$pdf->Ln();

$stmt = $pdo->query("
    SELECT 
        rc.camp_name,
        dc.category_name,
        cl.location_name,
        rc.capacity,
        rc.current_population,
        rc.status
    FROM relief_camps rc
    LEFT JOIN disaster_categories dc ON rc.category_id = dc.category_id
    LEFT JOIN camp_locations cl ON rc.location_id = cl.location_id
    ORDER BY rc.camp_id DESC
");

$camps = $stmt->fetchAll();

$pdf->SetFont('Arial', '', 9);

foreach ($camps as $camp) {
    $pdf->Cell(45, 8, substr($camp['camp_name'], 0, 22), 1);
    $pdf->Cell(35, 8, substr($camp['category_name'] ?? '', 0, 16), 1);
    $pdf->Cell(35, 8, substr($camp['location_name'] ?? '', 0, 16), 1);
    $pdf->Cell(25, 8, $camp['capacity'], 1);
    $pdf->Cell(30, 8, $camp['current_population'], 1);
    $pdf->Cell(20, 8, $camp['status'], 1);
    $pdf->Ln();
}

$pdf->Output('D', 'camp_summary_report.pdf');
exit;
?>