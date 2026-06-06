<?php
/**
 * Disaster Relief Camp & Volunteer Coordination System
 * Relief Status Inquiry Page (check_status.php)
 * 
 * This file processes the Request ID and Mobile Number submitted from index.php,
 * queries the database, and displays a beautiful status page with a visual
 * progress indicator bar, assigned camp details, and support information.
 */

// Include the secure PDO connection
require_once 'db_connect.php';

// Get and sanitize query parameters
$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mobile     = isset($_GET['mobile']) ? trim($_GET['mobile']) : '';

$request_found = false;
$error_message = '';
$data = [];

// Validation check
if ($request_id <= 0 || empty($mobile)) {
    $error_message = "Please provide both a valid Request ID and Mobile Number.";
} else {
    try {
        // Prepare SQL query joining affected_families, help_requests, and relief_camps dynamically
        $sql = "SELECT af.family_id AS id, 
                       af.head_name AS full_name, 
                       af.phone AS mobile, 
                       af.total_members AS member_count, 
                       af.address, 
                       hr.need_type AS help_needed, 
                       hr.details AS description, 
                       hr.request_status,
                       af.camp_id,
                       c.camp_name AS assigned_camp, 
                       hr.created_at
                FROM affected_families af
                LEFT JOIN help_requests hr ON af.family_id = hr.family_id
                LEFT JOIN relief_camps c ON af.camp_id = c.camp_id
                WHERE af.family_id = :id AND af.phone = :mobile 
                ORDER BY hr.request_id DESC
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id'     => $request_id,
            ':mobile' => $mobile
        ]);
        
        $data = $stmt->fetch();
        
        if ($data) {
            $request_found = true;
        } else {
            $error_message = "No matching request found for ID <strong>" . htmlspecialchars($request_id) . "</strong> and Mobile <strong>" . htmlspecialchars($mobile) . "</strong>.";
        }
    } catch (PDOException $e) {
        $error_message = "A database error occurred: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Relief Status | Disaster Relief Camp & Volunteer Coordination System</title>
    
    <!-- Link to the main external stylesheet -->
    <link rel="stylesheet" href="style.css?v=1.3">
    
    <!-- Minor adjustments specific to status page styling -->
    <style>
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 25px;
            transition: var(--transition);
        }
        .back-link:hover {
            color: var(--primary-hover);
            transform: translateX(-5px);
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 15px;
        }
        .meta-table th, .meta-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .meta-table th {
            font-weight: 600;
            color: var(--text-muted);
            width: 35%;
        }
        .meta-table td {
            color: var(--text-dark);
            font-weight: 500;
        }
    </style>
</head>
<body>

    <!-- ==========================================
       1. WEBSITE HEADER & NAVIGATION BAR
       ========================================== -->
    <header class="header">
        <div class="container navbar">
            <a href="index.html" class="logo">
                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <line x1="12" y1="8" x2="12" y2="16"/>
                    <line x1="8" y1="12" x2="16" y2="12"/>
                </svg>
                <div class="logo-text">
                    DISASTER RELIEF
                    <span>Camp & Volunteer Coordination System</span>
                </div>
            </a>
            
            <nav>
                <ul class="nav-menu">
                    <!-- Placeholder link: Will be connected to the external home page by team member -->
                    <li><a href="#" class="nav-link">Home</a></li>
                    <li><a href="index.html#request-form" class="nav-link">Request Help</a></li>
                    <li><a href="index.html#status-check" class="nav-link active">Check Status</a></li>
                    <li><a href="index.html#camps" class="nav-link">Assigned Camps</a></li>
                    <li><a href="index.html#chat" class="nav-link">Support Desk</a></li>
                </ul>
            </nav>

            <!-- Dedicated Emergency Action Button on Right -->
            <div class="nav-actions">
                <a href="index.html#emergency" class="btn-emergency">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    <span>Emergency Hotline: 1090</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="py-80" style="background-color: var(--bg-light); min-height: calc(100vh - 170px);">
        <div class="container">
            
            <!-- Back to Home navigation -->
            <a href="index.html#status-check" class="back-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
                Back to Tracking Form
            </a>

            <!-- Status results box -->
            <?php if ($request_found): ?>
                
                <?php
                // Map database status string to display parameters
                $request_status = trim($data['request_status'] ?? 'Submitted');
                $camp_id = $data['camp_id'] ?? null;
                
                $status = 'Pending';
                $step_class_fill = 'fill-pending';
                $node_pending = 'active';
                $node_progress = '';
                $node_assigned = '';
                $node_resolved = '';

                // Map status values to appropriate classes for visual highlights
                if ($request_status === 'Resolved') {
                    $status = 'Resolved';
                    $step_class_fill = 'fill-resolved';
                    $node_pending = 'completed';
                    $node_progress = 'completed';
                    $node_assigned = 'completed';
                    $node_resolved = 'completed';
                } elseif (!empty($camp_id)) {
                    $status = 'Assigned';
                    $step_class_fill = 'fill-assigned';
                    $node_pending = 'completed';
                    $node_progress = 'completed';
                    $node_assigned = 'active';
                } elseif ($request_status === 'In Progress' || $request_status === 'Approved') {
                    $status = 'In Progress';
                    $step_class_fill = 'fill-progress';
                    $node_pending = 'completed';
                    $node_progress = 'active';
                }
                
                // Set text styling for status label
                $status_color_class = 'accent-border';
                if ($status === 'Resolved') {
                    $status_color_class = 'success-border';
                }

                // Extract disaster type and original description from details
                $disaster_type = 'N/A';
                $description_text = $data['description'] ?? '';
                if (preg_match('/^Disaster Type:\s*(.*?)\n(.*)$/s', $description_text, $matches)) {
                    $disaster_type = trim($matches[1]);
                    $description_text = trim($matches[2]);
                }
                ?>

                <div class="status-result-card">
                    <div class="status-result-header">
                        <div>
                            <h2 style="font-size: 1.6rem;"><?php echo htmlspecialchars($data['full_name']); ?></h2>
                            <p style="color:var(--text-muted); font-size:0.9rem;">Mobile: <?php echo htmlspecialchars($data['mobile']); ?></p>
                        </div>
                        <span class="request-id-badge">ID: #<?php echo htmlspecialchars($data['id']); ?></span>
                    </div>

                    <!-- Visual Progress Tracker -->
                    <div class="progress-bar-container">
                        <h3 style="font-size: 1.1rem; text-align: center; margin-bottom: 20px; color: var(--text-dark);">
                            Current Progress: <span style="color:var(--primary-color);"><?php echo htmlspecialchars($status); ?></span>
                        </h3>
                        
                        <div class="progress-steps">
                            <!-- Horizontal Fill Bar -->
                            <div class="progress-bar-fill <?php echo $step_class_fill; ?>"></div>
                            
                            <!-- Step 1: Pending -->
                            <div class="step-node <?php echo $node_pending; ?>" title="Pending Approval">1</div>
                            <!-- Step 2: In Progress -->
                            <div class="step-node <?php echo $node_progress; ?>" title="Under Review">2</div>
                            <!-- Step 3: Assigned -->
                            <div class="step-node <?php echo $node_assigned; ?>" title="Assigned to Camp">3</div>
                            <!-- Step 4: Resolved -->
                            <div class="step-node <?php echo $node_resolved; ?>" title="Relief Delivered">4</div>
                        </div>
                        
                        <div class="step-labels">
                            <span class="<?php echo $node_pending; ?>">Pending</span>
                            <span class="<?php echo $node_progress; ?>">In Progress</span>
                            <span class="<?php echo $node_assigned; ?>">Camp Assigned</span>
                            <span class="<?php echo $node_resolved; ?>">Resolved</span>
                        </div>
                    </div>

                    <!-- Detailed Request Information Grid -->
                    <div class="status-details-grid" style="margin-top: 40px;">
                        
                        <!-- Status Box -->
                        <div class="detail-item <?php echo $status_color_class; ?>">
                            <h4>Request Status</h4>
                            <p><?php echo htmlspecialchars($status); ?></p>
                        </div>

                        <!-- Last Updated -->
                        <div class="detail-item">
                            <h4>Last Update Date</h4>
                            <p><?php echo date("F d, Y - h:i A", strtotime($data['created_at'])); ?></p>
                        </div>

                        <!-- Assigned Camp -->
                        <div class="detail-item full-width" style="grid-column: span 2; border-left-color: var(--primary-color);">
                            <h4>Assigned Camp</h4>
                            <p style="font-size: 1.1rem; color: var(--primary-color);">
                                <?php echo !empty($data['assigned_camp']) ? htmlspecialchars($data['assigned_camp']) : 'Not yet assigned (Under evaluation)'; ?>
                            </p>
                        </div>

                        <!-- Support Information -->
                        <div class="detail-item full-width" style="grid-column: span 2; background-color: var(--primary-light); border-left-color: var(--primary-color);">
                            <h4>Support Information / Action Taken</h4>
                            <p style="font-weight: 400; font-size: 0.95rem; line-height: 1.5; margin-top: 5px;">
                                <?php 
                                if ($status === 'Resolved') {
                                    echo "Relief has been successfully distributed to your family. Thank you.";
                                } elseif ($status === 'Assigned') {
                                    echo "Your family has been assigned to " . htmlspecialchars($data['assigned_camp']) . ". Please report to the shelter camp address.";
                                } elseif ($status === 'In Progress') {
                                    echo "Your request is currently under review by our coordinating team. Volunteers are evaluating local camp capacity.";
                                } else {
                                    echo "Request submitted successfully. Waiting for a relief team volunteer to assign a coordinator.";
                                }
                                ?>
                            </p>
                        </div>

                    </div>

                    <!-- Meta Information Table (For user review) -->
                    <h3 style="font-size: 1.2rem; margin-top: 35px; border-bottom: 1.5px solid var(--border-color); padding-bottom: 8px;">Help Request Summary</h3>
                    <table class="meta-table">
                        <tr>
                            <th>Family Member Count</th>
                            <td><?php echo htmlspecialchars($data['member_count']); ?> Person(s)</td>
                        </tr>
                        <tr>
                            <th>Disaster Type</th>
                            <td><?php echo htmlspecialchars($disaster_type); ?></td>
                        </tr>
                        <tr>
                            <th>Primary Help Needed</th>
                            <td><?php echo htmlspecialchars($data['help_needed']); ?></td>
                        </tr>
                        <tr>
                            <th>Address / Location</th>
                            <td><?php echo htmlspecialchars($data['address']); ?></td>
                        </tr>
                        <tr>
                            <th>Details Provided</th>
                            <td style="font-weight: normal; font-size: 0.9rem; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($description_text)); ?></td>
                        </tr>
                        <tr>
                            <th>Request Date</th>
                            <td><?php echo date("F d, Y", strtotime($data['created_at'])); ?></td>
                        </tr>
                    </table>

                </div>

            <?php else: ?>
                
                <!-- Query Failed / Record Not Found Card -->
                <div class="status-result-card" style="border-top-color: var(--primary-color); text-align: center; padding: 50px 30px;">
                    <div style="width: 70px; height: 70px; background-color: var(--primary-light); color: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    </div>
                    <h2 style="font-size: 1.6rem; margin-bottom: 10px; color: var(--text-dark);">Record Search Failed</h2>
                    <p style="color: var(--text-muted); max-width: 480px; margin: 0 auto 30px; line-height: 1.5;">
                        <?php echo $error_message; ?>
                    </p>
                    <a href="index.html#status-check" class="btn btn-submit" style="display:inline-flex; width:auto; padding: 12px 30px;">
                        Try Search Again
                    </a>
                </div>

            <?php endif; ?>

        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container" style="text-align: center; font-size: 0.85rem;">
            <p>&copy; 2026 Disaster Relief Camp & Volunteer Coordination System. All rights reserved.</p>
        </div>
    </footer>

    <!-- Script for active navigation states on check_status.php -->
    <script>
        const navMenuLinks = document.querySelectorAll('.nav-link');
        navMenuLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                // If it is the placeholder Home link, prevent navigation but set active styling
                if (link.getAttribute('href') === '#') {
                    e.preventDefault();
                    navMenuLinks.forEach(l => l.classList.remove('active'));
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
