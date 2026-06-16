<?php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/functions.php";
require_role(['Admin']);

$action = $_POST['action'] ?? '';

function nullable_value($value) {
    return isset($value) && $value !== '' ? $value : null;
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

try {
    if ($action === 'approve_user') {
        $stmt = $pdo->prepare("UPDATE users SET account_status='Approved' WHERE user_id=?");
        $stmt->execute([$_POST['user_id']]);
        flash_success("User approved successfully.");
    }

    if ($action === 'reject_user') {
        $stmt = $pdo->prepare("UPDATE users SET account_status='Rejected' WHERE user_id=?");
        $stmt->execute([$_POST['user_id']]);
        flash_success("User rejected successfully.");
    }

    if ($action === 'update_user') {
        if (!empty($_POST['new_password'])) {
            $password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users
                SET role_id=?, full_name=?, email=?, phone=?, password_hash=?, account_status=?
                WHERE user_id=?
            ");
            $stmt->execute([
                $_POST['role_id'],
                $_POST['full_name'],
                $_POST['email'],
                $_POST['phone'],
                $password_hash,
                $_POST['account_status'],
                $_POST['user_id']
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users
                SET role_id=?, full_name=?, email=?, phone=?, account_status=?
                WHERE user_id=?
            ");
            $stmt->execute([
                $_POST['role_id'],
                $_POST['full_name'],
                $_POST['email'],
                $_POST['phone'],
                $_POST['account_status'],
                $_POST['user_id']
            ]);
        }

        flash_success("User updated successfully.");
    }

    if ($action === 'delete_user') {
        if ($_POST['user_id'] == $_SESSION['user_id']) {
            flash_error("You cannot delete the currently logged-in admin account.");
            redirect_to("admin.php");
        }

        $user_id = $_POST['user_id'];

        $pdo->prepare("UPDATE relief_camps SET manager_id = NULL WHERE manager_id = ?")->execute([$user_id]);
        $pdo->prepare("UPDATE volunteer_tasks SET volunteer_id = NULL WHERE volunteer_id = ?")->execute([$user_id]);
        $pdo->prepare("UPDATE volunteer_tasks SET assigned_by = NULL WHERE assigned_by = ?")->execute([$user_id]);
        $pdo->prepare("UPDATE donations SET donor_id = NULL WHERE donor_id = ?")->execute([$user_id]);
        $pdo->prepare("UPDATE help_requests SET affected_user_id = NULL WHERE affected_user_id = ?")->execute([$user_id]);
        $pdo->prepare("UPDATE announcements SET created_by = NULL WHERE created_by = ?")->execute([$user_id]);
        $pdo->prepare("UPDATE reports SET generated_by = NULL WHERE generated_by = ?")->execute([$user_id]);
        $pdo->prepare("DELETE FROM chat_messages WHERE sender_id = ? OR receiver_id = ?")->execute([$user_id, $user_id]);
        $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$user_id]);

        flash_success("User removed successfully.");
    }

    if ($action === 'add_camp_manager') {
        $role_id = get_role_id($pdo, 'Camp Manager');
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (role_id, full_name, email, phone, password_hash, account_status)
            VALUES (?, ?, ?, ?, ?, 'Approved')
        ");
        $stmt->execute([$role_id, $_POST['full_name'], $_POST['email'], $_POST['phone'], $password_hash]);

        flash_success("Camp manager added successfully.");
    }

    if ($action === 'add_camp') {
        $stmt = $pdo->prepare("
            INSERT INTO relief_camps
            (camp_name, category_id, location_id, manager_id, capacity, current_population, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['camp_name'],
            nullable_value($_POST['category_id']),
            nullable_value($_POST['location_id']),
            nullable_value($_POST['manager_id']),
            $_POST['capacity'] ?? 0,
            $_POST['current_population'] ?? 0,
            $_POST['status'] ?? 'Active'
        ]);

        flash_success("Relief camp added successfully.");
    }

    if ($action === 'update_camp') {
        $stmt = $pdo->prepare("
            UPDATE relief_camps
            SET camp_name=?, category_id=?, location_id=?, manager_id=?, capacity=?, current_population=?, status=?
            WHERE camp_id=?
        ");
        $stmt->execute([
            $_POST['camp_name'],
            nullable_value($_POST['category_id']),
            nullable_value($_POST['location_id']),
            nullable_value($_POST['manager_id']),
            $_POST['capacity'] ?? 0,
            $_POST['current_population'] ?? 0,
            $_POST['status'] ?? 'Active',
            $_POST['camp_id']
        ]);

        flash_success("Relief camp updated successfully.");
    }

    if ($action === 'delete_camp') {
        $camp_id = $_POST['camp_id'];

        $pdo->prepare("UPDATE donation_usage SET camp_id = NULL WHERE camp_id = ?")->execute([$camp_id]);
        $pdo->prepare("DELETE FROM aid_distribution WHERE camp_id = ?")->execute([$camp_id]);
        $pdo->prepare("DELETE fm FROM family_members fm JOIN affected_families af ON fm.family_id = af.family_id WHERE af.camp_id = ?")->execute([$camp_id]);
        $pdo->prepare("DELETE FROM affected_families WHERE camp_id = ?")->execute([$camp_id]);
        $pdo->prepare("DELETE FROM camp_stock WHERE camp_id = ?")->execute([$camp_id]);
        $pdo->prepare("DELETE FROM stock_alerts WHERE camp_id = ?")->execute([$camp_id]);
        $pdo->prepare("DELETE FROM volunteer_tasks WHERE camp_id = ?")->execute([$camp_id]);
        $pdo->prepare("UPDATE reports SET camp_id = NULL WHERE camp_id = ?")->execute([$camp_id]);
        $pdo->prepare("DELETE FROM relief_camps WHERE camp_id = ?")->execute([$camp_id]);

        flash_success("Relief camp removed successfully.");
    }

    if ($action === 'add_category') {
        $stmt = $pdo->prepare("INSERT INTO disaster_categories (category_name, description) VALUES (?, ?)");
        $stmt->execute([$_POST['category_name'], $_POST['description']]);
        flash_success("Disaster category added successfully.");
    }

    if ($action === 'update_category') {
        $stmt = $pdo->prepare("UPDATE disaster_categories SET category_name=?, description=? WHERE category_id=?");
        $stmt->execute([$_POST['category_name'], $_POST['description'], $_POST['category_id']]);
        flash_success("Disaster category updated successfully.");
    }

    if ($action === 'delete_category') {
        $pdo->prepare("UPDATE relief_camps SET category_id = NULL WHERE category_id = ?")->execute([$_POST['category_id']]);
        $pdo->prepare("DELETE FROM disaster_categories WHERE category_id = ?")->execute([$_POST['category_id']]);
        flash_success("Disaster category removed successfully.");
    }

    if ($action === 'add_location') {
        $stmt = $pdo->prepare("INSERT INTO camp_locations (location_name, district, address) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['location_name'], $_POST['district'], $_POST['address']]);
        flash_success("Camp location added successfully.");
    }

    if ($action === 'update_location') {
        $stmt = $pdo->prepare("UPDATE camp_locations SET location_name=?, district=?, address=? WHERE location_id=?");
        $stmt->execute([$_POST['location_name'], $_POST['district'], $_POST['address'], $_POST['location_id']]);
        flash_success("Camp location updated successfully.");
    }

    if ($action === 'delete_location') {
        $pdo->prepare("UPDATE relief_camps SET location_id = NULL WHERE location_id = ?")->execute([$_POST['location_id']]);
        $pdo->prepare("DELETE FROM camp_locations WHERE location_id = ?")->execute([$_POST['location_id']]);
        flash_success("Camp location removed successfully.");
    }

    if ($action === 'update_stock_admin') {
        $quantity = (int)($_POST['quantity'] ?? 0);
        $minimum_required = (int)($_POST['minimum_required'] ?? 0);

        $stmt = $pdo->prepare("
            UPDATE camp_stock
            SET quantity=?, minimum_required=?
            WHERE stock_id=?
        ");
        $stmt->execute([$quantity, $minimum_required, $_POST['stock_id']]);

        sync_stock_alert($pdo, $_POST['camp_id'], $_POST['item_id'], $quantity, $minimum_required);

        flash_success("Stock record updated and alerts synced.");
    }

    if ($action === 'delete_stock_admin') {
        $pdo->prepare("DELETE FROM stock_alerts WHERE camp_id=? AND item_id=?")->execute([$_POST['camp_id'], $_POST['item_id']]);
        $pdo->prepare("DELETE FROM camp_stock WHERE stock_id=?")->execute([$_POST['stock_id']]);

        flash_success("Stock record removed successfully.");
    }


    if ($action === 'record_donation_usage') {
        $donation_id = $_POST['donation_id'];
        $camp_id = !empty($_POST['camp_id']) ? $_POST['camp_id'] : null;

        $stmt = $pdo->prepare("SELECT * FROM donations WHERE donation_id = ?");
        $stmt->execute([$donation_id]);
        $donation = $stmt->fetch();

        if (!$donation) {
            flash_error("Donation not found.");
            redirect_to("admin.php");
        }

        $item_id = $donation['donation_type'] === 'Supply' ? $donation['item_id'] : null;
        $quantity_used = null;

        if ($donation['donation_type'] === 'Supply') {
            $quantity_used = (int)($_POST['quantity_used'] ?? 0);
            if ($quantity_used <= 0) {
                flash_error("Quantity used is required for supply donation usage.");
                redirect_to("admin.php");
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO donation_usage (donation_id, camp_id, item_id, quantity_used, used_for)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$donation_id, $camp_id, $item_id, $quantity_used, $_POST['used_for']]);

        $stmt = $pdo->prepare("UPDATE donations SET donation_status='Distributed' WHERE donation_id=?");
        $stmt->execute([$donation_id]);

        flash_success("Donation usage recorded successfully.");
    }


    if ($action === 'update_affected_support') {
        $affected_user_id = $_POST['affected_user_id'];
        $family_id = !empty($_POST['family_id']) ? $_POST['family_id'] : null;
        $chat_allowed = isset($_POST['chat_allowed']) ? (int)$_POST['chat_allowed'] : 0;
        $support_note = $_POST['support_note'] ?? '';

        $stmt = $pdo->prepare("SELECT link_id FROM affected_user_links WHERE user_id = ?");
        $stmt->execute([$affected_user_id]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE affected_user_links
                SET family_id=?, chat_allowed=?, support_note=?
                WHERE user_id=?
            ");
            $stmt->execute([$family_id, $chat_allowed, $support_note, $affected_user_id]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO affected_user_links (user_id, family_id, chat_allowed, support_note)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$affected_user_id, $family_id, $chat_allowed, $support_note]);
        }

        flash_success("Affected person support access updated successfully.");
    }

    if ($action === 'update_help_request_status') {
        $stmt = $pdo->prepare("UPDATE help_requests SET request_status=? WHERE request_id=?");
        $stmt->execute([$_POST['request_status'], $_POST['request_id']]);

        flash_success("Help request status updated successfully.");
    }

    if ($action === 'send_announcement') {
        $stmt = $pdo->prepare("INSERT INTO announcements (title, message, audience, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['title'], $_POST['message'], $_POST['audience'], $_SESSION['user_id']]);
        flash_success("Announcement sent successfully.");
    }

} catch (PDOException $e) {
    flash_error("Action failed: " . $e->getMessage());
}

redirect_to("admin.php");
?>
