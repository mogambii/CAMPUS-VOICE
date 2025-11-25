<?php
// Generate password hash for "password"
$password = "password";
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password: " . $password . "\n";
echo "Hash: " . $hash . "\n";
echo "\n";
echo "SQL INSERT:\n";
echo "INSERT INTO users (first_name, last_name, email, password, role, email_verified) VALUES ";
echo "('Admin', 'User', 'admin@campusvoice.edu', '" . $hash . "', 'admin', TRUE);\n";

// Verify the hash
echo "\nVerification:\n";
echo "password_verify('password', '" . $hash . "'): " . (password_verify('password', $hash) ? 'TRUE' : 'FALSE') . "\n";
?>
