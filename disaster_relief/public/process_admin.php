<?php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/functions.php";
require_role(['Admin']);

$action = $_POST['action'] ?? '';

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

    if ($action === 'add_camp_manager') {
        $role_id = get_role_id($pdo, 'Camp Manager');
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, account_status) VALUES (?, ?, ?, ?, ?, 'Approved')");
        $stmt->execute([$role_id, $_POST['full_name'], $_POST['email'], $_POST['phone'], $password_hash]);
        flash_success("Camp manager added successfully.");
    }

    if ($action === 'send_announcement') {
        $stmt = $pdo->prepare("INSERT INTO announcements (title, message, audience, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['title'], $_POST['message'], $_POST['audience'], $_SESSION['user_id']]);
        flash_success("Announcement sent successfully.");
    }

    if ($action === 'add_category') {
        $stmt = $pdo->prepare("INSERT INTO disaster_categories (category_name, description) VALUES (?, ?)");
        $stmt->execute([$_POST['category_name'], $_POST['description']]);
        flash_success("Disaster category added successfully.");
    }

    if ($action === 'add_location') {
        $stmt = $pdo->prepare("INSERT INTO camp_locations (location_name, district, address) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['location_name'], $_POST['district'], $_POST['address']]);
        flash_success("Camp location added successfully.");
    }


    if ($action === 'add_camp') {
        $category_id = $_POST['category_id'] !== '' ? $_POST['category_id'] : null;
        $location_id = $_POST['location_id'] !== '' ? $_POST['location_id'] : null;
        $manager_id = $_POST['manager_id'] !== '' ? $_POST['manager_id'] : null;

        $stmt = $pdo->prepare("
            INSERT INTO relief_camps
            (camp_name, category_id, location_id, manager_id, capacity, current_population, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['camp_name'],
            $category_id,
            $location_id,
            $manager_id,
            $_POST['capacity'] ?? 0,
            $_POST['current_population'] ?? 0,
            $_POST['status'] ?? 'Active'
        ]);

        flash_success("Relief camp added successfully. It will appear publicly if status is Active or Standby.");
    }

} catch (PDOException $e) {
    flash_error("Action failed: " . $e->getMessage());
}

redirect_to("admin.php");
?>