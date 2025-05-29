<?php
// create_assessment.php
session_start();

// Check if the user is logged in and is an educator
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'educator') {
    header('Location: ../login.php');
    exit();
}

include '../backend/db.php'; // Database connection

// Fetch classes created by the educator
$educator_id = $_SESSION['user']['id'];
$query = "SELECT id, name FROM classes WHERE educator_id = ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $educator_id, PDO::PARAM_INT);
$stmt->execute();
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = $_POST['class_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];

    // Insert assessment into the database
    $query = "INSERT INTO assessments (class_id, title, description) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $class_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $title, PDO::PARAM_STR);
    $stmt->bindParam(3, $description, PDO::PARAM_STR);

    if ($stmt->execute()) {
        $success = "Assessment created successfully!";
    } else {
        $error = "Failed to create assessment. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Create Assessment</h1>
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="class_id" class="form-label">Select Class</label>
                <select class="form-select" id="class_id" name="class_id" required>
                    <option value="">Choose a class</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="title" class="form-label">Assessment Title</label>
                <input type="text" class="form-control" id="title" name="title" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Assessment Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Create Assessment</button>
        </form>
    </div>
</body>
</html>