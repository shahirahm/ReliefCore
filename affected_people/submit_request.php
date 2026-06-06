<?php
/**
 * Disaster Relief Camp & Volunteer Coordination System
 * Help Request Form Handler (submit_request.php)
 * 
 * This file handles the POST submission from index.html, validates inputs,
 * inserts data into the affected_families MySQL table, and redirects back.
 */

// Start the session to retain values if needed (for tracking sessions)
session_start();

// Include database connection
require_once 'db_connect.php';

// Check if the form was actually submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Retrieve and sanitize input fields to prevent Cross-Site Scripting (XSS)
    // Support both 'head_name' and fallback 'full_name' for compatibility
    $head_name     = isset($_POST['head_name']) ? trim($_POST['head_name']) : (isset($_POST['full_name']) ? trim($_POST['full_name']) : '');
    $mobile        = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
    $member_count  = isset($_POST['member_count']) ? intval($_POST['member_count']) : 1;
    $address       = isset($_POST['address']) ? trim($_POST['address']) : '';
    $disaster_type = isset($_POST['disaster_type']) ? trim($_POST['disaster_type']) : '';
    $help_needed   = isset($_POST['help_needed']) ? trim($_POST['help_needed']) : '';
    $description   = isset($_POST['description']) ? trim($_POST['description']) : '';

    // 2. Perform Basic Server-side Validation
    $errors = [];

    if (empty($head_name)) {
        $errors[] = "Family Head Name is required.";
    }
    if (empty($mobile)) {
        $errors[] = "Mobile Number is required.";
    } elseif (!preg_match('/^[0-9+]{11,15}$/', $mobile)) {
        $errors[] = "Please enter a valid mobile number (11-15 digits).";
    }
    if ($member_count < 1) {
        $errors[] = "Family member count must be at least 1.";
    }
    if (empty($address)) {
        $errors[] = "Address is required.";
    }
    if (empty($disaster_type)) {
        $errors[] = "Please select a Disaster Type.";
    }
    if (empty($help_needed)) {
        $errors[] = "Please select the help needed.";
    }
    if (empty($description)) {
        $errors[] = "Description of your situation is required.";
    }

    // 3. If there are validation errors, redirect back to index.html with error parameters
    if (!empty($errors)) {
        $error_msg = implode(". ", $errors);
        header("Location: index.html?status=error&msg=" . urlencode($error_msg) . "#request-form");
        exit;
    }

    try {
        // Start transaction to insert into both affected_families and help_requests
        $pdo->beginTransaction();

        // 4. Insert into affected_families
        $sql_family = "INSERT INTO affected_families (
                            head_name, 
                            phone, 
                            address, 
                            total_members,
                            registration_date,
                            status
                        ) VALUES (
                            :head_name, 
                            :phone, 
                            :address, 
                            :total_members,
                            :registration_date,
                            'Registered'
                        )";
        $stmt_family = $pdo->prepare($sql_family);
        $stmt_family->execute([
            ':head_name'         => $head_name,
            ':phone'            => $mobile,
            ':address'           => $address,
            ':total_members'     => $member_count,
            ':registration_date' => date('Y-m-d')
        ]);

        $family_id = $pdo->lastInsertId();

        // Map help_needed to the allowed ENUM values for need_type: 'Food','Medicine','Shelter','Rescue','Other'
        $allowed_needs = ['Food', 'Medicine', 'Shelter', 'Rescue'];
        $need_type = in_array($help_needed, $allowed_needs) ? $help_needed : 'Other';

        // Format details to include the disaster type and original request details
        $details = "Disaster Type: " . $disaster_type . "\n" . $description;

        // 5. Insert into help_requests
        $sql_request = "INSERT INTO help_requests (
                            affected_user_id,
                            family_id,
                            need_type,
                            urgency,
                            details,
                            request_status
                        ) VALUES (
                            NULL,
                            :family_id,
                            :need_type,
                            'Normal',
                            :details,
                            'Submitted'
                        )";
        $stmt_request = $pdo->prepare($sql_request);
        $stmt_request->execute([
            ':family_id' => $family_id,
            ':need_type' => $need_type,
            ':details'   => $details
        ]);

        // Commit transaction
        $pdo->commit();

        // Redirect back to index.html with a success anchor and Request ID (family_id) in the URL
        header("Location: index.html?status=success&id=" . $family_id . "#request-form");
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $msg = "Database error: " . $e->getMessage();
        header("Location: index.html?status=error&msg=" . urlencode($msg) . "#request-form");
        exit;
    }

} else {
    // Redirect direct access attempts to index.html
    header("Location: index.html");
    exit;
}
?>
