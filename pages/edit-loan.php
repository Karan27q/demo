<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

$loanId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($loanId <= 0) {
    header('Location: ../dashboard.php?page=loans');
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Fetch loan details
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ?");
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch();
    
    if (!$loan) {
        header('Location: ../dashboard.php?page=loans');
        exit();
    }
    
    // Fetch customer details
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$loan['customer_id']]);
    $customer = $stmt->fetch();
    
    // Get all customers for dropdown
    $stmt = $pdo->query("SELECT id, customer_no, name FROM customers ORDER BY name");
    $customers = $stmt->fetchAll();
    
} catch (PDOException $e) {
    header('Location: ../dashboard.php?page=loans');
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
    <title>Edit Loan - Lakshmi Finance</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container" style="max-width: 1200px; margin: 20px auto; padding: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1 style="margin: 0; color: #2d3748;">
                <i class="fas fa-edit"></i> Edit Loan
            </h1>
            <a href="../dashboard.php?page=loans" class="btn-secondary" style="text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Loans
            </a>
        </div>
        
        <div class="card" style="background: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <!-- Loan Calculation Display Box at Top -->
            <div id="loanCalculationBox" style="background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 8px; padding: 15px; margin-bottom: 20px; display: none;">
                <h3 style="margin: 0 0 12px 0; font-size: 16px; color: #495057;">
                    <i class="fas fa-calculator"></i> Interest Calculation
                </h3>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                    <div>
                        <strong style="color: #6c757d; font-size: 13px;">Principal Amount:</strong>
                        <div id="calcPrincipal" style="font-size: 18px; color: #212529; font-weight: 600;">₹0.00</div>
                    </div>
                    <div>
                        <strong style="color: #6c757d; font-size: 13px;">Interest Amount:</strong>
                        <div id="calcInterest" style="font-size: 18px; color: #0d6efd; font-weight: 600;">₹0.00</div>
                    </div>
                    <div style="grid-column: 1 / -1; border-top: 2px solid #dee2e6; padding-top: 12px; margin-top: 8px;">
                        <strong style="color: #6c757d; font-size: 13px;">Total Amount (Principal + Interest):</strong>
                        <div id="calcTotal" style="font-size: 22px; color: #198754; font-weight: 700;">₹0.00</div>
                    </div>
                </div>
            </div>
            
            <form id="editLoanForm" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" id="loanId" name="id" value="<?php echo $loan['id']; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="customerSelect">Customer *</label>
                        <select id="customerSelect" name="customer_id" required autocomplete="off">
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $cust): ?>
                                <option value="<?php echo $cust['id']; ?>" <?php echo $cust['id'] == $loan['customer_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cust['customer_no'] . ' - ' . $cust['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="loanNo">Loan No *</label>
                        <input type="text" id="loanNo" name="loan_no" required placeholder="Loan No" value="<?php echo htmlspecialchars($loan['loan_no']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="loanDate">Date *</label>
                        <input type="date" id="loanDate" name="loan_date" required autocomplete="off" value="<?php echo $loan['loan_date']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="principalAmount">Principal Amount *</label>
                        <input type="number" id="principalAmount" name="principal_amount" step="0.01" required placeholder="Principal Amount" value="<?php echo $loan['principal_amount']; ?>" autocomplete="off">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="interestRate">Interest Rate (%) *</label>
                        <input type="number" id="interestRate" name="interest_rate" step="0.01" required placeholder="Interest Rate" value="<?php echo $loan['interest_rate']; ?>" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="loanDays">Loan Days *</label>
                        <input type="number" id="loanDays" name="loan_days" required placeholder="Enter days" min="1" value="<?php echo $loan['loan_days'] ?? ''; ?>" autocomplete="off">
                    </div>
                    <div class="form-group" style="display: none;">
                        <!-- Spacer -->
                    </div>
                    <div class="form-group" style="display: none;">
                        <!-- Spacer -->
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="totalWeight">Total Weight (g)</label>
                        <input type="number" id="totalWeight" name="total_weight" step="0.01" placeholder="0.00" value="<?php echo $loan['total_weight'] ?? ''; ?>" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="netWeight">Net Weight (g)</label>
                        <input type="number" id="netWeight" name="net_weight" step="0.01" placeholder="0.00" value="<?php echo $loan['net_weight'] ?? ''; ?>" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="ornamentFile">Upload Ornament</label>
                        <input type="file" id="ornamentFile" name="ornament_file" accept="image/*,.pdf">
                        <?php if ($loan['ornament_file']): ?>
                            <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                                Current: <a href="../<?php echo htmlspecialchars($loan['ornament_file']); ?>" target="_blank"><?php echo basename($loan['ornament_file']); ?></a>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group" style="display: none;">
                        <!-- Spacer -->
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="pledgeItems">Pledge Items</label>
                    <textarea id="pledgeItems" name="pledge_items" rows="3" placeholder="e.g., RING - 1, BANGLE - 2" autocomplete="off"><?php echo htmlspecialchars($loan['pledge_items'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="loanStatus">Status</label>
                    <select id="loanStatus" name="status" autocomplete="off">
                        <option value="active" <?php echo $loan['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="closed" <?php echo $loan['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
                
                <div class="form-actions" style="margin-top: 30px; display: flex; gap: 10px; justify-content: flex-end;">
                    <a href="../dashboard.php?page=loans" class="btn-secondary" style="text-decoration: none;">Cancel</a>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Update Loan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // API URL helper
        function apiUrl(path) {
            return '../' + path;
        }
        
        // Real-time interest calculation
        // Formula: Principal × (Interest Rate / 100) × (Days / 30)
        function calculateInterest() {
            const principal = parseFloat(document.getElementById('principalAmount').value) || 0;
            const interestRate = parseFloat(document.getElementById('interestRate').value) || 0;
            const loanDays = parseFloat(document.getElementById('loanDays').value) || 0;
            const calcBox = document.getElementById('loanCalculationBox');
            
            if (principal > 0 && interestRate > 0 && loanDays > 0) {
                // Calculate interest: Principal × (Interest Rate / 100) × (Days / 30)
                const interest = principal * (interestRate / 100) * (loanDays / 30);
                const total = principal + interest;
                
                // Update calculation display
                document.getElementById('calcPrincipal').textContent = '₹' + principal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('calcInterest').textContent = '₹' + interest.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('calcTotal').textContent = '₹' + total.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                
                if (calcBox) {
                    calcBox.style.display = 'block';
                }
            } else {
                if (calcBox) {
                    calcBox.style.display = 'none';
                }
            }
        }
        
        // Add event listeners for interest calculation
        document.addEventListener('DOMContentLoaded', function() {
            const principalField = document.getElementById('principalAmount');
            const interestRateField = document.getElementById('interestRate');
            const loanDaysField = document.getElementById('loanDays');
            
            if (principalField) {
                principalField.addEventListener('input', calculateInterest);
                principalField.addEventListener('change', calculateInterest);
            }
            
            if (interestRateField) {
                interestRateField.addEventListener('input', calculateInterest);
                interestRateField.addEventListener('change', calculateInterest);
            }
            
            if (loanDaysField) {
                loanDaysField.addEventListener('input', calculateInterest);
                loanDaysField.addEventListener('change', calculateInterest);
            }
            
            // Initial calculation - ensure it runs after DOM is ready
            setTimeout(function() {
                calculateInterest();
            }, 50);
            
            // Form submission
            const editLoanForm = document.getElementById('editLoanForm');
            if (editLoanForm) {
                editLoanForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    const loanId = formData.get('id');
                    
                    // Calculate interest amount using the same formula
                    const principal = parseFloat(formData.get('principal_amount')) || 0;
                    const interestRate = parseFloat(formData.get('interest_rate')) || 0;
                    const loanDays = parseFloat(formData.get('loan_days')) || 0;
                    
                    if (principal > 0 && interestRate > 0 && loanDays > 0) {
                        const interestAmount = principal * (interestRate / 100) * (loanDays / 30);
                        formData.append('interest_amount', interestAmount.toFixed(2));
                    }
                    
                    // Convert FormData to URL-encoded format for PUT request (except files)
                    const data = new URLSearchParams();
                    for (const [key, value] of formData.entries()) {
                        if (key !== 'ornament_file' && value instanceof File === false) {
                            data.append(key, value);
                        }
                    }
                    
                    fetch(apiUrl('api/loans.php'), {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: data.toString()
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Loan updated successfully!');
                            window.location.href = '../dashboard.php?page=loans';
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while updating the loan.');
                    });
                });
            }
        });
    </script>
</body>
</html>
