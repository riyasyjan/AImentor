<?php
// Absolute first line - no whitespace before this
session_start();

// Debugging: Uncomment these lines to check session status (remove in production)
// error_log('Session status: ' . session_status());
// if (session_status() !== PHP_SESSION_ACTIVE) {
//     die('Session initialization failed');
// }

// Use absolute path for includes
require_once __DIR__ . '/backend/db.php';
$pdo = require __DIR__ . '/backend/db.php';

// Enhanced authentication check with proper redirect
if (!isset($_SESSION['user'])) {
    // Use absolute URL for redirect
    $redirect_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
                   . "://$_SERVER[HTTP_HOST]"
                   . dirname($_SERVER['PHP_SELF']) 
                   . '/login.php';
    header("Location: $redirect_url");
    exit();
}

// Verify user role
if ($_SESSION['user']['role'] !== 'educator') {
    $redirect_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
                   . "://$_SERVER[HTTP_HOST]"
                   . dirname($_SERVER['PHP_SELF']) 
                   . '/unauthorized.php';
    header("Location: $redirect_url");
    exit();
}

$user = $_SESSION['user'];

// Handle class creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_class'])) {
    $className = filter_var($_POST['class_name'], FILTER_SANITIZE_STRING);
    $classDescription = filter_var($_POST['class_description'], FILTER_SANITIZE_STRING);
    $classCode = substr(md5(uniqid(rand(), true)), 0, 8); // Generate 8-character code

    try {
        $stmt = $pdo->prepare("INSERT INTO classes (educator_id, name, description, class_code) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user['id'], $className, $classDescription, $classCode]);
        $_SESSION['success'] = "Class created successfully!";
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error creating class: " . $e->getMessage();
    }
}

// Fetch educator's classes with activity counts
$classes = [];
try {
    $stmt = $pdo->prepare("SELECT c.*, 
                          (SELECT COUNT(*) FROM class_activities WHERE class_id = c.id) as activity_count,
                          (SELECT COUNT(*) FROM class_members WHERE class_id = c.id) as student_count
                          FROM classes c 
                          WHERE c.educator_id = ?");
    $stmt->execute([$user['id']]);
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching classes: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Educator Dashboard - AI Mentor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-header {
            background-color: #343a40;
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .sidebar {
            background-color: #f8f9fa;
            min-height: calc(100vh - 56px);
            padding: 1rem;
            border-right: 1px solid #dee2e6;
        }
        
        .sidebar .nav-link {
            color: #495057;
            margin-bottom: 0.5rem;
            border-radius: 5px;
            transition: all 0.2s;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: var(--primary-color);
            color: white;
            transform: translateX(5px);
        }
        
        .main-content {
            padding: 2rem;
            background-color: #fff;
        }
        
        .card {
            margin-bottom: 1.5rem;
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
        }
        
        .class-code {
            font-family: monospace;
            background-color: #f8f9fa;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .stats-badge {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .create-class-btn {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .welcome-message {
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1><i class="fas fa-chalkboard-teacher me-2"></i>Educator Dashboard</h1>
                <div>
                    <span class="me-3 welcome-message">Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
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
                        <a class="nav-link" href="#students"><i class="fas fa-users me-2"></i>Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#classes"><i class="fas fa-book me-2"></i>Classes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#assignments"><i class="fas fa-tasks me-2"></i>Assignments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#analytics"><i class="fas fa-chart-bar me-2"></i>Analytics</a>
                    </li>
                </ul>
            </div>
            <div class="col-md-9 main-content">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 id="classes">My Classes</h2>
                    <button class="btn create-class-btn text-white" data-bs-toggle="modal" data-bs-target="#createClassModal">
                        <i class="fas fa-plus me-2"></i>Create New Class
                    </button>
                </div>

                <div class="row">
                    <?php foreach ($classes as $class): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title"><?php echo htmlspecialchars($class['name']); ?></h5>
                                    <div>
                                        <span class="badge bg-primary stats-badge me-1">
                                            <i class="fas fa-tasks me-1"></i> <?php echo $class['activity_count']; ?>
                                        </span>
                                        <span class="badge bg-success stats-badge">
                                            <i class="fas fa-users me-1"></i> <?php echo $class['student_count']; ?>
                                        </span>
                                    </div>
                                </div>
                                <p class="card-text flex-grow-1"><?php echo htmlspecialchars($class['description']); ?></p>
                                <div class="mt-auto">
                                    <p class="card-text mb-2">
                                        <small class="text-muted">Class Code: <span class="class-code"><?php echo $class['class_code']; ?></span></small>
                                    </p>
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="manage_class.php?id=<?php echo $class['id']; ?>" class="btn btn-primary me-md-2">
                                            <i class="fas fa-cog me-1"></i> Manage
                                        </a>
                                        <a href="class_activities.php?id=<?php echo $class['id']; ?>" class="btn btn-outline-secondary">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($classes)): ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-book-open fa-4x text-muted mb-4"></i>
                                    <h3>No Classes Yet</h3>
                                    <p class="text-muted">Get started by creating your first class</p>
                                    <button class="btn create-class-btn text-white" data-bs-toggle="modal" data-bs-target="#createClassModal">
                                        <i class="fas fa-plus me-2"></i>Create Your First Class
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Class Modal -->
    <div class="modal fade" id="createClassModal" tabindex="-1" aria-labelledby="createClassModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="createClassModalLabel">Create New Class</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="class_name" class="form-label">Class Name</label>
                            <input type="text" class="form-control" id="class_name" name="class_name" required placeholder="e.g., Mathematics 101">
                        </div>
                        <div class="mb-3">
                            <label for="class_description" class="form-label">Description</label>
                            <textarea class="form-control" id="class_description" name="class_description" rows="4" placeholder="Brief description of what students will learn"></textarea>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> A unique class code will be automatically generated for student enrollment.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_class" class="btn btn-success">
                            <i class="fas fa-save me-1"></i> Create Class
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enable Bootstrap tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });
    </script>
</body>
</html>