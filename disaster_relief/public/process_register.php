<?php
require_once "../config/database.php";
require_once "../includes/auth.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect_to("register.php");
}

$full_name = trim($_POST['full_name']);
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);
$role_id = $_POST['role_id'];
$password = $_POST['password'];

if ($full_name === "" || $email === "" || $password === "" || $role_id === "") {
    flash_error("Please fill all required fields.");
    redirect_to("register.php");
}

try {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, account_status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    $stmt->execute([$role_id, $full_name, $email, $phone, $password_hash]);

    flash_success("Registration submitted. Please wait for admin approval.");
    redirect_to("login.php");
} catch (PDOException $e) {
    flash_error("Registration failed. Email may already exist.");
    redirect_to("register.php");
}
?>