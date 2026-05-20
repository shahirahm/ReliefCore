<?php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_login();

try {
    $stmt = $pdo->prepare("INSERT INTO help_requests (affected_user_id, need_type, urgency, details) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $_POST['need_type'], $_POST['urgency'], $_POST['details']]);
    flash_success("Help request submitted successfully.");
} catch (PDOException $e) {
    flash_error("Request failed: " . $e->getMessage());
}

redirect_to("affected-person.php");
?>