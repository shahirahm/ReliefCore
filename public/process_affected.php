<?php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_login();

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? 'submit_help_request';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS affected_user_links (
        link_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        family_id INT,
        chat_allowed TINYINT(1) DEFAULT 0,
        support_note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (family_id) REFERENCES affected_families(family_id) ON DELETE SET NULL
    )
");

try {
    if ($action === 'submit_help_request') {
        $family_id = null;

        $stmt = $pdo->prepare("SELECT family_id FROM affected_user_links WHERE user_id=?");
        $stmt->execute([$user_id]);
        $link = $stmt->fetch();

        if ($link && !empty($link['family_id'])) {
            $family_id = $link['family_id'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO help_requests
            (affected_user_id, family_id, need_type, urgency, details, request_status)
            VALUES (?, ?, ?, ?, ?, 'Submitted')
        ");
        $stmt->execute([
            $user_id,
            $family_id,
            $_POST['need_type'],
            $_POST['urgency'],
            $_POST['details']
        ]);

        flash_success("Help request submitted successfully.");
        redirect_to("affected-person.php");
    }

    flash_error("Invalid action.");
    redirect_to("affected-person.php");

} catch (PDOException $e) {
    flash_error("Request failed: " . $e->getMessage());
    redirect_to("affected-person.php");
}
?>
