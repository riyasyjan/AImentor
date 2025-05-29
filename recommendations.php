// backend/recommendations.php
function getRecommendedVideos($studentId, $pdo) {
    try {
        // Get student's watched videos and tags they engaged with
        $stmt = $pdo->prepare('SELECT v.tags 
                              FROM educator_videos v
                              JOIN student_video_interactions i ON v.id = i.video_id
                              WHERE i.student_id = ? AND i.rating > 3');
        $stmt->execute([$studentId]);
        $watchedTags = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Flatten tags and count occurrences
        $tagCounts = [];
        foreach ($watchedTags as $tagString) {
            $tags = explode(',', $tagString);
            foreach ($tags as $tag) {
                $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
            }
        }
        
        // Get top 3 preferred tags
        arsort($tagCounts);
        $preferredTags = array_slice(array_keys($tagCounts), 0, 3);
        
        if (empty($preferredTags)) {
            // If no preferences yet, return most popular videos
            return getPopularVideos($pdo);
        }
        
        // Find videos matching preferred tags
        $placeholders = implode(',', array_fill(0, count($preferredTags), '?'));
        $query = "SELECT * FROM educator_videos 
                 WHERE tags IN ($placeholders) 
                 ORDER BY created_at DESC 
                 LIMIT 10";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($preferredTags);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Recommendation error: " . $e->getMessage());
        return getPopularVideos($pdo); // Fallback
    }
}

function getPopularVideos($pdo) {
    $stmt = $pdo->query('SELECT v.* 
                        FROM educator_videos v
                        LEFT JOIN student_video_interactions i ON v.id = i.video_id
                        GROUP BY v.id
                        ORDER BY COUNT(i.id) DESC
                        LIMIT 10');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}