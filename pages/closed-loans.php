<?php
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $where = "WHERE l.status = 'closed'";
    $params = [];
    if (!empty($search)) {
        $where .= " AND (c.name LIKE ? OR c.mobile LIKE ? OR l.loan_no LIKE ?)";
        $term = "%$search%";
        $params = [$term, $term, $term];
    }

    // Prefer API for consistency if needed; keep server render but same data shape
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM loans l JOIN customers c ON c.id=l.customer_id $where");
    $stmt->execute($params);
    $total = (int)$stmt->fetch()['total'];
    $totalPages = max(1, ceil($total / $limit));

    // Build query with numeric LIMIT/OFFSET (MariaDB doesn't accept quoted params there)
    $limitNum = (int)$limit;
    $offsetNum = (int)$offset;
    $sql = "SELECT 
            l.id, l.loan_no, l.loan_date, l.principal_amount, l.interest_rate,
            c.name AS customer_name, c.mobile,
            lc.closing_date, lc.total_interest_paid,
            COALESCE(i.total_interest_paid,0) AS interest_received
        FROM loans l
        JOIN customers c ON c.id=l.customer_id
        LEFT JOIN loan_closings lc ON lc.loan_id = l.id
        LEFT JOIN (
            SELECT loan_id, SUM(interest_amount) AS total_interest_paid
            FROM interest
            GROUP BY loan_id
        ) i ON i.loan_id = l.id
        $where
        ORDER BY lc.closing_date DESC
        LIMIT $limitNum OFFSET $offsetNum";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<div class="content-card">
    <div class="page-title">Closed Loans</div>

    <div class="search-section">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Name, mobile number" id="closedLoanSearch" value="<?php echo htmlspecialchars($search); ?>">
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="content-card" style="margin-top:10px;color:#b00020;">Error: <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="pagination">
        <div class="pagination-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
        <div class="pagination-controls">
            <button class="pagination-btn" <?php echo $page <= 1 ? 'disabled' : ''; ?> onclick="changePage(<?php echo $page - 1; ?>)">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="pagination-btn" <?php echo $page >= $totalPages ? 'disabled' : ''; ?> onclick="changePage(<?php echo $page + 1; ?>)">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Loan Date</th>
                <th>Closing Date</th>
                <th>Loan Number</th>
                <th>Customer Name</th>
                <th>Mobile Number</th>
                <th>Principal</th>
                <th>Interest Rate</th>
                <th>Interest Received</th>
                <th>Interest Paid (at close)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($rows)): ?>
                <?php foreach ($rows as $idx => $r): ?>
                    <tr>
                        <td><?php echo $offset + $idx + 1; ?></td>
                        <td><?php echo date('d-m-Y', strtotime($r['loan_date'])); ?></td>
                        <td><?php echo $r['closing_date'] ? date('d-m-Y', strtotime($r['closing_date'])) : '-'; ?></td>
                        <td><?php echo htmlspecialchars($r['loan_no']); ?></td>
                        <td><?php echo htmlspecialchars($r['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($r['mobile']); ?></td>
                        <td>₹<?php echo number_format($r['principal_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($r['interest_rate']); ?>%</td>
                        <td>₹<?php echo number_format($r['interest_received'], 2); ?></td>
                        <td>₹<?php echo number_format($r['total_interest_paid'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" style="text-align:center;">No closed loans found (total: <?php echo isset($total) ? (int)$total : 0; ?>)</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="pagination">
        <div class="pagination-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
        <div class="pagination-controls">
            <button class="pagination-btn" <?php echo $page <= 1 ? 'disabled' : ''; ?> onclick="changePage(<?php echo $page - 1; ?>)">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="pagination-btn" <?php echo $page >= $totalPages ? 'disabled' : ''; ?> onclick="changePage(<?php echo $page + 1; ?>)">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('closedLoanSearch');
    if (!input) return;
    input.addEventListener('input', function(){
        clearTimeout(this._t);
        this._t = setTimeout(() => {
            const v = this.value;
            const url = new URL(window.location);
            if (v) url.searchParams.set('search', v); else url.searchParams.delete('search');
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }, 500);
    });
});
</script>
