<?php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_role(['Camp Manager', 'Admin']);

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];
$is_admin = current_user_role() === 'Admin';

function manager_can_access_camp($pdo, $camp_id, $user_id, $is_admin) {
    if ($is_admin) return true;

    $stmt = $pdo->prepare("SELECT camp_id FROM relief_camps WHERE camp_id = ? AND manager_id = ?");
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
            $stmt->execute([$camp_id, $item_id, 'Stock is below minimum required level']);
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

function go_back_to_camp($camp_id) {
    redirect_to("camp-manager.php?camp_id=" . urlencode($camp_id));
}

try {
    $camp_id = $_POST['camp_id'] ?? null;

    if ($camp_id && !manager_can_access_camp($pdo, $camp_id, $user_id, $is_admin)) {
        flash_error("You are not allowed to manage this camp.");
        redirect_to("camp-manager.php");
    }

    if ($action === 'register_family') {
        $stmt = $pdo->prepare("
            INSERT INTO affected_families
            (camp_id, head_name, phone, address, total_members, registration_date)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $camp_id,
            $_POST['head_name'],
            $_POST['phone'],
            $_POST['address'],
            $_POST['total_members'] ?: 1,
            $_POST['registration_date'] ?: date('Y-m-d')
        ]);

        flash_success("Affected family registered successfully.");
        go_back_to_camp($camp_id);
    }

    if ($action === 'add_member') {
        $stmt = $pdo->prepare("
            SELECT af.family_id
            FROM affected_families af
            WHERE af.family_id = ?
            AND af.camp_id = ?
        ");
        $stmt->execute([$_POST['family_id'], $camp_id]);

        if (!$stmt->fetch()) {
            flash_error("Selected family does not belong to your assigned camp.");
            go_back_to_camp($camp_id);
        }

        $stmt = $pdo->prepare("
            INSERT INTO family_members
            (family_id, member_name, age, gender, relation_to_head, health_note)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['family_id'],
            $_POST['member_name'],
            $_POST['age'] ?: null,
            $_POST['gender'],
            $_POST['relation_to_head'],
            $_POST['health_note'] ?? ''
        ]);

        flash_success("Individual / family member added successfully.");
        go_back_to_camp($camp_id);
    }

    if ($action === 'update_stock') {
        $quantity = (int)($_POST['quantity'] ?? 0);
        $minimum_required = (int)($_POST['minimum_required'] ?? 0);

        $stmt = $pdo->prepare("SELECT stock_id FROM camp_stock WHERE camp_id=? AND item_id=?");
        $stmt->execute([$camp_id, $_POST['item_id']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("UPDATE camp_stock SET quantity=?, minimum_required=? WHERE stock_id=?");
            $stmt->execute([$quantity, $minimum_required, $existing['stock_id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO camp_stock (camp_id, item_id, quantity, minimum_required) VALUES (?, ?, ?, ?)");
            $stmt->execute([$camp_id, $_POST['item_id'], $quantity, $minimum_required]);
        }

        sync_stock_alert($pdo, $camp_id, $_POST['item_id'], $quantity, $minimum_required);

        flash_success("Food/medicine/shelter stock updated successfully.");
        go_back_to_camp($camp_id);
    }

    if ($action === 'assign_task') {
        $stmt = $pdo->prepare("
            INSERT INTO volunteer_tasks
            (camp_id, volunteer_id, assigned_by, task_title, task_description, due_date)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $camp_id,
            $_POST['volunteer_id'],
            $user_id,
            $_POST['task_title'],
            $_POST['task_description'],
            $_POST['due_date'] ?: null
        ]);

        flash_success("Volunteer task assigned successfully.");
        go_back_to_camp($camp_id);
    }

    if ($action === 'record_distribution') {
        $stmt = $pdo->prepare("
            SELECT family_id
            FROM affected_families
            WHERE family_id = ?
            AND camp_id = ?
        ");
        $stmt->execute([$_POST['family_id'], $camp_id]);

        if (!$stmt->fetch()) {
            flash_error("Selected family does not belong to this camp.");
            go_back_to_camp($camp_id);
        }

        $quantity = (int)$_POST['quantity'];

        $stmt = $pdo->prepare("
            INSERT INTO aid_distribution
            (camp_id, family_id, item_id, quantity, distributed_by, distribution_date, note)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $camp_id,
            $_POST['family_id'],
            $_POST['item_id'],
            $quantity,
            $user_id,
            $_POST['distribution_date'] ?: date('Y-m-d'),
            $_POST['note']
        ]);

        $stmt = $pdo->prepare("SELECT stock_id, quantity, minimum_required FROM camp_stock WHERE camp_id=? AND item_id=?");
        $stmt->execute([$camp_id, $_POST['item_id']]);
        $stock = $stmt->fetch();

        if ($stock) {
            $new_quantity = max(0, (int)$stock['quantity'] - $quantity);
            $stmt = $pdo->prepare("UPDATE camp_stock SET quantity=? WHERE stock_id=?");
            $stmt->execute([$new_quantity, $stock['stock_id']]);

            sync_stock_alert($pdo, $camp_id, $_POST['item_id'], $new_quantity, (int)$stock['minimum_required']);
        }

        flash_success("Aid distribution recorded successfully.");
        go_back_to_camp($camp_id);
    }

    flash_error("Invalid action.");
    redirect_to("camp-manager.php");

} catch (PDOException $e) {
    flash_error("Action failed: " . $e->getMessage());
    redirect_to("camp-manager.php");
}
?>
