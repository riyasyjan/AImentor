// rate_video.php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user'])) {
    $videoId = $_POST['video_id'];
    $rating = $_POST['rating'];
    
    $stmt = $pdo->prepare('INSERT INTO student_video_interactions 
                          (student_id, video_id, rating) 
                          VALUES (?, ?, ?)
                          ON DUPLICATE KEY UPDATE rating = VALUES(rating)');
    $stmt->execute([$_SESSION['user']['id'], $videoId, $rating]);
    
    header('Location: student_dashboard.php');
    exit();
}