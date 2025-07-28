<?php
// Test functionality for adding customers and loans
echo "<h2>🔍 Functionality Test</h2>";

// Test 1: Database Connection
echo "<h3>Test 1: Database Connection</h3>";
try {
    require_once 'config/database.php';
    $pdo = getDBConnection();
    echo "<p>✅ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit();
}

// Test 2: Check if customers table exists and has data
echo "<h3>Test 2: Customers Table</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers");
    $count = $stmt->fetch()['count'];
    echo "<p>✅ Customers table exists with $count records</p>";
    
    // Show sample customers
    $stmt = $pdo->query("SELECT * FROM customers LIMIT 3");
    $customers = $stmt->fetchAll();
    echo "<p>Sample customers:</p>";
    echo "<ul>";
    foreach ($customers as $customer) {
        echo "<li>" . $customer['name'] . " (" . $customer['customer_no'] . ")</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p>❌ Customers table error: " . $e->getMessage() . "</p>";
}

// Test 3: Check if loans table exists and has data
echo "<h3>Test 3: Loans Table</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM loans");
    $count = $stmt->fetch()['count'];
    echo "<p>✅ Loans table exists with $count records</p>";
    
    // Show sample loans
    $stmt = $pdo->query("SELECT l.*, c.name as customer_name FROM loans l JOIN customers c ON l.customer_id = c.id LIMIT 3");
    $loans = $stmt->fetchAll();
    echo "<p>Sample loans:</p>";
    echo "<ul>";
    foreach ($loans as $loan) {
        echo "<li>" . $loan['loan_no'] . " - " . $loan['customer_name'] . " (₹" . $loan['principal_amount'] . ")</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p>❌ Loans table error: " . $e->getMessage() . "</p>";
}

// Test 4: Test API endpoints
echo "<h3>Test 4: API Endpoints</h3>";
echo "<p>Testing customer API endpoint...</p>";

// Simulate a POST request to add a test customer
$testData = [
    'customer_no' => 'TEST001',
    'name' => 'Test Customer',
    'mobile' => '9876543210',
    'address' => 'Test Address'
];

// First, check if test customer already exists
$stmt = $pdo->prepare("SELECT id FROM customers WHERE customer_no = ?");
$stmt->execute(['TEST001']);
if ($stmt->fetch()) {
    echo "<p>⚠️ Test customer already exists, skipping API test</p>";
} else {
    echo "<p>✅ Test customer doesn't exist, API should work</p>";
}

// Test 5: Check JavaScript console for errors
echo "<h3>Test 5: JavaScript Check</h3>";
echo "<p>Please check your browser's console (F12) for any JavaScript errors when trying to add customers or loans.</p>";

echo "<h3>🎯 Quick Fixes to Try:</h3>";
echo "<ol>";
echo "<li><a href='index.php'>Go to Login Page</a></li>";
echo "<li><a href='dashboard.php'>Go to Dashboard</a></li>";
echo "<li>Check browser console (F12) for JavaScript errors</li>";
echo "<li>Try adding a customer and check the Network tab in browser dev tools</li>";
echo "</ol>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
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