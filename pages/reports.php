<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get summary statistics
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END) as total_credit,
            SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END) as total_debit
        FROM transactions
    ");
    $balanceData = $stmt->fetch();
    $currentBalance = ($balanceData['total_credit'] ?? 0) - ($balanceData['total_debit'] ?? 0);
    $totalCredit = $balanceData['total_credit'] ?? 0;
    $totalDebit = $balanceData['total_debit'] ?? 0;
    
    // Get loan statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM loans WHERE status = 'active'");
    $activeLoans = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM loans WHERE status = 'closed'");
    $closedLoans = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="dashboard-page">
    <!-- Financial Summary Cards -->
    <div class="dashboard-cards">
        <div class="dashboard-card">
            <div class="card-icon <?php echo $currentBalance >= 0 ? 'loan' : 'outstanding'; ?>">
                <i class="fas fa-balance-scale"></i>
            </div>
            <div class="card-content">
                <div class="card-number" style="color: <?php echo $currentBalance >= 0 ? '#38a169' : '#e53e3e'; ?>;">
                    ₹<?php echo number_format($currentBalance, 2); ?>
                </div>
                <div class="card-label">Current Balance</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon interest">
                <i class="fas fa-arrow-down"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($totalCredit, 2); ?></div>
                <div class="card-label">Total Credits</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon outstanding">
                <i class="fas fa-arrow-up"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($totalDebit, 2); ?></div>
                <div class="card-label">Total Debits</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon loan">
                <i class="fas fa-coins"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($activeLoans); ?></div>
                <div class="card-label">Active Loans</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon recovery">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($closedLoans); ?></div>
                <div class="card-label">Closed Loans</div>
            </div>
        </div>
    </div>
    
    <!-- Balance Sheet Section -->
    <div class="content-card">
        <div class="section-header">
            <h2 class="page-title">
                <i class="fas fa-balance-scale"></i> Balance Sheet
            </h2>
            <div class="section-actions">
                <button class="btn-primary" onclick="generateBalanceSheet()">
                    <i class="fas fa-download"></i> Download Balance Sheet
                </button>
            </div>
        </div>
        
        <div class="balance-sheet-container">
            <div class="balance-sheet-section">
                <h3>Assets</h3>
                <table class="balance-table">
                    <tr>
                        <td>Active Loans (Principal)</td>
                        <td class="amount">₹<?php 
                            $stmt = $pdo->query("SELECT COALESCE(SUM(principal_amount), 0) as total FROM loans WHERE status = 'active'");
                            echo number_format($stmt->fetch()['total'], 2); 
                        ?></td>
                    </tr>
                    <tr>
                        <td>Outstanding Interest</td>
                        <td class="amount">₹<?php 
                            // Calculate outstanding interest
                            $stmt = $pdo->query("
                                SELECT 
                                    l.id, l.principal_amount, l.interest_rate, l.loan_date,
                                    COALESCE(SUM(i.interest_amount), 0) as interest_paid
                                FROM loans l
                                LEFT JOIN interest i ON l.id = i.loan_id
                                WHERE l.status = 'active'
                                GROUP BY l.id
                            ");
                            $loans = $stmt->fetchAll();
                            require_once $basePath . '/config/interest_calculator.php';
                            $totalOutstanding = 0;
                            foreach ($loans as $loan) {
                                $expectedInterest = calculateExpectedInterestByCalendarMonths(
                                    $loan['principal_amount'],
                                    $loan['interest_rate'],
                                    $loan['loan_date']
                                );
                                $totalOutstanding += max(0, $expectedInterest - $loan['interest_paid']);
                            }
                            echo number_format($totalOutstanding, 2);
                        ?></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>Total Assets</strong></td>
                        <td class="amount"><strong>₹<?php 
                            $stmt = $pdo->query("SELECT COALESCE(SUM(principal_amount), 0) as total FROM loans WHERE status = 'active'");
                            $principal = $stmt->fetch()['total'];
                            echo number_format($principal + $totalOutstanding, 2);
                        ?></strong></td>
                    </tr>
                </table>
            </div>
            
            <div class="balance-sheet-section">
                <h3>Liabilities & Equity</h3>
                <table class="balance-table">
                    <tr>
                        <td>Cash Balance</td>
                        <td class="amount">₹<?php echo number_format($currentBalance, 2); ?></td>
                    </tr>
                    <tr>
                        <td>Interest Collected</td>
                        <td class="amount">₹<?php 
                            $stmt = $pdo->query("SELECT COALESCE(SUM(interest_amount), 0) as total FROM interest");
                            echo number_format($stmt->fetch()['total'], 2);
                        ?></td>
                    </tr>
                    <tr>
                        <td>Total Credits</td>
                        <td class="amount">₹<?php echo number_format($totalCredit, 2); ?></td>
                    </tr>
                    <tr>
                        <td>Total Expenses</td>
                        <td class="amount">₹<?php echo number_format($totalDebit, 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>Net Worth</strong></td>
                        <td class="amount"><strong>₹<?php 
                            $netWorth = $currentBalance + ($principal + $totalOutstanding);
                            echo number_format($netWorth, 2);
                        ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Reports Section -->
    <div class="content-card" style="margin-top: 30px;">
        <div class="section-header">
            <h2 class="page-title">
                <i class="fas fa-chart-bar"></i> Other Reports
            </h2>
        </div>
        
        <div class="reports-grid">
            <!-- Loan PDF Generator -->
            <div class="report-card">
                <div class="report-header">
                    <h3><i class="fas fa-file-pdf"></i> Loan Statement PDF</h3>
                </div>
                <div class="report-filters">
                    <div class="filter-row">
                        <div class="form-group">
                            <label>Customer</label>
                            <select id="pdfCustomer" class="form-control">
                                <option value="">Select Customer</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Loan</label>
                            <select id="pdfLoan" class="form-control">
                                <option value="">Select Loan</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button class="btn-success" onclick="viewLoanPdf()">
                            <i class="fas fa-file-pdf"></i> Generate PDF
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Day Book Report -->
            <div class="report-card">
                <div class="report-header">
                    <h3><i class="fas fa-book"></i> Day Book Report</h3>
                </div>
                <div class="report-filters">
                    <div class="filter-row">
                        <div class="form-group">
                            <label>From Date</label>
                            <input type="date" id="dayBookFromDate" class="form-control" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div class="form-group">
                            <label>To Date</label>
                            <input type="date" id="dayBookToDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button class="btn-primary" onclick="generateDayBook()">
                            <i class="fas fa-download"></i> Generate Report
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Loan Report -->
            <div class="report-card">
                <div class="report-header">
                    <h3><i class="fas fa-coins"></i> Loan Report</h3>
                </div>
                <div class="report-filters">
                    <div class="filter-row">
                        <div class="form-group">
                            <label>Status</label>
                            <select id="loanReportStatus" class="form-control">
                                <option value="all">All Loans</option>
                                <option value="active">Active Loans</option>
                                <option value="closed">Closed Loans</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>From Date</label>
                            <input type="date" id="loanReportFromDate" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>To Date</label>
                            <input type="date" id="loanReportToDate" class="form-control">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button class="btn-primary" onclick="generateLoanReport()">
                            <i class="fas fa-download"></i> Generate Report
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Interest Report -->
            <div class="report-card">
                <div class="report-header">
                    <h3><i class="fas fa-money-bill-wave"></i> Interest Report</h3>
                </div>
                <div class="report-filters">
                    <div class="filter-row">
                        <div class="form-group">
                            <label>From Date</label>
                            <input type="date" id="interestReportFromDate" class="form-control" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div class="form-group">
                            <label>To Date</label>
                            <input type="date" id="interestReportToDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button class="btn-primary" onclick="generateInterestReport()">
                            <i class="fas fa-download"></i> Generate Report
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Transaction Report -->
            <div class="report-card">
                <div class="report-header">
                    <h3><i class="fas fa-exchange-alt"></i> Transaction Report</h3>
                </div>
                <div class="report-filters">
                    <div class="filter-row">
                        <div class="form-group">
                            <label>Type</label>
                            <select id="transactionReportType" class="form-control">
                                <option value="all">All Transactions</option>
                                <option value="credit">Credit Only</option>
                                <option value="debit">Debit Only</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>From Date</label>
                            <input type="date" id="transactionReportFromDate" class="form-control" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div class="form-group">
                            <label>To Date</label>
                            <input type="date" id="transactionReportToDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button class="btn-primary" onclick="generateTransactionReport()">
                            <i class="fas fa-download"></i> Generate Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form to POST to PDF generator -->
<form id="fpdfLoanForm" action="api/loan-pdf-generator.php" method="POST" target="_blank" style="display:none">
    <input type="hidden" name="loan_no" id="fpdfLoanNo">
</form>

<script>
// Initialize reports page
// Note: initReportsPage is defined in dashboard.js but called before this script is evaluated.
// So we execute our logic directly here, which runs via eval() in dashboard.js
loadPdfCustomers();

// Attach event listener
const pdfCustomerSelect = document.getElementById('pdfCustomer');
if (pdfCustomerSelect && !pdfCustomerSelect.dataset.listenerAttached) {
    pdfCustomerSelect.dataset.listenerAttached = 'true';
    pdfCustomerSelect.addEventListener('change', function() {
        const customerId = this.value;
        const loanSelect = document.getElementById('pdfLoan');
        
        if (!customerId || !loanSelect) return;
        
        loanSelect.innerHTML = '<option value="">Select Loan</option>';
        
        if (customerId) {
            fetch(apiUrl(`api/loans.php?action=by_customer&customer_id=${customerId}`))
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.loans)) {
                        // Deduplicate by loan_no to ensure each loan number appears only once
                        const seenLoanNos = new Set();
                        const uniqueLoans = [];
                        
                        data.loans.forEach(loan => {
                            const loanNo = String(loan.loan_no || '').trim();
                            if (loanNo && !seenLoanNos.has(loanNo)) {
                                seenLoanNos.add(loanNo);
                                uniqueLoans.push(loan);
                            }
                        });
                        
                        // Add unique loans to dropdown
                        uniqueLoans.forEach(loan => {
                            const option = document.createElement('option');
                            option.value = loan.id;
                            option.setAttribute('data-loan-no', loan.loan_no || '');
                            const date = loan.loan_date ? new Date(loan.loan_date).toLocaleDateString('en-GB') : '';
                            option.textContent = `${loan.loan_no} (${date}) - ${loan.status}`;
                            loanSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading loans:', error);
                });
        }
    });
}

function loadPdfCustomers() {
    fetch(apiUrl('api/customers.php'))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.customers) {
                const select = document.getElementById('pdfCustomer');
                if (select) {
                    select.innerHTML = '<option value="">Select Customer</option>';
                    data.customers.forEach(customer => {
                        const option = document.createElement('option');
                        option.value = customer.id;
                        option.textContent = `${customer.customer_no} - ${customer.name}`;
                        select.appendChild(option);
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error loading customers:', error);
        });
}

function viewLoanPdf() {
    const loanSelect = document.getElementById('pdfLoan');
    const loanNo = loanSelect.options[loanSelect.selectedIndex]?.getAttribute('data-loan-no');
    
    if (!loanNo) {
        alert('Please select a loan');
        return;
    }
    
    document.getElementById('fpdfLoanNo').value = loanNo;
    document.getElementById('fpdfLoanForm').submit();
}

function generateDayBook() {
    const fromDate = document.getElementById('dayBookFromDate').value;
    const toDate = document.getElementById('dayBookToDate').value;
    
    if (!fromDate || !toDate) {
        alert('Please select both from and to dates');
        return;
    }
    
    window.open(apiUrl(`api/reports.php?type=daybook&from_date=${fromDate}&to_date=${toDate}`), '_blank');
}

function generateLoanReport() {
    const status = document.getElementById('loanReportStatus').value;
    const fromDate = document.getElementById('loanReportFromDate').value;
    const toDate = document.getElementById('loanReportToDate').value;
    
    let url = apiUrl(`api/reports.php?type=loan&status=${status}`);
    if (fromDate) url += `&from_date=${fromDate}`;
    if (toDate) url += `&to_date=${toDate}`;
    
    window.open(url, '_blank');
}

function generateInterestReport() {
    const fromDate = document.getElementById('interestReportFromDate').value;
    const toDate = document.getElementById('interestReportToDate').value;
    
    if (!fromDate || !toDate) {
        alert('Please select both from and to dates');
        return;
    }
    
    window.open(apiUrl(`api/reports.php?type=interest&from_date=${fromDate}&to_date=${toDate}`), '_blank');
}

function generateTransactionReport() {
    const type = document.getElementById('transactionReportType').value;
    const fromDate = document.getElementById('transactionReportFromDate').value;
    const toDate = document.getElementById('transactionReportToDate').value;
    
    if (!fromDate || !toDate) {
        alert('Please select both from and to dates');
        return;
    }
    
    window.open(apiUrl(`api/reports.php?type=transaction&transaction_type=${type}&from_date=${fromDate}&to_date=${toDate}`), '_blank');
}

function generateBalanceSheet() {
    window.open(apiUrl('api/reports.php?type=balance_sheet'), '_blank');
}
</script>

<style>
.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.report-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}

.report-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e2e8f0;
}

.report-header h3 {
    margin: 0;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 18px;
}

.report-filters {
    margin-top: 15px;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.filter-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
}

.balance-sheet-container {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 30px;
    margin-top: 20px;
}

.balance-sheet-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.balance-sheet-section h3 {
    margin: 0 0 20px 0;
    color: #2d3748;
    font-size: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #cbd5e0;
}

.balance-table {
    width: 100%;
    border-collapse: collapse;
}

.balance-table td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
}

.balance-table td:first-child {
    color: #4a5568;
    font-weight: 500;
}

.balance-table .amount {
    text-align: right;
    color: #2d3748;
    font-weight: 600;
}

.balance-table .total-row {
    background: #edf2f7;
    border-top: 2px solid #cbd5e0;
}

.balance-table .total-row td {
    padding: 15px 12px;
    font-size: 16px;
}

@media (max-width: 768px) {
    .reports-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-row {
        grid-template-columns: 1fr;
    }
    
    .balance-sheet-container {
        grid-template-columns: 1fr;
    }
}
</style>
