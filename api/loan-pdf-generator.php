<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/fpdf/vendor/autoload.php';

use setasign\Fpdi\Fpdi;

try {
    $pdo = getDBConnection();
    
    // Get loan number from POST data
    $loan_no = $_POST['loan_no'] ?? '';
    
    if (empty($loan_no)) {
        die("Loan number is required.");
    }
    
    // Fetch loan and customer data
    $stmt = $pdo->prepare("
        SELECT 
            l.*,
            c.name as customer_name,
            c.mobile,
            c.address,
            c.customer_no
        FROM loans l
        JOIN customers c ON l.customer_id = c.id
        WHERE l.loan_no = ?
    ");
    $stmt->execute([$loan_no]);
    $loan = $stmt->fetch();
    
    if (!$loan) {
        die("Loan not found.");
    }
    
    // Continue with PDF generation using the existing template
    date_default_timezone_set('Asia/Kolkata');
    $currentDate = date('Y-m-d');
    
    // Load the existing PDF template
    $pdf = new Fpdi();
    $existing_pdf_path = dirname(__DIR__) . '/fpdf/Loan Statement.pdf';
    
    // Set the source file
    $pageCount = $pdf->setSourceFile($existing_pdf_path);
    
    // Loop through all the pages of the PDF
    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $pdf->AddPage();
        
        // Import each page
        $tplId = $pdf->importPage($pageNo);
        $pdf->useTemplate($tplId, 10, 10, 200);
    
        // Apply changes only to the first page
        if ($pageNo == 1) {
    
            $pdf->SetTextColor(0, 0, 0);
    
            // Date
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(32, 46, $currentDate);
    
            //Name
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(34, 57, $loan["customer_name"]);
    
            //Amount lent
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(170, 53, $loan["principal_amount"]);
    
            //Cover Number
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(170, 57, $loan["customer_no"]);
    
            //Debt Number
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(170, 60, $loan["loan_no"]);
    
            //Debt Period
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(170, 63.5, "6 months");
    
            //Amount Lent per gram
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(170, 67, $loan["interest_rate"] . "%");
    
            //Amount in Paragraphs
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(133, 83.75, $loan["principal_amount"]);
    
            //Amount in Declaration
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(101, 105.5, $loan["principal_amount"]);
    
            //Customer Details
            //Name
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(66, 126, $loan["customer_name"]);
    
            //Date of birth (using loan date as placeholder)
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(66, 134, date('d-m-Y', strtotime($loan["loan_date"])));
    
            //Address
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(66, 142, $loan["address"] ?? 'N/A');
    
            //Contact Number
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(66, 147, $loan["mobile"]);
    
            //Purpose (using pledge items)
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(30, 166.5, $loan["pledge_items"] ?? 'N/A');
    
            //Nett Weight
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(47, 174.5, $loan["net_weight"] . 'g');
    
            //Total Weight
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(114, 174.5, $loan["total_weight"] . 'g');
    
            //Amount
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(127, 195.2, $loan["principal_amount"]);
    
            //Name
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(167, 195.5, $loan["customer_name"]);
    
            //Vaarisu Niyamana
            //Name
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(50, 230, $loan["customer_name"]);
    
            //Debt Person Name
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(75, 262, $loan["customer_name"]);
    
            //Date
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(75, 267, $currentDate);
        }
    }
    
    // Output the PDF
    $filename = 'loan_statement_' . $loan_no . '_' . date('Ymd_His') . '.pdf';
    $pdf->Output('D', $filename);
    
} catch (Exception $e) {
    die("Error generating PDF: " . $e->getMessage());
}
?>
