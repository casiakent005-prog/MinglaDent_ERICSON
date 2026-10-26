<?php
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../includes/registration.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MinglaDent</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
        }

        .register-container {
            display: flex;
            width: 100%;
            max-width: 1200px;
            min-height: 100vh;
            background: #fff;
            position: relative;
        }

        /* Left Panel - Registration Form */
        .register-form-panel {
            flex: 1;
            padding: 40px 60px 60px 60px;
            display: flex;
            flex-direction: column;
            background: #fff;
            position: relative;
            z-index: 2;
            overflow-y: auto;
            height: 100vh;
        }

        /* Right Panel - Image */
        .register-image-panel {
            flex: 1;
            background: url('https://images.unsplash.com/photo-1629909613654-28e377c37b09?w=800&q=80') center/cover no-repeat;
            position: relative;
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
            padding: 40px;
        }

        .register-image-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(103, 132, 200, 0.3) 0%, rgba(64, 224, 208, 0.3) 100%);
        }

        .brand-logo {
            position: relative;
            z-index: 2;
            color: #fff;
            font-size: 28px;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        /* Curved Background Shapes */
        .bg-shape {
            position: absolute;
            border-radius: 50%;
            z-index: 1;
        }

        .bg-shape-1 {
            width: 600px;
            height: 600px;
            background: linear-gradient(135deg, #6784c8 0%, #5a6fa8 100%);
            top: -200px;
            right: -200px;
            opacity: 0.1;
        }

        .bg-shape-2 {
            width: 400px;
            height: 400px;
            background: linear-gradient(135deg, #40e0d0 0%, #48d1cc 100%);
            bottom: -150px;
            left: -150px;
            opacity: 0.15;
        }

        .register-title {
            color: #6784c8;
            font-size: 36px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .register-subtitle {
            color: #666;
            font-size: 15px;
            margin-bottom: 30px;
        }

        .section-title {
            color: #6784c8;
            font-size: 18px;
            font-weight: 600;
            margin: 25px 0 15px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #6784c8;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
        }

        .form-group label .required {
            color: #dc3545;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            font-size: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            background: #fff;
            color: #333;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #6784c8;
            box-shadow: 0 0 0 3px rgba(103, 132, 200, 0.1);
        }

        .input-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6784c8;
            font-size: 16px;
        }

        select.form-control {
            cursor: pointer;
        }

        /* Password Strength Indicator */
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s ease, background-color 0.3s ease;
        }

        .strength-weak { background-color: #dc3545; }
        .strength-medium { background-color: #ffc107; }
        .strength-strong { background-color: #28a745; }

        .strength-text {
            font-size: 12px;
            margin-top: 4px;
            color: #666;
        }

        /* Terms Checkbox */
        .terms-checkbox {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 20px 0;
        }

        .terms-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            margin-top: 2px;
        }

        .terms-checkbox label {
            font-size: 13px;
            color: #666;
            margin: 0;
            line-height: 1.5;
            cursor: pointer;
        }

        .terms-checkbox a {
            color: #6784c8;
            text-decoration: none;
        }

        .terms-checkbox a:hover {
            text-decoration: underline;
        }

        .btn-register {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #6784c8 0%, #5a6fa8 100%);
            color: #fff;
            border: none;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(103, 132, 200, 0.3);
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(103, 132, 200, 0.4);
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }

        .login-link a {
            color: #6784c8;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .back-link {
            text-align: center;
            margin-top: 15px;
        }

        .back-link a {
            color: #6784c8;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Validation Messages */
        .validation-message {
            font-size: 12px;
            margin-top: 4px;
            display: none;
        }

        .validation-message.valid {
            color: #28a745;
            display: block;
        }

        .validation-message.invalid {
            color: #dc3545;
            display: block;
        }

        .input-valid {
            border-color: #28a745 !important;
        }

        .input-invalid {
            border-color: #dc3545 !important;
        }

        /* Responsive Design */
        @media (max-width: 968px) {
            .register-container {
                flex-direction: column;
            }

            .register-image-panel {
                display: none;
            }

            .register-form-panel {
                padding: 30px 25px;
                max-height: none;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .register-form-panel {
                padding: 25px 20px;
            }

            .register-title {
                font-size: 28px;
            }
        }
        .registration-options { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 12px; margin: 12px 0 24px; }
        .registration-option { display:flex; flex-direction:column; gap:10px; border:2px solid #dbe4f4; border-radius:10px; padding:18px 12px; text-decoration:none; color:#43617f; font-size:13px; line-height:1.5; }
        .registration-option i { color:#6784c8; font-size:24px; }
        .registration-option:hover, .registration-option.selected { border-color:#6784c8; background:#f1f6ff; }
        .review-notice { color:#52647d; font-size:13px; line-height:1.7; margin-bottom:20px; }
        textarea.form-control { resize:vertical; font:inherit; }
        .form-group small { display:block; margin-top:8px; line-height:1.6; color:#52647d; }
        @media(max-width:420px) { .registration-options {grid-template-columns:1fr;} }
    </style>
</head>
<body>
    <div class="register-container">
        <!-- Background Shapes -->
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <!-- Left Panel - Registration Form -->
        <div class="register-form-panel">
            <h1 class="register-title">Create Account</h1>
            <p class="register-subtitle">Fill in your details to register with MinglaDent</p>

            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php require __DIR__ . '/../includes/registration_form.php'; ?>

            <p class="login-link">
                Already have an account? <a href="login_new.php">Sign In</a>
            </p>

            <div class="back-link">
                <a href="../index.php">← Back to Home</a>
            </div>
        </div>

        <!-- Right Panel - Image -->
        <div class="register-image-panel">
            <div class="brand-logo">MinglaDent</div>
        </div>
    </div>

    <script>
        if (document.getElementById('password')) {
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const confirmPassword = document.getElementById('confirmPassword');
            const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPassword.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        document.querySelectorAll('.password-toggle').forEach(toggle => {
            toggle.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); toggle.click(); }
            });
        });
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;

            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;

            strengthBar.style.width = (strength * 25) + '%';

            if (strength <= 1) {
                strengthBar.className = 'password-strength-bar strength-weak';
                strengthText.textContent = 'Weak';
                strengthText.style.color = '#dc3545';
            } else if (strength <= 2) {
                strengthBar.className = 'password-strength-bar strength-medium';
                strengthText.textContent = 'Medium';
                strengthText.style.color = '#ffc107';
            } else {
                strengthBar.className = 'password-strength-bar strength-strong';
                strengthText.textContent = 'Strong';
                strengthText.style.color = '#28a745';
            }

            if (password.length === 0) {
                strengthBar.style.width = '0';
                strengthText.textContent = '';
            }
        });

        // Email validation
        const emailInput = document.getElementById('email');
        emailInput.addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const messageDiv = document.getElementById('emailMessage');

            if (email && !emailRegex.test(email)) {
                messageDiv.textContent = 'Invalid email format';
                messageDiv.className = 'validation-message invalid';
                this.classList.add('input-invalid');
            } else if (email) {
                // Check if email exists via AJAX
                fetch('check_email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'email=' + encodeURIComponent(email)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        messageDiv.textContent = 'Email already registered';
                        messageDiv.className = 'validation-message invalid';
                        this.classList.add('input-invalid');
                    } else {
                        messageDiv.textContent = '✓ Email available';
                        messageDiv.className = 'validation-message valid';
                        this.classList.remove('input-invalid');
                        this.classList.add('input-valid');
                    }
                })
                .catch(() => {
                    messageDiv.textContent = '';
                    this.classList.remove('input-invalid', 'input-valid');
                });
            } else {
                messageDiv.textContent = '';
                this.classList.remove('input-invalid', 'input-valid');
            }
        });

        // Username validation
        const usernameInput = document.getElementById('username');
        usernameInput.addEventListener('blur', function() {
            const username = this.value;
            const messageDiv = document.getElementById('usernameMessage');

            if (username) {
                // Check if username exists via AJAX
                fetch('check_username.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'username=' + encodeURIComponent(username)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        messageDiv.textContent = 'Username already taken';
                        messageDiv.className = 'validation-message invalid';
                        this.classList.add('input-invalid');
                    } else {
                        messageDiv.textContent = '✓ Username available';
                        messageDiv.className = 'validation-message valid';
                        this.classList.remove('input-invalid');
                        this.classList.add('input-valid');
                    }
                })
                .catch(() => {
                    messageDiv.textContent = '';
                    this.classList.remove('input-invalid', 'input-valid');
                });
            } else {
                messageDiv.textContent = '';
                this.classList.remove('input-invalid', 'input-valid');
            }
        });

        // Confirm password validation
        const confirmPasswordInput = document.getElementById('confirmPassword');
        confirmPasswordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirmPassword = this.value;
            const messageDiv = document.getElementById('confirmPasswordMessage');

            if (confirmPassword && password !== confirmPassword) {
                messageDiv.textContent = 'Passwords do not match';
                messageDiv.className = 'validation-message invalid';
                this.classList.add('input-invalid');
            } else if (confirmPassword) {
                messageDiv.textContent = '✓ Passwords match';
                messageDiv.className = 'validation-message valid';
                this.classList.remove('input-invalid');
                this.classList.add('input-valid');
            } else {
                messageDiv.textContent = '';
                this.classList.remove('input-invalid', 'input-valid');
            }
        });
        }
    </script>
</body>
</html>
