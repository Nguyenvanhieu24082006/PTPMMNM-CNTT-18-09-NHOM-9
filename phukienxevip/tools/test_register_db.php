<?php
require_once __DIR__ . '/../src/Database.php';

$db = db();
$testUser = 'test_reg_' . time();
$email = $testUser . '@gmail.com';
$pass = 'password123';
$hash = password_hash($pass, PASSWORD_DEFAULT);

// Insert test
queryParams(
    $db,
    "INSERT INTO users (username, password_hash, full_name, email, phone, address, role, created_at)
     VALUES (?, ?, 'Nguyen Van Test', ?, '0988776655', 'Ha Noi', 'customer', NOW())",
    [$testUser, $hash, $email]
);

// Query back
$stmt = queryParams($db, "SELECT * FROM users WHERE username = ?", [$testUser]);
$u = fetchOne($stmt);

echo "REGISTER TEST:\n";
echo "ID: " . $u['id'] . "\n";
echo "Username: " . $u['username'] . "\n";
echo "Email: " . $u['email'] . "\n";
echo "Hash: " . substr($u['password_hash'], 0, 20) . "...\n";
echo "Password Verify: " . (password_verify($pass, $u['password_hash']) ? 'TRUE (MATCH)' : 'FALSE') . "\n";

// Cleanup test user
queryParams($db, "DELETE FROM users WHERE username = ?", [$testUser]);
echo "Cleanup completed successfully.\n";

