<?php
// session_start(); // Commented out for now
include(__DIR__ . "/includes/db.php");
include(__DIR__ . "/includes/functions.php");

// Enable error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Temporarily bypass login check
// check_session();
// if ($_SESSION['role'] !== 'admin') {
//     header("Location: login.php");
//     exit;
// }

// Hardcode admin_id for testing without login
$admin_id = 1; // Assuming admin ID 1 exists; adjust as needed

$success_message = '';
$error_message = '';
$admin_data = [];

try {
    // Fetch admin details from admins table (correct table based on your database dump)
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = :admin_id");
    $stmt->execute(['admin_id' => $admin_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no data found, hardcode some dummy data for testing
    if (!$admin_data) {
        $admin_data = [
            'first_name' => 'Admin',
            'surname' => 'User',
            'email' => 'admin@novatech.co.za',
            'phone' => '1234567890',
            'created_at' => '2025-01-01',
            'updated_at' => '2025-01-01'
        ];
        // Uncomment the next line if you want to redirect or handle no data
        // header("Location: login.php"); exit;
    }

    // Get initials for avatar
    $initials = strtoupper(substr($admin_data['first_name'], 0, 1) . substr($admin_data['surname'], 0, 1));

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_first_name = trim($_POST['first_name'] ?? '');
        $new_surname = trim($_POST['surname'] ?? '');
        $new_email = trim($_POST['email'] ?? '');
        $new_phone = trim($_POST['phone'] ?? '');
        $new_password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validate required fields
        if (empty($new_first_name) || empty($new_surname) || empty($new_email)) {
            $error_message = "First name, surname, and email are required fields.";
        } 
        // Validate password if provided
        elseif (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                $error_message = "New passwords do not match.";
            } elseif (!validatePassword($new_password)) {
                $error_message = "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character (!@#$%^&*).";
            }
        }

        // If no errors, proceed with update
        if (empty($error_message)) {
            try {
                // Start transaction for atomic update
                $pdo->beginTransaction();
                
                // Update admins table - basic profile info
                $update_sql = "UPDATE admins SET 
                    first_name = :first_name, 
                    surname = :surname, 
                    email = :email, 
                    phone = :phone,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id";
                
                $stmt = $pdo->prepare($update_sql);
                $stmt->execute([
                    'first_name' => $new_first_name,
                    'surname' => $new_surname,
                    'email' => $new_email,
                    'phone' => $new_phone,
                    'id' => $admin_id
                ]);

                // Update password if provided and validated
                if (!empty($new_password) && $new_password === $confirm_password && validatePassword($new_password)) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_password_sql = "UPDATE admins SET password = :password, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
                    $stmt = $pdo->prepare($update_password_sql);
                    $stmt->execute([
                        'password' => $hashed_password,
                        'id' => $admin_id
                    ]);
                }

                // Commit transaction
                $pdo->commit();

                // Refresh admin data
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = :admin_id");
                $stmt->execute(['admin_id' => $admin_id]);
                $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

                $success_message = "Profile updated successfully!";
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                $error_message = "Error updating profile: " . $e->getMessage();
                error_log("Profile update error: " . $e->getMessage());
            }
        }
    }

} catch (PDOException $e) {
    // Log the error for admin review
    error_log("Database error in admin_settings.php: " . $e->getMessage());
    $error_message = "Unable to load profile data. Please try again later. Error: " . $e->getMessage();
} catch (Exception $e) {
    // Catch any other unexpected errors
    error_log("Unexpected error in admin_settings.php: " . $e->getMessage());
    $error_message = "An unexpected error occurred. Please contact support. Error: " . $e->getMessage();
}

// Password validation function
function validatePassword($password) {
    // At least 8 characters
    if (strlen($password) < 8) {
        return false;
    }
    
    // At least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    
    // At least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    
    // At least one number
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    
    // At least one special character (!@#$%^&*)
    if (!preg_match('/[!@#$%^&*]/', $password)) {
        return false;
    }
    
    return true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - NovaTech FET College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        :root {
            --navy: #1e3a6c;
            --gold: #fbbf24;
            --beige: #f5f5dc;
        }
        body { font-family: 'Poppins', sans-serif; }
        .bg-navy { background-color: var(--navy); }
        .bg-gold { background-color: var(--gold); }
        .bg-beige { background-color: var(--beige); }
        .text-navy { color: var(--navy); }
        .text-gold { color: var(--gold); }
        .border-gold { border-color: var(--gold); }
        .dashboard-card { 
            transition: transform 0.3s ease, box-shadow 0.3s ease; 
            backdrop-filter: blur(10px);
        }
        .dashboard-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15); 
        }
        .sidebar { transition: all 0.3s ease; }
        .stat-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
        }
        .metric-trend { animation: pulse 2s infinite; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -300px; z-index: 1000; height: 100vh; }
            .sidebar.active { left: 0; }
            .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
            .overlay.active { display: block; }
        }
        .notification-dot {
            animation: bounce 1s infinite;
        }
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        .active-nav-item {
            background-color: var(--gold) !important;
            color: var(--navy) !important;
        }
        .form-input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.1);
        }
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 38px;
            background: none;
            border: none;
            cursor: pointer;
            color: #6b7280;
            z-index: 10;
        }
        .password-container {
            position: relative;
        }
        .password-input {
            padding-right: 40px !important;
        }
        .requirement.met {
            color: #10b981;
        }
        .requirement.unmet {
            color: #6b7280;
        }
    </style>
</head>
<body class="bg-beige">
    <!-- Overlay for mobile sidebar -->
    <div class="overlay" id="overlay"></div>
    
    <!-- Sidebar -->
    <div class="sidebar bg-navy text-white w-64 fixed h-screen overflow-y-auto" id="sidebar">
        <div class="p-6">
            <div class="flex items-center justify-between mb-10">
                <div class="flex items-center">
                    <img src="Images/ChatGPT Image Sep 15, 2025, 08_43_22 PM.png" alt="NovaTech Logo" class="h-10 w-auto"/>
                    <span class="ml-3 text-xl font-bold">NovaTech FET <span class="text-gold">College</span></span>
                </div>
                <button class="text-white md:hidden" id="closeSidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Admin Profile -->
            <div class="mb-8 p-4 bg-white bg-opacity-10 rounded-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gold rounded-full flex items-center justify-center mr-3">
                        <span class="text-navy font-bold"><?php echo $initials; ?></span>
                    </div>
                    <div>
                        <h3 class="font-semibold"><?php echo htmlspecialchars($admin_data['first_name'] . ' ' . $admin_data['surname']); ?></h3>
                        <p class="text-sm mt-1 text-gold">System Administrator</p>
                    </div>
                </div>
            </div>
            
            <!-- ADMIN SIDEBAR NAVIGATION -->
            <nav>
                <ul class="space-y-2">
                    <li><a href="admin_dashboard.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10" onclick="showSection('dashboard', this)"><i class="fas fa-tachometer-alt mr-3"></i> Dashboard</a></li>
                    <li><a href="user_management.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10" onclick="showSection('users', this)"><i class="fas fa-users mr-3"></i> User Management</a></li>
					<li><a href="master-timetable.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-calendar-alt mr-3"></i> Master Timetable</a></li>
                    <li><a href="courseContent_admin.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10" onclick="showSection('courses', this)"><i class="fas fa-book mr-3"></i> Course & Content</a></li>
                    <li><a href="package_management.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10" onclick="showSection('packages', this)"><i class="fas fa-box mr-3"></i> Package Management</a></li>
					<li><a href="admin_support_cases.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10" onclick="showSection('support', this)"><i class="fas fa-headset mr-3"></i> Support Cases</a></li>
					<li><a href="admin_communications.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10" onclick="showSection('communications', this)"><i class="fas fa-envelope mr-3"></i> NovaTechMail</a></li>
                    <li><a href="admin_analytics.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10" onclick="showSection('analytics', this)"><i class="fas fa-chart-line mr-3"></i> Analytics & Reports</a></li>
                    <li><a href="admin_announcements.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10" onclick="showSection('announcements', this)"><i class="fas fa-bullhorn mr-3"></i> Announcements</a></li>
                    <li><a href="admin_settings.php" class="flex items-center p-2 rounded-lg active-nav-item"><i class="fas fa-cog mr-3"></i> Settings</a></li>
                    <li><a href="logout.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-sign-out-alt mr-3"></i> Logout</a></li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="md:ml-64">
        <!-- Top Navigation -->
        <header class="bg-white shadow-md relative z-50">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <button class="text-navy md:hidden" id="menuButton">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="text-xl font-bold text-navy">Admin Settings</h1>
                    <div class="flex items-center space-x-4">
                       
                        <!-- Admin Profile -->
                        <div class="hidden md:block">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gold rounded-full flex items-center justify-center mr-2">
                                    <span class="text-navy font-bold text-sm"><?php echo $initials; ?></span>
                                </div>
                                <span class="text-navy"><?php echo htmlspecialchars($admin_data['first_name'] . ' ' . $admin_data['surname']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <main class="container mx-auto px-6 py-8">
            <?php if (isset($error_message) && !empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-8">
                <p><?php echo $error_message; ?></p>
            </div>
            <?php endif; ?>

            <?php if (isset($success_message) && !empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-8">
                <p><?php echo $success_message; ?></p>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column - Profile Information -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                        <h2 class="text-2xl font-bold text-navy mb-6">Update Profile</h2>
                        
                        <form method="POST" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                                    <input type="text" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($admin_data['first_name']); ?>" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gold form-input"
                                           required>
                                </div>
                                
                                <div>
                                    <label for="surname" class="block text-sm font-medium text-gray-700 mb-2">Surname</label>
                                    <input type="text" id="surname" name="surname" 
                                           value="<?php echo htmlspecialchars($admin_data['surname']); ?>" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gold form-input"
                                           required>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                    <input type="email" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($admin_data['email']); ?>" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gold form-input"
                                           required>
                                </div>
                                
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($admin_data['phone']); ?>" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gold form-input">
                                </div>
                            </div>

                            <div class="border-t pt-6">
                                <h3 class="text-lg font-semibold text-navy mb-4">Change Password</h3>
                                <p class="text-sm text-gray-600 mb-4">Leave blank to keep current password</p>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="password-container">
                                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                        <input type="password" id="password" name="password" 
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gold form-input password-input"
                                               placeholder="Enter new password">
                                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="password-container">
                                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                        <input type="password" id="confirm_password" name="confirm_password" 
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gold form-input password-input"
                                               placeholder="Confirm new password">
                                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm_password', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                                    <h4 class="text-sm font-medium text-gray-700 mb-2">Password Requirements:</h4>
                                    <ul class="text-xs space-y-1">
                                        <li id="length" class="requirement unmet">• At least 8 characters</li>
                                        <li id="lowercase" class="requirement unmet">• At least one lowercase letter</li>
                                        <li id="uppercase" class="requirement unmet">• At least one uppercase letter</li>
                                        <li id="number" class="requirement unmet">• At least one number</li>
                                        <li id="special" class="requirement unmet">• At least one special character (!@#$%^&*)</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="flex justify-end space-x-4 pt-6">
                                <button type="button" onclick="resetForm()" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                                    Reset
                                </button>
                                <button type="submit" class="px-6 py-2 bg-navy text-white rounded-lg hover:bg-opacity-90 transition">
                                    Save Profile Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Column - Account Information -->
                <div class="space-y-8">
                    <!-- Account Summary -->
                    <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                        <h3 class="text-lg font-semibold text-navy mb-4">Account Information</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Account Type:</span>
                                <span class="font-medium text-navy">Admin</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Member Since:</span>
                                <span class="font-medium text-navy">
                                    <?php echo date('M Y', strtotime($admin_data['created_at'] ?? 'now')); ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Last Updated:</span>
                                <span class="font-medium text-navy">
                                    <?php echo date('M j, Y', strtotime($admin_data['updated_at'] ?? 'now')); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Password Requirements -->
                    <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                        <h3 class="text-lg font-semibold text-navy mb-4">Security Information</h3>
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <i class="fas fa-shield-alt text-gold mr-3"></i>
                                <span class="text-gray-700">Password must meet all requirements</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-lock text-gold mr-3"></i>
                                <span class="text-gray-700">Passwords are securely hashed</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-history text-gold mr-3"></i>
                                <span class="text-gray-700">Last password change: <?php echo date('M j, Y', strtotime($admin_data['updated_at'] ?? 'now')); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile sidebar toggle
        const menuButton = document.getElementById('menuButton');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const closeSidebar = document.getElementById('closeSidebar');

        if (menuButton && sidebar && overlay) {
            menuButton.addEventListener('click', () => {
                sidebar.classList.add('active');
                overlay.classList.add('active');
            });
            
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
            
            closeSidebar.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
        }

        // Form reset function
        function resetForm() {
            document.querySelector('form').reset();
            resetPasswordRequirements();
        }
        
        // Password toggle functionality
        function togglePasswordVisibility(fieldId, button) {
            const passwordField = document.getElementById(fieldId);
            const icon = button.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Password validation
        function validatePasswordRequirements(password) {
            // Check each requirement
            const hasLength = password.length >= 8;
            const hasLowercase = /[a-z]/.test(password);
            const hasUppercase = /[A-Z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[!@#$%^&*]/.test(password);
            
            // Update UI based on requirements
            updateRequirementStatus('length', hasLength);
            updateRequirementStatus('lowercase', hasLowercase);
            updateRequirementStatus('uppercase', hasUppercase);
            updateRequirementStatus('number', hasNumber);
            updateRequirementStatus('special', hasSpecial);
            
            return hasLength && hasLowercase && hasUppercase && hasNumber && hasSpecial;
        }
        
        function updateRequirementStatus(id, isValid) {
            const element = document.getElementById(id);
            if (isValid) {
                element.classList.remove('unmet');
                element.classList.add('met');
            } else {
                element.classList.remove('met');
                element.classList.add('unmet');
            }
        }
        
        function resetPasswordRequirements() {
            const requirements = document.querySelectorAll('.requirement');
            requirements.forEach(req => {
                req.classList.remove('met');
                req.classList.add('unmet');
            });
        }
        
        // Password confirmation validation
        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('confirm_password');
        
        function validatePassword() {
            if (passwordField.value !== confirmPasswordField.value) {
                confirmPasswordField.setCustomValidity("Passwords do not match");
            } else {
                confirmPasswordField.setCustomValidity("");
            }
            
            // Validate password requirements
            validatePasswordRequirements(passwordField.value);
        }
        
        if (passwordField && confirmPasswordField) {
            passwordField.addEventListener('input', validatePassword);
            confirmPasswordField.addEventListener('input', validatePassword);
        }
        
        // Form submission validation
        const form = document.querySelector('form');
        form.addEventListener('submit', function(event) {
            const password = passwordField.value;
            const confirmPassword = confirmPasswordField.value;
            
            // If password is being changed, validate it
            if (password) {
                if (!validatePasswordRequirements(password)) {
                    event.preventDefault();
                    alert('Please ensure your password meets all requirements.');
                    return false;
                }
                
                if (password !== confirmPassword) {
                    event.preventDefault();
                    alert('Passwords do not match.');
                    return false;
                }
            }
        });
        
        // Auto-hide success/error messages after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.bg-red-100, .bg-green-100');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>