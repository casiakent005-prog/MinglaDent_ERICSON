<?php
function patientPortalStart(array $patient, string $title, string $activePage): void
{
    $navigation = [
        ['key' => 'dashboard', 'label' => 'My dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-home'],
        ['key' => 'clinics', 'label' => 'Find a clinic', 'href' => 'clinics.php', 'icon' => 'fa-hospital'],
        ['key' => 'appointments', 'label' => 'Appointments', 'href' => 'appointments.php', 'icon' => 'fa-calendar-alt'],
        ['key' => 'history', 'label' => 'My history', 'href' => 'history.php', 'icon' => 'fa-notes-medical'],
        ['key' => 'profile', 'label' => 'My profile', 'href' => 'profile.php', 'icon' => 'fa-user-circle'],
        ['key' => 'home', 'label' => 'Back to Home', 'href' => '../index.php', 'icon' => 'fa-arrow-left', 'external' => true],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo patientPortalEscape($title); ?> - MinglaDent</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../clinic_portal/portal.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-brand"><i class="fas fa-tooth"></i><h1>MinglaDent</h1></div>
        <nav class="sidebar-nav" aria-label="Patient portal">
            <?php foreach ($navigation as $item): ?>
            <a href="<?php echo patientPortalEscape($item['href']); ?>" class="<?php echo $activePage === $item['key'] ? 'active' : ''; ?>" <?php echo $activePage === $item['key'] ? 'aria-current="page"' : ''; ?>>
                <i class="fas <?php echo patientPortalEscape($item['icon']); ?>"></i><span><?php echo patientPortalEscape($item['label']); ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user"><strong><?php echo patientPortalEscape($patient['name']); ?></strong><span>Patient account</span></div>
            <a href="../login/logout.php" class="btn-sidebar-logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>
    <main class="main-content">
        <header class="top-bar">
            <div class="page-title"><h2><?php echo patientPortalEscape($title); ?></h2><p>Your dental care, in one place</p></div>
            <div class="top-user"><i class="fas fa-user-circle"></i><span><?php echo patientPortalEscape($patient['name']); ?></span></div>
        </header>
<?php
}

function patientPortalEnd(): void
{
    ?>
    </main>
</body>
</html>
<?php
}