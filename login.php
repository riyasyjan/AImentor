<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';
require_once 'database.php';

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->email) && !empty($data->password)) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM users WHERE email = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$data->email]);
    
    if($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(password_verify($data->password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            
            echo json_encode([
                "success" => true,
                "message" => "Login successful!",
                "user" => [
                    "id" => $user['id'],
                    "name" => $user['name'],
                    "role" => $user['role']
                ]
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Invalid password!"
            ]);
        }
    } else {
        echo json_encode([
            "success" => false,
            "message" => "User not found!"
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields!"
    ]);
}
?>