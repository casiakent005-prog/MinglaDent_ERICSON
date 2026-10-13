<?php
require_once '../database/config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
    $sex = trim($_POST['sex'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $completeAddress = trim($_POST['complete_address'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $termsAgreed = isset($_POST['terms_agreed']);

    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($contactNumber)) {
        $message = 'First name, last name, email, and contact number are required.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters long.';
        $messageType = 'error';
    } elseif ($password !== $confirmPassword) {
        $message = 'Passwords do not match.';
        $messageType = 'error';
    } elseif (!$termsAgreed) {
        $message = 'You must agree to the Terms and Conditions and Privacy Policy.';
        $messageType = 'error';
    } else {
        try {
            $pdo->beginTransaction();

            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM auth_users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $message = 'Email already registered. Please use a different email or login.';
                $messageType = 'error';
                $pdo->rollBack();
            } else {
                // Check if username already exists
                if (!empty($username)) {
                    $stmt = $pdo->prepare("SELECT id FROM auth_users WHERE username = ? LIMIT 1");
                    $stmt->execute([$username]);
                    if ($stmt->fetch()) {
                        $message = 'Username already taken. Please choose a different username.';
                        $messageType = 'error';
                        $pdo->rollBack();
                    }
                }

                if (empty($message)) {
                    // Create full name from parts
                    $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName);
                    $fullName = preg_replace('/\s+/', ' ', $fullName); // Remove extra spaces

                    // Insert into auth_users
                    $stmt = $pdo->prepare("INSERT INTO auth_users (name, first_name, middle_name, last_name, email, username, password, role, status, date_of_birth, sex, contact_number, complete_address, auth_provider) VALUES (?, ?, ?, ?, ?, ?, ?, 'patient', 'active', ?, ?, ?, ?, 'email')");
                    $stmt->execute([
                        $fullName,
                        $firstName ?: null,
                        $middleName ?: null,
                        $lastName,
                        $email,
                        $username ?: null,
                        password_hash($password, PASSWORD_DEFAULT),
                        $dateOfBirth ?: null,
                        $sex ?: null,
                        $contactNumber,
                        $completeAddress ?: null
                    ]);

                    $userId = (int) $pdo->lastInsertId();

                    // Insert into patient_profiles for backward compatibility
                    $stmt = $pdo->prepare("INSERT INTO patient_profiles (user_id, mobile_number, address, date_of_birth, privacy_consent_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([
                        $userId,
                        $contactNumber,
                        $completeAddress ?: null,
                        $dateOfBirth ?: null
                    ]);

                    $pdo->commit();

                    $message = 'Registration successful! Please log in with your credentials.';
                    $messageType = 'success';
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = 'Registration failed. Please try again.';
            $messageType = 'error';
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
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

            <form method="POST">
                <h3 class="section-title">Personal Information</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" class="form-control" required autocomplete="given-name">
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" class="form-control" autocomplete="additional-name">
                    </div>
                </div>

                <div class="form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" class="form-control" required autocomplete="family-name">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Sex</label>
                        <select name="sex" class="form-control">
                            <option value="">Select</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Contact Number <span class="required">*</span></label>
                        <input type="tel" name="contact_number" class="form-control" required autocomplete="tel">
                    </div>
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" id="email" class="form-control" required autocomplete="email">
                        <span id="emailMessage" class="validation-message"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Complete Address</label>
                    <input type="text" name="complete_address" class="form-control" autocomplete="street-address">
                </div>

                <h3 class="section-title">Account Information</h3>

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="username" class="form-control" autocomplete="username">
                    <span id="usernameMessage" class="validation-message"></span>
                </div>

                <div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="password" class="form-control" required minlength="8" autocomplete="new-password">
                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                    </div>
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                    <p class="strength-text" id="strengthText"></p>
                </div>

                <div class="form-group">
                    <label>Confirm Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="password" name="confirm_password" id="confirmPassword" class="form-control" required minlength="8" autocomplete="new-password">
                        <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
                    </div>
                    <span id="confirmPasswordMessage" class="validation-message"></span>
                </div>

                <div class="terms-checkbox">
                    <input type="checkbox" name="terms_agreed" id="termsAgreed" required>
                    <label for="termsAgreed">
                        I agree to the <a href="#">Terms and Conditions</a> and <a href="#">Privacy Policy</a> of MinglaDent.
                    </label>
                </div>

                <button type="submit" class="btn-register">Create Account</button>
            </form>

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
    </script>
</body>
</html>
