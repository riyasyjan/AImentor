<?php
// submit_task.php
session_start();

// Check if the user is logged in and is a student
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
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
    $task_id = $_POST['task_id'];
    $submission_text = $_POST['submission_text'];
    $student_id = $_SESSION['user']['id'];

    // Insert the submission into the database
    $query = "INSERT INTO submissions (task_id, student_id, submission_text) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $task_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $student_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $submission_text, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $message = "Submission successful!";
    } else {
        $message = "Failed to submit. Please try again.";
    }
}

// Fetch tasks assigned to the student
$student_id = $_SESSION['user']['id'];
$query = "SELECT t.id, t.task_name, t.task_description, t.due_date, c.name AS class_name 
          FROM tasks t
          JOIN classes c ON t.class_id = c.id
          JOIN student_classes sc ON c.id = sc.class_id
          WHERE sc.student_id = ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $student_id, PDO::PARAM_INT);
$stmt->execute();
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Task - AI Mentor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Submit Task</h1>
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="task_id" class="form-label">Select Task</label>
                <select class="form-control" id="task_id" name="task_id" required>
                    <?php foreach ($tasks as $task): ?>
                        <option value="<?php echo htmlspecialchars($task['id']); ?>">
                            <?php echo htmlspecialchars($task['task_name']); ?> (<?php echo htmlspecialchars($task['class_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="submission_text" class="form-label">Your Submission</label>
                <textarea class="form-control" id="submission_text" name="submission_text" rows="5" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>
</body>
</html>