<?php

// Connect to database
$con = new mysqli("localhost", "root", "", "brac_loan");

// Check connection
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

// Validate and sanitize input
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nid = isset($_POST['nid']) ? trim($_POST['nid']) : '';
    $loan_id = isset($_POST['loan_id']) ? intval($_POST['loan_id']) : 0;
    $borrower_id = isset($_POST['borrower_id']) ? intval($_POST['borrower_id']) : 0;
    $loan_no = isset($_POST['loan_no']) ? trim($_POST['loan_no']) : '';
    
    if (empty($nid) && empty($loan_id) && empty($borrower_id)) {
        die("Invalid request. Identification data not provided.");
    }
} else {
    die("Invalid request method.");
}

// Fetch borrower data using NID
$query1 = "SELECT * FROM tbl_borrower WHERE nid = ?";
$stmt1 = $con->prepare($query1);
$stmt1->bind_param("s", $nid);
$stmt1->execute();
$result1 = $stmt1->get_result();
$row_data1 = $result1->fetch_assoc();
$stmt1->close();

// If borrower not found by NID but we have borrower_id
if (!$row_data1 && $borrower_id > 0) {
    $query1b = "SELECT * FROM tbl_borrower WHERE id = ?";
    $stmt1b = $con->prepare($query1b);
    $stmt1b->bind_param("i", $borrower_id);
    $stmt1b->execute();
    $result1b = $stmt1b->get_result();
    $row_data1 = $result1b->fetch_assoc();
    $stmt1b->close();
}

// If we found borrower data, get their ID for other queries
if ($row_data1) {
    $borrower_id = $row_data1['id'];
}

// Fetch loan application data using loan_id or borrower_id
if ($loan_id > 0) {
    $query3 = "SELECT * FROM tbl_loan_application WHERE id = ?";
    $stmt3 = $con->prepare($query3);
    $stmt3->bind_param("i", $loan_id);
} else if ($loan_no != '') {
    $query3 = "SELECT * FROM tbl_loan_application WHERE loan_no = ?";
    $stmt3 = $con->prepare($query3);
    $stmt3->bind_param("s", $loan_no);
} else if ($borrower_id > 0) {
    $query3 = "SELECT * FROM tbl_loan_application WHERE b_id = ? ORDER BY id DESC LIMIT 1";
    $stmt3 = $con->prepare($query3);
    $stmt3->bind_param("i", $borrower_id);
} else {
    die("Not enough information to find loan data.");
}

$stmt3->execute();
$result3 = $stmt3->get_result();
$row_data3 = $result3->fetch_assoc();
$stmt3->close();

// If we found loan data, get loan_id for other queries
if ($row_data3) {
    $loan_id = $row_data3['id'];
    // If we didn't have borrower info before, try to get it now
    if (!$row_data1 && isset($row_data3['b_id'])) {
        $query1c = "SELECT * FROM tbl_borrower WHERE id = ?";
        $stmt1c = $con->prepare($query1c);
        $stmt1c->bind_param("i", $row_data3['b_id']);
        $stmt1c->execute();
        $result1c = $stmt1c->get_result();
        $row_data1 = $result1c->fetch_assoc();
        $stmt1c->close();
    }
}

// Fetch liability data (if any) using borrower_id and loan_id
$row_data2 = null;
if ($borrower_id > 0 && $loan_id > 0) {
    $query2 = "SELECT * FROM tbl_liability WHERE b_id = ? AND loan_id = ? LIMIT 1";
    $stmt2 = $con->prepare($query2);
    $stmt2->bind_param("ii", $borrower_id, $loan_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $row_data2 = $result2->fetch_assoc();
    $stmt2->close();
    
    // If no specific liability for this loan, try to get any liability for this borrower
    if (!$row_data2) {
        $query2b = "SELECT * FROM tbl_liability WHERE b_id = ? ORDER BY id DESC LIMIT 1";
        $stmt2b = $con->prepare($query2b);
        $stmt2b->bind_param("i", $borrower_id);
        $stmt2b->execute();
        $result2b = $stmt2b->get_result();
        $row_data2 = $result2b->fetch_assoc();
        $stmt2b->close();
    }
}

// Check if we have enough data to generate the report
// We need at least borrower and loan data
if (!$row_data1 || !$row_data3) {
    die("No data found for the provided information.");
}

// If no liability data was found, create an empty array with default values
if (!$row_data2) {
    $row_data2 = [
        "property_details" => "N/A",
        "b_id" => $borrower_id,
        "property_name" => "N/A"
    ];
}

// Continue with PDF generation
date_default_timezone_set('Asia/Kolkata');
$currentDate = date('Y-m-d');

require('vendor/autoload.php');
use setasign\Fpdi\Fpdi;

// Load the existing PDF
$pdf = new Fpdi();
$existing_pdf_path = 'Loan Statement.pdf';

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
        $pdf->Text(34, 57, $row_data1["name"]);

        //Amount lent
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Text(170, 53, $row_data2["property_details"]);

        //Cover Number
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Text(170, 57, '');

        //Debt Number
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Text(170, 60, $row_data2["b_id"]);

        //Debt Period
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Text(170, 63.5, "");

        //Amount Lent per gram
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Text(170, 67, "");

        //Amount in Paragraphs
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Text(133, 83.75, $row_data3["expected_loan"]);

        //Amount in Declaration
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Text(101, 105.5, $row_data3["expected_loan"]);

        //Customer Details
        //Name
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Text(66, 126, $row_data1["name"]);

        //Date of birth
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Text(66, 134, $row_data1["dob"]);

        //Address
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Text(66, 142, $row_data1["address"]);

        //Contact Number
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Text(66, 147, $row_data1["mobile"]);

        //Purpose
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Text(30, 166.5, $row_data2["property_name"]);

        //Nett Weight
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Text(47, 174.5, $row_data2["property_details"]);

        //Total Weight
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Text(114, 174.5, $row_data2["property_details"]);

        //Amount
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Text(127, 195.2, $row_data3["expected_loan"]);

        //Name
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Text(167, 195.5, $row_data1["name"]);

        //Vaarisu Niyamana
        //Name
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Text(50, 230, $row_data1["name"]);

        //Debt Person Name
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Text(75, 262, $row_data1["name"]);

        //Date
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Text(75, 267, $currentDate);

        // // Add the current date and time to the top-right corner
        // $pdf->SetFont('Arial', '', 8);
        // $pdf->Text(150, 8, 'Generated On: ' . date('Y-m-d H:i:s'));
    }
}

// Output the PDF
$pdf->Output();
exit;

?>