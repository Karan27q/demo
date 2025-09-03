<?php
header('Content-Type: application/json');
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Accept either loan_id or loan_number
        $loanId = $_POST['loan_id'] ?? null;
        $loanNumber = $_POST['loan_number'] ?? null;
        $closingDate = $_POST['closing_date'] ?? '';
        $closingAmount = isset($_POST['closing_amount']) && $_POST['closing_amount'] !== '' ? (float)$_POST['closing_amount'] : 0.0;
        $manualClose = isset($_POST['manual_close']) && $_POST['manual_close'] == '1';

        if (empty($closingDate) || ($loanId === null && empty($loanNumber))) {
            echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
            exit;
        }

        // Resolve loan by id or number
        if (!$loanId && $loanNumber) {
            $stmt = $pdo->prepare("SELECT id, status FROM loans WHERE loan_no = ?");
            $stmt->execute([$loanNumber]);
            $loanRow = $stmt->fetch();
            if (!$loanRow) {
                echo json_encode(['success' => false, 'message' => 'Loan not found']);
                exit;
            }
            $loanId = $loanRow['id'];
            $loanStatus = $loanRow['status'];
        } else {
            $stmt = $pdo->prepare("SELECT status FROM loans WHERE id = ?");
            $stmt->execute([$loanId]);
            $loanRow = $stmt->fetch();
            if (!$loanRow) {
                echo json_encode(['success' => false, 'message' => 'Loan not found']);
                exit;
            }
            $loanStatus = $loanRow['status'];
        }

        if ($loanStatus !== 'active') {
            echo json_encode(['success' => false, 'message' => 'Loan is already closed']);
            exit;
        }

        // Auto-close rule: require amount == 0 unless manual_close is checked
        $isZero = abs((float)$closingAmount) < 0.000001;
        if (!$isZero && !$manualClose) {
            echo json_encode(['success' => false, 'message' => 'Auto close requires amount to be zero. Enable Manual Close to override.']);
            exit;
        }

        // Prevent duplicate closings
        $stmt = $pdo->prepare("SELECT id FROM loan_closings WHERE loan_id = ?");
        $stmt->execute([$loanId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Loan closing record already exists']);
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO loan_closings (loan_id, closing_date, total_interest_paid) VALUES (?, ?, ?)");
            $stmt->execute([$loanId, $closingDate, $closingAmount]);

            $stmt = $pdo->prepare("UPDATE loans SET status = 'closed' WHERE id = ?");
            $stmt->execute([$loanId]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Loan closed successfully']);
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to close loan: ' . $e->getMessage()]);
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

