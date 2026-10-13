<?php
function systemAdminStart(array $admin, string $title, string $activePage): void
{
    $navigation = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-home'],
        ['key' => 'clinics', 'label' => 'Clinics', 'href' => 'clinics.php', 'icon' => 'fa-hospital'],
        ['key' => 'users', 'label' => 'Users & staff', 'href' => 'users.php', 'icon' => 'fa-users'],
        ['key' => 'services', 'label' => 'Services & fees', 'href' => '../clinic_admin/services.php', 'icon' => 'fa-tooth'],
        ['key' => 'profile', 'label' => 'Profile', 'href' => '../clinic_admin/profile.php', 'icon' => 'fa-id-card'],
        ['key' => 'reports', 'label' => 'Reports', 'href' => '../clinic_admin/reports.php', 'icon' => 'fa-chart-line'],
        ['key' => 'activity', 'label' => 'Activity log', 'href' => '../clinic_admin/activity.php', 'icon' => 'fa-clipboard-list'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo clinicPortalEscape($title); ?> - MinglaDent System Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../clinic_portal/portal.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-brand"><i class="fas fa-tooth"></i><h1>MinglaDent</h1></div>
        <nav class="sidebar-nav" aria-label="System administration">
            <?php foreach ($navigation as $item): ?>
            <a href="<?php echo clinicPortalEscape($item['href']); ?>" class="<?php echo $activePage === $item['key'] ? 'active' : ''; ?>" <?php echo $activePage === $item['key'] ? 'aria-current="page"' : ''; ?>>
                <i class="fas <?php echo clinicPortalEscape($item['icon']); ?>"></i><span><?php echo clinicPortalEscape($item['label']); ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user"><strong><?php echo clinicPortalEscape($admin['name']); ?></strong><span>System Admin</span></div>
            <a href="../login/logout.php" class="btn-sidebar-logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>
    <main class="main-content">
        <header class="top-bar"><div class="page-title"><h2><?php echo clinicPortalEscape($title); ?></h2><p>System administration</p></div><div class="top-user"><i class="fas fa-user-shield"></i><span><?php echo clinicPortalEscape($admin['name']); ?></span></div></header>
<?php
}

function systemAdminEnd(): void
{
    ?>
    </main>
</body>
</html>
<?php
}