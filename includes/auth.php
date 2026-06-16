<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function current_user_role() {
    return $_SESSION['role_name'] ?? null;
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php?error=Please login first");
        exit;
    }
}

function require_role($allowed_roles) {
    require_login();
    if (!in_array(current_user_role(), $allowed_roles)) {
        header("Location: index.php?error=Access denied");
        exit;
    }
}

function flash_success($message) {
    $_SESSION['success'] = $message;
}

function flash_error($message) {
    $_SESSION['error'] = $message;
}

function redirect_to($path) {
    header("Location: $path");
    exit;
}
?>