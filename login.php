<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NovaTech FET College - LMS Login</title>
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
        
        .login-container {
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
        
        .login-left {
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
        
        .login-left::before {
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
        
        .login-left::after {
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
        
        .login-right {
            flex: 1;
            background-color: var(--beige);
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            color: var(--dark-blue);
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: var(--gray);
        }
        
        .login-form {
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
            color: #2a4a7c;
            text-decoration: underline;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(to right, var(--dark-blue), #2a4a7c);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 10px rgba(26, 58, 108, 0.25);
        }
        
        .btn-login:hover {
            background: linear-gradient(to right, #2a4a7c, var(--dark-blue));
            box-shadow: 0 6px 15px rgba(26, 58, 108, 0.35);
            transform: translateY(-2px);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .role-info {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
            color: var(--gray);
        }
        
        .message {
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            text-align: center;
            font-weight: 500;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .admin-info {
            background-color: #e8f4fd;
            border: 1px solid #b6d7f9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .admin-info h3 {
            color: var(--dark-blue);
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .admin-info ul {
            list-style: none;
            padding-left: 0;
        }
        
        .admin-info li {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        
        .admin-info i {
            color: var(--dark-blue);
            margin-right: 8px;
            width: 16px;
        }
        
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 100%;
            }
            
            .login-left, .login-right {
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="college-logo">
                <i class="fas fa-graduation-cap" style="font-size: 48px; color: var(--yellow);"></i>
                <h1>NovaTech FET College</h1>
            </div>
            
            <div class="welcome-text">
                <h2>Welcome to LMS Portal</h2>
                <p>Access your educational resources and continue your learning journey</p>
            </div>
            
            <ul class="features-list">
                <li><i class="fas fa-check-circle"></i> Access to past exam papers and study guides</li>
                <li><i class="fas fa-check-circle"></i> Live and recorded lessons</li>
                <li><i class="fas fa-check-circle"></i> Personalized learning pathways</li>
                <li><i class="fas fa-check-circle"></i> Peer collaboration and support</li>
                <li><i class="fas fa-check-circle"></i> Progress tracking and analytics</li>
            </ul>
            
            <div class="quote">
                <p>"Success is not the absence of obstacles, but the courage to push through them."</p>
            </div>
        </div>
        
        <div class="login-right">
            <div class="login-header">
                <h2>Login to Your Account</h2>
                <p>Enter your credentials to access the portal</p>
            </div>
            
            <form class="login-form" id="loginForm">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" placeholder="Enter your username or email" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" id="password-toggle">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <!-- REMOVED REMEMBER ME OPTION -->
                <div class="remember-forgot">
                    <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn-login">Login</button>
            </form>
            
            <div class="role-info">
                <p>Your account type will be automatically detected</p>
            </div>
            
            <!-- REMOVED "Don't have an account? Enroll Now" -->
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.getElementById('password-toggle');
            
            // Password toggle functionality
            passwordToggle.addEventListener('click', function() {
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
            
            // Form submission
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value;
                
                // Basic validation
                if (!username || !password) {
                    showError('Please fill in all fields');
                    return;
                }
                
                // Show loading state
                const loginBtn = document.querySelector('.btn-login');
                const originalText = loginBtn.textContent;
                loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
                loginBtn.disabled = true;
                
                // Send to server for validation
                fetch('process_login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username: username,
                        password: password
                    })
                })
                .then(response => response.json())
                .then(data => {
                    // Reset button
                    loginBtn.innerHTML = originalText;
                    loginBtn.disabled = false;
                    
                    if (data.success) {
                        showSuccess('Login successful! Redirecting...');
                        
                        // Store user info in localStorage
                        localStorage.setItem('user_type', data.user_type);
                        localStorage.setItem('user_id', data.user_id);
                        localStorage.setItem('user_name', data.user_name);
                        
                        // Redirect to appropriate dashboard
                        setTimeout(() => {
                            switch(data.user_type) {
                                case 'student':
                                    window.location.href = 'student-dashboard.php';
                                    break;
                                case 'parent':
                                    window.location.href = 'parent_dashboard.php';
                                    break;
                                case 'admin':
                                    window.location.href = 'admin_dashboard.php';
                                    break;
                                case 'teacher':
                                    window.location.href = 'teacher_dashboard.php';
                                    break;
                                case 'content':
                                    window.location.href = 'content_dashboard.php';
                                    break;
                                default:
                                    window.location.href = 'student-dashboard.php';
                            }
                        }, 1500);
                    } else {
                        showError(data.message || 'Invalid username or password');
                    }
                })
                .catch(err => {
                    // Reset button
                    loginBtn.innerHTML = originalText;
                    loginBtn.disabled = false;
                    showError('Login failed. Please try again.');
                    console.error('Login error:', err);
                });
            });
            
            function showError(message) {
                removeMessages();
                
                const errorDiv = document.createElement('div');
                errorDiv.className = 'message error-message';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
                
                loginForm.parentNode.insertBefore(errorDiv, loginForm.nextSibling);
                
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
                
                loginForm.parentNode.insertBefore(successDiv, loginForm.nextSibling);
                
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
        });
    </script>
</body>
</html>