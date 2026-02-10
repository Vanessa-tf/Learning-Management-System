<?php
include(__DIR__ . "/includes/db.php");

$case_id = $_GET['id'] ?? 0;

// Get content developer case details
$stmt = $pdo->prepare("
    SELECT c.*, cd.first_name, cd.last_name, cd.email, cd.phone
    FROM support_cases c 
    JOIN content_developers cd ON c.logged_by_role = 'content_developer'
    WHERE c.id = :id AND c.logged_by_role = 'content_developer'
    LIMIT 1
");
$stmt->execute([':id' => $case_id]);
$case = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$case) {
    header("Location: admin_support_cases.php");
    exit;
}

// Get case responses
$stmt = $pdo->prepare("
    SELECT cr.*, 
           CASE 
               WHEN cr.user_type = 'Admin' THEN 'NovaTech Support'
               WHEN cr.user_type = 'Content Developer' THEN CONCAT(cd.first_name, ' ', cd.last_name)
               ELSE cr.user_type
           END as user_display_name
    FROM case_responses cr
    LEFT JOIN content_developers cd ON cr.user_type = 'Content Developer' AND cr.user_id = cd.id
    WHERE cr.case_id = :case_id
    ORDER BY cr.created_at ASC
");
$stmt->execute([':case_id' => $case_id]);
$responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['message'])) {
        // Handle new message
        $message = trim($_POST['message']);
        
        if (!empty($message)) {
            $stmt = $pdo->prepare("
                INSERT INTO case_responses (case_id, user_type, user_id, message) 
                VALUES (:case_id, 'Admin', :admin_id, :message)
            ");
            
            $stmt->execute([
                ':case_id' => $case_id,
                ':admin_id' => 1, // Default admin ID
                ':message' => $message
            ]);
            
            // Update case status to In Progress when admin responds
            $stmt = $pdo->prepare("UPDATE support_cases SET status = 'In Progress', updated_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => $case_id]);
        }
    }
    elseif (isset($_POST['status'])) {
        // Handle status change
        $new_status = $_POST['status'];
        $admin_notes = $_POST['admin_notes'] ?? '';
        
        $stmt = $pdo->prepare("
            UPDATE support_cases 
            SET status = :status, admin_notes = :admin_notes, updated_at = NOW() 
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':status' => $new_status,
            ':admin_notes' => $admin_notes,
            ':id' => $case_id
        ]);
        
        // Add system message about status change
        $status_messages = [
            'In Progress' => 'Case is now being handled by support team',
            'Resolved' => 'Case has been resolved by admin',
            'Closed' => 'Case has been closed'
        ];
        
        if (isset($status_messages[$new_status])) {
            $stmt = $pdo->prepare("
                INSERT INTO case_responses (case_id, user_type, user_id, message) 
                VALUES (:case_id, 'Admin', :admin_id, :message)
            ");
            
            $stmt->execute([
                ':case_id' => $case_id,
                ':admin_id' => 1, // Default admin ID
                ':message' => "Status updated to {$new_status}: {$status_messages[$new_status]}"
            ]);
        }
    }
    
    header("Location: admin_view_content_case.php?id=" . $case_id);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Developer Case <?= $case['case_number'] ?> - Admin - NovaTech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .content-developer-message { background-color: #e8f5e8; border-left: 4px solid #4caf50; }
        .admin-message { background-color: #f3e5f5; border-left: 4px solid #9c27b0; }
        .system-message { background-color: #e3f2fd; border-left: 4px solid #2196f3; }
        .message-container { max-height: 400px; overflow-y: auto; }
        .case-info { background-color: #f8f9fa; border-radius: 5px; padding: 15px; }
        .card-header h5 { margin-bottom: 0; }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row">
            <div class="col-md-10 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1>Content Developer Case: <?= $case['case_number'] ?></h1>
                        <p class="text-muted">Logged by Content Developer</p>
                    </div>
                    <a href="admin_support_cases.php" class="btn btn-secondary">← Back to Cases</a>
                </div>

                <!-- Case Information -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Case Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="case-info mb-3">
                                    <h6>Content Developer Information</h6>
                                    <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($case['first_name'] . ' ' . $case['last_name']) ?></p>
                                    <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($case['email']) ?></p>
                                    <?php if (!empty($case['phone'])): ?>
                                        <p class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($case['phone']) ?></p>
                                    <?php endif; ?>
                                    <p class="mb-0"><strong>Role:</strong> Content Developer</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="case-info mb-3">
                                    <h6>Case Details</h6>
                                    <p class="mb-1"><strong>Created:</strong> <?= date('F j, Y g:i A', strtotime($case['created_at'])) ?></p>
                                    <p class="mb-1"><strong>Last Updated:</strong> <?= date('F j, Y g:i A', strtotime($case['updated_at'])) ?></p>
                                    <p class="mb-0"><strong>Logged by:</strong> Content Developer</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <p><strong>Category:</strong> <?= $case['category'] ?></p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Priority:</strong> 
                                    <span class="badge bg-<?= $case['priority'] === 'Urgent' ? 'danger' : ($case['priority'] === 'High' ? 'warning' : 'info') ?>">
                                        <?= $case['priority'] ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?= 
                                        $case['status'] === 'Open' ? 'warning' : 
                                        ($case['status'] === 'In Progress' ? 'info' : 
                                        ($case['status'] === 'Resolved' ? 'success' : 'secondary')) 
                                    ?>">
                                        <?= $case['status'] ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <hr>
                        <p><strong>Subject:</strong> <?= htmlspecialchars($case['subject']) ?></p>
                        <p><strong>Description:</strong></p>
                        <div class="bg-light p-3 rounded">
                            <?= nl2br(htmlspecialchars($case['description'])) ?>
                        </div>
                        
                        <?php if (!empty($case['admin_notes'])): ?>
                            <div class="mt-3">
                                <p><strong>Admin Notes:</strong></p>
                                <div class="bg-warning bg-opacity-25 p-3 rounded">
                                    <?= nl2br(htmlspecialchars($case['admin_notes'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status Management -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Case Management</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label"><strong>Update Status</strong></label>
                                <select class="form-select" name="status" required>
                                    <option value="Open" <?= $case['status'] === 'Open' ? 'selected' : '' ?>>Open</option>
                                    <option value="In Progress" <?= $case['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="Resolved" <?= $case['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                    <option value="Closed" <?= $case['status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Admin Notes (Internal)</label>
                                <textarea class="form-control" name="admin_notes" rows="2" 
                                          placeholder="Add internal notes about this case..."><?= htmlspecialchars($case['admin_notes'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Update Status</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Messages -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Case Discussion</h5>
                    </div>
                    <div class="card-body">
                        <div class="message-container mb-3">
                            <?php if (empty($responses)): ?>
                                <p class="text-muted">No messages yet.</p>
                            <?php else: ?>
                                <?php foreach ($responses as $response): ?>
                                    <div class="p-3 mb-2 rounded <?= $response['user_type'] === 'Content Developer' ? 'content-developer-message' : ($response['user_type'] === 'Admin' ? 'admin-message' : 'system-message') ?>">
                                        <div class="d-flex justify-content-between">
                                            <strong>
                                                <?= $response['user_display_name'] ?>
                                                <?php if ($response['user_type'] === 'Content Developer'): ?>
                                                    (Content Developer)
                                                <?php endif; ?>
                                            </strong>
                                            <small><?= date('M j, g:i A', strtotime($response['created_at'])) ?></small>
                                        </div>
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($response['message'])) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Admin Response Form -->
                        <?php if ($case['status'] !== 'Closed'): ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label"><strong>Admin Response</strong></label>
                                    <textarea class="form-control" name="message" rows="3" 
                                              placeholder="Type your response to the content developer..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-success">Send Response</button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info">This case is closed. No further responses can be added.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>