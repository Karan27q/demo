<?php
header('Content-Type: application/json');
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    $type = $_GET['type'] ?? '';
    $fromDate = $_GET['from'] ?? '';
    $toDate = $_GET['to'] ?? '';
    $status = $_GET['status'] ?? '';
    $format = $_GET['format'] ?? 'json';
    
    if ($type === 'daywise') {
        // Day-wise Summary Report
        $data = generateDayWiseSummary($pdo, $fromDate, $toDate);
        
    } elseif ($type === 'advance') {
        // Advance Report
        $data = generateAdvanceReport($pdo, $fromDate, $toDate, $status);
        
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid report type']);
        exit;
    }
    
    if ($format === 'json') {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        // Handle PDF/Excel export
        handleExport($type, $data, $format, $pdo, $fromDate, $toDate, $status);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function generateDayWiseSummary($pdo, $fromDate, $toDate) {
    $whereClause = '';
    $params = [];
    
    if (!empty($fromDate) && !empty($toDate)) {
        $whereClause = "WHERE date BETWEEN ? AND ?";
        $params = [$fromDate, $toDate];
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            date,
            SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END) as total_credit,
            SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END) as total_debit,
            SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE -amount END) as net_amount
        FROM transactions 
        $whereClause
        GROUP BY date 
        ORDER BY date DESC
    ");
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    $result = [];
    $runningBalance = 0;
    
    foreach ($transactions as $transaction) {
        $openingBalance = $runningBalance;
        $runningBalance += $transaction['net_amount'];
        
        $result[] = [
            'date' => date('d/m/Y', strtotime($transaction['date'])),
            'opening_balance' => number_format($openingBalance, 2),
            'total_credit' => number_format($transaction['total_credit'], 2),
            'total_debit' => number_format($transaction['total_debit'], 2),
            'closing_balance' => number_format($runningBalance, 2)
        ];
    }
    
    return $result;
}

function generateAdvanceReport($pdo, $fromDate, $toDate, $status) {
    $whereConditions = [];
    $params = [];
    
    if (!empty($fromDate) && !empty($toDate)) {
        $whereConditions[] = "l.loan_date BETWEEN ? AND ?";
        $params[] = $fromDate;
        $params[] = $toDate;
    }
    
    if (!empty($status) && $status !== 'all') {
        $whereConditions[] = "l.status = ?";
        $params[] = $status;
    }
    
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            l.loan_date,
            l.loan_no,
            c.name as customer_name,
            l.total_weight,
            l.net_weight,
            l.interest_rate,
            l.principal_amount,
            l.status
        FROM loans l
        JOIN customers c ON l.customer_id = c.id
        $whereClause
        ORDER BY l.loan_date DESC
    ");
    $stmt->execute($params);
    
    $result = [];
    while ($row = $stmt->fetch()) {
        $result[] = [
            'loan_date' => date('d-m-Y', strtotime($row['loan_date'])),
            'loan_no' => $row['loan_no'],
            'customer_name' => $row['customer_name'],
            'total_weight' => number_format($row['total_weight'], 2),
            'net_weight' => number_format($row['net_weight'], 2),
            'interest_rate' => $row['interest_rate'],
            'principal_amount' => number_format($row['principal_amount'], 2),
            'status' => $row['status']
        ];
    }
    
    return $result;
}


function handleExport($type, $data, $format, $pdo = null, $fromDate = '', $toDate = '', $status = '') {
    if ($format === 'pdf') {
        // Generate PDFs using FPDF
        $basePath = dirname(__DIR__);
        $fpdfIncluded = false;
        $paths = [
            $basePath . '/fpdf/fpdf.php',
            $basePath . '/fpdf/vendor/setasign/fpdf/fpdf.php'
        ];
        foreach ($paths as $p) {
            if (is_file($p)) { require_once $p; $fpdfIncluded = true; break; }
        }
        if (!$fpdfIncluded || !class_exists('FPDF')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'FPDF library not found']);
            return;
        }


        // Default PDF for other types: simple fallback
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'PDF export not implemented for this report type']);
    } elseif ($format === 'excel' || $format === 'csv') {
        // For Excel/CSV export
        $filename = $type . '_report_' . date('Y-m-d') . '.' . $format;
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        if (!empty($data)) {
            $output = fopen('php://output', 'w');
            
            // Write headers
            if ($type === 'daywise') {
                fputcsv($output, ['Date', 'Day Opening Balance', 'Total Credit (Varavu)', 'Total Debit (Patru)', 'Day Closing Balance']);
            } elseif ($type === 'advance') {
                fputcsv($output, ['Serial No', 'Date', 'Loan No', 'Name', 'Total Weight (g)', 'Net Weight (g)', 'Interest Rate', 'Amount (₹)', 'Status']);
            }
            
            // Write data
            foreach ($data as $row) {
                fputcsv($output, array_values($row));
            }
            
            fclose($output);
        }
    }
}
?> 