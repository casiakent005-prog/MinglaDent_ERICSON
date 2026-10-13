<?php
require_once 'bootstrap.php';
require_once 'layout.php';

$patient = patientPortalContext();
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    if (!patientPortalVerifyCsrf()) {
        http_response_code(419);
        exit('Your session expired. Reload the page and try again.');
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
    $emergencyName = trim($_POST['emergency_contact_name'] ?? '');
    $emergencyPhone = trim($_POST['emergency_contact_phone'] ?? '');
    $birthDate = $dateOfBirth === '' ? null : DateTimeImmutable::createFromFormat('!Y-m-d', $dateOfBirth);

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $mobile === '') {
        $message = 'Enter your name, a valid email, and mobile number.';
        $messageType = 'error';
    } elseif ($dateOfBirth !== '' && (!$birthDate || $birthDate->format('Y-m-d') !== $dateOfBirth)) {
        $message = 'Enter a valid date of birth.';
        $messageType = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM auth_users WHERE email = ? AND id <> ? LIMIT 1");
            $stmt->execute([$email, $patient['user_id']]);
            if ($stmt->fetchColumn()) {
                $message = 'That email address is already in use.';
                $messageType = 'error';
            } else {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE auth_users SET name = ?, email = ? WHERE id = ? AND role = 'patient'");
                $stmt->execute([$name, $email, $patient['user_id']]);
                $stmt = $pdo->prepare("UPDATE patient_profiles SET mobile_number = ?, address = ?, date_of_birth = ?, emergency_contact_name = ?, emergency_contact_phone = ? WHERE user_id = ?");
                $stmt->execute([$mobile, $address ?: null, $dateOfBirth ?: null, $emergencyName ?: null, $emergencyPhone ?: null, $patient['user_id']]);
                $stmt = $pdo->prepare("UPDATE patients SET name = ?, email = ?, phone = ?, address = ?, date_of_birth = ?, emergency_contact_name = ?, emergency_contact_phone = ? WHERE user_id = ?");
                $stmt->execute([$name, $email, $mobile, $address ?: null, $dateOfBirth ?: null, $emergencyName ?: null, $emergencyPhone ?: null, $patient['user_id']]);
                $pdo->commit();
                clinicPortalAudit($pdo, (int) $patient['user_id'], null, 'patient.profile_updated', 'patient', (int) $patient['user_id']);
                $_SESSION['name'] = $name;
                $_SESSION['email'] = $email;
                $message = 'Your profile was updated.';
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = 'Could not update your profile. Please try again.';
            $messageType = 'error';
        }
    }

    $patient = patientPortalContext();
}

patientPortalStart($patient, 'My profile', 'profile');
$csrfToken = patientPortalCsrfToken();
?>
<?php if ($message): ?><div class="notice <?php echo $messageType === 'error' ? 'error' : ''; ?>"><?php echo patientPortalEscape($message); ?></div><?php endif; ?>
<section class="card">
    <h3>Personal and contact information</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?php echo patientPortalEscape($csrfToken); ?>">
        <input type="hidden" name="action" value="update_profile">
        <div class="form-field"><label for="name">Full name</label><input id="name" name="name" value="<?php echo patientPortalEscape($patient['name']); ?>" maxlength="255" autocomplete="name" required></div>
        <div class="form-field"><label for="email">Email address</label><input id="email" name="email" type="email" value="<?php echo patientPortalEscape($patient['email']); ?>" maxlength="255" autocomplete="email" required></div>
        <div class="form-field"><label for="mobile_number">Mobile number</label><input id="mobile_number" name="mobile_number" type="tel" value="<?php echo patientPortalEscape($patient['mobile_number']); ?>" maxlength="20" autocomplete="tel" required></div>
        <div class="form-field"><label for="date_of_birth">Date of birth</label><input id="date_of_birth" name="date_of_birth" type="date" value="<?php echo patientPortalEscape($patient['date_of_birth']); ?>"></div>
        <div class="form-field full"><label for="address">Address</label><input id="address" name="address" value="<?php echo patientPortalEscape($patient['address']); ?>" maxlength="255" autocomplete="street-address"></div>
        <div class="form-field"><label for="emergency_contact_name">Emergency contact name</label><input id="emergency_contact_name" name="emergency_contact_name" value="<?php echo patientPortalEscape($patient['emergency_contact_name']); ?>" maxlength="255"></div>
        <div class="form-field"><label for="emergency_contact_phone">Emergency contact number</label><input id="emergency_contact_phone" name="emergency_contact_phone" type="tel" value="<?php echo patientPortalEscape($patient['emergency_contact_phone']); ?>" maxlength="20"></div>
        <div class="form-field full"><button class="button" type="submit"><i class="fas fa-save"></i> Save profile</button></div>
    </form>
    <p class="empty-state">Privacy consent recorded <?php echo patientPortalEscape(date('M j, Y', strtotime($patient['privacy_consent_at']))); ?>. Contact the clinic if you need your account or health information removed.</p>
</section>
<?php patientPortalEnd(); ?>