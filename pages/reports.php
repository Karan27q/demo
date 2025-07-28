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
        <!-- Day-wise Summary Report -->
        <div class="report-card">
            <div class="report-header">
                <h3><i class="fas fa-calendar-alt"></i> Day-wise Summary</h3>
                <p class="current-balance">Current Balance: ₹<?php echo number_format($currentBalance, 2); ?></p>
            </div>
            
            <div class="report-filters">
                <div class="filter-row">
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" id="fromDate" placeholder="mm/dd/yyyy">
                    </div>
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" id="toDate" placeholder="mm/dd/yyyy">
                    </div>
                </div>
                <div class="filter-actions">
                    <button class="btn-primary" onclick="generateDayWiseSummary()">Download PDF</button>
                    <button class="btn-secondary" onclick="undoFilter()">Undo Filter</button>
                </div>
            </div>
            
            <div class="report-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day Opening Balance</th>
                            <th>Total Credit (Varavu)</th>
                            <th>Total Debit (Patru)</th>
                            <th>Day Closing Balance</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="dayWiseSummaryData">
                        <!-- Data will be loaded dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Advance Report -->
        <div class="report-card">
            <div class="report-header">
                <h3><i class="fas fa-chart-line"></i> Advance Report</h3>
            </div>
            
            <div class="report-filters">
                <div class="filter-row">
                    <div class="form-group">
                        <label>Report Type</label>
                        <select id="reportType">
                            <option value="all">All</option>
                            <option value="active">Active Loans</option>
                            <option value="closed">Closed Loans</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" id="advanceFromDate">
                    </div>
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" id="advanceToDate">
                    </div>
                </div>
                <div class="filter-actions">
                    <button class="btn-success" onclick="generateAdvanceReport()">Generate Report</button>
                    <button class="btn-success" onclick="downloadAdvancePDF()">Download PDF</button>
                    <button class="btn-success" onclick="exportAdvanceExcel()">Export to Excel</button>
                </div>
            </div>
            
            <div class="report-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Serial No</th>
                            <th>Date</th>
                            <th>Loan No</th>
                            <th>Name</th>
                            <th>Total Weight (g)</th>
                            <th>Net Weight (g)</th>
                            <th>Interest Rate</th>
                            <th>Amount (₹)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="advanceReportData">
                        <!-- Data will be loaded dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pledge Report -->
        <div class="report-card">
            <div class="report-header">
                <h3><i class="fas fa-shield-alt"></i> Bank Pledge Report</h3>
            </div>
            
            <div class="report-filters">
                <div class="filter-row">
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" id="pledgeFromDate">
                    </div>
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" id="pledgeToDate">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="pledgeStatus">
                            <option value="all">All</option>
                            <option value="pledged">Pledged</option>
                            <option value="redeemed">Redeemed</option>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button class="btn-success" onclick="applyPledgeFilters()">Apply Filters</button>
                    <button class="btn-success" onclick="exportPledgeCSV()">Export to CSV</button>
                    <button class="btn-success" onclick="exportPledgePDF()">Export to PDF</button>
                </div>
            </div>
            
            <div class="report-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>S.NO</th>
                            <th>DATE</th>
                            <th>LOAN NO</th>
                            <th>NAME</th>
                            <th>BANK PLEDGE DATE</th>
                            <th>BANK ASSESSOR NAME</th>
                            <th>BANK NAME</th>
                            <th>INTEREST</th>
                            <th>LOAN AMOUNT</th>
                            <th>DUE DATE</th>
                            <th>ADDITIONAL CHARGES</th>
                            <th>LOAN NO</th>
                        </tr>
                    </thead>
                    <tbody id="pledgeReportData">
                        <!-- Data will be loaded dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize date inputs with current month
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    
    // Set default dates for all date inputs
    const dateInputs = ['fromDate', 'toDate', 'advanceFromDate', 'advanceToDate', 'pledgeFromDate', 'pledgeToDate'];
    dateInputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            if (id.includes('From')) {
                input.value = firstDay.toISOString().split('T')[0];
            } else {
                input.value = today.toISOString().split('T')[0];
            }
        }
    });
    
    // Load initial data
    loadDayWiseSummary();
    loadAdvanceReport();
    loadPledgeReport();
});

function loadDayWiseSummary() {
    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;
    
    fetch(`api/reports.php?type=daywise&from=${fromDate}&to=${toDate}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('dayWiseSummaryData');
            tbody.innerHTML = '';
            
            if (data.success && data.data) {
                data.data.forEach((row, index) => {
                    tbody.innerHTML += `
                        <tr>
                            <td>${row.date}</td>
                            <td>₹${row.opening_balance}</td>
                            <td>₹${row.total_credit}</td>
                            <td>₹${row.total_debit}</td>
                            <td>₹${row.closing_balance}</td>
                            <td><button class="btn-small" onclick="viewDayDetails('${row.date}')">View Details</button></td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No data available</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading day-wise summary:', error);
        });
}

function loadAdvanceReport() {
    const reportType = document.getElementById('reportType').value;
    const fromDate = document.getElementById('advanceFromDate').value;
    const toDate = document.getElementById('advanceToDate').value;
    
    fetch(`api/reports.php?type=advance&status=${reportType}&from=${fromDate}&to=${toDate}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('advanceReportData');
            tbody.innerHTML = '';
            
            if (data.success && data.data) {
                data.data.forEach((row, index) => {
                    const status = row.status === 'active' ? 'நகை மீட்கபடவில்லை' : 'நகை மீட்கபட்டது';
                    tbody.innerHTML += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${row.loan_date}</td>
                            <td>${row.loan_no}</td>
                            <td>${row.customer_name}</td>
                            <td>${row.total_weight}</td>
                            <td>${row.net_weight}</td>
                            <td>${row.interest_rate}%</td>
                            <td>₹${row.principal_amount}</td>
                            <td>${status}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align: center;">No data available</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading advance report:', error);
        });
}

function loadPledgeReport() {
    const fromDate = document.getElementById('pledgeFromDate').value;
    const toDate = document.getElementById('pledgeToDate').value;
    const status = document.getElementById('pledgeStatus').value;
    
    fetch(`api/reports.php?type=pledge&from=${fromDate}&to=${toDate}&status=${status}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('pledgeReportData');
            tbody.innerHTML = '';
            
            if (data.success && data.data) {
                data.data.forEach((row, index) => {
                    tbody.innerHTML += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${row.date}</td>
                            <td>${row.loan_no}</td>
                            <td>${row.customer_name}</td>
                            <td>${row.bank_pledge_date || '-'}</td>
                            <td>${row.bank_assessor_name || '-'}</td>
                            <td>${row.bank_name || '-'}</td>
                            <td>${row.interest || '-'}</td>
                            <td>${row.loan_amount || '-'}</td>
                            <td>${row.due_date || '-'}</td>
                            <td>${row.additional_charges || '-'}</td>
                            <td>${row.bank_loan_no || '-'}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="12" style="text-align: center;">No data available</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading pledge report:', error);
        });
}

function generateDayWiseSummary() {
    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;
    
    if (!fromDate || !toDate) {
        alert('Please select both from and to dates');
        return;
    }
    
    window.open(`api/reports.php?type=daywise&from=${fromDate}&to=${toDate}&format=pdf`, '_blank');
}

function undoFilter() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    
    document.getElementById('fromDate').value = firstDay.toISOString().split('T')[0];
    document.getElementById('toDate').value = today.toISOString().split('T')[0];
    
    loadDayWiseSummary();
}

function generateAdvanceReport() {
    loadAdvanceReport();
}

function downloadAdvancePDF() {
    const reportType = document.getElementById('reportType').value;
    const fromDate = document.getElementById('advanceFromDate').value;
    const toDate = document.getElementById('advanceToDate').value;
    
    window.open(`api/reports.php?type=advance&status=${reportType}&from=${fromDate}&to=${toDate}&format=pdf`, '_blank');
}

function exportAdvanceExcel() {
    const reportType = document.getElementById('reportType').value;
    const fromDate = document.getElementById('advanceFromDate').value;
    const toDate = document.getElementById('advanceToDate').value;
    
    window.open(`api/reports.php?type=advance&status=${reportType}&from=${fromDate}&to=${toDate}&format=excel`, '_blank');
}

function applyPledgeFilters() {
    loadPledgeReport();
}

function exportPledgeCSV() {
    const fromDate = document.getElementById('pledgeFromDate').value;
    const toDate = document.getElementById('pledgeToDate').value;
    const status = document.getElementById('pledgeStatus').value;
    
    window.open(`api/reports.php?type=pledge&from=${fromDate}&to=${toDate}&status=${status}&format=csv`, '_blank');
}

function exportPledgePDF() {
    const fromDate = document.getElementById('pledgeFromDate').value;
    const toDate = document.getElementById('pledgeToDate').value;
    const status = document.getElementById('pledgeStatus').value;
    
    window.open(`api/reports.php?type=pledge&from=${fromDate}&to=${toDate}&status=${status}&format=pdf`, '_blank');
}

function viewDayDetails(date) {
    // Implement day details view
    console.log('View details for date:', date);
}

// Event listeners for filters
document.getElementById('reportType').addEventListener('change', loadAdvanceReport);
document.getElementById('advanceFromDate').addEventListener('change', loadAdvanceReport);
document.getElementById('advanceToDate').addEventListener('change', loadAdvanceReport);
document.getElementById('pledgeStatus').addEventListener('change', loadPledgeReport);
document.getElementById('pledgeFromDate').addEventListener('change', loadPledgeReport);
document.getElementById('pledgeToDate').addEventListener('change', loadPledgeReport);
</script>

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