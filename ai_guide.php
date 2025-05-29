<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php'; // API keys should be defined here

function generateAIResources($task_id, $task_title, $task_description) {
    global $pdo;
    
    // 1. YouTube Video Search
    $youtube_results = searchYouTubeVideos($task_title, 3);
    
    // 2. Google Scholar/Web Search
    $article_results = searchWebResources($task_title, 3);
    
    // 3. Save to database
    $stmt = $pdo->prepare("INSERT INTO task_resources 
                          (task_id, resource_type, title, url, description, ai_generated)
                          VALUES (?, ?, ?, ?, ?, 1)");
    
    foreach ($youtube_results as $video) {
        $stmt->execute([$task_id, 'video', $video['title'], $video['url'], $video['description']]);
    }
    
    foreach ($article_results as $article) {
        $stmt->execute([$task_id, 'article', $article['title'], $article['url'], $article['snippet']]);
    }
    
    return true;
}

function searchYouTubeVideos($query, $max_results = 3) {
    $api_key = YOUTUBE_API_KEY;
    $query = urlencode($query . " tutorial");
    $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&maxResults=$max_results&q=$query&key=$api_key&type=video";
    
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    $results = [];
    foreach ($data['items'] as $item) {
        $results[] = [
            'title' => $item['snippet']['title'],
            'url' => "https://www.youtube.com/watch?v=" . $item['id']['videoId'],
            'description' => $item['snippet']['description'],
            'thumbnail' => $item['snippet']['thumbnails']['high']['url']
        ];
    }
    
    return $results;
}

function searchWebResources($query, $max_results = 3) {
    $api_key = GOOGLE_SEARCH_API_KEY;
    
    $url = "https://www.googleapis.com/customsearch/v1?q=" . urlencode($query) . 
           "&key=$api_key&cx=$cx&num=$max_results";
    
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    $results = [];
    foreach ($data['items'] as $item) {
        $results[] = [
            'title' => $item['title'],
            'url' => $item['link'],
            'snippet' => $item['snippet']
        ];
    }
    
    return $results;
}