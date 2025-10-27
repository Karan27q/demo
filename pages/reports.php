<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get current balance
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END) as total_credit,
            SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END) as total_debit
        FROM transactions
    ");
    $balanceData = $stmt->fetch();
    $currentBalance = ($balanceData['total_credit'] ?? 0) - ($balanceData['total_debit'] ?? 0);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="content-card">
    <div class="page-title">Reports</div>
    
    <div class="reports-grid">
        <!-- Customer/Loan PDF Generator -->
        <div class="report-card">
            <div class="report-header">
                <h3><i class="fas fa-file-pdf"></i> Loan PDF (FPDF)</h3>
            </div>
            <div class="report-filters">
                <div class="filter-row">
                    <div class="form-group">
                        <label>Customer</label>
                        <select id="pdfCustomer"></select>
                    </div>
                    <div class="form-group">
                        <label>Loan</label>
                        <select id="pdfLoan"></select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button class="btn-success" onclick="viewLoanPdf()">View PDF</button>
                </div>
            </div>
        </div>

        <!-- Hidden form to POST to our PDF generator -->
        <form id="fpdfLoanForm" action="api/loan-pdf-generator.php" method="POST" target="_blank" style="display:none">
            <input type="hidden" name="loan_no" id="fpdfLoanNo">
        </form> 

<style>
.reports-grid {
    display: grid;
    gap: 2rem;
    margin-top: 1rem;
}

.report-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}

.report-header h3 {
    margin: 0;
    color: #333;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.current-balance {
    font-size: 1.1rem;
    font-weight: bold;
    color: #28a745;
    margin: 0;
}

.report-filters {
    margin-bottom: 1rem;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-success {
    background: #28a745;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.9rem;
}

.btn-success:hover {
    background: #218838;
}

.btn-small {
    background: #007bff;
    color: white;
    border: none;
    padding: 0.25rem 0.5rem;
    border-radius: 3px;
    cursor: pointer;
    font-size: 0.8rem;
}

.btn-small:hover {
    background: #0056b3;
}

.report-table {
    overflow-x: auto;
}

@media (max-width: 768px) {
    .filter-row {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .report-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style> 