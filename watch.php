// watch.php (when a student watches a video)
session_start();
require_once 'db.php';

if (!isset($_SESSION['user']) {
    header('Location: login.php');
    exit();
}

$videoId = $_GET['id'] ?? 0;

// Record the view
$stmt = $pdo->prepare('INSERT INTO student_video_interactions 
                      (student_id, video_id, watched) 
                      VALUES (?, ?, TRUE)
                      ON DUPLICATE KEY UPDATE watched = TRUE');
$stmt->execute([$_SESSION['user']['id'], $videoId]);

// Then show the video page...