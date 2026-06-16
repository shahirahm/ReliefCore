<?php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_role(['Volunteer', 'Admin']);

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];
$is_admin = current_user_role() === 'Admin';

/*
  Ensure volunteer delivery table exists for old DB imports.
*/
$pdo->exec("
    CREATE TABLE IF NOT EXISTS volunteer_deliveries (
        delivery_id INT AUTO_INCREMENT PRIMARY KEY,
        volunteer_id INT,
        camp_id INT,
        item_id INT,
        quantity INT NOT NULL,
        delivered_to VARCHAR(160),
        delivery_note TEXT,
        delivery_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (volunteer_id) REFERENCES users(user_id) ON DELETE SET NULL,
        FOREIGN KEY (camp_id) REFERENCES relief_camps(camp_id) ON DELETE SET NULL,
        FOREIGN KEY (item_id) REFERENCES supply_items(item_id) ON DELETE SET NULL
    )
");

function volunteer_can_access_task($pdo, $task_id, $user_id, $is_admin) {
    if ($is_admin) return true;

    $stmt = $pdo->prepare("SELECT task_id FROM volunteer_tasks WHERE task_id = ? AND volunteer_id = ?");
    $stmt->execute([$task_id, $user_id]);
    return (bool)$stmt->fetch();
}

function volunteer_can_access_camp($pdo, $camp_id, $user_id, $is_admin) {
    if ($is_admin) return true;

    $stmt = $pdo->prepare("
        SELECT task_id
        FROM volunteer_tasks
        WHERE camp_id = ?
        AND volunteer_id = ?
        LIMIT 1
    ");
    $stmt->execute([$camp_id, $user_id]);
    return (bool)$stmt->fetch();
}

function sync_stock_alert($pdo, $camp_id, $item_id, $quantity, $minimum_required) {
    if ($minimum_required > 0 && $quantity < $minimum_required) {
        $check = $pdo->prepare("
            SELECT alert_id
            FROM stock_alerts
            WHERE camp_id = ?
            AND item_id = ?
            AND alert_status = 'Open'
            LIMIT 1
        ");
        $check->execute([$camp_id, $item_id]);

        if (!$check->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO stock_alerts (camp_id, item_id, alert_message, alert_status)
                VALUES (?, ?, ?, 'Open')
            ");
            $stmt->execute([$camp_id, $item_id, 'Stock is below minimum required level after volunteer delivery']);
        }
    } else {
        $stmt = $pdo->prepare("
            UPDATE stock_alerts
            SET alert_status = 'Resolved'
            WHERE camp_id = ?
            AND item_id = ?
            AND alert_status = 'Open'
        ");
        $stmt->execute([$camp_id, $item_id]);
    }
}

try {
    if ($action === 'update_task_status') {
        $task_id = $_POST['task_id'];
        $new_status = $_POST['task_status'];

        if (!volunteer_can_access_task($pdo, $task_id, $user_id, $is_admin)) {
            flash_error("You are not allowed to update this task.");
            redirect_to("volunteer.php");
        }

        $stmt = $pdo->prepare("SELECT task_status FROM volunteer_tasks WHERE task_id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch();

        if (!$task) {
            flash_error("Task not found.");
            redirect_to("volunteer.php");
        }

        if ($task['task_status'] === 'Completed') {
            flash_error("This task is already completed and cannot be changed.");
            redirect_to("volunteer.php");
        }

        if (!in_array($new_status, ['Pending', 'In Progress', 'Completed'])) {
            flash_error("Invalid task status.");
            redirect_to("volunteer.php");
        }

        $stmt = $pdo->prepare("UPDATE volunteer_tasks SET task_status = ? WHERE task_id = ?");
        $stmt->execute([$new_status, $task_id]);

        flash_success("Task status updated successfully.");
        redirect_to("volunteer.php");
    }

    if ($action === 'record_delivery') {
        $stock_id = $_POST['stock_id'];
        $quantity = (int)$_POST['quantity'];

        if ($quantity <= 0) {
            flash_error("Delivery quantity must be greater than zero.");
            redirect_to("volunteer.php");
        }

        $stmt = $pdo->prepare("
            SELECT cs.*, si.item_name
            FROM camp_stock cs
            JOIN supply_items si ON cs.item_id = si.item_id
            WHERE cs.stock_id = ?
        ");
        $stmt->execute([$stock_id]);
        $stock = $stmt->fetch();

        if (!$stock) {
            flash_error("Selected stock item does not exist.");
            redirect_to("volunteer.php");
        }

        if (!volunteer_can_access_camp($pdo, $stock['camp_id'], $user_id, $is_admin)) {
            flash_error("You can only record deliveries for camps assigned to your tasks.");
            redirect_to("volunteer.php");
        }

        if ((int)$stock['quantity'] < $quantity) {
            flash_error("Delivery quantity exceeds available stock. Available: " . $stock['quantity']);
            redirect_to("volunteer.php");
        }

        $new_stock_quantity = (int)$stock['quantity'] - $quantity;

        $stmt = $pdo->prepare("
            INSERT INTO volunteer_deliveries
            (volunteer_id, camp_id, item_id, quantity, delivered_to, delivery_note, delivery_date)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $stock['camp_id'],
            $stock['item_id'],
            $quantity,
            $_POST['delivered_to'],
            $_POST['delivery_note'],
            $_POST['delivery_date'] ?: date('Y-m-d')
        ]);

        $stmt = $pdo->prepare("UPDATE camp_stock SET quantity = ? WHERE stock_id = ?");
        $stmt->execute([$new_stock_quantity, $stock_id]);

        sync_stock_alert($pdo, $stock['camp_id'], $stock['item_id'], $new_stock_quantity, (int)$stock['minimum_required']);

        flash_success("Delivered supplies recorded and stock updated successfully.");
        redirect_to("volunteer.php");
    }

    if ($action === 'report_field_issue') {
        $camp_id = $_POST['camp_id'] ?? null;

        if ($camp_id && !volunteer_can_access_camp($pdo, $camp_id, $user_id, $is_admin)) {
            flash_error("You can only report field issues for camps assigned to your tasks.");
            redirect_to("volunteer.php");
        }

        $details = "Volunteer ID: " . $user_id . "\\n";
        if ($camp_id) {
            $details .= "Camp ID: " . $camp_id . "\\n";
        }
        $details .= $_POST['details'];

        $stmt = $pdo->prepare("
            INSERT INTO help_requests
            (affected_user_id, family_id, need_type, urgency, details, request_status)
            VALUES (NULL, NULL, ?, ?, ?, 'Submitted')
        ");
        $stmt->execute([
            $_POST['need_type'],
            $_POST['urgency'],
            $details
        ]);

        flash_success("Field issue reported successfully.");
        redirect_to("volunteer.php");
    }

    flash_error("Invalid action.");
    redirect_to("volunteer.php");

} catch (PDOException $e) {
    flash_error("Action failed: " . $e->getMessage());
    redirect_to("volunteer.php");
}
?>
