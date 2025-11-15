<?php
header('Content-Type: application/json');
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';
require_once $basePath . '/config/interest_calculator.php';

try {
    $pdo = getDBConnection();
    
    // Get comprehensive dashboard statistics
    $stats = [];
    
    // Customer count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers");
    $stats['customers'] = $stmt->fetch()['count'];
    
    // Active loans count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM loans WHERE status = 'active'");
    $stats['active_loans'] = $stmt->fetch()['count'];
    
    // Closed loans count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM loans WHERE status = 'closed'");
    $stats['closed_loans'] = $stmt->fetch()['count'];
    
    // Total principal amount (active loans)
    $stmt = $pdo->query("SELECT COALESCE(SUM(principal_amount), 0) as total FROM loans WHERE status = 'active'");
    $stats['total_principal'] = (float)$stmt->fetch()['total'];
    
    // Total interest paid
    $stmt = $pdo->query("SELECT COALESCE(SUM(interest_amount), 0) as total FROM interest");
    $stats['total_interest_paid'] = (float)$stmt->fetch()['total'];
    
    // Calculate outstanding interest for active loans
    $stmt = $pdo->query("
        SELECT 
            l.id,
            l.principal_amount,
            l.interest_rate,
            l.loan_date,
            COALESCE(SUM(i.interest_amount), 0) as interest_paid
        FROM loans l
        LEFT JOIN interest i ON l.id = i.loan_id
        WHERE l.status = 'active'
        GROUP BY l.id
    ");
    $activeLoans = $stmt->fetchAll();
    
    $totalOutstanding = 0;
    foreach ($activeLoans as $loan) {
        // Calculate expected interest using standardized function
        $expectedInterest = calculateExpectedInterestByCalendarMonths(
            $loan['principal_amount'],
            $loan['interest_rate'],
            $loan['loan_date']
        );
        $outstanding = max(0, $expectedInterest - $loan['interest_paid']);
        $totalOutstanding += $outstanding;
    }
    $stats['outstanding_interest'] = $totalOutstanding;
    
    // Total transactions (credit)
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE transaction_type = 'credit'");
    $stats['total_credit'] = (float)$stmt->fetch()['total'];
    
    // Total transactions (debit)
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE transaction_type = 'debit'");
    $stats['total_debit'] = (float)$stmt->fetch()['total'];
    
    // Net balance
    $stats['net_balance'] = $stats['total_credit'] - $stats['total_debit'];
    
    // Recent loans (last 10)
    $stmt = $pdo->query("
        SELECT 
            l.*, 
            c.name as customer_name, 
            c.mobile,
            c.customer_no,
            COALESCE(SUM(i.interest_amount), 0) as interest_paid
        FROM loans l 
        JOIN customers c ON l.customer_id = c.id 
        LEFT JOIN interest i ON l.id = i.loan_id
        WHERE l.status = 'active' 
        GROUP BY l.id
        ORDER BY l.loan_date DESC 
        LIMIT 10
    ");
    $stats['recent_loans'] = $stmt->fetchAll();
    
    // Calculate days and outstanding for each loan
    foreach ($stats['recent_loans'] as &$loan) {
        $loanDate = new DateTime($loan['loan_date']);
        $today = new DateTime();
        $daysDiff = $today->diff($loanDate)->days;
        $loan['days_passed'] = $daysDiff;
        
        // Calculate expected interest using standardized function
        $expectedInterest = calculateExpectedInterestByCalendarMonths(
            $loan['principal_amount'],
            $loan['interest_rate'],
            $loan['loan_date']
        );
        $loan['interest_outstanding'] = max(0, $expectedInterest - $loan['interest_paid']);
    }
    
    // Recent transactions (last 10)
    $stmt = $pdo->query("
        SELECT * FROM transactions 
        ORDER BY date DESC, created_at DESC 
        LIMIT 10
    ");
    $stats['recent_transactions'] = $stmt->fetchAll();
    
    // Loan trends (last 30 days)
    $stmt = $pdo->query("
        SELECT 
            DATE(loan_date) as date,
            COUNT(*) as count,
            SUM(principal_amount) as total_amount
        FROM loans 
        WHERE loan_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(loan_date)
        ORDER BY date ASC
    ");
    $stats['loan_trends'] = $stmt->fetchAll();
    
    // If only trends are requested, return only that data
    if (isset($_GET['trends_only']) && $_GET['trends_only'] == '1') {
        echo json_encode(['success' => true, 'data' => ['loan_trends' => $stats['loan_trends']]]);
    } else {
        echo json_encode(['success' => true, 'data' => $stats]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>

