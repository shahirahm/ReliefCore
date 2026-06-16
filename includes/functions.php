<?php
function count_table($pdo, $table) {
    $allowed = [
        'users', 'relief_camps', 'affected_families', 'volunteer_tasks',
        'donations', 'help_requests', 'announcements', 'chat_messages',
        'stock_alerts', 'aid_distribution'
    ];

    if (!in_array($table, $allowed)) {
        return 0;
    }

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM $table");
    $row = $stmt->fetch();
    return $row['total'] ?? 0;
}

function get_role_id($pdo, $role_name) {
    $stmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = ?");
    $stmt->execute([$role_name]);
    $row = $stmt->fetch();
    return $row ? $row['role_id'] : null;
}

function get_user_role_name($pdo, $role_id) {
    $stmt = $pdo->prepare("SELECT role_name FROM roles WHERE role_id = ?");
    $stmt->execute([$role_id]);
    $row = $stmt->fetch();
    return $row ? $row['role_name'] : null;
}
?>