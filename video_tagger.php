// backend/video_tagger.php
function generateVideoTags($title, $description) {
    $text = $title . ' ' . $description;
    $text = strtolower($text);
    
    // Common educational categories
    $categories = [
        'mathematics' => ['math', 'algebra', 'calculus', 'geometry'],
        'science' => ['science', 'physics', 'chemistry', 'biology'],
        'programming' => ['code', 'programming', 'python', 'javascript'],
        'history' => ['history', 'historical', 'civilization'],
        'language' => ['language', 'english', 'grammar', 'writing']
    ];
    
    $foundTags = [];
    foreach ($categories as $tag => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $foundTags[] = $tag;
                break;
            }
        }
    }
    
    return array_unique($foundTags);
}