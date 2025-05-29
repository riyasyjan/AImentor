<?php
session_start();
require_once __DIR__ . '/backend/db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['user']['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$user = $_SESSION['user'];
$pdo = require __DIR__ . '/backend/db.php';

// Handle class joining with enhanced error prevention
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_class'])) {
    $classCode = strtoupper(trim(filter_var($_POST['class_code'], FILTER_SANITIZE_STRING)));
    
    try {
        // Start transaction for atomic operations
        $pdo->beginTransaction();
        
        // 1. Verify class exists (with lock to prevent race conditions)
        $stmt = $pdo->prepare("SELECT * FROM classes WHERE class_code = ? FOR UPDATE");
        $stmt->execute([$classCode]);
        $class = $stmt->fetch();
        
        if (!$class) {
            $_SESSION['error'] = "Class not found with code: $classCode";
            $pdo->rollBack();
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        
        // 2. Check existing enrollment
        $stmt = $pdo->prepare("SELECT 1 FROM class_members WHERE class_id = ? AND student_id = ?");
        $stmt->execute([$class['id'], $user['id']]);
        
        if ($stmt->fetch()) {
            $_SESSION['error'] = "You're already enrolled in this class";
            $pdo->rollBack();
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        
        // 3. Enroll student
        $stmt = $pdo->prepare("INSERT INTO class_members (class_id, student_id) VALUES (?, ?)");
        $stmt->execute([$class['id'], $user['id']]);
        
        $pdo->commit();
        $_SESSION['success'] = "Successfully joined class: ".htmlspecialchars($class['name']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = ($e->getCode() == '23000') 
            ? "Enrollment error. Please try again." 
            : "System error: ".htmlspecialchars($e->getMessage());
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Fetch student's classes
$enrolledClasses = [];
try {
    $stmt = $pdo->prepare("SELECT c.* FROM classes c 
                          JOIN class_members cm ON c.id = cm.class_id 
                          WHERE student_id = ?");
    $stmt->execute([$user['id']]);
    $enrolledClasses = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching classes: " . $e->getMessage();
}

// Fetch tasks assigned to the student
$studentTasks = [];
try {
    $stmt = $pdo->prepare("SELECT t.*, c.name AS class_name 
                          FROM tasks t
                          JOIN class_activities ca ON t.id = ca.task_id
                          JOIN classes c ON ca.class_id = c.id
                          JOIN class_members cm ON c.id = cm.class_id
                          WHERE cm.student_id = ?
                          ORDER BY t.due_date ASC");
    $stmt->execute([$user['id']]);
    $studentTasks = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching tasks: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - AI Mentor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .dashboard-header {
            background-color: #343a40;
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        .sidebar {
            background-color: #f8f9fa;
            min-height: calc(100vh - 56px);
            padding: 1rem;
        }
        .sidebar .nav-link {
            color: #495057;
            margin-bottom: 0.5rem;
            border-radius: 5px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #007bff;
            color: white;
        }
        .main-content {
            padding: 2rem;
        }
        .card {
            margin-bottom: 1.5rem;
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        .task-card {
            border-left: 4px solid #007bff;
            transition: transform 0.2s;
        }
        .task-card:hover {
            transform: translateY(-3px);
        }
        .task-due {
            font-size: 0.9rem;
        }
        .task-due.urgent {
            color: #dc3545;
            font-weight: bold;
        }
        .is-invalid {
            border-color: #dc3545;
        }
        .invalid-feedback {
            color: #dc3545;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1><i class="fas fa-user-graduate me-2"></i>Student Dashboard</h1>
                <div>
                    <span class="me-3">Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
                    <a href="<?php echo dirname($_SERVER['PHP_SELF']) . '/backend/logout.php'; ?>" class="btn btn-outline-light">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 sidebar">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#classes"><i class="fas fa-book me-2"></i>My Classes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#tasks"><i class="fas fa-tasks me-2"></i>My Tasks</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#progress"><i class="fas fa-chart-line me-2"></i>Progress</a>
                    </li>
                </ul>
            </div>
            <div class="col-md-9 main-content">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <div class="col">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#joinClassModal">
                            <i class="fas fa-plus me-2"></i>Join a Class
                        </button>
                    </div>
                </div>

                <!-- Tasks Section -->
                <div class="row mb-5" id="tasks">
                    <h3 class="mb-4"><i class="fas fa-tasks me-2"></i>My Tasks</h3>
                    <?php if (!empty($studentTasks)): ?>
                        <?php foreach ($studentTasks as $task): ?>
                            <?php
                            $dueClass = '';
                            if (new DateTime($task['due_date']) < new DateTime('+3 days')) {
                                $dueClass = 'urgent';
                            }
                            ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card task-card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                                        <p class="card-text"><?php echo htmlspecialchars($task['description']); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($task['class_name']); ?></span>
                                           
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <a href="task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">You don't have any tasks assigned yet.</div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Classes Section -->
                <div class="row" id="classes">
                    <h3 class="mb-4"><i class="fas fa-book me-2"></i>My Classes</h3>
                    <?php foreach ($enrolledClasses as $class): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo htmlspecialchars($class['name']); ?></h5>
                                <p class="card-text flex-grow-1"><?php echo htmlspecialchars($class['description']); ?></p>
                                <div class="mt-auto">
                                    <a href="class.php?id=<?php echo $class['id']; ?>" class="btn btn-primary">View Class</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($enrolledClasses)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">You haven't joined any classes yet.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Join Class Modal -->
    <div class="modal fade" id="joinClassModal" tabindex="-1" aria-labelledby="joinClassModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="joinClassModalLabel">Join a Class</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" onsubmit="return validateClassCode()">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="class_code" class="form-label">Class Code</label>
                            <input type="text" class="form-control" id="class_code" name="class_code" 
                                   pattern="[A-Z0-9]{6,8}" title="6-8 character alphanumeric code" required
                                   placeholder="Enter class code (e.g., MATH101)">
                            <div class="invalid-feedback">Please enter a valid 6-8 character code (letters/numbers only)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="join_class" class="btn btn-primary">Join Class</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Client-side validation
        function validateClassCode() {
            const codeInput = document.getElementById('class_code');
            const originalValue = codeInput.value;
            
            // Normalize input (uppercase, trim)
            codeInput.value = codeInput.value.trim().toUpperCase();
            
            // Validate pattern
            if (!/^[A-Z0-9]{6,8}$/.test(codeInput.value)) {
                codeInput.classList.add('is-invalid');
                codeInput.value = originalValue; // Restore original value
                return false;
            }
            
            codeInput.classList.remove('is-invalid');
            return true;
        }

        // Live validation as user types
        document.getElementById('class_code').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
            if (this.value.length > 8) {
                this.value = this.value.slice(0, 8);
            }
        });
    </script>
</body>
</html>