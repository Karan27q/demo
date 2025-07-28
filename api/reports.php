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
        
    } elseif ($type === 'pledge') {
        // Pledge Report
        $data = generatePledgeReport($pdo, $fromDate, $toDate, $status);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid report type']);
        exit;
    }
    
    if ($format === 'json') {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        // Handle PDF/Excel export
        handleExport($type, $data, $format);
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

function generatePledgeReport($pdo, $fromDate, $toDate, $status) {
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
            l.loan_date as date,
            l.loan_no,
            c.name as customer_name,
            l.loan_date as bank_pledge_date,
            '-' as bank_assessor_name,
            '-' as bank_name,
            l.interest_rate as interest,
            l.principal_amount as loan_amount,
            DATE_ADD(l.loan_date, INTERVAL 6 MONTH) as due_date,
            '-' as additional_charges,
            '-' as bank_loan_no
        FROM loans l
        JOIN customers c ON l.customer_id = c.id
        $whereClause
        ORDER BY l.loan_date DESC
    ");
    $stmt->execute($params);
    
    $result = [];
    while ($row = $stmt->fetch()) {
        $result[] = [
            'date' => date('Y-m-d', strtotime($row['date'])),
            'loan_no' => $row['loan_no'],
            'customer_name' => $row['customer_name'],
            'bank_pledge_date' => date('d-m-Y', strtotime($row['bank_pledge_date'])),
            'bank_assessor_name' => $row['bank_assessor_name'],
            'bank_name' => $row['bank_name'],
            'interest' => $row['interest'],
            'loan_amount' => $row['loan_amount'],
            'due_date' => date('Y-m-d', strtotime($row['due_date'])),
            'additional_charges' => $row['additional_charges'],
            'bank_loan_no' => $row['bank_loan_no']
        ];
    }
    
    return $result;
}

function handleExport($type, $data, $format) {
    if ($format === 'pdf') {
        // For PDF export, you would typically use a library like TCPDF or FPDF
        // For now, we'll return JSON with a message
        echo json_encode(['success' => true, 'message' => 'PDF export functionality would be implemented here']);
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
            } elseif ($type === 'pledge') {
                fputcsv($output, ['S.NO', 'DATE', 'LOAN NO', 'NAME', 'BANK PLEDGE DATE', 'BANK ASSESSOR NAME', 'BANK NAME', 'INTEREST', 'LOAN AMOUNT', 'DUE DATE', 'ADDITIONAL CHARGES', 'LOAN NO']);
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