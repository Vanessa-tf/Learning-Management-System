<?php
// download_study_material.php - IMPROVED VERSION with better error handling
session_start();
include(__DIR__ . "/includes/db.php");
include(__DIR__ . "/includes/functions.php");

// Enable detailed error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/download_errors.log');
error_reporting(E_ALL);

// Debug mode - set to false in production
$DEBUG_MODE = true;

// Check if user is logged in
check_session();

// Get user role
$user_role = $_SESSION['role'] ?? 'guest';
$user_id = $_SESSION['user_id'] ?? 0;

// Get and validate material ID
$material_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Enhanced logging
error_log("Download attempt - Material ID: $material_id, User ID: $user_id, Role: $user_role");

// Validate material ID
if ($material_id <= 0) {
    $error_msg = "Invalid material ID received: " . ($_GET['id'] ?? 'none');
    error_log($error_msg);
    
    if ($DEBUG_MODE) {
        die("
        <html>
        <head><title>Download Error</title></head>
        <body style='font-family: Arial; padding: 40px;'>
            <h1 style='color: #dc2626;'>Download Error</h1>
            <div style='background: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0;'>
                <strong>Invalid Material ID:</strong> $material_id
            </div>
            <p><strong>Details:</strong></p>
            <ul>
                <li>Received ID parameter: " . htmlspecialchars($_GET['id'] ?? 'not provided') . "</li>
                <li>Converted to integer: $material_id</li>
                <li>URL: " . htmlspecialchars($_SERVER['REQUEST_URI']) . "</li>
            </ul>
            <p><strong>Possible causes:</strong></p>
            <ul>
                <li>The material was just created but database didn't assign an ID (AUTO_INCREMENT issue)</li>
                <li>The link is broken or corrupted</li>
                <li>Material was deleted</li>
            </ul>
            <p><a href='javascript:history.back()' style='color: #2563eb;'>&larr; Go Back</a></p>
        </body>
        </html>");
    }
    
    $_SESSION['error'] = "Invalid study material. Please try again.";
    header("Location: " . ($user_role === 'teacher' ? 'my-subjects.php' : 'my-courses.php'));
    exit;
}

try {
    // Fetch material with all details
    $stmt = $pdo->prepare("
        SELECT 
            sm.id,
            sm.title,
            sm.file_name,
            sm.file_path,
            sm.file_type,
            sm.file_size,
            sm.status,
            sm.target_audience,
            sm.course_id,
            sm.download_count,
            c.course_name
        FROM study_materials sm
        LEFT JOIN courses c ON sm.course_id = c.id
        WHERE sm.id = :material_id
    ");
    $stmt->execute(['material_id' => $material_id]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);

    // Debug material fetch
    if ($DEBUG_MODE && !$material) {
        // Check if material exists at all
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM study_materials WHERE id = :id");
        $checkStmt->execute(['id' => $material_id]);
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        die("
        <html>
        <head><title>Material Not Found</title></head>
        <body style='font-family: Arial; padding: 40px;'>
            <h1 style='color: #dc2626;'>Material Not Found</h1>
            <div style='background: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0;'>
                <strong>No material found with ID:</strong> $material_id
            </div>
            <p><strong>Debugging info:</strong></p>
            <ul>
                <li>Material ID: $material_id</li>
                <li>Exists in database: " . ($exists ? 'Yes' : 'No') . "</li>
                <li>Query executed successfully: Yes</li>
            </ul>
            <p><strong>Recent materials in database:</strong></p>
            <div style='background: #f3f4f6; padding: 10px; border-radius: 5px; overflow-x: auto;'>
                <pre>" . print_r($pdo->query("SELECT id, title, status, created_at FROM study_materials ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC), true) . "</pre>
            </div>
            <p><a href='javascript:history.back()' style='color: #2563eb;'>&larr; Go Back</a></p>
        </body>
        </html>");
    }

    if (!$material) {
        throw new Exception("Material not found in database");
    }

    // Check if published
    if ($material['status'] !== 'published') {
        error_log("Material $material_id is not published (status: {$material['status']})");
        $_SESSION['error'] = "This material is not available for download.";
        header("Location: " . ($user_role === 'teacher' ? 'my-subjects.php' : 'my-courses.php'));
        exit;
    }

    // Check permissions
    $can_download = false;
    $target_audience = $material['target_audience'] ?? 'students';
    
    if ($user_role === 'student' && in_array($target_audience, ['students', 'both'])) {
        $can_download = true;
    } elseif ($user_role === 'teacher' && in_array($target_audience, ['teachers', 'both'])) {
        $can_download = true;
    } elseif ($user_role === 'content') {
        $can_download = true;
    }
    
    if (!$can_download) {
        error_log("User $user_id (role: $user_role) denied access to material $material_id (audience: $target_audience)");
        $_SESSION['error'] = "You do not have permission to download this material.";
        header("Location: " . ($user_role === 'teacher' ? 'my-subjects.php' : 'my-courses.php'));
        exit;
    }

    // Normalize file path
    $stored_path = $material['file_path'];
    
    // Handle Windows paths
    if (preg_match('/[A-Z]:\\\\/', $stored_path)) {
        if (preg_match('/uploads[\/\\\\]study_materials[\/\\\\].+$/', $stored_path, $matches)) {
            $stored_path = str_replace('\\', '/', $matches[0]);
        }
    }
    
    // Build full file path
    $base_path = __DIR__;
    $full_file_path = $base_path . '/' . $stored_path;
    $full_file_path = str_replace('\\', '/', $full_file_path);
    $full_file_path = preg_replace('#/+#', '/', $full_file_path);

    error_log("File path resolution - Stored: $stored_path, Full: $full_file_path");

    // Check if file exists
    if (!file_exists($full_file_path)) {
        $error_details = [
            'material_id' => $material_id,
            'title' => $material['title'],
            'stored_path' => $material['file_path'],
            'normalized_path' => $stored_path,
            'full_path' => $full_file_path,
            'base_dir' => $base_path,
            'file_exists' => false,
            'directory_contents' => glob($base_path . '/uploads/study_materials/*')
        ];
        
        error_log("File not found: " . json_encode($error_details));
        
        if ($DEBUG_MODE) {
            die("
            <html>
            <head><title>File Not Found</title></head>
            <body style='font-family: Arial; padding: 40px;'>
                <h1 style='color: #dc2626;'>File Not Found on Server</h1>
                <div style='background: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0;'>
                    <strong>Material:</strong> " . htmlspecialchars($material['title']) . "
                </div>
                <p><strong>Path debugging:</strong></p>
                <ul>
                    <li><strong>Database path:</strong> " . htmlspecialchars($material['file_path']) . "</li>
                    <li><strong>Normalized path:</strong> " . htmlspecialchars($stored_path) . "</li>
                    <li><strong>Full system path:</strong> " . htmlspecialchars($full_file_path) . "</li>
                    <li><strong>Base directory:</strong> " . htmlspecialchars($base_path) . "</li>
                    <li><strong>File exists:</strong> No</li>
                </ul>
                <p><strong>Files in upload directory:</strong></p>
                <div style='background: #f3f4f6; padding: 10px; border-radius: 5px; max-height: 300px; overflow-y: auto;'>
                    <pre>" . print_r(glob($base_path . '/uploads/study_materials/*'), true) . "</pre>
                </div>
                <p><a href='javascript:history.back()' style='color: #2563eb;'>&larr; Go Back</a></p>
            </body>
            </html>");
        }
        
        $_SESSION['error'] = "File not found on server. Please contact support.";
        header("Location: " . ($user_role === 'teacher' ? 'my-subjects.php' : 'my-courses.php'));
        exit;
    }

    // Record download in study_material_downloads table
    try {
        $stmt = $pdo->prepare("
            INSERT INTO study_material_downloads (material_id, user_id, downloaded_at)
            VALUES (:material_id, :user_id, NOW())
        ");
        $stmt->execute([
            'material_id' => $material_id,
            'user_id' => $user_id
        ]);
        error_log("Download recorded: Material $material_id by User $user_id");
    } catch (PDOException $e) {
        error_log("Could not record download: " . $e->getMessage());
    }

    // Update download count
    try {
        $stmt = $pdo->prepare("
            UPDATE study_materials 
            SET download_count = COALESCE(download_count, 0) + 1 
            WHERE id = :material_id
        ");
        $stmt->execute(['material_id' => $material_id]);
        error_log("Download count updated for material $material_id");
    } catch (PDOException $e) {
        error_log("Could not update download count: " . $e->getMessage());
    }

    // Determine content type
    $fileExt = strtolower($material['file_type']);
    $contentTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt' => 'text/plain',
        'zip' => 'application/zip'
    ];

    $contentType = $contentTypes[$fileExt] ?? 'application/octet-stream';

    // Clear output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    error_log("Sending file: {$material['file_name']} | Size: " . filesize($full_file_path) . " bytes");

    // Send file headers
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . basename($material['file_name']) . '"');
    header('Content-Length: ' . filesize($full_file_path));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Expires: 0');

    // Output file
    readfile($full_file_path);
    exit;

} catch (PDOException $e) {
    $error_msg = "Database error: " . $e->getMessage();
    error_log("Database error in download_study_material.php: " . $error_msg);
    
    if ($DEBUG_MODE) {
        die("
        <html>
        <head><title>Database Error</title></head>
        <body style='font-family: Arial; padding: 40px;'>
            <h1 style='color: #dc2626;'>Database Error</h1>
            <div style='background: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0;'>
                <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "
            </div>
            <p><strong>Material ID:</strong> $material_id</p>
            <p><a href='javascript:history.back()' style='color: #2563eb;'>&larr; Go Back</a></p>
        </body>
        </html>");
    }
    
    $_SESSION['error'] = "An error occurred while downloading. Please try again.";
    header("Location: " . ($user_role === 'teacher' ? 'my-subjects.php' : 'my-courses.php'));
    exit;
} catch (Exception $e) {
    $error_msg = "Error: " . $e->getMessage();
    error_log("Error in download_study_material.php: " . $error_msg);
    
    if ($DEBUG_MODE) {
        die("
        <html>
        <head><title>Error</title></head>
        <body style='font-family: Arial; padding: 40px;'>
            <h1 style='color: #dc2626;'>Download Error</h1>
            <div style='background: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0;'>
                <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "
            </div>
            <p><a href='javascript:history.back()' style='color: #2563eb;'>&larr; Go Back</a></p>
        </body>
        </html>");
    }
    
    $_SESSION['error'] = "An error occurred. Please try again.";
    header("Location: " . ($user_role === 'teacher' ? 'my-subjects.php' : 'my-courses.php'));
    exit;
}
?>