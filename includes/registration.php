<?php
require_once __DIR__ . '/clinic_applications.php';
$registrationPolicy = require __DIR__ . '/../database/registration_policy.php';
$message = '';
$messageType = 'error';
$registrationType = $_GET['type'] ?? '';
if (!is_string($registrationType) || !in_array($registrationType, ['patient', 'clinic'], true)) $registrationType = '';

function registrationText(string $key, int $max, bool $required = false): string {
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) throw new RuntimeException('Invalid form value.');
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value) > $max) {
        throw new RuntimeException('Enter ' . str_replace('_', ' ', $key) . ' (at most ' . $max . ' characters).');
    }
    return $value;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    authRequireCsrf();
    $movedFiles = [];
    try {
        $registrationType = registrationText('registration_type', 20, true);
        if (!in_array($registrationType, ['patient', 'clinic'], true)) throw new RuntimeException('Choose Patient or Dental Clinic.');
        if (isLoggedIn()) throw new RuntimeException('Sign out before registering another account.');
        $firstName = registrationText('first_name', 100, true);
        $middleName = registrationText('middle_name', 100);
        $lastName = registrationText('last_name', 100, true);
        $name = preg_replace('/\s+/', ' ', trim("$firstName $middleName $lastName"));
        if (mb_strlen($name) > 255) throw new RuntimeException('Your full name must be at most 255 characters.');
        $email = strtolower(registrationText('email', 255, true));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email address.');
        $phone = registrationText('contact_number', 20, true);
        if (!preg_match('/^[+()\d .-]{7,20}$/', $phone)) throw new RuntimeException('Enter a valid contact number.');
        $address = registrationText('complete_address', 255, true);
        $dateOfBirth = registrationText('date_of_birth', 10, $registrationType === 'patient');
        $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateOfBirth);
        if ($dateOfBirth !== '' && (!$birthDate || $birthDate->format('Y-m-d') !== $dateOfBirth || $birthDate > new DateTimeImmutable('today'))) {
            throw new RuntimeException('Enter a valid date of birth that is not in the future.');
        }
        $sex = registrationText('sex', 10);
        if (!in_array($sex, ['', 'male', 'female', 'other'], true)) throw new RuntimeException('Choose a valid sex option.');
        $username = strtolower(registrationText('username', 100));
        if ($username !== '' && !preg_match('/^[a-z0-9_.-]{3,100}$/', $username)) throw new RuntimeException('Use 3–100 letters, numbers, dots, hyphens, or underscores for your username.');
        $password = $_POST['password'] ?? '';
        $confirmation = $_POST['confirm_password'] ?? '';
        if (!is_string($password) || !is_string($confirmation) || strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new RuntimeException('Use a password of 8–72 bytes.');
        }
        if ($password !== $confirmation) throw new RuntimeException('Passwords do not match.');
        if (($_POST['terms_agreed'] ?? '') !== '1') throw new RuntimeException('Agree to the Terms and Conditions and Privacy Policy.');
        $emergencyName = registrationText('emergency_contact_name', 255);
        $emergencyPhone = registrationText('emergency_contact_phone', 20);
        $stmt = $pdo->prepare('SELECT id FROM auth_users WHERE email = ? OR (username = ? AND ? <> \'\') LIMIT 1');
        $stmt->execute([$email, $username, $username]);
        if ($stmt->fetchColumn()) throw new RuntimeException('Email already registered or username already taken. Please sign in or choose different details.');
        $documents = [];
        if ($registrationType === 'clinic') {
            $clinicName = registrationText('clinic_name', 255, true);
            $clinicAddress = registrationText('clinic_address', 255, true);
            $clinicPhone = registrationText('clinic_contact_number', 20, true);
            $clinicEmail = strtolower(registrationText('clinic_email', 255, true));
            $hours = registrationText('operating_hours', 255, true);
            $description = registrationText('description', 5000, true);
            $offered = registrationText('services_offered', 5000);
            $area = registrationText('service_area', 100, true);
            if (!in_array($area, $registrationPolicy['service_areas'], true)) throw new RuntimeException('Choose a supported service area.');
            foreach (explode(',', $area) as $part) {
                if (mb_stripos($clinicAddress, trim($part)) === false) throw new RuntimeException('Include the selected service area in the complete clinic address.');
            }
            if (!filter_var($clinicEmail, FILTER_VALIDATE_EMAIL) || !preg_match('/^[+()\d .-]{7,20}$/', $clinicPhone)) {
                throw new RuntimeException('Enter a valid clinic email and contact number.');
            }
            $documents = clinicValidateUploads($registrationPolicy);
        }
        $pdo->beginTransaction();
        // Role and account state are assigned here. Client-supplied role/clinic/status fields are never used.
        $role = $registrationType === 'clinic' ? 'clinic' : 'patient';
        $stmt = $pdo->prepare("INSERT INTO auth_users (name, first_name, middle_name, last_name, email, username, password, role, status, date_of_birth, sex, contact_number, complete_address, auth_provider, terms_agreed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, 'email', NOW())");
        $stmt->execute([$name, $firstName, $middleName ?: null, $lastName, $email, $username ?: null,
            password_hash($password, PASSWORD_DEFAULT), $role, $dateOfBirth ?: null, $sex ?: null, $phone, $address]);
        $userId = (int) $pdo->lastInsertId();
        if ($registrationType === 'patient') {
            $stmt = $pdo->prepare('INSERT INTO patient_profiles (user_id, mobile_number, address, date_of_birth, emergency_contact_name, emergency_contact_phone, privacy_consent_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$userId, $phone, $address, $dateOfBirth, $emergencyName ?: null, $emergencyPhone ?: null]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO clinics (clinic_name, address, contact_number, email, operating_hours, description, services_offered, service_area, owner_user_id, status, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$clinicName, $clinicAddress, $clinicPhone, $clinicEmail, $hours, $description, $offered ?: null, $area, $userId]);
            $clinicId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO clinic_staff (user_id, clinic_id, role, is_dentist) VALUES (?, ?, 'clinic_admin', ?)");
            $stmt->execute([$userId, $clinicId, ($_POST['practicing_dentist'] ?? '') === '1' ? 1 : 0]);
            $stmt = $pdo->prepare('UPDATE auth_users SET clinic_id = ? WHERE id = ?');
            $stmt->execute([$clinicId, $userId]);
            foreach ($documents as $document) {
                $path = clinicDocumentDirectory() . '/' . $document['storage_name'];
                if (!move_uploaded_file($document['tmp_name'], $path)) throw new RuntimeException('Document storage is unavailable. Please try again later.');
                $movedFiles[] = $path;
                chmod($path, 0600);
                $stmt = $pdo->prepare('INSERT INTO clinic_application_documents (clinic_id, uploaded_by, document_key, document_label, original_name, storage_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$clinicId, $userId, $document['key'], $document['label'], $document['original_name'], $document['storage_name'], $document['mime_type'], $document['file_size']]);
            }
        }
        $pdo->commit();
        if ($registrationType === 'clinic') {
            authStartSession(['id' => $userId, 'name' => $name, 'email' => $email, 'role' => 'clinic', 'status' => 'active']);
            redirect('clinic_status.php');
        }
        $_SESSION['registration_success'] = 'Registration successful! Please sign in.';
        redirect('login_new.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($movedFiles as $path) { if (is_file($path)) unlink($path); }
        if ($e instanceof PDOException) {
            $message = $e->getCode() === '23000' ? 'Email already registered or username already taken.' : 'Registration is temporarily unavailable. Please try again later.';
            error_log('Registration database error (' . $e->getCode() . ').');
        } else {
            $message = $e->getMessage();
        }
    }
}
function registrationValue(string $name): string {
    return authEscape(is_string($_POST[$name] ?? null) ? $_POST[$name] : '');
}
