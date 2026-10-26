<div class="registration-options" aria-label="Registration type">
    <a class="registration-option <?php echo $registrationType === 'patient' ? 'selected' : ''; ?>" href="?type=patient" <?php if ($registrationType === 'patient') echo 'aria-current="true"'; ?>><i class="fas fa-user"></i><strong>Register as Patient</strong><span>Book visits and manage your dental care.</span></a>
    <a class="registration-option <?php echo $registrationType === 'clinic' ? 'selected' : ''; ?>" href="?type=clinic" <?php if ($registrationType === 'clinic') echo 'aria-current="true"'; ?>><i class="fas fa-hospital"></i><strong>Register as Dental Clinic</strong><span>Apply to become a MinglaDent partner.</span></a>
</div>
<?php if ($registrationReady && $registrationType): ?>
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo authEscape(authCsrfToken()); ?>">
    <input type="hidden" name="registration_type" value="<?php echo authEscape($registrationType); ?>">
    <h3 class="section-title"><?php echo $registrationType === 'clinic' ? 'Owner / Authorized Representative' : 'Personal Information'; ?></h3>
    <div class="form-row">
        <div class="form-group"><label for="first_name">First name *</label><input id="first_name" name="first_name" class="form-control" maxlength="100" required autocomplete="given-name" value="<?php echo registrationValue('first_name'); ?>"></div>
        <div class="form-group"><label for="middle_name">Middle name (optional)</label><input id="middle_name" name="middle_name" class="form-control" maxlength="100" autocomplete="additional-name" value="<?php echo registrationValue('middle_name'); ?>"></div>
    </div>
    <div class="form-group"><label for="last_name">Last name *</label><input id="last_name" name="last_name" class="form-control" maxlength="100" required autocomplete="family-name" value="<?php echo registrationValue('last_name'); ?>"></div>
    <div class="form-row">
        <div class="form-group"><label for="date_of_birth">Date of birth<?php echo $registrationType === 'patient' ? ' *' : ' (optional)'; ?></label><input type="date" id="date_of_birth" name="date_of_birth" class="form-control" max="<?php echo date('Y-m-d'); ?>" <?php if ($registrationType === 'patient') echo 'required'; ?> value="<?php echo registrationValue('date_of_birth'); ?>"></div>
        <div class="form-group"><label for="sex">Sex (optional)</label><select id="sex" name="sex" class="form-control"><?php foreach (['' => 'Select', 'male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php if (($_POST['sex'] ?? '') === $value) echo 'selected'; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="contact_number">Contact number *</label><input type="tel" id="contact_number" name="contact_number" class="form-control" maxlength="20" required autocomplete="tel" value="<?php echo registrationValue('contact_number'); ?>"></div>
        <div class="form-group"><label for="email">Email address *</label><input type="email" id="email" name="email" class="form-control" maxlength="255" required autocomplete="email" value="<?php echo registrationValue('email'); ?>"><span id="emailMessage" class="validation-message"></span></div>
    </div>
    <div class="form-group"><label for="complete_address">Complete home address *</label><input id="complete_address" name="complete_address" class="form-control" maxlength="255" required autocomplete="street-address" value="<?php echo registrationValue('complete_address'); ?>"></div>
    <?php if ($registrationType === 'patient'): ?>
    <div class="form-row">
        <div class="form-group"><label for="emergency_contact_name">Emergency contact name (optional)</label><input id="emergency_contact_name" name="emergency_contact_name" maxlength="255" class="form-control" value="<?php echo registrationValue('emergency_contact_name'); ?>"></div>
        <div class="form-group"><label for="emergency_contact_phone">Emergency contact number (optional)</label><input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" maxlength="20" class="form-control" value="<?php echo registrationValue('emergency_contact_phone'); ?>"></div>
    </div>
    <?php else: ?>
    <h3 class="section-title">Clinic Information</h3>
    <?php foreach (['clinic_name' => ['Clinic name', 'text', 255], 'clinic_address' => ['Complete clinic address (include municipality and province)', 'text', 255], 'clinic_contact_number' => ['Clinic contact number', 'tel', 20], 'clinic_email' => ['Clinic email address', 'email', 255], 'operating_hours' => ['Operating days and hours', 'text', 255]] as $key => [$label, $type, $max]): ?>
    <div class="form-group"><label for="<?php echo $key; ?>"><?php echo $label; ?> *</label><input type="<?php echo $type; ?>" id="<?php echo $key; ?>" name="<?php echo $key; ?>" maxlength="<?php echo $max; ?>" class="form-control" required value="<?php echo registrationValue($key); ?>"></div>
    <?php endforeach; ?>
    <div class="form-group"><label for="service_area">Service area *</label><select id="service_area" name="service_area" class="form-control" required><?php foreach ($registrationPolicy['service_areas'] as $area): ?><option <?php if (($_POST['service_area'] ?? '') === $area) echo 'selected'; ?>><?php echo authEscape($area); ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="description">Clinic description *</label><textarea id="description" name="description" class="form-control" maxlength="5000" rows="3" required><?php echo registrationValue('description'); ?></textarea></div>
    <div class="form-group"><label for="services_offered">Dental services offered (optional)</label><textarea id="services_offered" name="services_offered" class="form-control" maxlength="5000" rows="3"><?php echo registrationValue('services_offered'); ?></textarea><small>After approval, configure your bookable services, durations, and fees in the clinic dashboard.</small></div>
    <div class="terms-checkbox"><input type="checkbox" id="practicing_dentist" name="practicing_dentist" value="1" <?php if (($_POST['practicing_dentist'] ?? '') === '1') echo 'checked'; ?>><label for="practicing_dentist">I also practice as a dentist at this clinic. Include dentist access for this account in the application.</label></div>
    <h3 class="section-title">Supporting Documents</h3>
    <p class="review-notice">MinglaDent will review your clinic information and documents. Your listing and clinic management access remain unavailable until a System Admin approves the application. This is MinglaDent’s verification policy, not an official licensing checklist.</p>
    <?php foreach ($registrationPolicy['documents'] as $key => $rule): ?>
    <div class="form-group"><label for="document_<?php echo authEscape($key); ?>"><?php echo authEscape($rule['label']); ?><?php echo $rule['required'] ? ' *' : ' (optional)'; ?></label><input type="file" class="form-control" id="document_<?php echo authEscape($key); ?>" name="document_<?php echo authEscape($key); ?>" accept="<?php echo authEscape(implode(',', array_unique(array_values($registrationPolicy['allowed_types'])))); ?>" <?php if ($rule['required']) echo 'required'; ?>></div>
    <?php endforeach; ?>
    <p class="review-notice">PDF, JPEG, or PNG; maximum <?php echo (int) ($registrationPolicy['max_file_bytes'] / 1048576); ?> MB per file. Documents are accessible only to your account and authorized System Admins.</p>
    <?php endif; ?>
    <h3 class="section-title">Account Information</h3>
    <div class="form-group"><label for="username">Username (optional; you can sign in with email)</label><input id="username" name="username" class="form-control" maxlength="100" pattern="[a-zA-Z0-9_.\-]{3,100}" autocomplete="username" value="<?php echo registrationValue('username'); ?>"><span id="usernameMessage" class="validation-message"></span></div>
    <div class="form-group"><label for="password">Password *</label><div class="input-wrapper"><input type="password" id="password" name="password" class="form-control" minlength="8" maxlength="72" required autocomplete="new-password"><i class="fas fa-eye password-toggle" id="togglePassword" role="button" tabindex="0" aria-label="Show password"></i></div><div class="password-strength"><div class="password-strength-bar" id="strengthBar"></div></div><p id="strengthText" class="strength-text"></p></div>
    <div class="form-group"><label for="confirmPassword">Confirm password *</label><div class="input-wrapper"><input type="password" id="confirmPassword" name="confirm_password" class="form-control" minlength="8" maxlength="72" required autocomplete="new-password"><i class="fas fa-eye password-toggle" id="toggleConfirmPassword" role="button" tabindex="0" aria-label="Show confirmation password"></i></div><span id="confirmPasswordMessage" class="validation-message"></span></div>
    <div class="terms-checkbox"><input type="checkbox" id="termsAgreed" name="terms_agreed" value="1" required><label for="termsAgreed">I agree to the <a href="policies.php#terms" target="_blank" rel="noopener">Terms and Conditions</a> and <a href="policies.php#privacy" target="_blank" rel="noopener">Privacy Policy</a> of MinglaDent.</label></div>
    <button type="submit" class="btn-register"><?php echo $registrationType === 'clinic' ? 'Submit Clinic Application' : 'Create Patient Account'; ?></button>
</form>
<?php elseif ($registrationReady): ?><p class="review-notice">Choose an account type to get started. Already registered? Sign in below.</p><?php endif; ?>
