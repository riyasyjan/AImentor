<?php
session_start();

// Check if the user is logged in
if (isset($_SESSION['user'])) {
    // Redirect logged-in users to the dashboard
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Mentor - Home</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f9;
            color: #333;
        }
        header {
            background-color: #007BFF;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header h1 {
            margin: 0;
        }
        header nav a {
            color: white;
            text-decoration: none;
            margin-left: 15px;
        }
        .hero {
            background-color: #007BFF;
            color: white;
            padding: 50px 20px;
            text-align: center;
        }
        .hero h2 {
            font-size: 2.5em;
            margin: 0;
        }
        .hero p {
            font-size: 1.2em;
            margin: 20px 0;
        }
        .hero .cta-buttons {
            margin-top: 20px;
        }
        .hero .cta-buttons a {
            background-color: white;
            color: #007BFF;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin: 0 10px;
        }
        .features {
            padding: 50px 20px;
            text-align: center;
        }
        .features h3 {
            font-size: 2em;
            margin-bottom: 20px;
        }
        .features .feature-list {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
        }
        .features .feature {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 30%;
            margin: 10px;
        }
        footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 50px;
        }
        footer a {
            color: #007BFF;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <h1>AI Mentor</h1>
        <nav>
            <a href="backend/login.php">Login</a>
            <a href="backend/register.php">Register</a>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <h2>Welcome to AI Mentor</h2>
        <p>Your personal guide to mastering AI and machine learning.</p>
        <div class="cta-buttons">
            <a href="backend/register.php">Get Started</a>
            <a href="backend/login.php">Login</a>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <h3>Why Choose AI Mentor?</h3>
        <div id="root"></div> <!-- React app will be rendered here -->
    </section>

    <!-- Footer -->
    <footer>
        <p>&copy; 2023 AI Mentor. All rights reserved. | <a href="#">Privacy Policy</a></p>
    </footer>

    <!-- Include React JS -->
    <script src="/ai_mentor/static/js/main.js"></script>
</body>
</html>