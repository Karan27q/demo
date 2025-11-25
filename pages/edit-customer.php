<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($customerId <= 0) {
    header('Location: ../dashboard.php?page=customers');
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Fetch customer details
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        header('Location: ../dashboard.php?page=customers');
        exit();
    }
    
    // Get customer folder name
    $customerFolderName = '';
    $photoPath = '';
    if (!empty($customer['customer_photo'])) {
        $pathParts = explode('/', $customer['customer_photo']);
        if (count($pathParts) >= 2) {
            $customerFolderName = $pathParts[1];
            $photoPath = $customer['customer_photo'];
        }
    } else {
        $customerFolderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $customer['name']);
        $customerFolderName = str_replace(' ', '_', $customerFolderName);
        $customerFolderName = strtolower($customerFolderName);
    }
    
} catch (PDOException $e) {
    header('Location: ../dashboard.php?page=customers');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#2196F3">
    <title>Edit Customer - <?php echo htmlspecialchars($customer['name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-page">
        <div class="content-card">
            <div class="section-header">
                <h2 class="page-title">
                    <i class="fas fa-user-edit"></i> Edit Customer
                </h2>
                <button class="btn-secondary" onclick="window.location.href='../dashboard.php?page=customers'">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
            </div>
            
            <form id="editCustomerForm" onsubmit="updateCustomer(event)" enctype="multipart/form-data" style="margin-top: 20px;">
                <input type="hidden" id="customerId" name="id" value="<?php echo $customerId; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="customerNo">Customer No *</label>
                        <input type="text" id="customerNo" name="customer_no" value="<?php echo htmlspecialchars($customer['customer_no']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="customerName">Customer Name *</label>
                        <input type="text" id="customerName" name="name" value="<?php echo htmlspecialchars($customer['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="customerMobile">Mobile Number *</label>
                        <input type="text" id="customerMobile" name="mobile" value="<?php echo htmlspecialchars($customer['mobile']); ?>" required maxlength="15">
                    </div>
                    <div class="form-group">
                        <label for="additionalNumber">Additional Number</label>
                        <input type="text" id="additionalNumber" name="additional_number" value="<?php echo htmlspecialchars($customer['additional_number'] ?? ''); ?>" maxlength="15">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="place">Place</label>
                        <input type="text" id="place" name="place" value="<?php echo htmlspecialchars($customer['place'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="pincode">Pincode</label>
                        <input type="text" id="pincode" name="pincode" value="<?php echo htmlspecialchars($customer['pincode'] ?? ''); ?>" maxlength="10">
                    </div>
                    <div class="form-group">
                        <label for="reference">Reference</label>
                        <input type="text" id="reference" name="reference" value="<?php echo htmlspecialchars($customer['reference'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="proofType">Proof Type</label>
                        <select id="proofType" name="proof_type">
                            <option value="">-- Select Proof Type --</option>
                            <option value="Aadhar" <?php echo ($customer['proof_type'] ?? '') === 'Aadhar' ? 'selected' : ''; ?>>Aadhar</option>
                            <option value="PAN" <?php echo ($customer['proof_type'] ?? '') === 'PAN' ? 'selected' : ''; ?>>PAN</option>
                            <option value="Driving License" <?php echo ($customer['proof_type'] ?? '') === 'Driving License' ? 'selected' : ''; ?>>Driving License</option>
                            <option value="Voter ID" <?php echo ($customer['proof_type'] ?? '') === 'Voter ID' ? 'selected' : ''; ?>>Voter ID</option>
                            <option value="Passport" <?php echo ($customer['proof_type'] ?? '') === 'Passport' ? 'selected' : ''; ?>>Passport</option>
                            <option value="Other" <?php echo ($customer['proof_type'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="customerPhoto">Customer Photo</label>
                        <?php if (!empty($photoPath) && file_exists($basePath . '/' . $photoPath)): ?>
                            <div style="margin-bottom: 10px;">
                                <img src="../<?php echo htmlspecialchars($photoPath); ?>" 
                                     alt="Current Photo" 
                                     style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid #ddd;">
                            </div>
                        <?php endif; ?>
                        <input type="file" id="customerPhoto" name="customer_photo" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label for="proofFile">Proof File</label>
                        <?php if (!empty($customer['proof_file']) && file_exists($basePath . '/' . $customer['proof_file'])): ?>
                            <div style="margin-bottom: 10px;">
                                <a href="../<?php echo htmlspecialchars($customer['proof_file']); ?>" target="_blank" style="color: #4299e1;">
                                    <i class="fas fa-file"></i> View Current Proof
                                </a>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="proofFile" name="proof_file" accept="image/*,.pdf">
                    </div>
                </div>
                
                <div class="form-actions" style="margin-top: 20px;">
                    <button type="button" onclick="window.location.href='../dashboard.php?page=customers'" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Update Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    function apiUrl(path) {
        const p = window.location && window.location.pathname || '';
        const underPages = p.indexOf('/pages/') !== -1;
        return (underPages ? '../' : '') + path;
    }
    
    function updateCustomer(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        
        fetch(apiUrl('api/customers.php'), {
            method: 'PUT',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Customer updated successfully!');
                window.location.href = '../dashboard.php?page=customers';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the customer.');
        });
    }
    </script>
</body>
</html>

