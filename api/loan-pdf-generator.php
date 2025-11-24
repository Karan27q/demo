<?php
require_once dirname(__DIR__) . '/config/database.php';

// Define font path for FPDF before autoload
define('FPDF_FONTPATH', dirname(__DIR__) . '/fpdf/font/');

// Load FPDF first (required by FPDI)
$fpdfPath = dirname(__DIR__) . '/fpdf/fpdf.php';
if (file_exists($fpdfPath)) {
    require_once $fpdfPath;
} else {
    die("FPDF library not found at: $fpdfPath");
}

// Now load FPDI via Composer autoloader
require_once dirname(__DIR__) . '/fpdf/vendor/autoload.php';

use setasign\Fpdi\Fpdi;

try {
    $pdo = getDBConnection();
    
    // Get loan ID from POST data (preferred) or loan_no (fallback for compatibility)
    $loan_id = $_POST['loan_id'] ?? '';
    $loan_no = $_POST['loan_no'] ?? '';
    
    if (empty($loan_id) && empty($loan_no)) {
        die("Loan ID or loan number is required.");
    }
    
    // Fetch loan and customer data - use loan_id if available, otherwise use loan_no
    // Since multiple loans can have the same loan_no, prefer loan_id (unique)
    // Include all customer and loan fields for complete data
    if (!empty($loan_id)) {
        $stmt = $pdo->prepare("
            SELECT 
                l.*,
                l.recovery_period,
                l.loan_days,
                l.interest_rate,
                c.id as customer_id,
                c.name as customer_name,
                c.mobile,
                c.address,
                c.place,
                c.pincode,
                c.customer_no,
                c.additional_number,
                c.reference,
                DATE_FORMAT(l.loan_date, '%d-%m-%Y') as formatted_loan_date,
                DATE_FORMAT(l.loan_date, '%Y-%m-%d') as loan_date_iso
            FROM loans l
            INNER JOIN customers c ON l.customer_id = c.id
            WHERE l.id = ?
        ");
        $stmt->execute([$loan_id]);
    } else {
        // Fallback: use loan_no and get the latest loan (highest id) for that customer
        $customer_id = $_POST['customer_id'] ?? '';
        if (!empty($customer_id)) {
            // If customer_id is provided, get the specific loan for that customer
            $stmt = $pdo->prepare("
                SELECT 
                    l.*,
                    l.recovery_period,
                    l.loan_days,
                    l.interest_rate,
                    c.id as customer_id,
                    c.name as customer_name,
                    c.mobile,
                    c.address,
                    c.place,
                    c.pincode,
                    c.customer_no,
                    c.additional_number,
                    c.reference,
                    DATE_FORMAT(l.loan_date, '%d-%m-%Y') as formatted_loan_date,
                    DATE_FORMAT(l.loan_date, '%Y-%m-%d') as loan_date_iso
                FROM loans l
                INNER JOIN customers c ON l.customer_id = c.id
                WHERE l.loan_no = ? AND l.customer_id = ?
                ORDER BY l.loan_date DESC, l.id DESC
                LIMIT 1
            ");
            $stmt->execute([$loan_no, $customer_id]);
        } else {
            // No customer_id, just get latest by loan_no
            $stmt = $pdo->prepare("
                SELECT 
                    l.*,
                    l.recovery_period,
                    l.loan_days,
                    l.interest_rate,
                    c.id as customer_id,
                    c.name as customer_name,
                    c.mobile,
                    c.address,
                    c.place,
                    c.pincode,
                    c.customer_no,
                    c.additional_number,
                    c.reference,
                    DATE_FORMAT(l.loan_date, '%d-%m-%Y') as formatted_loan_date,
                    DATE_FORMAT(l.loan_date, '%Y-%m-%d') as loan_date_iso
                FROM loans l
                INNER JOIN customers c ON l.customer_id = c.id
                WHERE l.loan_no = ?
                ORDER BY l.loan_date DESC, l.id DESC
                LIMIT 1
            ");
            $stmt->execute([$loan_no]);
        }
    }
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loan) {
        die("Loan not found. Please ensure the loan exists for the selected customer and date.");
    }
    
    // Calculate loan period in days from loan_days or recovery_period
    $loanPeriodDays = "180 days"; // Default fallback (6 months = 180 days)
    if (!empty($loan["loan_days"])) {
        // Use loan_days directly (preferred)
        $days = intval($loan["loan_days"]);
        $loanPeriodDays = $days . " days";
    } elseif (!empty($loan["recovery_period"])) {
        // If recovery_period exists, try to extract days or convert from months
        $recoveryPeriod = trim($loan["recovery_period"]);
        if (preg_match('/(\d+)\s*(day|days?|d)/i', $recoveryPeriod, $matches)) {
            // Already in days format
            $loanPeriodDays = $matches[1] . " days";
        } elseif (preg_match('/(\d+)\s*(month|months?|m)/i', $recoveryPeriod, $matches)) {
            // Convert months to days (1 month = 30 days)
            $months = intval($matches[1]);
            $days = $months * 30;
            $loanPeriodDays = $days . " days";
        } elseif (is_numeric($recoveryPeriod)) {
            // If it's just a number, assume it's days
            $days = intval($recoveryPeriod);
            $loanPeriodDays = $days . " days";
        } else {
            // Keep as is if it's text
            $loanPeriodDays = $recoveryPeriod;
        }
    }
    
    // Get interest rate (ensure it's formatted correctly)
    $interestRate = !empty($loan["interest_rate"]) 
        ? number_format(floatval($loan["interest_rate"]), 2, '.', '') 
        : "1.00";
    
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
    
            // Date (use loan date if available, otherwise current date)
            $pdf->SetFont('Arial', 'B', 9);
            $displayDate = !empty($loan["formatted_loan_date"]) 
                ? $loan["formatted_loan_date"] 
                : $currentDate;
            $pdf->Text(32, 46, $displayDate);
    
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
    
            //Debt Period (fetched from loan data - displayed in days)
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(170, 63.5, $loanPeriodDays);
            
            //Amount Lent per gram (Interest Rate - fetched from loan data)
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(170, 67, $interestRate . "%");
    
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
    
            //Date of birth (using loan date or date_of_birth if available)
            $pdf->SetFont('Arial', 'B', 9);
            $dateToShow = !empty($loan["date_of_birth"]) 
                ? date('d-m-Y', strtotime($loan["date_of_birth"]))
                : (!empty($loan["formatted_loan_date"]) 
                    ? $loan["formatted_loan_date"] 
                    : date('d-m-Y', strtotime($loan["loan_date"])));
            $pdf->Text(66, 134, $dateToShow);
    
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
