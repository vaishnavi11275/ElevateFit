<?php
// Admin's plain password
$adminPassword = "admin@123";

// Hash the password using the default bcrypt algorithm
$hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);

// Output the hashed password
echo "Hashed Password: " . $hashedPassword;
?>
