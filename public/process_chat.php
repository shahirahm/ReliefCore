<?php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_login();

$user_id = $_SESSION['user_id'];
$role = current_user_role();

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

function can_chat_with($pdo, $sender_id, $sender_role, $receiver_id) {
    if ($sender_id == $receiver_id) return false;

    $stmt = $pdo->prepare("
        SELECT u.user_id, r.role_name
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE u.user_id = ?
        AND u.account_status='Approved'
    ");
    $stmt->execute([$receiver_id]);
    $receiver = $stmt->fetch();

    if (!$receiver) return false;

    if ($sender_role === 'Admin') {
        return true;
    }

    if ($sender_role === 'Camp Manager') {
        if ($receiver['role_name'] === 'Admin') return true;

        if ($receiver['role_name'] === 'Volunteer') {
            $stmt = $pdo->prepare("
                SELECT vt.task_id
                FROM volunteer_tasks vt
                JOIN relief_camps rc ON vt.camp_id = rc.camp_id
                WHERE rc.manager_id = ?
                AND vt.volunteer_id = ?
                LIMIT 1
            ");
            $stmt->execute([$sender_id, $receiver_id]);
            return (bool)$stmt->fetch();
        }

        return false;
    }

    if ($sender_role === 'Volunteer') {
        $stmt = $pdo->prepare("
            SELECT vt.task_id
            FROM volunteer_tasks vt
            JOIN relief_camps rc ON vt.camp_id = rc.camp_id
            WHERE vt.volunteer_id = ?
            AND rc.manager_id = ?
            LIMIT 1
        ");
        $stmt->execute([$sender_id, $receiver_id]);
        return (bool)$stmt->fetch();
    }

    if ($sender_role === 'Donor') {
        return $receiver['role_name'] === 'Admin';
    }

    if ($sender_role === 'Affected Person') {
        $stmt = $pdo->prepare("
            SELECT aul.chat_allowed, rc.manager_id
            FROM affected_user_links aul
            LEFT JOIN affected_families af ON aul.family_id = af.family_id
            LEFT JOIN relief_camps rc ON af.camp_id = rc.camp_id
            WHERE aul.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$sender_id]);
        $link = $stmt->fetch();

        if (!$link || (int)$link['chat_allowed'] !== 1) return false;
        if ($receiver['role_name'] === 'Admin') return true;
        if (!empty($link['manager_id']) && (int)$receiver_id === (int)$link['manager_id']) return true;

        return false;
    }

    return false;
}

try {
    $receiver_id = $_POST['receiver_id'] ?? null;
    $message = trim($_POST['message'] ?? '');

    if (!$receiver_id || $message === '') {
        flash_error("Receiver and message are required.");
        redirect_to("chat.php");
    }

    if (!can_chat_with($pdo, $user_id, $role, $receiver_id)) {
        flash_error("You are not allowed to chat with this user.");
        redirect_to("chat.php");
    }

    $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $receiver_id, $message]);

    flash_success("Message sent successfully.");
} catch (PDOException $e) {
    flash_error("Message failed: " . $e->getMessage());
}

redirect_to("chat.php");
?>
