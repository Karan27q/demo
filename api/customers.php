<?php
// Start output buffering and set JSON header FIRST
if (!ob_get_level()) {
    ob_start();
}
header('Content-Type: application/json');
ini_set('display_errors', 0);

// Include API helper for consistent error handling (if available)
$apiHelperPath = __DIR__ . '/api-helper.php';
if (file_exists($apiHelperPath)) {
    require_once $apiHelperPath;
} else {
    // Fallback error handler if api-helper.php doesn't exist
    function returnJsonError($message, $code = 500) {
        http_response_code($code);
        ob_clean();
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    }
}

// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

// Function to ensure database columns exist
function ensureCustomerColumns($pdo) {
    $columns = [
        'place' => 'VARCHAR(100) DEFAULT NULL',
        'pincode' => 'VARCHAR(10) DEFAULT NULL',
        'additional_number' => 'VARCHAR(15) DEFAULT NULL',
        'reference' => 'VARCHAR(100) DEFAULT NULL',
        'proof_type' => 'VARCHAR(50) DEFAULT NULL',
        'customer_photo' => 'VARCHAR(255) DEFAULT NULL',
        'proof_file' => 'VARCHAR(255) DEFAULT NULL'
    ];
    
    foreach ($columns as $column => $type) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM customers LIKE '$column'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE customers ADD COLUMN $column $type");
            }
        } catch(PDOException $e) {
            // Column might already exist or table doesn't exist, ignore
        }
    }
}

try {
    $pdo = getDBConnection();
    
    // Ensure all required columns exist
    ensureCustomerColumns($pdo);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new customer
        $customerNo = $_POST['customer_no'] ?? '';
        $name = $_POST['name'] ?? '';
        $mobile = $_POST['mobile'] ?? '';
        $address = $_POST['address'] ?? '';
        $place = $_POST['place'] ?? '';
        $pincode = $_POST['pincode'] ?? '';
        $additionalNumber = $_POST['additional_number'] ?? '';
        $reference = $_POST['reference'] ?? '';
        $proofType = $_POST['proof_type'] ?? '';
        
        if (empty($customerNo) || empty($name) || empty($mobile)) {
            echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
            exit();
        }
        
        // Check if customer number already exists
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE customer_no = ?");
        $stmt->execute([$customerNo]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Customer number already exists']);
            exit();
        }
        
        // Handle file uploads - organize by customer name folder
        $customerPhoto = '';
        $proofFile = '';
        
        // Sanitize customer name for folder name (remove special characters)
        $customerFolderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $customerFolderName = str_replace(' ', '_', $customerFolderName);
        $customerFolderName = strtolower($customerFolderName);
        
        // Create customer-specific upload directory
        $uploadDir = $basePath . '/uploads/' . $customerFolderName . '/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Handle customer photo upload
        if (isset($_FILES['customer_photo']) && $_FILES['customer_photo']['error'] === UPLOAD_ERR_OK) {
            $photoFile = $_FILES['customer_photo'];
            $photoExtension = pathinfo($photoFile['name'], PATHINFO_EXTENSION);
            $photoFileName = 'customer_photo.' . $photoExtension;
            $photoPath = $uploadDir . $photoFileName;
            
            // Validate image file
            $allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (in_array($photoFile['type'], $allowedImageTypes)) {
                if (move_uploaded_file($photoFile['tmp_name'], $photoPath)) {
                    $customerPhoto = 'uploads/' . $customerFolderName . '/' . $photoFileName;
                }
            }
        }
        
        // Handle proof file upload
        if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
            $proofFileUpload = $_FILES['proof_file'];
            $proofExtension = pathinfo($proofFileUpload['name'], PATHINFO_EXTENSION);
            $proofFileName = 'proof_' . time() . '.' . $proofExtension;
            $proofPath = $uploadDir . $proofFileName;
            
            // Validate file type (images and PDF)
            $allowedProofTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
            if (in_array($proofFileUpload['type'], $allowedProofTypes)) {
                if (move_uploaded_file($proofFileUpload['tmp_name'], $proofPath)) {
                    $proofFile = 'uploads/' . $customerFolderName . '/' . $proofFileName;
                }
            }
        }
        
        // Insert new customer
        $stmt = $pdo->prepare("
            INSERT INTO customers (customer_no, name, mobile, address, place, pincode, additional_number, reference, proof_type, customer_photo, proof_file) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([
            $customerNo, 
            $name, 
            $mobile, 
            $address, 
            $place, 
            $pincode, 
            $additionalNumber, 
            $reference, 
            $proofType, 
            $customerPhoto, 
            $proofFile
        ]);
        
        if ($result) {
            // Get the inserted customer to verify
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_no = ?");
            $stmt->execute([$customerNo]);
            $insertedCustomer = $stmt->fetch();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Customer added successfully',
                'customer' => $insertedCustomer
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to insert customer']);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update customer - handle both form-data (with files) and URL-encoded
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'] ?? '';
        $mobile = $_POST['mobile'] ?? '';
        $address = $_POST['address'] ?? '';
        $place = $_POST['place'] ?? '';
        $pincode = $_POST['pincode'] ?? '';
        $additionalNumber = $_POST['additional_number'] ?? '';
        $reference = $_POST['reference'] ?? '';
        $proofType = $_POST['proof_type'] ?? '';
        
        // If no POST data, try parsing input (for URL-encoded)
        if (empty($id)) {
            parse_str(file_get_contents("php://input"), $data);
            $id = $data['id'] ?? '';
            $name = $data['name'] ?? '';
            $mobile = $data['mobile'] ?? '';
            $address = $data['address'] ?? '';
        }
        
        if (empty($id) || empty($name) || empty($mobile)) {
            echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
            exit();
        }
        
        // Get existing customer to preserve folder structure
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        $existingCustomer = $stmt->fetch();
        
        if (!$existingCustomer) {
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
            exit();
        }
        
        // Get customer folder name
        $customerFolderName = '';
        if (!empty($existingCustomer['customer_photo'])) {
            $pathParts = explode('/', $existingCustomer['customer_photo']);
            if (count($pathParts) >= 2) {
                $customerFolderName = $pathParts[1];
            }
        }
        
        // If no folder name, generate from customer name
        if (empty($customerFolderName)) {
            $customerFolderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
            $customerFolderName = str_replace(' ', '_', $customerFolderName);
            $customerFolderName = strtolower($customerFolderName);
        }
        
        // Create customer folder if it doesn't exist
        $uploadDir = $basePath . '/uploads/' . $customerFolderName . '/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $customerPhoto = $existingCustomer['customer_photo'] ?? '';
        $proofFile = $existingCustomer['proof_file'] ?? '';
        
        // Handle customer photo upload
        if (isset($_FILES['customer_photo']) && $_FILES['customer_photo']['error'] === UPLOAD_ERR_OK) {
            $photoFile = $_FILES['customer_photo'];
            $photoExtension = pathinfo($photoFile['name'], PATHINFO_EXTENSION);
            $photoFileName = 'customer_photo.' . $photoExtension;
            $photoPath = $uploadDir . $photoFileName;
            
            // Delete old photo if exists
            if (!empty($customerPhoto) && file_exists($basePath . '/' . $customerPhoto)) {
                unlink($basePath . '/' . $customerPhoto);
            }
            
            // Validate and move new photo
            $allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (in_array($photoFile['type'], $allowedImageTypes)) {
                if (move_uploaded_file($photoFile['tmp_name'], $photoPath)) {
                    $customerPhoto = 'uploads/' . $customerFolderName . '/' . $photoFileName;
                }
            }
        }
        
        // Handle proof file upload
        if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
            $proofFileUpload = $_FILES['proof_file'];
            $proofExtension = pathinfo($proofFileUpload['name'], PATHINFO_EXTENSION);
            $proofFileName = 'proof_' . time() . '.' . $proofExtension;
            $proofPath = $uploadDir . $proofFileName;
            
            // Validate and move new proof file
            $allowedProofTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
            if (in_array($proofFileUpload['type'], $allowedProofTypes)) {
                if (move_uploaded_file($proofFileUpload['tmp_name'], $proofPath)) {
                    // Delete old proof file if exists
                    if (!empty($proofFile) && file_exists($basePath . '/' . $proofFile)) {
                        unlink($basePath . '/' . $proofFile);
                    }
                    $proofFile = 'uploads/' . $customerFolderName . '/' . $proofFileName;
                }
            }
        }
        
        // Update customer
        $stmt = $pdo->prepare("
            UPDATE customers 
            SET name = ?, mobile = ?, address = ?, place = ?, pincode = ?, 
                additional_number = ?, reference = ?, proof_type = ?, 
                customer_photo = ?, proof_file = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $name, $mobile, $address, $place, $pincode, 
            $additionalNumber, $reference, $proofType,
            $customerPhoto, $proofFile, $id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Customer updated successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete customer
        $id = $_GET['id'] ?? '';
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
            exit();
        }
        
        // Get customer details before deletion
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        $customer = $stmt->fetch();
        
        if (!$customer) {
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
            exit();
        }
        
        // For study purposes: Delete all associated loans first
        // This allows deletion of customers even if they have loans
        $stmt = $pdo->prepare("SELECT id, ornament_file, proof_file FROM loans WHERE customer_id = ?");
        $stmt->execute([$id]);
        $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Delete loan files and then loans
        foreach ($loans as $loan) {
            // Delete ornament file if exists
            if (!empty($loan['ornament_file'])) {
                $ornamentPath = $basePath . '/' . $loan['ornament_file'];
                if (file_exists($ornamentPath)) {
                    @unlink($ornamentPath);
                }
            }
            
            // Delete proof file if exists
            if (!empty($loan['proof_file'])) {
                $proofPath = $basePath . '/' . $loan['proof_file'];
                if (file_exists($proofPath)) {
                    @unlink($proofPath);
                }
            }
        }
        
        // Delete all loans for this customer
        $stmt = $pdo->prepare("DELETE FROM loans WHERE customer_id = ?");
        $stmt->execute([$id]);
        
        // Delete customer folder and all files
        if (!empty($customer['customer_photo'])) {
            $pathParts = explode('/', $customer['customer_photo']);
            if (count($pathParts) >= 2) {
                $customerFolderName = $pathParts[1];
                $customerFolderPath = $basePath . '/uploads/' . $customerFolderName;
                
                // Delete entire customer folder
                if (file_exists($customerFolderPath) && is_dir($customerFolderPath)) {
                    // Recursively delete folder contents
                    $files = array_diff(scandir($customerFolderPath), array('.', '..'));
                    foreach ($files as $file) {
                        unlink($customerFolderPath . '/' . $file);
                    }
                    rmdir($customerFolderPath);
                }
            }
        } else {
            // Try to find folder by customer name
            $customerFolderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $customer['name']);
            $customerFolderName = str_replace(' ', '_', $customerFolderName);
            $customerFolderName = strtolower($customerFolderName);
            $customerFolderPath = $basePath . '/uploads/' . $customerFolderName;
            
            if (file_exists($customerFolderPath) && is_dir($customerFolderPath)) {
                $files = array_diff(scandir($customerFolderPath), array('.', '..'));
                foreach ($files as $file) {
                    unlink($customerFolderPath . '/' . $file);
                }
                rmdir($customerFolderPath);
            }
        }
        
        // Delete customer from database
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Customer deleted successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get next customer number if action is get_next_number
        $action = $_GET['action'] ?? '';
        
        if ($action === 'get_next_number') {
            // Get all customer numbers and find the highest numeric value
            $stmt = $pdo->query("SELECT customer_no FROM customers");
            $allCustomers = $stmt->fetchAll();
            
            $maxNumber = 0;
            
            foreach ($allCustomers as $customer) {
                $customerNo = $customer['customer_no'];
                
                // Try to extract number from customer_no (handle both formats: "1", "2" or "C0001", "C0002")
                if (preg_match('/^[A-Za-z]*(\d+)$/', $customerNo, $matches)) {
                    $number = intval($matches[1]);
                    if ($number > $maxNumber) {
                        $maxNumber = $number;
                    }
                } elseif (is_numeric($customerNo)) {
                    // If it's already just a number
                    $number = intval($customerNo);
                    if ($number > $maxNumber) {
                        $maxNumber = $number;
                    }
                }
            }
            
            // Next number is max + 1, or 1 if no customers exist
            $nextNumber = $maxNumber + 1;
            $nextCustomerNo = (string)$nextNumber;
            
            echo json_encode(['success' => true, 'customer_no' => $nextCustomerNo]);
            exit();
        }
        
        // Get single customer by ID if id parameter is provided
        $id = $_GET['id'] ?? '';
        
        if (!empty($id)) {
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$id]);
            $customer = $stmt->fetch();
            
            if ($customer) {
                echo json_encode(['success' => true, 'customer' => $customer]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Customer not found']);
            }
        } else {
            // Get customers for dropdown (default action)
            $stmt = $pdo->query("SELECT id, customer_no, name FROM customers ORDER BY name");
            $customers = $stmt->fetchAll();
            echo json_encode(['success' => true, 'customers' => $customers]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
    
} catch (PDOException $e) {
    if (function_exists('returnJsonError')) {
        returnJsonError('Database error: ' . $e->getMessage(), 500);
    } else {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
} catch (Exception $e) {
    if (function_exists('returnJsonError')) {
        returnJsonError('Error: ' . $e->getMessage(), 500);
    } else {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit();
    }
} catch (Error $e) {
    if (function_exists('returnJsonError')) {
        returnJsonError('Fatal error: ' . $e->getMessage(), 500);
    } else {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $e->getMessage()]);
        exit();
    }
}
?> 