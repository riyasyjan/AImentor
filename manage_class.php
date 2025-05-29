<?php
session_start();
require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/config.php'; // For API keys

// Check if educator is logged in
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'educator') {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$class_id = $_GET['id'] ?? 0;
$pdo = require __DIR__ . '/backend/db.php';

// Verify educator owns this class
try {
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND educator_id = ?");
    $stmt->execute([$class_id, $user['id']]);
    $class = $stmt->fetch();
    
    if (!$class) {
        $_SESSION['error'] = "Class not found or access denied";
        header("Location: educator_dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header("Location: educator_dashboard.php");
    exit();
}

// Handle task operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Create new task
        if (isset($_POST['add_task'])) {
            $title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);
            $description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);
            $due_date = !empty($_POST['due_date']) ? date('Y-m-d H:i:s', strtotime($_POST['due_date'])) : null;
            
            // Insert task
            $stmt = $pdo->prepare("INSERT INTO tasks (title, description, due_date, created_by, class_id) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $due_date, $user['id'], $class_id]);
            $task_id = $pdo->lastInsertId();
            
            // Link to class
            $stmt = $pdo->prepare("INSERT INTO class_activities (class_id, task_id) VALUES (?, ?)");
            $stmt->execute([$class_id, $task_id]);
            
            // Handle file upload
            if (isset($_FILES['task_file']) && $_FILES['task_file']['error'] === UPLOAD_ERR_OK) {
                $file_name = basename($_FILES['task_file']['name']);
                $file_tmp = $_FILES['task_file']['tmp_name'];
                $file_size = $_FILES['task_file']['size'];
                $file_type = $_FILES['task_file']['type'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $allowed_ext = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'png', 'txt', 'zip'];
                
                if (in_array($file_ext, $allowed_ext) && $file_size <= 10 * 1024 * 1024) {
                    $new_file_name = uniqid() . '_' . $file_name;
                    $upload_path = __DIR__ . '/uploads/tasks/' . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        $stmt = $pdo->prepare("INSERT INTO task_files 
                                              (task_id, file_name, file_path, file_size, file_type)
                                              VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$task_id, $file_name, $new_file_name, $file_size, $file_type]);
                    }
                }
            }
            
            // Generate AI learning resources
            require_once __DIR__ . '/backend/ai_guide.php';
            if (function_exists('generateAIResources')) {
                generateAIResources($task_id, $title, $description);
            }
            
            $_SESSION['success'] = "Task created successfully with AI learning resources!";
        }
        
        // Delete task
        if (isset($_POST['delete_task'])) {
            $task_id = filter_var($_POST['task_id'], FILTER_SANITIZE_NUMBER_INT);
            
            // Verify task belongs to this class
            $stmt = $pdo->prepare("SELECT 1 FROM tasks t 
                                  JOIN class_activities ca ON t.id = ca.task_id
                                  WHERE t.id = ? AND ca.class_id = ?");
            $stmt->execute([$task_id, $class_id]);
            
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
                $stmt->execute([$task_id]);
                $_SESSION['success'] = "Task deleted successfully!";
            }
        }
        
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header("Location: manage_class.php?id=$class_id");
    exit();
}

// Fetch class tasks with additional data
$tasks = [];
try {
    $stmt = $pdo->prepare("SELECT t.*, 
                          (SELECT COUNT(*) FROM student_tasks st WHERE st.task_id = t.id) AS submission_count,
                          (SELECT COUNT(*) FROM task_comments tc WHERE tc.task_id = t.id) AS comment_count,
                          (SELECT COUNT(*) FROM task_resources tr WHERE tr.task_id = t.id) AS resource_count
                          FROM tasks t
                          JOIN class_activities ca ON t.id = ca.task_id
                          WHERE ca.class_id = ?
                          ORDER BY t.due_date ASC");
    $stmt->execute([$class_id]);
    $tasks = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching tasks: " . $e->getMessage();
}

// Fetch class students
$students = [];
try {
    $stmt = $pdo->prepare("SELECT u.id, u.name FROM users u
                          JOIN class_members cm ON u.id = cm.student_id
                          WHERE cm.class_id = ?");
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching students: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Class - <?php echo htmlspecialchars($class['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .task-card {
            border-left: 4px solid #007bff;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        .task-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
        }
        .task-card.urgent {
            border-left-color: #dc3545;
        }
        .task-stats {
            font-size: 0.85rem;
        }
        .task-actions .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
        }
        .resource-badge {
            background-color: #6f42c1;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/partials/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1><?php echo htmlspecialchars($class['name']); ?></h1>
                <p class="text-muted"><?php echo htmlspecialchars($class['description']); ?></p>
                <p>Class Code: <span class="badge bg-primary"><?php echo $class['class_code']; ?></span></p>
            </div>
            <a href="educator_dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <!-- Task List -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h3 class="mb-0"><i class="fas fa-tasks me-2"></i>Class Tasks</h3>
                        <span class="badge bg-light text-dark"><?php echo count($tasks); ?> Tasks</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($tasks)): ?>
                            <?php foreach ($tasks as $task): 
                                $isUrgent = $task['due_date'] && strtotime($task['due_date']) < strtotime('+3 days');
                            ?>
                            <div class="card task-card <?php echo $isUrgent ? 'urgent' : ''; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                                            <?php if ($task['due_date']): ?>
                                                <p class="text-muted mb-2">
                                                    <i class="far fa-clock me-1"></i>
                                                    Due: <?php echo date('M j, Y g:i A', strtotime($task['due_date'])); ?>
                                                    <?php if ($isUrgent): ?>
                                                        <span class="badge bg-danger ms-2">Urgent</span>
                                                    <?php endif; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="task-actions">
                                            <a href="edit_task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <button type="submit" name="delete_task" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this task and all related data?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-3 task-stats">
                                        <div>
                                            <span class="badge bg-info text-dark me-2">
                                                <i class="fas fa-users me-1"></i>
                                                <?php echo $task['submission_count']; ?> submissions
                                            </span>
                                            <span class="badge bg-secondary me-2">
                                                <i class="fas fa-comments me-1"></i>
                                                <?php echo $task['comment_count']; ?> comments
                                            </span>
                                            <span class="badge resource-badge">
                                                <i class="fas fa-lightbulb me-1"></i>
                                                <?php echo $task['resource_count']; ?> resources
                                            </span>
                                        </div>
                                        <div>
                                            <a href="view_task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i> View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">No tasks yet for this class.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Add New Task -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h3 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Create New Task</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label">Task Title*</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description*</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="due_date" class="form-label">Due Date & Time</label>
                                <input type="datetime-local" class="form-control" id="due_date" name="due_date">
                            </div>
                            <div class="mb-3">
                                <label for="task_file" class="form-label">Attach File (Optional)</label>
                                <input class="form-control" type="file" id="task_file" name="task_file">
                                <div class="form-text">Max size: 10MB (PDF, DOC, PPT, XLS, JPG, PNG, ZIP)</div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="generate_resources" name="generate_resources" checked>
                                <label class="form-check-label" for="generate_resources">
                                    Generate AI learning resources
                                </label>
                            </div>
                            <button type="submit" name="add_task" class="btn btn-success w-100">
                                <i class="fas fa-save me-1"></i> Create Task
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Class Students -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h3 class="mb-0"><i class="fas fa-users me-2"></i>Class Students</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($students)): ?>
                            <ul class="list-group">
                                <?php foreach ($students as $student): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($student['name']); ?>
                                    <span class="badge bg-primary rounded-pill">Student</span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="mt-3 text-center">
                                <a href="class_students.php?id=<?php echo $class_id; ?>" class="btn btn-sm btn-outline-info">
                                    <i class="fas fa-chart-bar me-1"></i> View Progress
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">No students enrolled yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>