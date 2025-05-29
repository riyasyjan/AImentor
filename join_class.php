<?php
session_start();
require_once __DIR__ . '/backend/db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

$user = $_SESSION['user'];
$classId = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

// Verify student is enrolled in this class
$pdo = require __DIR__ . '/backend/db.php';
$stmt = $pdo->prepare("SELECT 1 FROM class_members WHERE class_id = ? AND student_id = ?");
$stmt->execute([$classId, $user['id']]);

if (!$stmt->fetch()) {
    $_SESSION['error'] = "You are not enrolled in this class";
    header('Location: dashboard.php');
    exit();
}

// Get class details
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->execute([$classId]);
$class = $stmt->fetch();

// Get tasks for this class with student's progress - UPDATED QUERY
$stmt = $pdo->prepare("SELECT 
        t.id, 
        t.title, 
        t.description, 
        t.due_date,
        COALESCE(st.progress, 0) AS progress,
        st.submitted_at
    FROM class_activities ca
    JOIN tasks t ON ca.task_id = t.id
    LEFT JOIN student_tasks st ON t.id = st.task_id AND st.student_id = ?
    WHERE ca.class_id = ?
    ORDER BY t.due_date ASC");
$stmt->execute([$user['id'], $classId]);
$tasks = $stmt->fetchAll();

// Handle task submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_task'])) {
    $taskId = filter_var($_POST['task_id'], FILTER_SANITIZE_NUMBER_INT);
    $progress = filter_var($_POST['progress'], FILTER_SANITIZE_NUMBER_INT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO student_tasks (student_id, task_id, progress, submitted_at)
                              VALUES (?, ?, ?, NOW())
                              ON DUPLICATE KEY UPDATE progress = ?, submitted_at = NOW()");
        $stmt->execute([$user['id'], $taskId, $progress, $progress]);
        
        $_SESSION['success'] = "Task progress updated successfully!";
        header("Location: class.php?id=$classId");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating task: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($class['name']); ?> - AI Mentor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .task-card {
            transition: transform 0.2s;
            margin-bottom: 1.5rem;
            border-left: 4px solid #0d6efd;
        }
        .task-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }
        .progress-bar {
            height: 20px;
            border-radius: 10px;
        }
        .due-date {
            font-size: 0.9rem;
        }
        .late {
            color: #dc3545;
            font-weight: bold;
        }
        .completed {
            color: #198754;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/partials/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?php echo htmlspecialchars($class['name']); ?></h1>
            <a href="student_dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Class Description</h5>
                        <p class="card-text"><?php echo htmlspecialchars($class['description']); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Class Statistics</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tasks Completed:</span>
                            <span>
                                <?php 
                                $completed = array_reduce($tasks, function($carry, $task) {
                                    return $carry + ($task['progress'] >= 100 ? 1 : 0);
                                }, 0);
                                echo "$completed/".count($tasks);
                                ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Overall Progress:</span>
                            <span>
                                <?php 
                                $totalProgress = array_reduce($tasks, function($carry, $task) {
                                    return $carry + ($task['progress'] ?? 0);
                                }, 0);
                                $avgProgress = count($tasks) > 0 ? round($totalProgress / count($tasks)) : 0;
                                echo "$avgProgress%";
                                ?>
                            </span>
                        </div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $avgProgress; ?>%"
                                 aria-valuenow="<?php echo $avgProgress; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <h3 class="mb-3">Class Tasks</h3>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if (empty($tasks)): ?>
            <div class="alert alert-info">No tasks have been assigned yet.</div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($tasks as $task): ?>
                <?php
                $isLate = strtotime($task['due_date']) < time() && $task['progress'] < 100;
                $isComplete = $task['progress'] >= 100;
                ?>
                <div class="col-md-6">
                    <div class="card task-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($task['title']); ?></h5>
                                <span class="due-date <?php echo $isLate ? 'late' : ($isComplete ? 'completed' : ''); ?>">
                                    <i class="far fa-clock me-1"></i>
                                    Due: <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                    <?php if ($isLate): ?>
                                        <span class="badge bg-danger ms-2">Late</span>
                                    <?php elseif ($isComplete): ?>
                                        <span class="badge bg-success ms-2">Completed</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <p class="card-text"><?php echo htmlspecialchars($task['description']); ?></p>
                            
                            <div class="mb-3">
                                <label class="form-label">Your Progress:</label>
                                <div class="progress mb-2">
                                    <div class="progress-bar <?php echo $isComplete ? 'bg-success' : ''; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $task['progress']; ?>%"
                                         aria-valuenow="<?php echo $task['progress']; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        <?php echo $task['progress']; ?>%
                                    </div>
                                </div>
                            </div>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <div class="input-group mb-3">
                                    <input type="number" name="progress" class="form-control" 
                                           min="0" max="100" 
                                           value="<?php echo $task['progress']; ?>"
                                           required>
                                    <button class="btn btn-primary" type="submit" name="submit_task">
                                        <i class="fas fa-save me-2"></i>Update
                                    </button>
                                </div>
                            </form>
                            
                            <?php if (!empty($task['submitted_at'])): ?>
                                <small class="text-muted">
                                    <i class="far fa-calendar-check me-1"></i>
                                    Last updated: <?php echo date('M j, Y g:i a', strtotime($task['submitted_at'])); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>