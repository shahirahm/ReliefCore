<?php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/functions.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect_to("login.php");
}

$email = trim($_POST['email']);
$password = $_POST['password'];

$stmt = $pdo->prepare("
    SELECT u.*, r.role_name 
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    WHERE u.email = ?
");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    flash_error("Invalid email or password.");
    redirect_to("login.php");
}

if ($user['account_status'] !== 'Approved') {
    flash_error("Your account is not approved yet.");
    redirect_to("login.php");
}

$_SESSION['user_id'] = $user['user_id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role_name'] = $user['role_name'];

if ($user['role_name'] === 'Admin') redirect_to("admin.php");
if ($user['role_name'] === 'Camp Manager') redirect_to("camp-manager.php");
if ($user['role_name'] === 'Volunteer') redirect_to("volunteer.php");
if ($user['role_name'] === 'Donor') redirect_to("donor.php");
if ($user['role_name'] === 'Affected Person') redirect_to("affected-person.php");

redirect_to("index.php");
?>