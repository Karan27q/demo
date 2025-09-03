<?php
header('Content-Type: application/json');
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();

    $action = $_GET['action'] ?? 'list';

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    if ($action === 'list') {
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');

        $where = "WHERE l.status = 'closed'";
        $params = [];
        if ($search !== '') {
            $where .= " AND (c.name LIKE ? OR c.mobile LIKE ? OR l.loan_no LIKE ?)";
            $term = "%$search%";
            $params = [$term, $term, $term];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM loans l JOIN customers c ON c.id=l.customer_id $where");
        $stmt->execute($params);
        $total = (int)($stmt->fetch()['total'] ?? 0);
        $totalPages = max(1, (int)ceil($total / $limit));

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

        echo json_encode([
            'success' => true,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $totalPages,
            'rows' => $rows
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>


