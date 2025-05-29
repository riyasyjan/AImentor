<?php
// class_details.php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: backend/login.php');
    exit();
}

$user = $_SESSION['user'];

// Verify user is an educator
if ($user['role'] !== 'educator') {
    header('Location: dashboard.php');
    exit();
}

// Database connection
include 'backend/db.php';
if (!isset($conn)) {
    die("Database connection failed");
}
// Get and validate class ID
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
if ($class_id <= 0) {
    header('Location: dashboard.php');
    exit();
}

// Fetch class details
try {
    $stmt = $conn->prepare("SELECT * FROM classes WHERE id = ? AND educator_id = ?");
    $stmt->execute([$class_id, $user['id']]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$class) {
        header('Location: dashboard.php');
        exit();
    }

    // Fetch tasks for this class
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE class_id = ? ORDER BY due_date ASC");
    $stmt->execute([$class_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch student progress for each task
    foreach ($tasks as &$task) {
        $stmt = $conn->prepare("
            SELECT s.name, st.progress 
            FROM student_tasks st
            JOIN students s ON st.student_id = s.id
            WHERE st.task_id = ?
        ");
        $stmt->execute([$task['id']]);
        $task['progress'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($class['name']); ?> - AI Mentor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }
        .class-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-top: 20px;
        }
        .task-card {
            border-left: 4px solid #007bff;
            transition: transform 0.2s;
        }
        .task-card:hover {
            transform: translateY(-2px);
        }
        .progress-bar-container {
            height: 25px;
            background: #e9ecef;
        }
        .progress-bar-fill {
            height: 100%;
            background: #28a745;
        }
    </style>
</head>
<body>
    <div class="container class-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1><?php echo htmlspecialchars($class['name']); ?></h1>
                <p class="text-muted">Class ID: <?php echo $class['id']; ?></p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Class Management</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h4>Join Code</h4>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" id="joinCode" 
                                   value="<?php echo 'http://'.$_SERVER['HTTP_HOST'].'/join_class.php?code='.htmlspecialchars($class['join_code']); ?>" 
                                   readonly>
                            <button class="btn btn-primary" onclick="copyJoinCode()">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="create_task.php?class_id=<?php echo $class_id; ?>" class="btn btn-success">
                            <i class="fas fa-plus"></i> Create New Task
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Class Tasks</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($tasks)): ?>
                    <div class="list-group">
                        <?php foreach ($tasks as $task): ?>
                            <div class="list-group-item task-card mb-3">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo htmlspecialchars($task['title']); ?></h4>
                                        <p><?php echo htmlspecialchars($task['description']); ?></p>
                                        <small class="text-muted">
                                            Due: <?php echo date('F j, Y', strtotime($task['due_date'])); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <a href="edit_task.php?task_id=<?php echo $task['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <h5>Student Progress</h5>
                                    <?php if (!empty($task['progress'])): ?>
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Student</th>
                                                    <th>Progress</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($task['progress'] as $progress): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($progress['name']); ?></td>
                                                        <td>
                                                            <div class="progress-bar-container rounded">
                                                                <div class="progress-bar-fill rounded" 
                                                                     style="width: <?php echo $progress['progress']; ?>%">
                                                                </div>
                                                            </div>
                                                            <small><?php echo $progress['progress']; ?>%</small>
                                                        </td>
                                                        <td>
                                                            <?php if ($progress['progress'] >= 100): ?>
                                                                <span class="badge bg-success">Completed</span>
                                                            <?php elseif ($progress['progress'] > 0): ?>
                                                                <span class="badge bg-warning text-dark">In Progress</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Not Started</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <div class="alert alert-info mb-0">
                                            No students have started this task yet.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No tasks have been created for this class yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function copyJoinCode() {
            const joinCode = document.getElementById('joinCode');
            joinCode.select();
            document.execCommand('copy');
            
            // Show a nice toast notification
            const toast = document.createElement('div');
            toast.className = 'position-fixed bottom-0 end-0 p-3';
            toast.innerHTML = `
                <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header bg-success text-white">
                        <strong class="me-auto">Success</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        Join link copied to clipboard!
                    </div>
                </div>
            `;
            document.body.appendChild(toast);
            
            // Remove after 3 seconds
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>