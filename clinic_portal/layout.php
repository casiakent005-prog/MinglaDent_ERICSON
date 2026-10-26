<?php
function clinicPortalStart(array $context, string $title, string $activePage, array $navigation): void
{
    $navigationByKey = [];
    foreach ($navigation as $item) {
        $navigationByKey[$item['key']] = $item;
    }

    $baseOrder = $context['portal_role'] === 'dentist'
        ? ['dashboard', 'schedule', 'patients', 'records']
        : ($context['portal_role'] === 'assistant' ? ['dashboard', 'patients', 'appointments', 'payments', 'activity', 'reports'] : ['dashboard', 'patients', 'appointments', 'payments']);
    $orderedNavigation = [];
    foreach ($baseOrder as $key) {
        if (isset($navigationByKey[$key])) {
            $orderedNavigation[] = $navigationByKey[$key];
            unset($navigationByKey[$key]);
        }
    }

    if ($context['portal_role'] !== 'assistant') {
        $orderedNavigation[] = $navigationByKey['clinic'] ?? ['key' => 'clinic', 'label' => 'My clinic & services', 'href' => '../clinic_portal/manage_clinic.php', 'icon' => 'fa-hospital'];
        unset($navigationByKey['clinic']);
    }

    $profileHref = $context['portal_role'] === 'assistant'
        ? '../clinic_assistant/profile.php'
        : ($context['portal_role'] === 'dentist' ? '../clinic_dentist/profile.php' : '../clinic_admin/profile.php');
    $orderedNavigation[] = ['key' => 'profile', 'label' => 'Profile', 'href' => $profileHref, 'icon' => 'fa-id-card'];
    unset($navigationByKey['profile']);

    if (($context['staff_role'] ?? '') === 'clinic_admin') {
        $orderedNavigation[] = ['key' => 'administration', 'label' => 'Clinic administration', 'href' => '../clinic_admin/dashboard.php', 'icon' => 'fa-hospital'];
    }
    $navigation = array_merge($orderedNavigation, array_values($navigationByKey));

    $roleLabel = $context['portal_role'] === 'dentist'
        ? 'Dentist'
        : ($context['portal_role'] === 'assistant' ? 'Clinic Assistant' : 'Dental Assistant / Cashier / Staff');
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
        <nav class="sidebar-nav" aria-label="Clinic workspace">
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
            <div class="top-user"><i class="fas fa-user-circle"></i><span><?php echo clinicPortalEscape($context['name']); ?></span></div>
        </header>
<?php
}

function clinicPortalEnd(): void
{
    ?>
    </main>
</body>
</html>
<?php
}