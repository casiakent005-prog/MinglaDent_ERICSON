<?php
require_once __DIR__ . '/../clinic_portal/bootstrap.php';

function clinicAdminStart(array $context, string $title, string $activePage, array $navigation = null): void
{
    if ($navigation === null) {
        $navigation = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-home'],
            ['key' => 'clinics', 'label' => 'Clinics', 'href' => 'clinics.php', 'icon' => 'fa-hospital'],
            ['key' => 'staff', 'label' => 'Staff', 'href' => 'clinic_staff.php', 'icon' => 'fa-users'],
            ['key' => 'services', 'label' => 'Services', 'href' => 'services.php', 'icon' => 'fa-tooth'],
            ['key' => 'fees', 'label' => 'Fees', 'href' => 'fees.php', 'icon' => 'fa-dollar-sign'],
            ['key' => 'profile', 'label' => 'Profile', 'href' => 'profile.php', 'icon' => 'fa-id-card'],
            ['key' => 'reports', 'label' => 'Reports', 'href' => 'reports.php', 'icon' => 'fa-chart-line'],
            ['key' => 'activity', 'label' => 'Activity log', 'href' => 'activity.php', 'icon' => 'fa-clipboard-list'],
        ];
    }

    // Override role label for clinic admin
    $roleLabel = $context['portal_role'] === 'admin'
        ? 'System Admin'
        : 'Clinic Admin';

    $navigationByKey = [];
    foreach ($navigation as $item) {
        $navigationByKey[$item['key']] = $item;
    }

    // Remove clinics from navigation if not admin
    if ($context['portal_role'] !== 'admin' && isset($navigationByKey['clinics'])) {
        unset($navigationByKey['clinics']);
    }

    if ($context['portal_role'] === 'clinic_admin') {
        $navigationByKey['clinic'] = ['key' => 'clinic', 'label' => 'My clinic & services', 'href' => '../clinic_portal/manage_clinic.php', 'icon' => 'fa-hospital'];
        if (!empty($context['is_dentist'])) {
            $navigationByKey['dentist'] = ['key' => 'dentist', 'label' => 'Dentist dashboard', 'href' => '../clinic_dentist/dashboard.php', 'icon' => 'fa-user-md'];
        }
    }
    $navigation = array_values($navigationByKey);

    // Manually build the layout since clinicPortalStart has different role handling
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo clinicPortalEscape($title); ?> - MinglaDent</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../clinic_portal/portal.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-brand"><i class="fas fa-tooth"></i><h1>MinglaDent</h1></div>
        <nav class="sidebar-nav" aria-label="Clinic administration">
            <?php foreach ($navigation as $item): ?>
            <a href="<?php echo clinicPortalEscape($item['href']); ?>" class="<?php echo $activePage === $item['key'] ? 'active' : ''; ?>" <?php echo $activePage === $item['key'] ? 'aria-current="page"' : ''; ?>>
                <i class="fas <?php echo clinicPortalEscape($item['icon']); ?>"></i><span><?php echo clinicPortalEscape($item['label']); ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user"><strong><?php echo clinicPortalEscape($context['name']); ?></strong><span><?php echo clinicPortalEscape($roleLabel); ?></span></div>
            <a href="../login/logout.php" class="btn-sidebar-logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>
    <main class="main-content">
        <header class="top-bar">
            <div class="page-title"><h2><?php echo clinicPortalEscape($title); ?></h2><p><?php echo clinicPortalEscape($context['clinic_name']); ?></p></div>
            <div class="top-user"><i class="fas fa-user-shield"></i><span><?php echo clinicPortalEscape($context['name']); ?></span></div>
        </header>
<?php
}

function clinicAdminEnd(): void
{
    ?>
    </main>
</body>
</html>
<?php
}
