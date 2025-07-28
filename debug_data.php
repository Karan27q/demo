<?php
// Debug data addition and display
echo "<h2>🔍 Debug Data Addition and Display</h2>";

try {
    require_once 'config/database.php';
    $pdo = getDBConnection();
    echo "<p>✅ Database connection successful</p>";
    
    // Test 1: Check all customers
    echo "<h3>Test 1: All Customers in Database</h3>";
    $stmt = $pdo->query("SELECT * FROM customers ORDER BY created_at DESC");
    $customers = $stmt->fetchAll();
    
    if ($customers) {
        echo "<p>Total customers: " . count($customers) . "</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Customer No</th><th>Name</th><th>Mobile</th><th>Created At</th></tr>";
        foreach ($customers as $customer) {
            echo "<tr>";
            echo "<td>" . $customer['id'] . "</td>";
            echo "<td>" . $customer['customer_no'] . "</td>";
            echo "<td>" . $customer['name'] . "</td>";
            echo "<td>" . $customer['mobile'] . "</td>";
            echo "<td>" . $customer['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No customers found</p>";
    }
    
    // Test 2: Check all loans
    echo "<h3>Test 2: All Loans in Database</h3>";
    $stmt = $pdo->query("SELECT l.*, c.name as customer_name FROM loans l JOIN customers c ON l.customer_id = c.id ORDER BY l.created_at DESC");
    $loans = $stmt->fetchAll();
    
    if ($loans) {
        echo "<p>Total loans: " . count($loans) . "</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Loan No</th><th>Customer</th><th>Amount</th><th>Created At</th></tr>";
        foreach ($loans as $loan) {
            echo "<tr>";
            echo "<td>" . $loan['id'] . "</td>";
            echo "<td>" . $loan['loan_no'] . "</td>";
            echo "<td>" . $loan['customer_name'] . "</td>";
            echo "<td>₹" . $loan['principal_amount'] . "</td>";
            echo "<td>" . $loan['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No loans found</p>";
    }
    
    // Test 3: Check all transactions
    echo "<h3>Test 3: All Transactions in Database</h3>";
    $stmt = $pdo->query("SELECT * FROM transactions ORDER BY created_at DESC");
    $transactions = $stmt->fetchAll();
    
    if ($transactions) {
        echo "<p>Total transactions: " . count($transactions) . "</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Date</th><th>Name</th><th>Type</th><th>Amount</th><th>Created At</th></tr>";
        foreach ($transactions as $transaction) {
            echo "<tr>";
            echo "<td>" . $transaction['id'] . "</td>";
            echo "<td>" . $transaction['date'] . "</td>";
            echo "<td>" . $transaction['transaction_name'] . "</td>";
            echo "<td>" . $transaction['transaction_type'] . "</td>";
            echo "<td>₹" . $transaction['amount'] . "</td>";
            echo "<td>" . $transaction['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No transactions found</p>";
    }
    
    // Test 4: Simulate adding a test customer
    echo "<h3>Test 4: Add Test Customer</h3>";
    $testCustomerNo = 'DEBUG' . date('YmdHis');
    $testName = 'Debug Customer ' . date('H:i:s');
    $testMobile = '9876543210';
    
    // Check if test customer already exists
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE customer_no = ?");
    $stmt->execute([$testCustomerNo]);
    if ($stmt->fetch()) {
        echo "<p>⚠️ Test customer already exists</p>";
    } else {
        // Add test customer
        $stmt = $pdo->prepare("INSERT INTO customers (customer_no, name, mobile, address) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$testCustomerNo, $testName, $testMobile, 'Debug Address']);
        
        if ($result) {
            echo "<p>✅ Test customer added successfully: $testCustomerNo - $testName</p>";
            
            // Check if it appears in the query
            $stmt = $pdo->query("SELECT * FROM customers ORDER BY created_at DESC LIMIT 5");
            $recentCustomers = $stmt->fetchAll();
            
            echo "<p>Recent customers after addition:</p>";
            echo "<ul>";
            foreach ($recentCustomers as $customer) {
                echo "<li>" . $customer['customer_no'] . " - " . $customer['name'] . " (" . $customer['created_at'] . ")</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>❌ Failed to add test customer</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<h3>🎯 Next Steps:</h3>";
echo "<ol>";
echo "<li><a href='dashboard.php'>Go to Dashboard</a></li>";
echo "<li>Try adding a customer and check if it appears</li>";
echo "<li>Check browser console (F12) for any errors</li>";
echo "<li>Check Network tab in browser dev tools for API responses</li>";
echo "</ol>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1200px;
    margin: 50px auto;
    padding: 20px;
    background: #f5f5f5;
    line-height: 1.6;
}

h2, h3 {
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 10px;
}

h2 {
    background: #007bff;
    color: white;
    padding: 15px;
    border-radius: 5px;
    text-align: center;
}

p {
    margin: 10px 0;
    padding: 10px;
    background: white;
    border-radius: 5px;
    border-left: 4px solid #007bff;
}

table {
    background: white;
    margin: 10px 0;
    border-radius: 5px;
    overflow: hidden;
}

th, td {
    padding: 8px;
    text-align: left;
    border: 1px solid #ddd;
}

th {
    background: #f8f9fa;
    font-weight: bold;
}

ul, ol {
    background: white;
    padding: 20px;
    border-radius: 5px;
    border-left: 4px solid #28a745;
}

li {
    margin: 5px 0;
}

a {
    color: #007bff;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}
</style> 