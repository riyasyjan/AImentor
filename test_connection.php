<?php
// test_connection.php

// Include the database connection file
require_once 'backend/database.php';

try {
    // Test the connection by running a simple query
    $stmt = $pdo->query('SELECT 1');
    
    // If the query runs successfully, the connection is working
    echo "Database connection successful!";
} catch (PDOException $e) {
    // If there's an error, display the error message
    echo "Database connection failed: " . $e->getMessage();
}
?>