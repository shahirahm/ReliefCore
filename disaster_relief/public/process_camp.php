<?php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_role(['Camp Manager', 'Admin']);

$action = $_POST['action'] ?? '';

try {
    if ($action === 'register_family') {
        $stmt = $pdo->prepare("INSERT INTO affected_families (camp_id, head_name, phone, address, total_members, registration_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['camp_id'], $_POST['head_name'], $_POST['phone'], $_POST['address'], $_POST['total_members'], $_POST['registration_date']]);
        flash_success("Affected family registered successfully.");
    }

    if ($action === 'add_member') {
        $stmt = $pdo->prepare("INSERT INTO family_members (family_id, member_name, age, gender, relation_to_head) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['family_id'], $_POST['member_name'], $_POST['age'], $_POST['gender'], $_POST['relation_to_head']]);
        flash_success("Family member added successfully.");
    }

    if ($action === 'update_stock') {
        $stmt = $pdo->prepare("SELECT stock_id FROM camp_stock WHERE camp_id=? AND item_id=?");
        $stmt->execute([$_POST['camp_id'], $_POST['item_id']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("UPDATE camp_stock SET quantity=?, minimum_required=? WHERE stock_id=?");
            $stmt->execute([$_POST['quantity'], $_POST['minimum_required'], $existing['stock_id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO camp_stock (camp_id, item_id, quantity, minimum_required) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['camp_id'], $_POST['item_id'], $_POST['quantity'], $_POST['minimum_required']]);
        }

        if ($_POST['quantity'] < $_POST['minimum_required']) {
            $stmt = $pdo->prepare("INSERT INTO stock_alerts (camp_id, item_id, alert_message) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['camp_id'], $_POST['item_id'], 'Stock is below minimum required level']);
        }

        flash_success("Camp stock updated successfully.");
    }

    if ($action === 'assign_task') {
        $stmt = $pdo->prepare("INSERT INTO volunteer_tasks (camp_id, volunteer_id, assigned_by, task_title, task_description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['camp_id'], $_POST['volunteer_id'], $_SESSION['user_id'], $_POST['task_title'], $_POST['task_description']]);
        flash_success("Volunteer task assigned successfully.");
    }
} catch (PDOException $e) {
    flash_error("Action failed: " . $e->getMessage());
}

redirect_to("camp-manager.php");
?>