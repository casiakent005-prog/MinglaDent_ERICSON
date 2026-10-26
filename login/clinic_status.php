<?php
require_once __DIR__ . '/../database/config.php';
if (!hasRole('clinic')) { http_response_code(403); exit('Clinic access is required.'); }
$application = authClinicAssignment((int) $_SESSION['user_id']);
if ($application && $application['clinic_status'] === 'approved' && $application['account_status'] === 'active') {
    redirect(authDestination(getCurrentUser()));
}
$status = $application['clinic_status'] ?? 'unassigned';
$notices = [
    'pending' => ['Pending Approval', 'Your clinic application has been received. A MinglaDent System Admin will review the information and supporting documents. Clinic management and public booking become available after approval.'],
    'rejected' => ['Application Rejected', 'Your clinic application was rejected. Review the reason below and contact MinglaDent through the Contact page to discuss corrections and a new review.'],
    'suspended' => ['Restricted Access — Clinic Suspended', 'Your clinic is suspended. Its listing and clinic management access are unavailable. Contact MinglaDent to discuss the restriction.'],
    'unassigned' => ['Clinic Assignment Required', 'Your account has no clinic assignment. Contact MinglaDent for assistance.'],
    'approved' => ['Account Pending', 'Your clinic is approved, but this account requires activation. Contact MinglaDent.'],
];
[$title, $notice] = $notices[$status] ?? $notices['unassigned'];
$documents = [];
if ($application && (int) $application['owner_user_id'] === (int) $_SESSION['user_id']) {
    $stmt = $pdo->prepare('SELECT document_id, document_label, original_name FROM clinic_application_documents WHERE clinic_id = ? ORDER BY document_id');
    $stmt->execute([$application['clinic_id']]);
    $documents = $stmt->fetchAll();
}
header('Cache-Control: no-store');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo authEscape($title); ?> - MinglaDent</title><link rel="stylesheet" href="../clinic_portal/portal.css"></head>
<body><main style="max-width:720px;margin:8vh auto;padding:20px"><section class="card"><h1 style="color:#6784c8">MinglaDent</h1><h2><?php echo authEscape($title); ?></h2>
<?php if ($application): ?><h3><?php echo authEscape($application['clinic_name']); ?></h3><p>Application #<?php echo (int) $application['clinic_id']; ?></p><?php endif; ?>
<p style="line-height:1.8;margin:20px 0"><?php echo authEscape($notice); ?></p>
<?php if (!empty($application['rejection_reason'])): ?><p><strong>Review reason:</strong> <?php echo nl2br(authEscape($application['rejection_reason'])); ?></p><?php endif; ?>
<?php if (!empty($application['reviewed_at'])): ?><p>Last reviewed: <?php echo authEscape($application['reviewed_at']); ?></p><?php endif; ?>
<?php if ($documents): ?><h3>Submitted documents</h3><ul><?php foreach ($documents as $document): ?><li><a href="clinic_document.php?id=<?php echo (int) $document['document_id']; ?>"><?php echo authEscape($document['document_label'] . ' — ' . $document['original_name']); ?></a></li><?php endforeach; ?></ul><?php endif; ?>
<div class="actions" style="margin-top:24px"><a class="button-link" href="../contact.php">Contact MinglaDent</a><a class="button-link secondary" href="clinic_status.php">Check status</a><a class="button-link secondary" href="logout.php">Sign out</a></div>
</section></main></body></html>
