<?php
header('Content-Type: application/json');
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

// Function to ensure database columns exist
function ensureLoanColumns($pdo) {
    $columns = [
        'date_of_birth' => 'DATE DEFAULT NULL',
        'group_id' => 'INT(11) DEFAULT NULL',
        'recovery_period' => 'VARCHAR(50) DEFAULT NULL',
        'ornament_file' => 'VARCHAR(255) DEFAULT NULL',
        'proof_file' => 'VARCHAR(255) DEFAULT NULL',
        'loan_days' => 'INT DEFAULT NULL',
        'interest_amount' => 'DECIMAL(10,2) DEFAULT NULL'
    ];
    
    foreach ($columns as $column => $type) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM loans LIKE '$column'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE loans ADD COLUMN $column $type");
            }
        } catch(PDOException $e) {
            // Column might already exist or table doesn't exist yet, ignore
        }
    }
    
    // Add index for group_id if it doesn't exist
    try {
        $stmt = $pdo->query("SHOW INDEX FROM loans WHERE Key_name = 'idx_group_id'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE loans ADD INDEX idx_group_id (group_id)");
        }
    } catch(PDOException $e) {
        // Index might already exist, ignore
    }
}

try {
    $pdo = getDBConnection();
    
    // Ensure all required columns exist
    ensureLoanColumns($pdo);
    
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
        $dateOfBirth = $_POST['date_of_birth'] ?? null;
        $groupId = $_POST['group_id'] ?? null;
        $recoveryPeriod = $_POST['recovery_period'] ?? null;
        $loanDays = $_POST['loan_days'] ?? null;
        $interestAmount = $_POST['interest_amount'] ?? null;
        
        if (empty($loanNo) || empty($customerId) || empty($loanDate) || empty($principalAmount) || empty($interestRate) || empty($loanDays)) {
            echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
            exit();
        }
        
        // Calculate interest amount if not provided
        // Formula: Principal × (Interest Rate / 100) × (Days / 30)
        if (empty($interestAmount) && !empty($principalAmount) && !empty($interestRate) && !empty($loanDays)) {
            $interestAmount = $principalAmount * ($interestRate / 100) * ($loanDays / 30);
        }
        
        // Note: Loan number uniqueness check removed - customers can have multiple loans
        // Each loan is uniquely identified by its auto-increment 'id' field
        
        // Check if customer exists
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ?");
        $stmt->execute([$customerId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
            exit();
        }
        
        // Handle file uploads - organize by customer name folder
        $ornamentFile = '';
        $proofFile = '';
        
        // Get customer name to create folder structure
        $stmt = $pdo->prepare("SELECT name FROM customers WHERE id = ?");
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch();
        
        if ($customer) {
            // Sanitize customer name for folder name
            $customerFolderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $customer['name']);
            $customerFolderName = str_replace(' ', '_', $customerFolderName);
            $customerFolderName = strtolower($customerFolderName);
            
            // Create customer-specific upload directory
            $uploadDir = $basePath . '/uploads/' . $customerFolderName . '/';
            
            // Create upload directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Handle ornament file upload
            if (isset($_FILES['ornament_file']) && $_FILES['ornament_file']['error'] === UPLOAD_ERR_OK) {
                $ornamentFileUpload = $_FILES['ornament_file'];
                $ornamentExtension = pathinfo($ornamentFileUpload['name'], PATHINFO_EXTENSION);
                $ornamentFileName = 'loan_' . $loanNo . '_ornament_' . time() . '.' . $ornamentExtension;
                $ornamentPath = $uploadDir . $ornamentFileName;
                
                // Validate file type (images and PDF)
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
                if (in_array($ornamentFileUpload['type'], $allowedTypes)) {
                    if (move_uploaded_file($ornamentFileUpload['tmp_name'], $ornamentPath)) {
                        $ornamentFile = 'uploads/' . $customerFolderName . '/' . $ornamentFileName;
                    }
                }
            }
            
            // Handle proof file upload
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
                $proofFileUpload = $_FILES['proof_file'];
                $proofExtension = pathinfo($proofFileUpload['name'], PATHINFO_EXTENSION);
                $proofFileName = 'loan_' . $loanNo . '_proof_' . time() . '.' . $proofExtension;
                $proofPath = $uploadDir . $proofFileName;
                
                // Validate file type (images and PDF)
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
                if (in_array($proofFileUpload['type'], $allowedTypes)) {
                    if (move_uploaded_file($proofFileUpload['tmp_name'], $proofPath)) {
                        $proofFile = 'uploads/' . $customerFolderName . '/' . $proofFileName;
                    }
                }
            }
        }
        
        // Insert new loan
        $stmt = $pdo->prepare("
            INSERT INTO loans (loan_no, customer_id, loan_date, principal_amount, interest_rate, loan_days, interest_amount, total_weight, net_weight, pledge_items, date_of_birth, group_id, recovery_period, ornament_file, proof_file) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $loanNo, 
            $customerId, 
            $loanDate, 
            $principalAmount, 
            $interestRate,
            $loanDays ?: null,
            $interestAmount ?: null,
            $totalWeight, 
            $netWeight, 
            $pledgeItems,
            $dateOfBirth ?: null,
            $groupId ?: null,
            $recoveryPeriod ?: null,
            $ornamentFile ?: null,
            $proofFile ?: null
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Loan added successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get single loan by ID if id parameter is provided (for editing)
        $id = $_GET['id'] ?? '';
        if (!empty($id)) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ?");
                $stmt->execute([$id]);
                $loan = $stmt->fetch();
                
                if ($loan) {
                    echo json_encode(['success' => true, 'loan' => $loan]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Loan not found']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit();
        }
        
        // Get next loan number if action is get_next_number
        $action = $_GET['action'] ?? '';
        
        if ($action === 'reset_loan_numbers') {
            // Reset all loan numbers to start from 1, 2, 3, etc. based on creation order
            try {
                $pdo->beginTransaction();
                
                // Get all loans ordered by creation date (id)
                $stmt = $pdo->query("SELECT id FROM loans ORDER BY id ASC");
                $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $loanNumber = 1;
                foreach ($loans as $loan) {
                    $updateStmt = $pdo->prepare("UPDATE loans SET loan_no = ? WHERE id = ?");
                    $updateStmt->execute([$loanNumber, $loan['id']]);
                    $loanNumber++;
                }
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Loan numbers reset successfully. Next loan number will be ' . $loanNumber]);
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error resetting loan numbers: ' . $e->getMessage()]);
                exit();
            }
        }
        
        if ($action === 'get_next_number') {
            try {
                // RESET: Always return 1 as the next loan number
                // This resets the loan numbering to start from 1
                $nextLoanNo = 1;
                
                echo json_encode(['success' => true, 'loan_no' => $nextLoanNo], JSON_NUMERIC_CHECK);
                exit();
            } catch (Exception $e) {
                // Return default value 1 if there's an error
                echo json_encode(['success' => true, 'loan_no' => 1], JSON_NUMERIC_CHECK);
                exit();
            }
        }
        
        // Get customer details by ID
        if ($action === 'get_customer') {
            $customerId = $_GET['customer_id'] ?? '';
            if (empty($customerId)) {
                echo json_encode(['success' => false, 'message' => 'customer_id is required']);
                exit();
            }
            try {
                $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
                $stmt->execute([$customerId]);
                $customer = $stmt->fetch();
                
                if ($customer) {
                    echo json_encode(['success' => true, 'customer' => $customer]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Customer not found']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit();
        }
        
        // Get active loans for dropdown
        // Show ALL active loans (no deduplication - each loan has unique id)
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
                    ORDER BY l.loan_date DESC, l.id DESC
                ");
                $activeLoans = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'loans' => $activeLoans]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit();
        } elseif ($action === 'by_customer') {
            // Return ALL loans for a given customer id (no deduplication - each loan has unique id)
            $customerId = $_GET['customer_id'] ?? '';
            if (empty($customerId)) {
                echo json_encode(['success' => false, 'message' => 'customer_id is required']);
                exit();
            }
            try {
                // Get ALL loans for customer (no deduplication - each loan has unique id)
                // Include all necessary fields for proper data fetching
                $stmt = $pdo->prepare("
                    SELECT 
                        l.id, 
                        l.loan_no, 
                        l.status, 
                        l.loan_date, 
                        l.principal_amount, 
                        l.interest_rate,
                        l.customer_id,
                        l.loan_days,
                        l.interest_amount,
                        DATE_FORMAT(l.loan_date, '%Y-%m-%d') as loan_date_iso
                    FROM loans l
                    WHERE l.customer_id = ? 
                    ORDER BY l.loan_date DESC, l.id DESC
                ");
                $stmt->execute([$customerId]);
                $allLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'loans' => $allLoans]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit();
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update loan
        parse_str(file_get_contents("php://input"), $data);
        
        $id = $data['id'] ?? '';
        $customerId = $data['customer_id'] ?? '';
        $loanDate = $data['loan_date'] ?? '';
        $principalAmount = $data['principal_amount'] ?? '';
        $interestRate = $data['interest_rate'] ?? '';
        $loanDays = $data['loan_days'] ?? null;
        $interestAmount = $data['interest_amount'] ?? null;
        $totalWeight = $data['total_weight'] ?? null;
        $netWeight = $data['net_weight'] ?? null;
        $pledgeItems = $data['pledge_items'] ?? '';
        $status = $data['status'] ?? 'active';
        
        if (empty($id) || empty($customerId) || empty($loanDate) || empty($principalAmount) || empty($interestRate) || empty($loanDays)) {
            echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
            exit();
        }
        
        // Calculate interest amount if not provided
        // Formula: Principal × (Interest Rate / 100) × (Days / 30)
        if (empty($interestAmount) && !empty($principalAmount) && !empty($interestRate) && !empty($loanDays)) {
            $interestAmount = $principalAmount * ($interestRate / 100) * ($loanDays / 30);
        }
        
        $stmt = $pdo->prepare("
            UPDATE loans 
            SET customer_id = ?, loan_date = ?, principal_amount = ?, interest_rate = ?, loan_days = ?, interest_amount = ?, total_weight = ?, net_weight = ?, pledge_items = ?, status = ? 
            WHERE id = ?
        ");
        $stmt->execute([
            $customerId, 
            $loanDate, 
            $principalAmount, 
            $interestRate, 
            $loanDays ?: null,
            $interestAmount ?: null,
            $totalWeight, 
            $netWeight, 
            $pledgeItems, 
            $status, 
            $id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Loan updated successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete loan
        $id = $_GET['id'] ?? '';
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Loan ID is required']);
            exit();
        }
        
        // Get loan details before deletion
        $stmt = $pdo->prepare("SELECT l.*, c.name as customer_name FROM loans l JOIN customers c ON l.customer_id = c.id WHERE l.id = ?");
        $stmt->execute([$id]);
        $loan = $stmt->fetch();
        
        if (!$loan) {
            echo json_encode(['success' => false, 'message' => 'Loan not found']);
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
        
        // Delete loan files from customer folder
        $customerFolderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $loan['customer_name']);
        $customerFolderName = str_replace(' ', '_', $customerFolderName);
        $customerFolderName = strtolower($customerFolderName);
        
        // Delete ornament file
        if (!empty($loan['ornament_file'])) {
            $ornamentPath = $basePath . '/' . $loan['ornament_file'];
            if (file_exists($ornamentPath)) {
                unlink($ornamentPath);
            }
        }
        
        // Delete proof file
        if (!empty($loan['proof_file'])) {
            $proofPath = $basePath . '/' . $loan['proof_file'];
            if (file_exists($proofPath)) {
                unlink($proofPath);
            }
        }
        
        // Delete loan from database
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
                    INNER JOIN (
                        SELECT loan_no, MAX(id) as max_id
                        FROM loans
                        GROUP BY loan_no
                    ) as latest ON l.loan_no = latest.loan_no AND l.id = latest.max_id
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
            // Return loans for a given customer id (deduplicated - only latest per loan_no)
            $customerId = $_GET['customer_id'] ?? '';
            if (empty($customerId)) {
                echo json_encode(['success' => false, 'message' => 'customer_id is required']);
                exit();
            }
            try {
                // First get all loans for customer, then deduplicate in PHP to ensure uniqueness
                $stmt = $pdo->prepare("
                    SELECT l.id, l.loan_no, l.status, l.loan_date 
                    FROM loans l
                    WHERE l.customer_id = ? 
                    ORDER BY l.loan_date DESC, l.id DESC
                ");
                $stmt->execute([$customerId]);
                $allLoans = $stmt->fetchAll();
                
                // Deduplicate: keep only the latest (highest id) for each loan_no
                $loanMap = [];
                foreach ($allLoans as $loan) {
                    $loanNo = trim($loan['loan_no']);
                    if (!isset($loanMap[$loanNo]) || $loan['id'] > $loanMap[$loanNo]['id']) {
                        $loanMap[$loanNo] = $loan;
                    }
                }
                
                // Convert back to array
                $rows = array_values($loanMap);
                
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