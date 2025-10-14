<?php
// Loan PDF generator
// URL: api/loan-pdf.php?loan_id=123

declare(strict_types=1);

// Basic guards
if (!isset($_GET['loan_id']) || !is_numeric($_GET['loan_id'])) {
    http_response_code(400);
    echo 'Missing or invalid loan_id';
    exit;
}

$loanId = (int) $_GET['loan_id'];

$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

// Include FPDF (prefer local bundled)
// Try primary path first, fallback to vendor path if needed
$fpdfIncluded = false;
$paths = [
    $basePath . '/fpdf/fpdf.php',
    $basePath . '/fpdf/vendor/setasign/fpdf/fpdf.php'
];
foreach ($paths as $p) {
    if (is_file($p)) {
        require_once $p;
        $fpdfIncluded = true;
        break;
    }
}

if (!$fpdfIncluded || !class_exists('FPDF')) {
    http_response_code(500);
    echo 'FPDF library not found.';
    exit;
}

try {
    $pdo = getDBConnection();

    // Fetch loan + customer + aggregates
    $stmt = $pdo->prepare("SELECT l.*, c.name AS customer_name, c.customer_no, c.mobile,
        COALESCE(SUM(i.interest_amount), 0) AS total_interest_paid
        FROM loans l
        JOIN customers c ON c.id = l.customer_id
        LEFT JOIN interest i ON i.loan_id = l.id
        WHERE l.id = ?
        GROUP BY l.id");
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch();

    if (!$loan) {
        http_response_code(404);
        echo 'Loan not found';
        exit;
    }

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetTitle('Loan Details - ' . $loan['loan_no']);
    $pdf->AddPage();

    // Header
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Lakshmi Finance - Loan Details', 0, 1, 'C');
    $pdf->Ln(2);

    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(40, 8, 'Generated:', 0, 0);
    $pdf->Cell(0, 8, date('d-m-Y H:i'), 0, 1);
    $pdf->Ln(2);

    // Loan info
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Loan Information', 0, 1);
    $pdf->SetFont('Arial', '', 11);

    $rows = [
        ['Loan No', $loan['loan_no']],
        ['Loan Date', date('d-m-Y', strtotime($loan['loan_date']))],
        ['Status', strtoupper($loan['status'])],
        ['Principal Amount', number_format((float)$loan['principal_amount'], 2)],
        ['Interest Rate (%)', (string)$loan['interest_rate']],
        ['Total Weight (g)', $loan['total_weight'] !== null ? number_format((float)$loan['total_weight'], 2) : '-'],
        ['Net Weight (g)', $loan['net_weight'] !== null ? number_format((float)$loan['net_weight'], 2) : '-'],
        ['Pledge Items', (string)($loan['pledge_items'] ?? '-')],
    ];

    foreach ($rows as $r) {
        $pdf->Cell(50, 8, $r[0] . ':', 0, 0);
        $pdf->MultiCell(0, 8, (string)$r[1], 0, 'L');
    }

    $pdf->Ln(2);

    // Customer info
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Customer', 0, 1);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(50, 8, 'Customer No:', 0, 0);
    $pdf->Cell(0, 8, $loan['customer_no'], 0, 1);
    $pdf->Cell(50, 8, 'Name:', 0, 0);
    $pdf->Cell(0, 8, $loan['customer_name'], 0, 1);
    $pdf->Cell(50, 8, 'Mobile:', 0, 0);
    $pdf->Cell(0, 8, $loan['mobile'], 0, 1);

    $pdf->Ln(2);

    // Totals
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Payments', 0, 1);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(50, 8, 'Total Interest Paid:', 0, 0);
    $pdf->Cell(0, 8, number_format((float)$loan['total_interest_paid'], 2), 0, 1);
    $remaining = max(0, (float)$loan['principal_amount'] - (float)$loan['total_interest_paid']);
    $pdf->Cell(50, 8, 'Remaining Amount:', 0, 0);
    $pdf->Cell(0, 8, number_format($remaining, 2), 0, 1);

    // Output inline
    $filename = 'loan_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$loan['loan_no']) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    $pdf->Output('I', $filename);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Failed to generate PDF: ' . $e->getMessage();
}
?>

