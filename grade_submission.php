<?php
// grade_submission.php
session_start();

// Check if the user is logged in and is an educator
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'educator') {
    header('Location: backend/login.php');
    exit();
}

include 'backend/db.php'; // Database connection

if (!isset($conn)) {
    die("Database connection is not established.");
}

$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submission_id = $_POST['submission_id'];
    $grade = $_POST['grade'];

    // Update the submission with the grade
    $query = "UPDATE submissions SET grade = ?, status = 'graded' WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $grade, PDO::PARAM_STR);
    $stmt->bindParam(2, $submission_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $message = "Submission graded successfully!";
    } else {
        $message = "Failed to grade submission. Please try again.";
    }
}

// Fetch submissions for grading
$query = "SELECT s.id, s.submission_text, s.submitted_at, u.name AS student_name, t.task_name 
          FROM submissions s
          JOIN users u ON s.student_id = u.id
          JOIN tasks t ON s.task_id = t.id
          WHERE s.status = 'completed'";
$stmt = $conn->prepare($query);
$stmt->execute();
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Submission - AI Mentor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Grade Submissions</h1>
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="submission_id" class="form-label">Select Submission</label>
                <select class="form-control" id="submission_id" name="submission_id" required>
                    <?php foreach ($submissions as $submission): ?>
                        <option value="<?php echo htmlspecialchars($submission['id']); ?>">
                            <?php echo htmlspecialchars($submission['student_name']); ?> - <?php echo htmlspecialchars($submission['task_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="grade" class="form-label">Grade</label>
                <input type="number" step="0.01" class="form-control" id="grade" name="grade" required>
            </div>
            <button type="submit" class="btn btn-primary">Grade</button>
        </form>
    </div>
</body>
</html>