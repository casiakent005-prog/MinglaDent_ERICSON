<?php
require_once '../clinic_portal/bootstrap.php';
require_once '../clinic_portal/layout.php';

$context = clinicPortalRequireRoles(['dentist']);
$view = ($_GET['view'] ?? 'day') === 'week' ? 'week' : 'day';
$requestedDate = $_GET['date'] ?? date('Y-m-d');
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $requestedDate);
if (!$date || $date->format('Y-m-d') !== $requestedDate) {
    $date = new DateTimeImmutable('today');
}

if ($view === 'week') {
    $rangeStart = $date->modify('monday this week');
    $rangeEnd = $rangeStart->modify('+7 days');
    $previousDate = $rangeStart->modify('-7 days')->format('Y-m-d');
    $nextDate = $rangeStart->modify('+7 days')->format('Y-m-d');
} else {
    $rangeStart = $date->setTime(0, 0);
    $rangeEnd = $rangeStart->modify('+1 day');
    $previousDate = $rangeStart->modify('-1 day')->format('Y-m-d');
    $nextDate = $rangeStart->modify('+1 day')->format('Y-m-d');
}

$stmt = $pdo->prepare("SELECT a.appointment_id, a.appointment_date, a.status, a.notes, p.patient_id, p.name AS patient_name, p.phone AS patient_phone FROM appointments a INNER JOIN patients p ON p.patient_id = a.patient_id WHERE a.clinic_id = ? AND a.staff_id = ? AND a.appointment_date >= ? AND a.appointment_date < ? ORDER BY a.appointment_date ASC");
$stmt->execute([$context['clinic_id'], $context['staff_id'], $rangeStart->format('Y-m-d H:i:s'), $rangeEnd->format('Y-m-d H:i:s')]);
$appointments = $stmt->fetchAll();

$navigation = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-home'],
    ['key' => 'schedule', 'label' => 'My Schedule', 'href' => 'schedule.php', 'icon' => 'fa-calendar-alt'],
    ['key' => 'patients', 'label' => 'Patients', 'href' => 'patients.php', 'icon' => 'fa-user-injured'],
    ['key' => 'records', 'label' => 'Dental Records', 'href' => 'records.php', 'icon' => 'fa-notes-medical'],
    ['key' => 'treatments', 'label' => 'Treatment plans', 'href' => 'treatments.php', 'icon' => 'fa-tooth'],
    ['key' => 'profile', 'label' => 'Profile', 'href' => 'profile.php', 'icon' => 'fa-id-card'],
];

clinicPortalStart($context, 'My Schedule', 'schedule', $navigation);
?>
<section class="card">
    <div class="week-nav">
        <a class="button-link secondary" href="?view=<?php echo $view; ?>&date=<?php echo clinicPortalEscape($previousDate); ?>"><i class="fas fa-chevron-left"></i> Previous</a>
        <strong><?php echo clinicPortalEscape($rangeStart->format('M j')); ?> - <?php echo clinicPortalEscape($rangeEnd->modify('-1 day')->format('M j, Y')); ?></strong>
        <a class="button-link secondary" href="?view=<?php echo $view; ?>&date=<?php echo clinicPortalEscape($nextDate); ?>">Next <i class="fas fa-chevron-right"></i></a>
    </div>
    <div class="actions" style="margin-bottom: 18px;">
        <a class="button-link <?php echo $view === 'day' ? '' : 'secondary'; ?>" href="?view=day&date=<?php echo clinicPortalEscape($date->format('Y-m-d')); ?>">Day</a>
        <a class="button-link <?php echo $view === 'week' ? '' : 'secondary'; ?>" href="?view=week&date=<?php echo clinicPortalEscape($date->format('Y-m-d')); ?>">Week</a>
        <form method="get" class="actions">
            <input type="hidden" name="view" value="<?php echo clinicPortalEscape($view); ?>">
            <input type="date" name="date" value="<?php echo clinicPortalEscape($date->format('Y-m-d')); ?>" aria-label="Choose schedule date">
            <button class="button secondary" type="submit">Go</button>
        </form>
    </div>
    <?php if (!$appointments): ?>
        <p class="empty-state">No appointments are assigned to you during this period.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Date and time</th><th>Patient</th><th>Contact</th><th>Status</th><th>Notes</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($appointments as $appointment): ?>
            <tr>
                <td><?php echo clinicPortalEscape(date('D, M j, Y g:i A', strtotime($appointment['appointment_date']))); ?></td>
                <td><?php echo clinicPortalEscape($appointment['patient_name']); ?></td>
                <td><?php echo clinicPortalEscape($appointment['patient_phone']); ?></td>
                <td><span class="badge <?php echo clinicPortalEscape($appointment['status']); ?>"><?php echo clinicPortalEscape($appointment['status']); ?></span></td>
                <td><?php echo clinicPortalEscape($appointment['notes'] ?: ''); ?></td>
                <td><a class="button-link secondary" href="records.php?patient_id=<?php echo (int) $appointment['patient_id']; ?>">Patient record</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php clinicPortalEnd(); ?>