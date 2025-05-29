<?php
// backend/api.php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Return user data
header('Content-Type: application/json');
echo json_encode($_SESSION['user']);
?>