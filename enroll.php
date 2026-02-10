<?php
session_start();
// Handle parent temporary session for upgrades/renewals
if (isset($_SESSION['temp_student_id']) && isset($_SESSION['temp_original_role']) && $_SESSION['temp_original_role'] === 'parent') {
    // Temporarily switch to student session for enroll.php
    $_SESSION['original_user_id'] = $_SESSION['user_id'];
    $_SESSION['original_role'] = $_SESSION['role'];
    $_SESSION['user_id'] = $_SESSION['temp_student_id'];
    $_SESSION['role'] = 'student';
    // Clean up temp session
    unset($_SESSION['temp_student_id']);
    unset($_SESSION['temp_original_role']);
    unset($_SESSION['temp_original_user_id']);
}
// Restore original session after processing (add this at the end of enroll.php if needed)
// Or handle it in submit_enrollment.php
include(__DIR__ . "/includes/db.php");
$isUpgrade = isset($_GET['action']) && $_GET['action'] === 'upgrade';
$isRenew = isset($_GET['action']) && $_GET['action'] === 'renew';
$upgradeFrom = $_GET['from'] ?? '';
$upgradeTo = $_GET['to'] ?? '';
$renewPackage = $_GET['package'] ?? '';
if ($isUpgrade) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        header("Location: login.php");
        exit;
    }
    $currentSubjects = json_decode($user['subjects'], true) ?? [];
    $currentPackage = $user['package_selected'] ?? 'Basic';
    // Verify the upgrade is valid
    $package_order = ['Basic' => 1, 'Standard' => 2, 'Premium' => 3];
    // Check if upgradeTo is set and valid
    if (empty($upgradeTo) || !isset($package_order[$upgradeTo])) {
        header("Location: settings.php?error=invalid_upgrade_package");
        exit;
    }
    // Make sure current package is set and valid
    if (empty($currentPackage) || !isset($package_order[$currentPackage])) {
        $currentPackage = 'Basic';
    }
    if ($package_order[$upgradeTo] <= $package_order[$currentPackage]) {
        header("Location: settings.php?error=invalid_upgrade");
        exit;
    }
} elseif ($isRenew) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        header("Location: login.php");
        exit;
    }
    $currentSubjects = json_decode($user['subjects'], true) ?? [];
    $currentPackage = $user['package_selected'] ?? 'Basic';
    $renewPackage = $currentPackage;
} else {
    // Regular enrollment
    $user = [];
    $currentSubjects = [];
    $currentPackage = '';
    $upgradeTo = '';
}
// Ensure variables are set for JavaScript
$user = $user ?? [];
$currentSubjects = $currentSubjects ?? [];
$currentPackage = $currentPackage ?? '';
$upgradeTo = $upgradeTo ?? '';
$renewPackage = $renewPackage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS Enrollment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- GOOGLE FONTS -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- YOUR BEAUTIFUL CSS -->
    <style>
        /* Color Palette */
        :root {
            --primary-blue: #1e3a8a;
            --primary-yellow: #facc15;
            --light-beige: #f5f1e3;
            --white: #ffffff;
            --text-dark: #333333;
            --shadow: rgba(0, 0, 0, 0.1);
        }
        /* General Styles */
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--light-beige);
            color: var(--text-dark);
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .section-title {
            text-align: center;
            margin-bottom: 40px;
        }
        .section-title h2 {
            color: var(--primary-blue);
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .section-title p {
            color: var(--primary-blue);
            font-size: 1.2em;
        }
        /* Header */
        header {
            background-color: var(--white);
            box-shadow: 0 2px 10px var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
        }
        .logo {
            width: 150px;
            height: 50px;
        }
        .logo-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        nav ul {
            list-style: none;
            display: flex;
            gap: 20px;
            margin: 0;
            padding: 0;
        }
        nav ul li a {
            text-decoration: none;
            color: var(--primary-blue);
            font-weight: 500;
            transition: color 0.3s;
        }
        nav ul li a:hover {
            color: var(--primary-yellow);
        }
        .auth-buttons .btn {
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-primary {
            background-color: var(--primary-yellow);
            color: var(--primary-blue);
            border: 2px solid var(--primary-yellow);
        }
        .btn-primary:hover {
            background-color: transparent;
            color: var(--primary-yellow);
        }
        .btn-outline {
            background-color: transparent;
            color: var(--primary-yellow);
            border: 2px solid var(--primary-yellow);
        }
        .btn-outline:hover {
            background-color: var(--primary-yellow);
            color: var(--primary-blue);
        }
        /* ENROLLMENT SPECIFIC STYLES */
        .enrollment-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 20px var(--shadow);
            margin: 40px auto;
            max-width: 900px;
        }
        .step {
            display: none;
        }
        .step.active {
            display: block;
        }
        .progress-step {
            width: 12.5%; /* 8 steps now! */
            text-align: center;
            position: relative;
            font-weight: bold;
            font-family: 'Poppins', sans-serif;
        }
        .progress-step.completed::before {
            content: "✓";
            color: white;
            background: var(--primary-blue);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .progress-step.active::before {
            content: "●";
            color: var(--primary-yellow);
            font-size: 24px;
        }
        .progress-step::before {
            content: "○";
            color: gray;
            font-size: 24px;
        }
        .error {
            color: red;
            font-size: 0.9em;
            margin-top: 5px;
            font-weight: 500;
        }
        .upload-box {
            border: 2px dashed #ccc;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            margin: 10px 0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .upload-box:hover {
            border-color: var(--primary-yellow);
            background: #fafafa;
        }
        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }
        .password-container {
            position: relative;
        }
        /* Payment Form Styling */
        .payment-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .payment-form h5 {
            color: var(--primary-blue);
            margin-bottom: 20px;
            text-align: center;
        }
        .card-icon {
            font-size: 2em;
            color: var(--primary-blue);
            margin-bottom: 15px;
            text-align: center;
        }
        /* Package Cards */
        .package-card {
            border: 2px solid #eee;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 10px 0;
            cursor: pointer;
            transition: all 0.3s;
        }
        .package-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px var(--shadow);
        }
        .package-card.selected {
            border-color: var(--primary-yellow);
            background-color: #fff9e6;
        }
        .package-card h4 {
            color: var(--primary-blue);
            margin: 10px 0;
        }
        .package-price {
            font-size: 1.5em;
            font-weight: bold;
            color: var(--primary-yellow);
            margin: 10px 0;
        }
        .package-subjects {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 15px;
        }
        /* Success Message */
        .billing-info {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #4caf50;
        }
        /* Responsive */
        @media (max-width: 768px) {
            .enrollment-container {
                padding: 20px;
                margin: 20px;
            }
            .progress-step {
                font-size: 10px;
            }
            .package-card {
                margin: 10px 0;
            }
        }
        /* Password Requirements */
        .password-requirements {
            font-size: 0.85em;
            margin-top: 5px;
        }
        .req-item {
            display: flex;
            align-items: center;
            margin: 2px 0;
        }
        .req-item i {
            margin-right: 5px;
            font-size: 0.9em;
        }
        .req-valid {
            color: green;
        }
        .req-invalid {
            color: #666;
        }
        /* Success Button */
        .btn-enroll-another {
            background-color: var(--primary-yellow) !important;
            color: var(--primary-blue) !important;
            border: none !important;
            padding: 15px 30px !important;
            font-size: 1.1em !important;
            border-radius: 8px !important;
            margin-top: 20px !important;
        }
        /* Exam Board Disclaimer */
        .exam-disclaimer {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin: 15px 0;
            font-size: 0.9em;
        }
        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: var(--primary-blue);
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .loading {
            display: none;
            color: var(--primary-blue);
            font-weight: bold;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container py-5">
        <div class="text-center mb-5">
            <h1 style="color: var(--primary-blue);"><?= $isUpgrade ? '🎓 Upgrade Your Package' : ($isRenew ? '🔄 Renew Your Package' : '🎓 Student Enrollment') ?></h1>
            <p>Complete all steps to <?= $isUpgrade ? 'upgrade your package' : ($isRenew ? 'renew your package' : 'enroll in your chosen subjects') ?></p>
        </div>
        <div class="enrollment-container">
            <!-- Progress Bar (NOW 8 STEPS!) -->
            <div class="d-flex justify-content-between mb-4">
                <div class="progress-step" id="step1-indicator">Account</div>
                <div class="progress-step" id="step2-indicator">OTP</div>
                <div class="progress-step" id="step3-indicator">Personal</div>
                <div class="progress-step" id="step4-indicator">Package</div>
                <div class="progress-step" id="step5-indicator">Academic</div>
                <div class="progress-step" id="step6-indicator">Financing</div>
                <div class="progress-step" id="step7-indicator">Payment</div>
                <div class="progress-step" id="step8-indicator">Done!</div>
            </div>
            <!-- Multi-step Form -->
            <div class="card-body">
                <!-- STEP 1: Account Setup (UPDATED WITH SERVER-SIDE VALIDATION!) -->
                <div class="step active" id="step1">
                    <h4>Step 1: Account Setup</h4>
                    <form id="form1">
                        <div class="mb-3">
                            <label>Email Address *</label>
                            <input type="email" class="form-control" id="email" required>
                            <div class="error" id="email-error"></div>
                        </div>
                        <div class="mb-3">
                            <label>Phone Number *</label>
                            <input type="tel" class="form-control" id="phone" required placeholder="e.g. 0821234567">
                            <div class="error" id="phone-error"></div>
                        </div>
                        <div class="mb-3">
                            <label>Password *</label>
                            <div class="password-container">
                                <input type="password" class="form-control" id="password" required>
                                <span class="password-toggle" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                            <div class="password-requirements">
                                <div class="req-item">
                                    <i class="fas fa-circle req-invalid" id="req-length"></i>
                                    <span>At least 8 characters</span>
                                </div>
                                <div class="req-item">
                                    <i class="fas fa-circle req-invalid" id="req-lowercase"></i>
                                    <span>At least one lowercase letter</span>
                                </div>
                                <div class="req-item">
                                    <i class="fas fa-circle req-invalid" id="req-uppercase"></i>
                                    <span>At least one uppercase letter</span>
                                </div>
                                <div class="req-item">
                                    <i class="fas fa-circle req-invalid" id="req-number"></i>
                                    <span>At least one number</span>
                                </div>
                                <div class="req-item">
                                    <i class="fas fa-circle req-invalid" id="req-special"></i>
                                    <span>At least one special character (!@#$%^&*)</span>
                                </div>
                            </div>
                            <div class="error" id="password-error"></div>
                        </div>
                        <div class="mb-3">
                            <label>Confirm Password *</label>
                            <div class="password-container">
                                <input type="password" class="form-control" id="confirm_password" required>
                                <span class="password-toggle" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                            <div class="error" id="confirm_password-error"></div>
                        </div>
                        <div class="loading" id="loading-check">
                            <span class="spinner"></span> Checking email availability...
                        </div>
                        <button type="button" class="btn btn-primary" style="background-color: var(--primary-yellow); color: var(--primary-blue); border: none;" onclick="nextStep(1)">Next →</button>
                    </form>
                </div>
                <!-- STEP 2: OTP VERIFICATION -->
                <div class="step" id="step2">
                    <h4>Step 2: Verify Your Email</h4>
                    <div class="alert alert-info">
                        ✉️ We sent a 6-digit OTP to <span id="otp-email-display"></span>
                    </div>
                    <form id="form2">
                        <div class="mb-3">
                            <label>Enter OTP *</label>
                            <input type="text" class="form-control" id="otp_input" maxlength="6" placeholder="123456">
                            <div class="error" id="otp-error"></div>
                            <small class="form-text text-muted">
                                Didn't receive it? Check spam folder or
                                <button type="button" class="btn btn-link p-0" onclick="resendOTP()">Resend OTP</button>
                            </small>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="prevStep(2)">← Back</button>
                        <button type="button" class="btn btn-primary" style="background-color: var(--primary-yellow); color: var(--primary-blue); border: none;" onclick="nextStep(2)">Verify & Next →</button>
                    </form>
                </div>
                <!-- STEP 3: Personal Info (UPDATED!) -->
                <div class="step" id="step3">
                    <h4>Step 3: Personal Information</h4>
                    <form id="form3">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>First Name *</label>
                                <input type="text" class="form-control" id="first_name" required>
                                <div class="error" id="first_name-error"></div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Middle Name</label>
                                <input type="text" class="form-control" id="middle_name">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Surname *</label>
                                <input type="text" class="form-control" id="surname" required>
                                <div class="error" id="surname-error"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Date of Birth *</label>
                                <input type="date" class="form-control" id="dob" required>
                                <div class="error" id="dob-error"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Gender *</label>
                                <select class="form-control" id="gender" required>
                                    <option value="">-- Select --</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                                <div class="error" id="gender-error"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Nationality *</label>
                            <select class="form-control" id="nationality" required>
                                <option value="">-- Select Nationality --</option>
                                <option value="South African">South African</option>
                                <option value="Other">Other</option>
                            </select>
                            <div class="error" id="nationality-error"></div>
                        </div>
                        <div class="mb-3">
                            <label>ID/Passport Number *</label>
                            <input type="text" class="form-control" id="id_passport" required>
                            <div class="error" id="id_passport-error"></div>
                        </div>
                        <div class="mb-3">
                            <label>Street Address *</label>
                            <input type="text" class="form-control" id="street" required>
                            <div class="error" id="street-error"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>City *</label>
                                <input type="text" class="form-control" id="city" required>
                                <div class="error" id="city-error"></div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Province *</label>
                                <select class="form-control" id="province" required>
                                    <option value="">-- Select Province --</option>
                                    <option value="Eastern Cape">Eastern Cape</option>
                                    <option value="Free State">Free State</option>
                                    <option value="Gauteng">Gauteng</option>
                                    <option value="KwaZulu-Natal">KwaZulu-Natal</option>
                                    <option value="Limpopo">Limpopo</option>
                                    <option value="Mpumalanga">Mpumalanga</option>
                                    <option value="Northern Cape">Northern Cape</option>
                                    <option value="North West">North West</option>
                                    <option value="Western Cape">Western Cape</option>
                                </select>
                                <div class="error" id="province-error"></div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Postal Code *</label>
                                <input type="text" class="form-control" id="postal_code" required>
                                <div class="error" id="postal_code-error"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Emergency Contact Name *</label>
                                <input type="text" class="form-control" id="emergency_contact_name" required>
                                <div class="error" id="emergency_contact_name-error"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Emergency Contact Phone *</label>
                                <div class="input-group">
                                    <span class="input-group-text">+27</span>
                                    <input type="tel" class="form-control" id="emergency_contact_phone" placeholder="e.g. 821234567" required>
                                </div>
                                <div class="error" id="emergency_contact_phone-error"></div>
                                <small class="form-text text-muted">Enter 9 digits after +27 (e.g., 821234567)</small>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="prevStep(3)">← Back</button>
                        <button type="button" class="btn btn-primary" style="background-color: var(--primary-yellow); color: var(--primary-blue); border: none;" onclick="nextStep(3)">Next →</button>
                    </form>
                </div>
                <!-- STEP 4: Package Selection (UPDATED!) -->
                <div class="step" id="step4">
                    <h4>Step 4: Choose Your Package</h4>
                    <form id="form4">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="package-card" onclick="selectPackage('Basic')">
                                    <h4>Basic</h4>
                                    <div class="package-price">FREE</div>
                                    <div class="package-subjects">1 Subject Only</div>
                                    <ul class="text-start">
                                        <li>Access to 1 subject</li>
                                        <li>Access to Learning Content</li>
                                        <li>Access to Digital Library (past papers and memos, textbooks)</li>
                                    </ul>
                                    <input type="radio" name="package" value="Basic" style="display:none" id="package_basic">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="package-card" onclick="selectPackage('Standard')">
                                    <h4>Standard</h4>
                                    <div class="package-price">R699/month</div>
                                    <div class="package-subjects">1-2 Subjects</div>
                                    <ul class="text-start">
                                        <li>Access to 1-2 subjects</li>
                                        <li>Access to Learning Content</li>
                                        <li>Access to Digital Library (past papers and memos, textbooks, study guides)</li>
                                        <li>Live and Recorded lessons</li>
                                        <li>Different Learning Styles (Auditory, Reading and Writing, Visual)</li>
                                        <li>Progress tracking</li>
                                    </ul>
                                    <input type="radio" name="package" value="Standard" style="display:none" id="package_standard">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="package-card" onclick="selectPackage('Premium')">
                                    <h4>Premium</h4>
                                    <div class="package-price">R1 199/month</div>
                                    <div class="package-subjects">1-4 Subjects</div>
                                    <ul class="text-start">
                                        <li>Access to 1-4 subjects</li>
                                        <li>Access to Learning Content</li>
                                        <li>Access to Digital Library (past papers and memos, textbooks, study guides)</li>
                                        <li>Live and Recorded lessons</li>
                                        <li>Different Learning Styles (Auditory, Reading and Writing, Visual)</li>
                                        <li>Progress tracking</li>
                                        <li>Tutor support</li>
                                        <li>Social Chatroom</li>
                                    </ul>
                                    <input type="radio" name="package" value="Premium" style="display:none" id="package_premium">
                                </div>
                            </div>
                        </div>
                        <div class="error mt-3" id="package-error"></div>
                        <button type="button" class="btn btn-secondary mt-4" onclick="prevStep(4)">← Back</button>
                        <button type="button" class="btn btn-primary mt-4" style="background-color: var(--primary-yellow); color: var(--primary-blue); border: none;" onclick="nextStep(4)">Next →</button>
                    </form>
                </div>
                <!-- STEP 5: Academic Info (UPDATED!) -->
                <div class="step" id="step5">
                    <h4>Step 5: Academic Information</h4>
                    <form id="form5">
                        <div class="mb-3">
                            <label>Subjects to Rewrite *</label><br>
                            <small id="subjects-help" class="form-text text-muted">Based on your package, you can select up to <span id="max-subjects">1</span> subject(s)</small>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input subject-checkbox" type="checkbox" id="subject_cat" value="CAT">
                                <label class="form-check-label">CAT</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input subject-checkbox" type="checkbox" id="subject_tech" value="English">
                                <label class="form-check-label">English</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input subject-checkbox" type="checkbox" id="subject_ps" value="Physical Science">
                                <label class="form-check-label">Physical Science</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input subject-checkbox" type="checkbox" id="subject_math" value="Mathematics">
                                <label class="form-check-label">Mathematics</label>
                            </div>
                            <div class="error" id="subjects-error"></div>
                        </div>
                        <div class="mb-3">
                            <label>Exam Board *</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="exam_board_nsc" checked disabled>
                                <label class="form-check-label">
                                    NSC (National Senior Certificate)
                                </label>
                            </div>
                            <div class="exam-disclaimer">
                                <strong>⚠️ Please be advised:</strong> Only students writing under the NSC exam board can proceed.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>South African Matric Exam Number *</label>
                            <input type="text" class="form-control" id="matric_exam_number" placeholder="13-digit number" required maxlength="13">
                            <div class="error" id="matric_exam_number-error"></div>
                            <small class="form-text text-muted">Enter your 13-digit matric exam number (numbers only)</small>
                            <div class="loading" id="loading-matric-check" style="display: none;">
                                <span class="spinner"></span> Checking matric number...
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Upload Certified ID/Passport (PDF/Image)</label>
                            <div class="upload-box" onclick="document.getElementById('proof_upload').click()">
                                📎 Click to Upload File
                            </div>
                            <input type="file" id="proof_upload" accept=".pdf,.jpg,.jpeg,.png" style="display:none" onchange="showFileName(this)">
                            <div id="file-name" class="mt-2"></div>
                            <!-- REMOVED ERROR DIV - UPLOAD IS NOW OPTIONAL -->
                        </div>
                        <div class="mb-3">
                            <label>Year of Last Attempt</label>
                            <select class="form-control" id="year_of_last_attempt">
                                <option value="">-- Select Year --</option>
                                <?php for($y = date('Y'); $y >= 2000; $y--): ?>
                                <option value="<?= $y ?>"><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="prevStep(5)">← Back</button>
                        <button type="button" class="btn btn-primary" style="background-color: var(--primary-yellow); color: var(--primary-blue); border: none;" onclick="nextStep(5)">Next →</button>
                    </form>
                </div>
                <!-- STEP 6: Financing (UPDATED! - SPONSOR REMOVED) -->
                <div class="step" id="step6">
                    <h4>Step 6: Financing Details</h4>
                    <form id="form6">
                        <div class="mb-3">
                            <label>Who will finance your studies? *</label><br>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="financier" id="financier_self" value="Self">
                                <label class="form-check-label">Self</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="financier" id="financier_parent" value="Parent/Guardian">
                                <label class="form-check-label">Parent/Guardian</label>
                            </div>
                            <!-- SPONSOR OPTION REMOVED -->
                        </div>
                        <!-- Self Financing -->
                        <div id="self-financing" style="display:none;">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="is_18_confirmed">
                                <label class="form-check-label">I am 18 years or older *</label>
                                <div class="error" id="is_18_confirmed-error"></div>
                            </div>
                            <div class="mb-3">
                                <label>Occupation/Income Source *</label>
                                <input type="text" class="form-control" id="income_source">
                                <div class="error" id="income_source-error"></div>
                            </div>
                        </div>
                        <!-- Parent Financing -->
                        <div id="parent-financing" style="display:none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Parent Full Name *</label>
                                    <input type="text" class="form-control" id="financier_name">
                                    <div class="error" id="financier_name-error"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Relationship to Student *</label>
                                    <input type="text" class="form-control" id="financier_relationship">
                                    <div class="error" id="financier_relationship-error"></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Parent ID/Passport *</label>
                                    <input type="text" class="form-control" id="financier_id_passport">
                                    <div class="error" id="financier_id_passport-error"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Parent Phone *</label>
                                    <input type="tel" class="form-control" id="financier_phone">
                                    <div class="error" id="financier_phone-error"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label>Parent Email (will receive login) *</label>
                                <input type="email" class="form-control" id="financier_email">
                                <div class="error" id="financier_email-error"></div>
                            </div>
                            <div class="mb-3">
                                <label>Parent Address *</label>
                                <textarea class="form-control" id="financier_address" rows="2"></textarea>
                                <div class="error" id="financier_address-error"></div>
                            </div>
                        </div>
                        <!-- SPONSOR FINANCING SECTION REMOVED -->
                        <div class="mb-3">
                            <label>Payment Method *</label>
                            <select class="form-control" id="payment_method" required>
                                <option value="">-- Select --</option>
                                <option value="Credit Card">Credit Card</option>
                            </select>
                            <div class="error" id="payment_method-error"></div>
                        </div>
                        <div class="error" id="financier-error"></div>
                        <button type="button" class="btn btn-secondary" onclick="prevStep(6)">← Back</button>
                        <button type="button" class="btn btn-primary" style="background-color: var(--primary-yellow); color: var(--primary-blue); border: none;" onclick="nextStep(6)">Next →</button>
                    </form>
                </div>
                <!-- STEP 7: Payment (Credit Card) (UPDATED!) -->
                <div class="step" id="step7">
                    <h4>Step 7: Payment Details</h4>
                    <div id="credit-card-form">
                        <div class="payment-form">
                            <div class="card-icon">💳</div>
                            <h5>Secure Credit Card Payment</h5>
                            <div class="mb-3">
                                <label>Cardholder Name *</label>
                                <input type="text" class="form-control" id="card_name" placeholder="John Doe">
                                <div class="error" id="card_name-error"></div>
                            </div>
                            <div class="mb-3">
                                <label>Card Number *</label>
                                <input type="text" class="form-control" id="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                                <div class="error" id="card_number-error"></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Expiry Date *</label>
                                    <input type="text" class="form-control" id="card_expiry" placeholder="MM/YY">
                                    <div class="error" id="card_expiry-error"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>CVV *</label>
                                    <input type="text" class="form-control" id="card_cvv" placeholder="123" maxlength="4">
                                    <div class="error" id="card_cvv-error"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label>Billing Email *</label>
                                <input type="email" class="form-control" id="billing_email" placeholder="you@example.com">
                                <div class="error" id="billing_email-error"></div>
                            </div>
                            <h5>Billing Address *</h5>
                            <div class="mb-3">
                                <label>Street *</label>
                                <input type="text" class="form-control" id="billing_street" required>
                                <div class="error" id="billing_street-error"></div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>City *</label>
                                    <input type="text" class="form-control" id="billing_city" required>
                                    <div class="error" id="billing_city-error"></div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Province *</label>
                                    <select class="form-control" id="billing_province" required>
                                        <option value="">-- Select Province --</option>
                                        <option value="Eastern Cape">Eastern Cape</option>
                                        <option value="Free State">Free State</option>
                                        <option value="Gauteng">Gauteng</option>
                                        <option value="KwaZulu-Natal">KwaZulu-Natal</option>
                                        <option value="Limpopo">Limpopo</option>
                                        <option value="Mpumalanga">Mpumalanga</option>
                                        <option value="Northern Cape">Northern Cape</option>
                                        <option value="North West">North West</option>
                                        <option value="Western Cape">Western Cape</option>
                                    </select>
                                    <div class="error" id="billing_province-error"></div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Postal Code *</label>
                                    <input type="text" class="form-control" id="billing_postal_code" required>
                                    <div class="error" id="billing_postal_code-error"></div>
                                </div>
                            </div>
                        </div>
                        <div class="billing-info">
                            <p><strong>❌ Cancellation:</strong> You can cancel anytime. No long-term contracts.</p>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="prevStep(7)">← Back</button>
                    <button type="button" class="btn btn-success" onclick="processPayment()">Complete Payment</button>
                </div>
                <!-- STEP 8: Success -->
                <div class="step text-center" id="step8">
                    <h2><?= $isUpgrade ? '🎉 Upgrade Successful!' : ($isRenew ? '🔄 Renewal Successful!' : '🎉 Enrollment Successful!') ?></h2>
                    <div style="color: var(--primary-blue); font-size: 1.2em; margin: 20px 0;">
                        Thank you for <?= $isUpgrade ? 'upgrading with us!' : ($isRenew ? 'renewing with us!' : 'enrolling with us!') ?>
                    </div>
                    <p>Check your email for details and instructions.</p>
                    <div class="alert alert-info" style="background: #e3f2fd; border-color: #bbdefb;">
                        <strong>Parent/Guardian:</strong> You will receive a separate email with your dashboard login.
                    </div>
                    <div class="billing-info">
                        <p><strong>❌ Cancellation:</strong> You can cancel anytime. No long-term contracts.</p>
                    </div>
                    <a href="<?= $isUpgrade ? 'my-courses.php' : ($isRenew ? 'my-courses.php' : 'login.php') ?>" class="btn btn-enroll-another">
                        ✅ Done
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentStep = 1;
        let formData = {};
        let generatedOTP = '';
        const isUpgrade = <?= json_encode($isUpgrade) ?>;
        const isRenew = <?= json_encode($isRenew) ?>;
        const upgradeFrom = '<?= addslashes($upgradeFrom) ?>';
        const upgradeTo = '<?= addslashes($upgradeTo) ?>';
        const renewPackage = '<?= addslashes($renewPackage) ?>';
        const userData = <?= !empty($user) ? json_encode($user) : '{}' ?>;
        const currentSubjects = <?= !empty($currentSubjects) ? json_encode($currentSubjects) : '[]' ?>;
        let userAge = null; // Global variable to store age

        // Debug logging
        console.log('Upgrade mode:', isUpgrade);
        console.log('Renew mode:', isRenew);
        console.log('Upgrade from:', upgradeFrom);
        console.log('Upgrade to:', upgradeTo);
        console.log('Renew package:', renewPackage);
        console.log('User data:', userData);

        if (isUpgrade && userData && Object.keys(userData).length > 0) {
            // Change title and text for upgrade
            document.querySelector('h1').textContent = '🎓 Upgrade Your Package';
            document.querySelector('p').textContent = 'Complete the upgrade process for your account';
            // Hide step1 and step2 indicators for upgrade
            document.getElementById('step1-indicator').style.display = 'none';
            document.getElementById('step2-indicator').style.display = 'none';
            // Adjust progress step widths
            const visibleSteps = Array.from(document.querySelectorAll('.progress-step')).filter(el => el.style.display !== 'none');
            visibleSteps.forEach(el => el.style.width = (100 / visibleSteps.length) + '%');
            // Pre-fill personal info (step3)
            document.getElementById('first_name').value = userData['first_name'] || '';
            document.getElementById('middle_name').value = userData['middle_name'] || '';
            document.getElementById('surname').value = userData['surname'] || '';
            document.getElementById('dob').value = userData['dob'] || '';
            document.getElementById('gender').value = userData['gender'] || '';
            document.getElementById('nationality').value = userData['nationality'] || '';
            document.getElementById('id_passport').value = userData['id_passport'] || '';
            document.getElementById('street').value = userData['street'] || '';
            document.getElementById('city').value = userData['city'] || '';
            document.getElementById('province').value = userData['province'] || '';
            document.getElementById('postal_code').value = userData['postal_code'] || '';
            document.getElementById('emergency_contact_name').value = userData['emergency_contact_name'] || '';
            document.getElementById('emergency_contact_phone').value = (userData['emergency_contact_phone'] || '').replace('+27', '');
            // For package selection, auto-select the upgrade package and hide others
            setTimeout(() => {
                if (upgradeTo) {
                    selectPackage(upgradeTo);
                    // Hide other package options
                    document.querySelectorAll('.package-card').forEach(card => {
                        const pkg = card.querySelector('input').value;
                        if (pkg !== upgradeTo) {
                            card.style.display = 'none';
                        }
                    });
                }
            }, 100);
            // For academic, pre-check current subjects and pre-fill matric number
            setTimeout(() => {
                currentSubjects.forEach(sub => {
                    const cb = document.querySelector(`.subject-checkbox[value="${sub}"]`);
                    if (cb) cb.checked = true;
                });
                // Pre-fill matric number from user data
                document.getElementById('matric_exam_number').value = userData['matric_exam_number'] || '';
            }, 200);
            // Start at step 3 for upgrade
            currentStep = 3;
            showStep(currentStep);
            // Pre-fill financing (step6) if available
            if (userData['financier_type']) {
                const financierId = 'financier_' + userData['financier_type'].toLowerCase().replace('/', '');
                const radio = document.getElementById(financierId);
                if (radio) {
                    radio.checked = true;
                    // Trigger change event to show the correct financing section
                    setTimeout(() => {
                        radio.dispatchEvent(new Event('change'));
                    }, 300);
                }
                if (userData['financier_type'] === 'Self') {
                    document.getElementById('is_18_confirmed').checked = userData['is_18_confirmed'] || false;
                    document.getElementById('income_source').value = userData['income_source'] || '';
                } else if (userData['financier_type'] === 'Parent/Guardian') {
                    document.getElementById('financier_name').value = userData['financier_name'] || '';
                    document.getElementById('financier_relationship').value = userData['financier_relationship'] || '';
                    document.getElementById('financier_id_passport').value = userData['financier_id_passport'] || '';
                    document.getElementById('financier_phone').value = userData['financier_phone'] || '';
                    document.getElementById('financier_email').value = userData['financier_email'] || '';
                    document.getElementById('financier_address').value = userData['financier_address'] || '';
                }
            }
            // Payment method
            document.getElementById('payment_method').value = userData['payment_method'] || 'Credit Card';
            // Set userAge from stored DOB if available
            if (userData['dob']) {
                const dobDate = new Date(userData['dob']);
                const today = new Date();
                let age = today.getFullYear() - dobDate.getFullYear();
                const monthDiff = today.getMonth() - dobDate.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dobDate.getDate())) {
                    age--;
                }
                userAge = age;
            }
        }

        if (isRenew && userData && Object.keys(userData).length > 0) {
            // Change title and text for renewal
            document.querySelector('h1').textContent = '🔄 Renew Your Package';
            document.querySelector('p').textContent = 'Complete the renewal process for your account';
            // Hide step1 and step2 indicators for renewal
            document.getElementById('step1-indicator').style.display = 'none';
            document.getElementById('step2-indicator').style.display = 'none';
            // Adjust progress step widths
            const visibleSteps = Array.from(document.querySelectorAll('.progress-step')).filter(el => el.style.display !== 'none');
            visibleSteps.forEach(el => el.style.width = (100 / visibleSteps.length) + '%');
            // Auto-select the current package and hide others
            setTimeout(() => {
                if (renewPackage) {
                    selectPackage(renewPackage);
                    // Hide other package options
                    document.querySelectorAll('.package-card').forEach(card => {
                        const pkg = card.querySelector('input').value;
                        if (pkg !== renewPackage) {
                            card.style.display = 'none';
                        }
                    });
                }
            }, 100);

            // RENEWAL AUTO-FILLING: Pre-fill academic info (step5) from existing user data
            setTimeout(() => {
                // Pre-fill subjects from existing data
                if (userData['subjects']) {
                    try {
                        const existingSubjects = JSON.parse(userData['subjects']);
                        existingSubjects.forEach(sub => {
                            const cb = document.querySelector(`.subject-checkbox[value="${sub}"]`);
                            if (cb) cb.checked = true;
                        });
                    } catch (e) {
                        console.error('Error parsing subjects:', e);
                    }
                }
                
                // Pre-fill matric number
                document.getElementById('matric_exam_number').value = userData['matric_exam_number'] || '';
                
                // Pre-fill year of last attempt
                document.getElementById('year_of_last_attempt').value = userData['year_of_last_attempt'] || '';
                
            }, 200);
            
            // RENEWAL AUTO-FILLING: Pre-fill financing (step6) from existing data
               setTimeout(() => {
        if (userData['financier_type']) {
            const financierId = 'financier_' + userData['financier_type'].toLowerCase().replace('/', '');
            const radio = document.getElementById(financierId);
            if (radio) {
                radio.checked = true;
                // Trigger change event to show the correct financing section
                setTimeout(() => {
                    radio.dispatchEvent(new Event('change'));
                    
                    // Now fill the specific financier data
                    if (userData['financier_type'] === 'Self') {
                        document.getElementById('is_18_confirmed').checked = userData['is_18_confirmed'] || false;
                        document.getElementById('income_source').value = userData['income_source'] || '';
                    } else if (userData['financier_type'] === 'Parent/Guardian') {
                        document.getElementById('financier_name').value = userData['financier_name'] || '';
                        document.getElementById('financier_relationship').value = userData['financier_relationship'] || '';
                        document.getElementById('financier_id_passport').value = userData['financier_id_passport'] || '';
                        document.getElementById('financier_phone').value = userData['financier_phone'] || '';
                        document.getElementById('financier_email').value = userData['financier_email'] || '';
                        document.getElementById('financier_address').value = userData['financier_address'] || '';
                        
                        // AUTO-FILL BILLING DETAILS FOR PARENT (STEP 7)
                        setTimeout(() => {
                            // Pre-fill billing details with parent info for parent-funded renewals
                            if (userData['financier_name']) {
                                document.getElementById('card_name').value = userData['financier_name'] || '';
                            }
                            if (userData['financier_email']) {
                                document.getElementById('billing_email').value = userData['financier_email'] || '';
                            }
                            if (userData['financier_address']) {
                                document.getElementById('billing_street').value = userData['financier_address'] || '';
                                document.getElementById('billing_city').value = userData['city'] || '';
                                document.getElementById('billing_province').value = userData['province'] || '';
                                document.getElementById('billing_postal_code').value = userData['postal_code'] || '';
                            }
                        }, 200);
                    }
                }, 100);
            }
        }
                
                // Pre-fill payment method
                document.getElementById('payment_method').value = userData['payment_method'] || 'Credit Card';
                
            }, 300);

            // Start at step 4 for renewal
            currentStep = 4;
            showStep(currentStep);
            // Set userAge from stored DOB if available
            if (userData['dob']) {
                const dobDate = new Date(userData['dob']);
                const today = new Date();
                let age = today.getFullYear() - dobDate.getFullYear();
                const monthDiff = today.getMonth() - dobDate.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dobDate.getDate())) {
                    age--;
                }
                userAge = age;
            }
        }

        function showStep(step) {
            // Hide all steps
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            // Show current step
            document.getElementById('step' + step).classList.add('active');
            // Update progress bar
            document.querySelectorAll('.progress-step').forEach((el, idx) => {
                el.classList.remove('completed', 'active');
                if (idx + 1 < step) el.classList.add('completed');
                if (idx + 1 === step) el.classList.add('active');
            });
        }

        function prevStep(step) {
            if ((isUpgrade || isRenew) && step === 3) {
                window.location.href = 'settings.php';
                return;
            }
            currentStep = step - 1;
            if ((isUpgrade || isRenew) && currentStep < 3) {
                currentStep = isRenew ? 4 : 3;
            }
            showStep(currentStep);
        }

        async function nextStep(step) {
            if (validateStep(step)) {
                saveStepData(step);
                
                // SPECIAL HANDLING FOR RENEWAL - AUTO-ADVANCE THROUGH PRE-FILLED STEPS
                if (isRenew && (step === 4 || step === 5)) {
                    currentStep = step + 1;
                    showStep(currentStep);
                    return;
                }
                
                // Special handling for Basic package after Step 5
                if (step === 5 && formData.package_selected === 'Basic') {
                    // Skip to final step (step 8) for Basic package
                    currentStep = 8;
                    showStep(currentStep);
                    // Submit enrollment directly
                    submitEnrollment();
                    return;
                }
                // Special handling for Step 1 - Check email availability from server
                if (step === 1) {
                    const email = document.getElementById('email').value.trim();
                    // Show loading indicator
                    const loadingDiv = document.getElementById('loading-check');
                    loadingDiv.style.display = 'block';
                    try {
                        // Check email availability from server
                        const emailAvailable = await checkEmailAvailability(email);
                        // Hide loading indicator
                        loadingDiv.style.display = 'none';
                        if (!emailAvailable) {
                            document.getElementById('email-error').textContent = 'This email is already registered. Please use a different email.';
                            return;
                        }
                        currentStep = step + 1;
                        showStep(currentStep);
                        // Send OTP after Step 1
                        sendOTP();
                    } catch (error) {
                        // Hide loading indicator
                        loadingDiv.style.display = 'none';
                        alert('Error checking email: ' + error.message);
                    }
                } else if (step === 5) {
                    // Special handling for Step 5 - Check matric number
                    const matricNumber = document.getElementById('matric_exam_number').value.trim();
                    const loadingDiv = document.getElementById('loading-matric-check');
                    loadingDiv.style.display = 'block';
                    try {
                        const matricValid = await checkMatricNumber(matricNumber);
                        loadingDiv.style.display = 'none';
                        if (!matricValid) {
                            // Error message already set by checkMatricNumber
                            return;
                        }
                        currentStep = step + 1;
                        showStep(currentStep);
                        // Special handling for payment method
                        if (step === 6) { // Step 6 is financing
                            const paymentMethod = document.getElementById('payment_method').value;
                            if (paymentMethod === 'Credit Card') {
                                // Credit card form is always shown now
                            }
                        }
                    } catch (error) {
                        loadingDiv.style.display = 'none';
                        alert('Error checking matric number: ' + error.message);
                    }
                } else {
                    currentStep = step + 1;
                    if ((isUpgrade || isRenew) && currentStep === 3) currentStep = isRenew ? 4 : 3; // Skip to appropriate step if needed
                    showStep(currentStep);
                    // Special handling for payment method
                    if (step === 6) { // Step 6 is financing
                        const paymentMethod = document.getElementById('payment_method').value;
                        if (paymentMethod === 'Credit Card') {
                            // Credit card form is always shown now
                        }
                    }
                }
            }
        }

        function validateStep(step) {
            // SKIP VALIDATION FOR PRE-FILLED STEPS IN RENEWAL MODE
            if (isRenew && (step === 5 || step === 6)) {
                // For renewal, steps 5 and 6 are pre-filled, so we just save the data but don't validate
                saveStepData(step);
                return true;
            }
            
            let valid = true;
            // Clear all errors first
            document.querySelectorAll('.error').forEach(el => el.textContent = '');

            if (step === 1) { // Updated validation!
                if (isUpgrade || isRenew) return true; // Skip for upgrade/renew
                const email = document.getElementById('email').value.trim();
                const phone = document.getElementById('phone').value.trim();
                const password = document.getElementById('password').value;
                const confirm = document.getElementById('confirm_password').value;
                if (!email) {
                    document.getElementById('email-error').textContent = 'Email is required';
                    valid = false;
                } else if (!/^\S+@\S+\.\S+$/.test(email)) {
                    document.getElementById('email-error').textContent = 'Invalid email format';
                    valid = false;
                }
                if (!phone) {
                    document.getElementById('phone-error').textContent = 'Phone number is required';
                    valid = false;
                } else if (!/^\d{10,15}$/.test(phone.replace(/\D/g, ''))) {
                    document.getElementById('phone-error').textContent = 'Please enter a valid phone number (10-15 digits)';
                    valid = false;
                }
                if (!password) {
                    document.getElementById('password-error').textContent = 'Password is required';
                    valid = false;
                } else {
                    // Validate each requirement
                    const lengthValid = password.length >= 8;
                    const lowercaseValid = /[a-z]/.test(password);
                    const uppercaseValid = /[A-Z]/.test(password);
                    const numberValid = /\d/.test(password);
                    const specialValid = /[!@#$%^&*]/.test(password);
                    // Update UI
                    document.getElementById('req-length').className = lengthValid ? 'fas fa-check req-valid' : 'fas fa-circle req-invalid';
                    document.getElementById('req-lowercase').className = lowercaseValid ? 'fas fa-check req-valid' : 'fas fa-circle req-invalid';
                    document.getElementById('req-uppercase').className = uppercaseValid ? 'fas fa-check req-valid' : 'fas fa-circle req-invalid';
                    document.getElementById('req-number').className = numberValid ? 'fas fa-check req-valid' : 'fas fa-circle req-invalid';
                    document.getElementById('req-special').className = specialValid ? 'fas fa-check req-valid' : 'fas fa-circle req-invalid';
                    if (!lengthValid) {
                        document.getElementById('password-error').textContent = 'Password must be at least 8 characters';
                        valid = false;
                    } else if (!lowercaseValid) {
                        document.getElementById('password-error').textContent = 'Password must contain at least one lowercase letter';
                        valid = false;
                    } else if (!uppercaseValid) {
                        document.getElementById('password-error').textContent = 'Password must contain at least one uppercase letter';
                        valid = false;
                    } else if (!numberValid) {
                        document.getElementById('password-error').textContent = 'Password must contain at least one number';
                        valid = false;
                    } else if (!specialValid) {
                        document.getElementById('password-error').textContent = 'Password must contain at least one special character (!@#$%^&*)';
                        valid = false;
                    }
                }
                if (!confirm) {
                    document.getElementById('confirm_password-error').textContent = 'Please confirm your password';
                    valid = false;
                } else if (password !== confirm) {
                    document.getElementById('confirm_password-error').textContent = 'Passwords do not match';
                    valid = false;
                }
            }
            if (step === 2) { // OTP Step
                if (isUpgrade || isRenew) return true; // Skip for upgrade/renew
                const otp = document.getElementById('otp_input').value.trim();
                if (!otp) {
                    document.getElementById('otp-error').textContent = 'Please enter the OTP';
                    valid = false;
                } else if (otp !== generatedOTP) {
                    document.getElementById('otp-error').textContent = 'Invalid OTP. Please check your email.';
                    valid = false;
                }
            }
            if (step === 3) { // Personal Info
                const requiredFields = [
                    'first_name', 'surname', 'dob', 'gender', 'nationality', 'id_passport',
                    'street', 'city', 'province', 'postal_code',
                    'emergency_contact_name', 'emergency_contact_phone'
                ];
                requiredFields.forEach(field => {
                    const value = document.getElementById(field).value.trim();
                    if (!value) {
                        document.getElementById(field + '-error').textContent = 'This field is required';
                        valid = false;
                    }
                });
                // Validate ID based on nationality
                const nationality = document.getElementById('nationality').value;
                const idPassport = document.getElementById('id_passport').value.trim();
                if (nationality === 'South African') {
                    if (!/^\d{13}$/.test(idPassport)) {
                        document.getElementById('id_passport-error').textContent = 'South African ID must be 13 digits';
                        valid = false;
                    }
                }
                // Validate emergency contact phone (9 digits after +27)
                const emergencyPhone = document.getElementById('emergency_contact_phone').value.trim();
                if (!/^\d{9}$/.test(emergencyPhone)) {
                    document.getElementById('emergency_contact_phone-error').textContent = 'Please enter exactly 9 digits';
                    valid = false;
                }

                // Validate Date of Birth: must be at least 16 years old
                const dob = document.getElementById('dob').value;
                if (dob) {
                    const dobDate = new Date(dob);
                    const today = new Date();
                    let age = today.getFullYear() - dobDate.getFullYear();
                    const monthDiff = today.getMonth() - dobDate.getMonth();
                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dobDate.getDate())) {
                        age--;
                    }
                    if (age < 16) {
                        document.getElementById('dob-error').textContent = 'Applicants must be above 16 years old.';
                        valid = false;
                    }
                    // Store age globally for step 6 validation
                    userAge = age;
                } else {
                    userAge = null;
                }
            }
            if (step === 4) { // Package selection
                const packageSelected = document.querySelector('input[name="package"]:checked');
                if (!packageSelected) {
                    document.getElementById('package-error').textContent = 'Select a package';
                    valid = false;
                } else {
                    formData.package_selected = packageSelected.value;
                    updateSubjectRules(formData.package_selected);
                }
            }
            if (step === 5) { // Academic (subjects)
                let subjectsChecked = 0;
                let subjects = [];
                document.querySelectorAll('.subject-checkbox').forEach(cb => {
                    if (cb.checked) {
                        subjectsChecked++;
                        subjects.push(cb.value);
                    }
                });
                let currentPackage = formData.package_selected;
                if (!currentPackage) {
                    document.getElementById('subjects-error').textContent = 'Please select a package first';
                    valid = false;
                    return false;
                }
                let maxSubjects = 1;
                if (currentPackage === 'Standard') maxSubjects = 2;
                else if (currentPackage === 'Premium') maxSubjects = 4;
                if (subjectsChecked === 0) {
                    document.getElementById('subjects-error').textContent = 'Select at least one subject';
                    valid = false;
                } else if (subjectsChecked > maxSubjects) {
                    document.getElementById('subjects-error').textContent = `You can only select up to ${maxSubjects} subject(s) for your ${currentPackage} package`;
                    valid = false;
                }
                formData.subjects = JSON.stringify(subjects);
                // Validate matric exam number format (13 digits only)
                const matricNumber = document.getElementById('matric_exam_number').value.trim();
                if (!matricNumber) {
                    document.getElementById('matric_exam_number-error').textContent = 'Matric exam number is required';
                    valid = false;
                } else if (!/^\d{13}$/.test(matricNumber)) {
                    document.getElementById('matric_exam_number-error').textContent = 'Invalid matric exam number format. Must be exactly 13 digits with no letters or special characters.';
                    valid = false;
                }
                // Upload is now optional - no validation required
            }
            if (step === 6) { // Financing (UPDATED! - SPONSOR REMOVED)
                const financier = document.querySelector('input[name="financier"]:checked');
                if (!financier) {
                    document.getElementById('financier-error').textContent = 'Select financing option';
                    valid = false;
                } else {
                    const type = financier.value;
                    if (type === 'Self') {
                        // Check age from step 3
                        if (userAge !== null && userAge < 18) {
                            document.getElementById('financier-error').textContent = 'Applicants below 18 years cannot finance themselves.';
                            valid = false;
                        } else if (!document.getElementById('is_18_confirmed').checked) {
                            document.getElementById('is_18_confirmed-error').textContent = 'You must confirm you are 18+';
                            valid = false;
                        }
                        if (!document.getElementById('income_source').value.trim()) {
                            document.getElementById('income_source-error').textContent = 'Income source required';
                            valid = false;
                        }
                    } else if (type === 'Parent/Guardian') {
                        const parentFields = ['financier_name', 'financier_relationship', 'financier_id_passport', 'financier_phone', 'financier_email', 'financier_address'];
                        parentFields.forEach(field => {
                            if (!document.getElementById(field).value.trim()) {
                                document.getElementById(field + '-error').textContent = 'Required';
                                valid = false;
                            }
                        });
                    }
                    // SPONSOR VALIDATION REMOVED
                    if (!document.getElementById('payment_method').value) {
                        document.getElementById('payment_method-error').textContent = 'Select payment method';
                        valid = false;
                    }
                }
            }
            if (step === 7) { // Payment
                const cardName = document.getElementById('card_name').value.trim();
                const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
                const cardExpiry = document.getElementById('card_expiry').value.trim();
                const cardCvv = document.getElementById('card_cvv').value.trim();
                const billingEmail = document.getElementById('billing_email').value.trim();
                const billingStreet = document.getElementById('billing_street').value.trim();
                const billingCity = document.getElementById('billing_city').value.trim();
                const billingProvince = document.getElementById('billing_province').value;
                const billingPostalCode = document.getElementById('billing_postal_code').value.trim();
                if (!cardName) {
                    document.getElementById('card_name-error').textContent = 'Cardholder name is required';
                    valid = false;
                }
                if (!cardNumber) {
                    document.getElementById('card_number-error').textContent = 'Card number is required';
                    valid = false;
                } else if (!/^\d{16}$/.test(cardNumber)) {
                    document.getElementById('card_number-error').textContent = 'Card number must be 16 digits';
                    valid = false;
                }
                if (!cardExpiry) {
                    document.getElementById('card_expiry-error').textContent = 'Expiry date is required';
                    valid = false;
                } else if (!/^\d{2}\/\d{2}$/.test(cardExpiry)) {
                    document.getElementById('card_expiry-error').textContent = 'Format: MM/YY';
                    valid = false;
                } else {
                    const [month, year] = cardExpiry.split('/');
                    const expMonth = parseInt(month);
                    const expYear = parseInt('20' + year);
                    const now = new Date();
                    const currentYear = now.getFullYear();
                    const currentMonth = now.getMonth() + 1;
                    if (expYear < currentYear || (expYear === currentYear && expMonth < currentMonth)) {
                        document.getElementById('card_expiry-error').textContent = 'Card is expired';
                        valid = false;
                    }
                    if (expMonth < 1 || expMonth > 12) {
                        document.getElementById('card_expiry-error').textContent = 'Invalid month';
                        valid = false;
                    }
                }
                if (!cardCvv) {
                    document.getElementById('card_cvv-error').textContent = 'CVV is required';
                    valid = false;
                } else if (!/^\d{3,4}$/.test(cardCvv)) {
                    document.getElementById('card_cvv-error').textContent = 'CVV must be 3 or 4 digits';
                    valid = false;
                }
                if (!billingEmail) {
                    document.getElementById('billing_email-error').textContent = 'Billing email is required';
                    valid = false;
                } else if (!/^\S+@\S+\.\S+$/.test(billingEmail)) {
                    document.getElementById('billing_email-error').textContent = 'Invalid email format';
                    valid = false;
                }
                if (!billingStreet) {
                    document.getElementById('billing_street-error').textContent = 'Billing street is required';
                    valid = false;
                }
                if (!billingCity) {
                    document.getElementById('billing_city-error').textContent = 'Billing city is required';
                    valid = false;
                }
                if (!billingProvince) {
                    document.getElementById('billing_province-error').textContent = 'Billing province is required';
                    valid = false;
                }
                if (!billingPostalCode) {
                    document.getElementById('billing_postal_code-error').textContent = 'Billing postal code is required';
                    valid = false;
                }
            }
            return valid;
        }

        function saveStepData(step) {
            if (step === 1) {
                formData.email = document.getElementById('email').value.trim();
                formData.phone = document.getElementById('phone').value.trim();
                formData.password = document.getElementById('password').value;
            }
            if (step === 3) {
                formData.first_name = document.getElementById('first_name').value.trim();
                formData.middle_name = document.getElementById('middle_name').value.trim();
                formData.surname = document.getElementById('surname').value.trim();
                formData.dob = document.getElementById('dob').value;
                formData.gender = document.getElementById('gender').value;
                formData.nationality = document.getElementById('nationality').value;
                formData.id_passport = document.getElementById('id_passport').value.trim();
                formData.street = document.getElementById('street').value.trim();
                formData.city = document.getElementById('city').value.trim();
                formData.province = document.getElementById('province').value;
                formData.postal_code = document.getElementById('postal_code').value.trim();
                formData.emergency_contact_name = document.getElementById('emergency_contact_name').value.trim();
                formData.emergency_contact_phone = '+27' + document.getElementById('emergency_contact_phone').value.trim();
                // Recalculate and store age
                const dob = formData.dob;
                if (dob) {
                    const dobDate = new Date(dob);
                    const today = new Date();
                    let age = today.getFullYear() - dobDate.getFullYear();
                    const monthDiff = today.getMonth() - dobDate.getMonth();
                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dobDate.getDate())) {
                        age--;
                    }
                    userAge = age;
                } else {
                    userAge = null;
                }
            }
            if (step === 4) {
                const packageSelected = document.querySelector('input[name="package"]:checked')?.value;
                if (packageSelected) {
                    formData.package_selected = packageSelected;
                    updateSubjectRules(packageSelected);
                }
            }
            if (step === 5) {
                let subjects = [];
                document.querySelectorAll('.subject-checkbox:checked').forEach(cb => {
                    subjects.push(cb.value);
                });
                formData.subjects = JSON.stringify(subjects);
                formData.previous_school = 'NSC'; // Fixed as per requirement
                formData.year_of_last_attempt = document.getElementById('year_of_last_attempt').value;
                formData.matric_exam_number = document.getElementById('matric_exam_number').value.trim();
                // Handle file upload in real system (now optional)
            }
            if (step === 6) {
                const financier = document.querySelector('input[name="financier"]:checked')?.value;
                formData.financier_type = financier;
                if (financier === 'Self') {
                    formData.is_18_confirmed = document.getElementById('is_18_confirmed').checked ? 1 : 0;
                    formData.income_source = document.getElementById('income_source').value.trim();
                    formData.financier_name = '';
                    formData.financier_email = '';
                } else if (financier === 'Parent/Guardian') {
                    formData.financier_name = document.getElementById('financier_name').value.trim();
                    formData.financier_relationship = document.getElementById('financier_relationship').value.trim();
                    formData.financier_id_passport = document.getElementById('financier_id_passport').value.trim();
                    formData.financier_phone = document.getElementById('financier_phone').value.trim();
                    formData.financier_email = document.getElementById('financier_email').value.trim();
                    formData.financier_address = document.getElementById('financier_address').value.trim();
                    formData.is_18_confirmed = 0;
                    formData.income_source = '';
                }
                // SPONSOR DATA SAVING REMOVED
                formData.payment_method = document.getElementById('payment_method').value;
            }
            if (step === 7) {
                formData.card_name = document.getElementById('card_name').value.trim();
                formData.billing_email = document.getElementById('billing_email').value.trim();
                formData.billing_street = document.getElementById('billing_street').value.trim();
                formData.billing_city = document.getElementById('billing_city').value.trim();
                formData.billing_province = document.getElementById('billing_province').value;
                formData.billing_postal_code = document.getElementById('billing_postal_code').value.trim();
            }
        }

        function selectPackage(packageName) {
            // Clear all selections
            document.querySelectorAll('.package-card').forEach(card => {
                card.classList.remove('selected');
            });
            // Select clicked package
            event.currentTarget.classList.add('selected');
            // Check the hidden radio button
            document.getElementById('package_' + packageName.toLowerCase()).checked = true;
            // Save package to formData immediately
            formData.package_selected = packageName;
            // Update subject selection rules
            updateSubjectRules(packageName);
        }

        function updateSubjectRules(packageName) {
            let maxSubjects = 1;
            if (packageName === 'Standard') maxSubjects = 2;
            else if (packageName === 'Premium') maxSubjects = 4;
            // Update help text
            document.getElementById('max-subjects').textContent = maxSubjects;
        }

        // Server-side email validation function
        async function checkEmailAvailability(email) {
            try {
                const response = await fetch('check_email.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email })
                });
                const data = await response.json();
                if (data.success && !data.exists) {
                    return true; // Email is available
                } else {
                    return false; // Email already exists
                }
            } catch (error) {
                console.error('Error checking email:', error);
                throw error;
            }
        }

        // Server-side matric number validation function
        async function checkMatricNumber(matricNumber) {
            try {
                const response = await fetch('check_matric.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ matric_number: matricNumber })
                });
                const data = await response.json();
                if (data.success) {
                    // Matric number is valid and available
                    return true;
                } else {
                    // Show error message
                    document.getElementById('matric_exam_number-error').textContent = data.message;
                    return false;
                }
            } catch (error) {
                console.error('Error checking matric number:', error);
                throw error;
            }
        }

        function sendOTP() {
            // Generate 6-digit OTP
            generatedOTP = Math.floor(100000 + Math.random() * 900000).toString();
            // Display email in OTP step
            document.getElementById('otp-email-display').textContent = formData.email;
            // Send via PHP
            fetch('send_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: formData.email,
                    otp: generatedOTP
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('OTP sent successfully!');
                } else {
                    alert('Failed to send OTP: ' + data.message);
                }
            })
            .catch(err => {
                alert('Error sending OTP: ' + err.message);
            });
        }

        function resendOTP() {
            sendOTP();
            alert('OTP resent! Check your email.');
        }

        function processPayment() {
            if (!validateStep(7)) return;
            // Submit enrollment
            submitEnrollment();
        }

        function submitEnrollment() {
            console.log('Starting enrollment submission...');
            // Prepare form data
            if (isUpgrade) {
                formData.action = 'upgrade';
                formData.user_id = userData['id'];
                formData.email = userData['email'];
                formData.current_package = upgradeFrom;
                formData.new_package = upgradeTo;
            } else if (isRenew) {
                formData.action = 'renew';
                formData.user_id = userData['id'];
                formData.email = userData['email'];
                formData.current_package = renewPackage;
                formData.new_package = renewPackage;
            } else {
                formData.action = 'register';
            }
            // Show loading state
            const submitBtn = document.querySelector('#step7 .btn-success');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner"></span> Processing...';
            submitBtn.disabled = true;
            console.log('Sending data:', formData);
            // Send to server with timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout
            fetch('submit_enrollment.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(formData),
                signal: controller.signal
            })
            .then(response => {
                clearTimeout(timeoutId);
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                if (!text) {
                    throw new Error('Server returned empty response');
                }
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response text that failed to parse:', text);
                    throw new Error('Server returned invalid JSON');
                }
                return data;
            })
            .then(data => {
                console.log('Parsed response:', data);
                if (data.success) {
                    currentStep = 8;
                    showStep(8);
                    console.log('Enrollment successful! Student ID:', data.student_id);
                    // Show success message
                    if (data.message) {
                        const successDiv = document.createElement('div');
                        successDiv.className = 'alert alert-success mt-3';
                        successDiv.innerHTML = `<strong>Success!</strong> ${data.message}`;
                        document.querySelector('#step8').prepend(successDiv);
                    }
                } else {
                    throw new Error(data.message || 'Unknown server error');
                }
            })
            .catch(err => {
                console.error('Submission error:', err);
                if (err.name === 'AbortError') {
                    alert('Submission timed out. Please try again.');
                } else {
                    alert('Submission failed: ' + err.message);
                }
            })
            .finally(() => {
                // Restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }

        function showFileName(input) {
            const fileName = input.files[0]?.name || 'No file chosen';
            document.getElementById('file-name').textContent = fileName;
        }

        // Show/hide financing sections
        document.querySelectorAll('input[name="financier"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('self-financing').style.display = 'none';
                document.getElementById('parent-financing').style.display = 'none';
                // SPONSOR SECTION REMOVED FROM HERE
                if (this.value === 'Self') {
                    document.getElementById('self-financing').style.display = 'block';
                } else if (this.value === 'Parent/Guardian') {
                    document.getElementById('parent-financing').style.display = 'block';
                }
                // SPONSOR DISPLAY LOGIC REMOVED
            });
        });

        // Format card number as user types
        document.getElementById('card_number')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '').replace(/(.{4})/g, '$1 ').trim();
            e.target.value = value;
        });

        // Format expiry date
        document.getElementById('card_expiry')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 3) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });

        // Real-time password validation
        document.getElementById('password')?.addEventListener('input', function() {
            const password = this.value;
            if (!password) return;
            const lengthValid = password.length >= 8;
            const lowercaseValid = /[a-z]/.test(password);
            const uppercaseValid = /[A-Z]/.test(password);
            const numberValid = /\d/.test(password);
            const specialValid = /[!@#$%^&*]/.test(password);
            document.getElementById('req-length').className = lengthValid ? 'fas fa-check req-valid' : 'fas fa-circle req-invalid';
            document.getElementById('req-lowercase').className = lowercaseValid ? 'fas fa-check req-valid' : 'fas fa-circle req-invalid';
            document.getElementById('req-uppercase').className = uppercaseValid ? 'fas fa-check req-valid' : 'fas fa-circle req-invalid';
            document.getElementById('req-number').className = numberValid ? 'fas fa-check req-valid' : 'fas fa-circle req-invalid';
            document.getElementById('req-special').className = specialValid ? 'fas fa-check req-valid' : 'fas fa-circle req-invalid';
        });

        // Limit matric number to 13 digits
        document.getElementById('matric_exam_number')?.addEventListener('input', function(e) {
            // Remove non-digits
            let value = e.target.value.replace(/\D/g, '');
            // Limit to 13 digits
            if (value.length > 13) {
                value = value.substring(0, 13);
            }
            e.target.value = value;
        });

        // Password toggle functionality
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        });

        document.getElementById('toggleConfirmPassword').addEventListener('click', function () {
            const confirmPasswordInput = document.getElementById('confirm_password');
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            this.querySelector('i').className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        });

        // Initialize
        showStep(currentStep);
    </script>
</body>
</html>