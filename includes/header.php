<?php
if (!isset($pageTitle)) {
    $pageTitle = 'MinglaDent';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
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
            background-color: #fffbee;
            min-height: 100vh;
        }

        /* Navigation */
        nav {
            background-color: #fffbee;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: transform 0.3s ease-in-out;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        nav.hidden {
            transform: translateY(-100%);
        }

        .logo {
            color: #6784c8;
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .logo-icon {
            font-size: 28px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 30px;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }

        .nav-links a {
            color: #6784c8;
            text-decoration: none;
            font-size: 16px;
            font-weight: 500;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .nav-links a::after {
            content: '▼';
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 10px;
            color: #000000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .nav-links a:hover::after {
            opacity: 1;
        }

        .book-btn {
            background-color: #6784c8;
            color: #fffbee;
            padding: 12px 24px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-decoration: none;
            border-radius: 5px;
            font-family: 'Montserrat', sans-serif;
        }

        .auth-buttons {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            color: #6784c8;
            font-size: 14px;
            font-weight: 500;
        }

        .auth-btn {
            padding: 8px 16px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-decoration: none;
            border-radius: 5px;
            font-family: 'Montserrat', sans-serif;
        }

        .auth-btn.login {
            background-color: transparent;
            color: #6784c8;
            border: 2px solid #6784c8;
        }

        .auth-btn.login:hover {
            background-color: #6784c8;
            color: #fffbee;
        }

        .auth-btn.register {
            background-color: #6784c8;
            color: #fffbee;
        }

        .auth-btn.register:hover {
            background-color: #5a6fa8;
        }

        .auth-btn.logout {
            background-color: #dc3545;
            color: #fffbee;
        }

        .auth-btn.logout:hover {
            background-color: #c82333;
        }

        /* User Avatar Circle */
        .user-avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6784c8, #43617f);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            border: 2px solid #6784c8;
        }

        /* Hamburger Icon */
        .menu-dropdown-container {
            position: relative;
        }

        .hamburger-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 2px solid #6784c8;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .hamburger-icon:hover {
            background: #f0f4ff;
        }

        .hamburger-icon span {
            width: 18px;
            height: 2px;
            background-color: #6784c8;
            border-radius: 2px;
        }

        /* Patient Menu Dropdown */
        .patient-menu-dropdown {
            display: none;
            position: absolute;
            top: 50px;
            right: 0;
            background: white;
            border: 2px solid #6784c8;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            min-width: 200px;
            z-index: 2000;
            overflow: hidden;
        }

        .patient-menu-dropdown.active {
            display: block;
        }

        .patient-menu-dropdown a {
            display: block;
            padding: 12px 20px;
            color: #6784c8;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s ease;
            border-bottom: 1px solid #e2e7ee;
        }

        .patient-menu-dropdown a:last-child {
            border-bottom: none;
        }

        .patient-menu-dropdown a:hover {
            background: #f0f4ff;
        }

        /* Responsive Navigation */
        @media (max-width: 768px) {
            nav {
                padding: 15px 20px;
            }

            .nav-links {
                display: none;
            }

            .logo {
                font-size: 20px;
            }

            .auth-buttons {
                gap: 10px;
            }

            .auth-btn {
                padding: 6px 12px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <nav>
        <a href="index.php" class="logo">
            <span class="logo-icon">🦷</span>
            MinglaDent
        </a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="about.php">About</a>
            <a href="services.php">Services</a>
            <a href="locations.php">Locations</a>
            <a href="contact.php">Contact</a>
        </div>
        <div class="auth-buttons">
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if (($_SESSION['role'] ?? '') === 'patient'): ?>
                <div class="user-avatar-circle"><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></div>
                <div class="menu-dropdown-container">
                    <button class="hamburger-icon" onclick="togglePatientMenu()">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <div class="patient-menu-dropdown" id="patientMenu">
                        <a href="patients/dashboard.php">Dashboard</a>
                        <a href="patients/appointments.php">My Appointments</a>
                        <a href="patients/history.php">Dental History</a>
                        <a href="patients/profile.php">My Profile</a>
                        <a href="locations.php">Book Appointment</a>
                        <a href="login/logout.php">Logout</a>
                    </div>
                </div>
                <?php else: ?>
                <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <a href="login/logout.php" class="auth-btn logout">Logout</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="login/login_new.php" class="auth-btn login">Login</a>
                <a href="login/register_new.php" class="auth-btn register">Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <script>
        let lastScrollTop = 0;
        const nav = document.querySelector('nav');

        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;

            if (scrollTop > lastScrollTop && scrollTop > 100) {
                nav.classList.add('hidden');
            } else {
                nav.classList.remove('hidden');
            }

            lastScrollTop = scrollTop;
        });

        // Toggle patient menu
        function togglePatientMenu() {
            const menu = document.getElementById('patientMenu');
            menu.classList.toggle('active');
        }

        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('patientMenu');
            const hamburger = document.querySelector('.hamburger-icon');
            if (menu && !menu.contains(event.target) && !hamburger.contains(event.target)) {
                menu.classList.remove('active');
            }
        });
    </script>
