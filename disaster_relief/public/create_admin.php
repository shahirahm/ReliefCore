<?php

require_once "../config/database.php";
require_once "../includes/functions.php";

$role_id = get_role_id($pdo, 'Admin');
$email = "admin@relief.com";
$password_hash = password_hash("admin123", PASSWORD_DEFAULT);

$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email=?");
$stmt->execute([$email]);

if ($stmt->fetch()) {
    echo "Admin already exists. Email: admin@relief.com Password: admin123";
    exit;
}

$stmt = $pdo->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, account_status) VALUES (?, 'System Admin', ?, '0000000000', ?, 'Approved')");
$stmt->execute([$role_id, $email, $password_hash]);

echo "Admin created successfully. Email: admin@relief.com Password: admin123";
?>