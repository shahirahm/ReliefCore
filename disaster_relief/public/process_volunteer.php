<?php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_role(['Volunteer', 'Admin']);

$action = $_POST['action'] ?? '';

try {
    if ($action === 'update_task') {
        $stmt = $pdo->prepare("UPDATE volunteer_tasks SET task_status=? WHERE task_id=?");
        $stmt->execute([$_POST['task_status'], $_POST['task_id']]);
        flash_success("Task status updated successfully.");
    }

    if ($action === 'report_issue') {
        $stmt = $pdo->prepare("INSERT INTO help_requests (need_type, urgency, details) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['need_type'], $_POST['urgency'], $_POST['details']]);
        flash_success("Urgent field issue reported successfully.");
    }
} catch (PDOException $e) {
    flash_error("Action failed: " . $e->getMessage());
}

redirect_to("volunteer.php");
?>