<?php
require_once 'config/database.php';

echo "<h2>Lakshmi Finance - Database Initialization</h2>";

try {
    // Initialize database
    initializeDatabase();
    echo "<p style='color: green;'>✅ Database initialized successfully!</p>";
    
    echo "<h3>Default Login Credentials:</h3>";
    echo "<p><strong>Username:</strong> admin</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    
    echo "<h3>Sample Data Added:</h3>";
    echo "<ul>";
    echo "<li>✅ Default admin user</li>";
    echo "<li>✅ Sample customers (siva, Anantha babu)</li>";
    echo "<li>✅ Sample loans</li>";
    echo "<li>✅ Sample products (chain, RING, BANGLE, etc.)</li>";
    echo "<li>✅ Sample transactions</li>";
    echo "</ul>";
    
    echo "<p><a href='index.php' style='background: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration in config/database.php</p>";
}
?> 