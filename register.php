<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'database.php';

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->email) && !empty($data->password) && !empty($data->name) && !empty($data->role)) {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if email already exists
    $check_query = "SELECT id FROM users WHERE email = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$data->email]);
    
    if($check_stmt->rowCount() > 0) {
        echo json_encode([
            "success" => false,
            "message" => "Email already exists!"
        ]);
        exit;
    }
    
    // Insert new user
    $query = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    
    $password_hash = password_hash($data->password, PASSWORD_DEFAULT);
    
    if($stmt->execute([$data->name, $data->email, $password_hash, $data->role])) {
        echo json_encode([
            "success" => true,
            "message" => "Registration successful!"
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Registration failed!"
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields!"
    ]);
}
?>