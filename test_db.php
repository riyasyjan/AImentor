<?php
include 'backend/db.php';

if ($conn) {
    echo "Database Connection Successful!";
} else {
    echo "Database Connection Failed!";
}
?>
