<?php
header('Content-Type: application/json');
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new loan
        $loanNo = $_POST['loan_no'] ?? '';
        $customerId = $_POST['customer_id'] ?? '';
        $loanDate = $_POST['loan_date'] ?? '';
        $principalAmount = $_POST['principal_amount'] ?? '';
        $interestRate = $_POST['interest_rate'] ?? '';
        $totalWeight = $_POST['total_weight'] ?? null;
        $netWeight = $_POST['net_weight'] ?? null;
        $pledgeItems = $_POST['pledge_items'] ?? '';
        
        if (empty($loanNo) || empty($customerId) || empty($loanDate) || empty($principalAmount) || empty($interestRate)) {
            echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
            exit();
        }
        
        // Check if loan number already exists
        $stmt = $pdo->prepare("SELECT id FROM loans WHERE loan_no = ?");
        $stmt->execute([$loanNo]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Loan number already exists']);
            exit();
        }
        
        // Check if customer exists
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ?");
        $stmt->execute([$customerId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
            exit();
        }
        
        // Insert new loan
        $stmt = $pdo->prepare("
            INSERT INTO loans (loan_no, customer_id, loan_date, principal_amount, interest_rate, total_weight, net_weight, pledge_items) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$loanNo, $customerId, $loanDate, $principalAmount, $interestRate, $totalWeight, $netWeight, $pledgeItems]);
        
        echo json_encode(['success' => true, 'message' => 'Loan added successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update loan
        parse_str(file_get_contents("php://input"), $data);
        
        $id = $data['id'] ?? '';
        $principalAmount = $data['principal_amount'] ?? '';
        $interestRate = $data['interest_rate'] ?? '';
        $totalWeight = $data['total_weight'] ?? null;
        $netWeight = $data['net_weight'] ?? null;
        $pledgeItems = $data['pledge_items'] ?? '';
        $status = $data['status'] ?? 'active';
        
        if (empty($id) || empty($principalAmount) || empty($interestRate)) {
            echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
            exit();
        }
        
        $stmt = $pdo->prepare("
            UPDATE loans 
            SET principal_amount = ?, interest_rate = ?, total_weight = ?, net_weight = ?, pledge_items = ?, status = ? 
            WHERE id = ?
        ");
        $stmt->execute([$principalAmount, $interestRate, $totalWeight, $netWeight, $pledgeItems, $status, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Loan updated successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete loan
        $id = $_GET['id'] ?? '';
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Loan ID is required']);
            exit();
        }
        
        // Check if loan has interest records
        $stmt = $pdo->prepare("SELECT COUNT(*) as interest_count FROM interest WHERE loan_id = ?");
        $stmt->execute([$id]);
        $interestCount = $stmt->fetch()['interest_count'];
        
        if ($interestCount > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete loan with existing interest records']);
            exit();
        }
        
        $stmt = $pdo->prepare("DELETE FROM loans WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Loan deleted successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get active loans for dropdown
        $action = $_GET['action'] ?? '';
        
        if ($action === 'get_active_loans') {
            try {
                $stmt = $pdo->query("
                    SELECT 
                        l.id, 
                        l.loan_no, 
                        c.name AS customer_name, 
                        l.principal_amount,
                        COALESCE(i.total_interest_paid, 0) AS total_interest_paid
                    FROM loans l
                    JOIN customers c ON l.customer_id = c.id
                    LEFT JOIN (
                        SELECT loan_id, SUM(interest_amount) AS total_interest_paid
                        FROM interest
                        GROUP BY loan_id
                    ) i ON i.loan_id = l.id
                    WHERE l.status = 'active'
                    ORDER BY l.loan_date DESC
                ");
                $activeLoans = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'loans' => $activeLoans]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        } elseif ($action === 'by_customer') {
            // Return loans for a given customer id
            $customerId = $_GET['customer_id'] ?? '';
            if (empty($customerId)) {
                echo json_encode(['success' => false, 'message' => 'customer_id is required']);
                exit();
            }
            try {
                $stmt = $pdo->prepare("SELECT id, loan_no, status, loan_date FROM loans WHERE customer_id = ? ORDER BY loan_date DESC");
                $stmt->execute([$customerId]);
                $rows = $stmt->fetchAll();
                echo json_encode(['success' => true, 'loans' => $rows]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 