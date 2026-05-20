<?php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_login();

try {
    $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $_POST['receiver_id'], $_POST['message']]);
    flash_success("Message sent successfully.");
} catch (PDOException $e) {
    flash_error("Message failed: " . $e->getMessage());
}

redirect_to("chat.php");
?>