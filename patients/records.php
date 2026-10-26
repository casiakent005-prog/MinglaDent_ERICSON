<?php
require_once '../database/config.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header('Location: ../login/login_new.php');
    exit;
}

// Get patient data
$patient = [
    'name' => $_SESSION['name'] ?? 'Patient',
    'email' => $_SESSION['email'] ?? ''
];

require_once 'layout.php';
patientPortalStart($patient, 'Dental Records', 'history');
?>

<div class="dashboard-content">
    <div class="card">
        <h3><i class="fas fa-file-medical"></i> Dental Records</h3>
        <p>Your dental treatment history and records will appear here.</p>
    </div>
</div>

<?php patientPortalEnd(); ?>
