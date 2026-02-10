<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - NovaTech FET College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --dark-blue: #1a3a6c;
            --yellow: #ffc107;
            --beige: #f5f5dc;
            --white: #ffffff;
            --light-blue: #e0f0ff;
            --gray: #6c757d;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #4a6fa5, #1a3a6c);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .reset-container {
            display: flex;
            width: 100%;
            max-width: 900px;
            min-height: 500px;
            background-color: var(--white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
            animation: fadeIn 0.8s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .reset-left {
            flex: 1;
            background: linear-gradient(to bottom right, var(--dark-blue), #2a4a7c);
            color: var(--white);
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .reset-left::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: var(--yellow);
            opacity: 0.1;
        }
        
        .reset-left::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -80px;
            width: 250px;
            height: 250px;
            border-radius: 50%;
            background: var(--yellow);
            opacity: 0.1;
        }
        
        .college-logo {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .college-logo h1 {
            font-size: 28px;
            margin-top: 10px;
            font-weight: 700;
        }
        
        .welcome-text {
            margin-bottom: 30px;
        }
        
        .welcome-text h2 {
            font-size: 24px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .features-list {
            list-style-type: none;
            margin-bottom: 40px;
        }
        
        .features-list li {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .features-list i {
            margin-right: 10px;
            color: var(--yellow);
            font-size: 18px;
        }
        
        .quote {
            font-style: italic;
            text-align: center;
            margin-top: auto;
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }
        
        .reset-right {
            flex: 1;
            background-color: var(--beige);
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .reset-header h2 {
            color: var(--dark-blue);
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .reset-header p {
            color: var(--gray);
        }
        
        .reset-form {
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-blue);
        }
        
        .input-with-icon {
            position: relative;
            width: 100%;
        }
        
        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        .input-with-icon input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            box-sizing: border-box;
        }
        
        .input-with-icon input:focus {
            border-color: var(--dark-blue);
            outline: none;
            box-shadow: 0 0 0 3px rgba(26, 58, 108, 0.1);
        }
        
        /* Password Toggle Button */
        .password-toggle {
            position: absolute;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            cursor: pointer;
            font-size: 16px;
            z-index: 10;
            background: transparent;
            border: none;
            padding: 0;
            margin: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }
        
        .password-toggle:hover {
            color: var(--dark-blue);
        }
        
        /* Updated remember-forgot section - REMOVED REMEMBER ME */
        .remember-forgot {
            display: flex;
            justify-content: flex-end; /* Align forgot password to right */
            align-items: center;
            margin-bottom: 20px;
        }
        
        .forgot-password {
            color: var(--dark-blue);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .forgot-password:hover {
            color: #2A4A7C;
            text-decoration: underline;
        }
        
        .btn-reset {
            width: 100%;
            padding: 14px;
            background: linear-gradient(to right, var(--dark-blue), #2A4A7C);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 10px rgba(26, 58, 108, 0.25);
        }
        
        .btn-reset:hover {
            background: linear-gradient(to right, #2A4A7C, var(--dark-blue));
            box-shadow: 0 6px 15px rgba(26, 58, 108, 0.35);
            transform: translateY(-2px);
        }
        
        .btn-reset:active {
            transform: translateY(0);
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-to-login a {
            color: var(--dark-blue);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .back-to-login a:hover {
            color: #2A4A7C;
            text-decoration: underline;
        }
        
        .message {
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            text-align: center;
            font-weight: 500;
        }
        
        .error-message {
            background-color: #F8D7DA;
            color: #721C24;
            border: 1px solid #F5C6CB;
        }
        
        .success-message {
            background-color: #D4EDDA;
            color: #155724;
            border: 1px solid #C3E6CB;
        }
        
        @media (max-width: 768px) {
            .reset-container {
                flex-direction: column;
                max-width: 100%;
            }
            
            .reset-left, .reset-right {
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-left">
            <div class="college-logo">
                <i class="fas fa-graduation-cap" style="font-size: 48px; color: var(--yellow);"></i>
                <h1>NovaTech FET College</h1>
            </div>
            
            <div class="welcome-text">
                <h2>Reset Your Password</h2>
                <p>Enter your Username or Email </p>
            </div>
            
            <ul class="features-list">
                <li><i class="fas fa-check-circle"></i> Secure password reset process</li>
                <li><i class="fas fa-check-circle"></i> One-time verification code</li>
                <li><i class="fas fa-check-circle"></i> Instant email delivery</li>
                <li><i class="fas fa-check-circle"></i> Easy password update</li>
                <li><i class="fas fa-check-circle"></i> Enhanced account security</li>
            </ul>
            
            <div class="quote">
                <p>"Your security is our top priority."</p>
            </div>
        </div>
        
        <div class="reset-right">
            <div class="reset-header">
                <h2>Forgot Your Password?</h2>
                <p>Enter your credentials to reset your password</p>
            </div>
            
            <!-- STEP 1: Enter Username/Email -->
            <div id="step1" class="reset-form">
                <div class="form-group">
                    <label for="username">Username or Email *</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" placeholder="Enter your Username or Email" required>
                    </div>
                </div>
                
                <button type="button" class="btn-reset" onclick="sendResetOTP()">Send Reset Code</button>
            </div>
            
            <!-- STEP 2: Enter OTP -->
            <div id="step2" class="reset-form" style="display: none;">
                <div class="form-group">
                    <label for="otp">Enter 6-Digit Reset Code *</label>
                    <div class="input-with-icon">
                        <i class="fas fa-key"></i>
                        <input type="text" id="otp" placeholder="Enter 6-digit code" maxlength="6" required>
                    </div>
                </div>
                
                <button type="button" class="btn-reset" onclick="verifyOTP()">Verify Code</button>
                <button type="button" class="btn-reset" style="background: #6C757D; margin-top: 10px;" onclick="showStep1()">← Back</button>
            </div>
            
            <!-- STEP 3: Set New Password -->
            <div id="step3" class="reset-form" style="display: none;">
                <div class="form-group">
                    <label for="new_password">New Password *</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="new_password" placeholder="Enter new password" required>
                        <button type="button" class="password-toggle" id="toggle-password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-requirements" style="font-size: 0.85em; margin-top: 5px;">
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
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password *</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" placeholder="Confirm new password" required>
                    </div>
                </div>
                
                <button type="button" class="btn-reset" onclick="updatePassword()">Update Password</button>
                <button type="button" class="btn-reset" style="background: #6C757D; margin-top: 10px;" onclick="showStep2()">← Back</button>
            </div>
            
            <div class="back-to-login">
                <a href="login.php">← Back to Login</a>
            </div>
        </div>
    </div>

    <script>
        let resetData = {};
        
        // Password toggle functionality
        document.getElementById('toggle-password')?.addEventListener('click', function() {
            const passwordInput = document.getElementById('new_password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon
            const eyeIcon = this.querySelector('i');
            if (eyeIcon.classList.contains('fa-eye')) {
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        });
        
        // Real-time password validation
        document.getElementById('new_password')?.addEventListener('input', function() {
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
        
        function sendResetOTP() {
            const username = document.getElementById('username').value.trim();
            
            if (!username) {
                showError('Please enter your email or matric number');
                return;
            }
            
            // Show loading state
            const resetBtn = document.querySelector('.btn-reset');
            const originalText = resetBtn.textContent;
            resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending Code...';
            resetBtn.disabled = true;
            
            // Send to server
            fetch('process_forgot_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'send_otp',
                    username: username
                })
            })
            .then(response => response.json())
            .then(data => {
                // Reset button
                resetBtn.innerHTML = originalText;
                resetBtn.disabled = false;
                
                if (data.success) {
                    resetData.username = username;
                    resetData.email = data.email;
                    showSuccess('Reset code sent to your email!');
                    showStep2();
                } else {
                    showError(data.message || 'User not found. Please check your email or matric number.');
                }
            })
            .catch(err => {
                // Reset button
                resetBtn.innerHTML = originalText;
                resetBtn.disabled = false;
                showError('Failed to send reset code. Please try again.');
                console.error('Error:', err);
            });
        }
        
        function verifyOTP() {
            const otp = document.getElementById('otp').value.trim();
            
            if (!otp) {
                showError('Please enter the 6-digit reset code');
                return;
            }
            
            // Show loading state
            const resetBtn = document.querySelector('.btn-reset');
            const originalText = resetBtn.textContent;
            resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying Code...';
            resetBtn.disabled = true;
            
            // Send to server
            fetch('process_forgot_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'verify_otp',
                    username: resetData.username,
                    otp: otp
                })
            })
            .then(response => response.json())
            .then(data => {
                // Reset button
                resetBtn.innerHTML = originalText;
                resetBtn.disabled = false;
                
                if (data.success) {
                    resetData.otp = otp;
                    showSuccess('Code verified! Please set your new password.');
                    showStep3();
                } else {
                    showError(data.message || 'Invalid or expired code. Please try again.');
                }
            })
            .catch(err => {
                // Reset button
                resetBtn.innerHTML = originalText;
                resetBtn.disabled = false;
                showError('Failed to verify code. Please try again.');
                console.error('Error:', err);
            });
        }
        
        function updatePassword() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Validate password
            if (!newPassword) {
                showError('Please enter a new password');
                return;
            }
            
            if (newPassword.length < 8) {
                showError('Password must be at least 8 characters');
                return;
            }
            
            if (!/[a-z]/.test(newPassword)) {
                showError('Password must contain at least one lowercase letter');
                return;
            }
            
            if (!/[A-Z]/.test(newPassword)) {
                showError('Password must contain at least one uppercase letter');
                return;
            }
            
            if (!/\d/.test(newPassword)) {
                showError('Password must contain at least one number');
                return;
            }
            
            if (!/[!@#$%^&*]/.test(newPassword)) {
                showError('Password must contain at least one special character (!@#$%^&*)');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showError('Passwords do not match');
                return;
            }
            
            // Show loading state
            const resetBtn = document.querySelector('.btn-reset');
            const originalText = resetBtn.textContent;
            resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating Password...';
            resetBtn.disabled = true;
            
            // Send to server
            fetch('process_forgot_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_password',
                    username: resetData.username,
                    otp: resetData.otp,
                    new_password: newPassword
                })
            })
            .then(response => response.json())
            .then(data => {
                // Reset button
                resetBtn.innerHTML = originalText;
                resetBtn.disabled = false;
                
                if (data.success) {
                    showSuccess('Password updated successfully! Redirecting to login...');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    showError(data.message || 'Failed to update password. Please try again.');
                }
            })
            .catch(err => {
                // Reset button
                resetBtn.innerHTML = originalText;
                resetBtn.disabled = false;
                showError('Failed to update password. Please try again.');
                console.error('Error:', err);
            });
        }
        
        function showStep1() {
            document.getElementById('step1').style.display = 'block';
            document.getElementById('step2').style.display = 'none';
            document.getElementById('step3').style.display = 'none';
            removeMessages();
        }
        
        function showStep2() {
            document.getElementById('step1').style.display = 'none';
            document.getElementById('step2').style.display = 'block';
            document.getElementById('step3').style.display = 'none';
            removeMessages();
        }
        
        function showStep3() {
            document.getElementById('step1').style.display = 'none';
            document.getElementById('step2').style.display = 'none';
            document.getElementById('step3').style.display = 'block';
            removeMessages();
        }
        
        function showError(message) {
            removeMessages();
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'message error-message';
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
            
            const resetForm = document.querySelector('.reset-form');
            if (resetForm) {
                resetForm.parentNode.insertBefore(errorDiv, resetForm.nextSibling);
            }
            
            setTimeout(() => {
                if (errorDiv.parentNode) {
                    errorDiv.parentNode.removeChild(errorDiv);
                }
            }, 5000);
        }
        
        function showSuccess(message) {
            removeMessages();
            
            const successDiv = document.createElement('div');
            successDiv.className = 'message success-message';
            successDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
            
            const resetForm = document.querySelector('.reset-form');
            if (resetForm) {
                resetForm.parentNode.insertBefore(successDiv, resetForm.nextSibling);
            }
            
            setTimeout(() => {
                if (successDiv.parentNode) {
                    successDiv.parentNode.removeChild(successDiv);
                }
            }, 5000);
        }
        
        function removeMessages() {
            const existingMessages = document.querySelectorAll('.message');
            existingMessages.forEach(msg => {
                if (msg.parentNode) {
                    msg.parentNode.removeChild(msg);
                }
            });
        }
    </script>
</body>
</html>