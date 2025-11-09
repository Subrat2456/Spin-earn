<?php
// --- DATABASE CONNECTION ---
// Instructions:
// 1. Replace 'localhost' with your database host if it's different.
// 2. Replace 'root' with your database username.
// 3. Replace '' with your database password.
// 4. Replace 'winzone_db' with the name of the database you created.

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'winzone_db';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    // A real app would log this error, not display it to the user.
    die(json_encode(['success' => false, 'error' => 'Database connection failed.']));
}

// Set charset to handle special characters
$conn->set_charset("utf8mb4");
?>