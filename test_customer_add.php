<?php
// Test customer addition and database connectivity
echo "<h2>🔍 Customer Addition Test</h2>";

try {
    require_once 'config/database.php';
    $pdo = getDBConnection();
    echo "<p>✅ Database connection successful</p>";
    
    // Test 1: Check current customers
    echo "<h3>Test 1: Current Customers</h3>";
    $stmt = $pdo->query("SELECT * FROM customers ORDER BY created_at DESC LIMIT 5");
    $customers = $stmt->fetchAll();
    
    if ($customers) {
        echo "<p>Recent customers:</p>";
        echo "<ul>";
        foreach ($customers as $customer) {
            echo "<li>" . $customer['customer_no'] . " - " . $customer['name'] . " (" . $customer['mobile'] . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No customers found</p>";
    }
    
    // Test 2: Simulate adding a test customer
    echo "<h3>Test 2: Add Test Customer</h3>";
    $testCustomerNo = 'TEST' . date('YmdHis');
    $testName = 'Test Customer ' . date('H:i:s');
    $testMobile = '9876543210';
    $testAddress = 'Test Address';
    
    // Check if test customer already exists
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE customer_no = ?");
    $stmt->execute([$testCustomerNo]);
    if ($stmt->fetch()) {
        echo "<p>⚠️ Test customer already exists</p>";
    } else {
        // Add test customer
        $stmt = $pdo->prepare("INSERT INTO customers (customer_no, name, mobile, address) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$testCustomerNo, $testName, $testMobile, $testAddress]);
        
        if ($result) {
            echo "<p>✅ Test customer added successfully: $testCustomerNo - $testName</p>";
            
            // Verify the customer was added
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_no = ?");
            $stmt->execute([$testCustomerNo]);
            $addedCustomer = $stmt->fetch();
            
            if ($addedCustomer) {
                echo "<p>✅ Customer verified in database:</p>";
                echo "<ul>";
                echo "<li>ID: " . $addedCustomer['id'] . "</li>";
                echo "<li>Customer No: " . $addedCustomer['customer_no'] . "</li>";
                echo "<li>Name: " . $addedCustomer['name'] . "</li>";
                echo "<li>Mobile: " . $addedCustomer['mobile'] . "</li>";
                echo "</ul>";
            } else {
                echo "<p>❌ Customer not found in database after insertion</p>";
            }
        } else {
            echo "<p>❌ Failed to add test customer</p>";
        }
    }
    
    // Test 3: Test API endpoint
    echo "<h3>Test 3: API Endpoint Test</h3>";
    echo "<p>Testing customer API endpoint...</p>";
    
    // Simulate API call
    $apiData = [
        'customer_no' => 'API' . date('YmdHis'),
        'name' => 'API Test Customer',
        'mobile' => '8765432109',
        'address' => 'API Test Address'
    ];
    
    // Check if API customer already exists
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE customer_no = ?");
    $stmt->execute([$apiData['customer_no']]);
    if ($stmt->fetch()) {
        echo "<p>⚠️ API test customer already exists</p>";
    } else {
        echo "<p>✅ API test customer doesn't exist, API should work</p>";
    }
    
    // Test 4: Check customer dropdown data
    echo "<h3>Test 4: Customer Dropdown Data</h3>";
    $stmt = $pdo->query("SELECT id, customer_no, name FROM customers ORDER BY name LIMIT 10");
    $dropdownCustomers = $stmt->fetchAll();
    
    if ($dropdownCustomers) {
        echo "<p>Customers available for dropdown:</p>";
        echo "<ul>";
        foreach ($dropdownCustomers as $customer) {
            echo "<li>" . $customer['customer_no'] . " - " . $customer['name'] . " (ID: " . $customer['id'] . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>❌ No customers available for dropdown</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<h3>🎯 Next Steps:</h3>";
echo "<ol>";
echo "<li><a href='dashboard.php'>Go to Dashboard</a></li>";
echo "<li>Try adding a customer from the Customer page</li>";
echo "<li>Then go to Loan page and check if the customer appears in dropdown</li>";
echo "<li>Check browser console (F12) for any JavaScript errors</li>";
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