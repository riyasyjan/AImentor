function searchYouTubeVideos($query) {
    $apiKey = 'YOUR_API_KEY'; // Replace with your actual key
    $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&type=video&maxResults=10&q=" . urlencode($query) . "&key=" . $apiKey;
    
    try {
        $response = file_get_contents($url);
        if ($response === FALSE) {
            throw new Exception("Failed to fetch from YouTube API");
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response");
        }
        
        return $data['items'] ?? [];
    } catch (Exception $e) {
        error_log("YouTube search error: " . $e->getMessage());
        return []; // Return empty array on error
    }
}