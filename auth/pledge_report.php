<?php
require_once '../config/database.php';
require_once '../vendor/fpdf/fpdf.php'; // Adjust path if needed

$pdo = getDBConnection();

// Fetch borrowings: join customers and loans
$stmt = $pdo->query("
    SELECT c.customer_no, c.name AS customer_name, c.mobile, l.loan_no, l.loan_date, l.principal_amount, l.interest_rate, l.status
    FROM customers c
    LEFT JOIN loans l ON c.id = l.customer_id
    ORDER BY c.customer_no, l.loan_date DESC
");
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Pledge Borrowings Report', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 10);

// Table header
$pdf->Cell(25, 8, 'Cust No', 1);
$pdf->Cell(35, 8, 'Name', 1);
$pdf->Cell(25, 8, 'Mobile', 1);
$pdf->Cell(25, 8, 'Loan No', 1);
$pdf->Cell(25, 8, 'Date', 1);
$pdf->Cell(25, 8, 'Amount', 1);
$pdf->Cell(15, 8, 'Rate', 1);
$pdf->Cell(15, 8, 'Status', 1);
$pdf->Ln();

// Table rows
$pdf->SetFont('Arial', '', 10);
foreach ($data as $row) {
    $pdf->Cell(25, 8, $row['customer_no'], 1);
    $pdf->Cell(35, 8, $row['customer_name'], 1);
    $pdf->Cell(25, 8, $row['mobile'], 1);
    $pdf->Cell(25, 8, $row['loan_no'], 1);
    $pdf->Cell(25, 8, $row['loan_date'], 1);
    $pdf->Cell(25, 8, $row['principal_amount'], 1);
    $pdf->Cell(15, 8, $row['interest_rate'], 1);
    $pdf->Cell(15, 8, $row['status'], 1);
    $pdf->Ln();
}

// Output PDF
$pdf->Output('D', 'pledge_borrowings_report.pdf');
exit;
?>