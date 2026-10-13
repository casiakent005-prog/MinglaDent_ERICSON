<?php
require_once 'bootstrap.php';
require_once 'layout.php';

$patient = patientPortalContext();
$search = trim($_GET['q'] ?? '');
$pattern = '%' . $search . '%';
$stmt = $pdo->prepare("SELECT c.clinic_id, c.clinic_name, c.address, c.contact_number, c.email, c.operating_hours, c.map_location, GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR ', ') AS service_names, MIN(sf.amount) AS lowest_fee, MAX(sf.amount) AS highest_fee, AVG(r.rating_average) AS average_rating FROM clinics c LEFT JOIN service_fees sf ON sf.clinic_id = c.clinic_id AND EXISTS (SELECT 1 FROM services active_service WHERE active_service.service_id = sf.service_id AND active_service.status = 'active') LEFT JOIN services s ON s.service_id = sf.service_id AND s.status = 'active' LEFT JOIN (SELECT clinic_id, AVG(rating) AS rating_average FROM clinic_ratings WHERE status = 'published' GROUP BY clinic_id) r ON r.clinic_id = c.clinic_id WHERE c.status = 'approved' AND (? = '' OR c.clinic_name LIKE ? OR c.address LIKE ? OR s.service_name LIKE ?) GROUP BY c.clinic_id ORDER BY c.clinic_name LIMIT 50");
$stmt->execute([$search, $pattern, $pattern, $pattern]);
$clinics = $stmt->fetchAll();

patientPortalStart($patient, 'Find a clinic', 'clinics');
?>
<section class="card">
    <form method="get" class="actions">
        <input type="search" name="q" value="<?php echo patientPortalEscape($search); ?>" placeholder="Search clinic, location, or service" aria-label="Search clinics">
        <button class="button" type="submit"><i class="fas fa-search"></i> Search</button>
        <?php if ($search !== ''): ?><a class="button-link secondary" href="clinics.php">Clear</a><?php endif; ?>
    </form>
</section>

<?php if (!$clinics): ?>
    <section class="card"><p class="empty-state">No approved clinics match this search. Contact the clinic administrator if you expected a clinic to be listed.</p></section>
<?php else: ?>
<section class="content-grid">
    <?php foreach ($clinics as $clinic): ?>
    <article class="card">
        <h3><?php echo patientPortalEscape($clinic['clinic_name']); ?></h3>
        <p><?php echo patientPortalEscape($clinic['address']); ?></p>
        <p><?php echo patientPortalEscape($clinic['contact_number']); ?> | <?php echo patientPortalEscape($clinic['email']); ?></p>
        <p>Hours: <?php echo patientPortalEscape($clinic['operating_hours'] ?: 'Contact clinic'); ?></p>
        <p>Services: <?php echo patientPortalEscape($clinic['service_names'] ?: 'Contact clinic for services'); ?></p>
        <p>Fees: <?php echo $clinic['lowest_fee'] === null ? 'Not listed' : '₱' . number_format((float) $clinic['lowest_fee'], 2) . ($clinic['highest_fee'] > $clinic['lowest_fee'] ? ' - ₱' . number_format((float) $clinic['highest_fee'], 2) : ''); ?></p>
        <?php if ($clinic['average_rating'] !== null): ?><p><i class="fas fa-star"></i> <?php echo number_format((float) $clinic['average_rating'], 1); ?> / 5</p><?php endif; ?>
        <div class="actions">
            <a class="button-link" href="appointments.php?clinic_id=<?php echo (int) $clinic['clinic_id']; ?>"><i class="fas fa-calendar-plus"></i> Book appointment</a>
            <?php if ($clinic['map_location'] && filter_var($clinic['map_location'], FILTER_VALIDATE_URL) && str_starts_with($clinic['map_location'], 'https://')): ?><a class="button-link secondary" href="<?php echo patientPortalEscape($clinic['map_location']); ?>" target="_blank" rel="noopener">Map</a><?php endif; ?>
        </div>
    </article>
    <?php endforeach; ?>
</section>
<?php endif; ?>
<?php patientPortalEnd(); ?>