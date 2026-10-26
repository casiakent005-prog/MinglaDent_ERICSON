# Patient and clinic registration

This feature extends the existing MinglaDent PHP application and its authentication tables. It does not create a second account system.

## Installation on an existing database

Requirements: PHP 8.0+ with PDO MySQL, fileinfo, mbstring, and cURL; MySQL/MariaDB with InnoDB. Development validation used PHP 8.5.4 and MariaDB 11.8.6. No Composer or Node dependencies are required.

1. Back up the existing database and uploaded files. Use the existing application's `auth_users`, `clinics` (`clinic_name` / `contact_number`), and `clinic_staff` (`user_id` / `clinic_id`) schema. The existing clinic/patient platform migrations described in `integrations.md` must already be applied for the dashboards. Do not execute the legacy `setup.php` on existing data: it drops tables. The unrelated old `database.sql` schema uses different names and is not the current application's baseline.
2. Configure `MINGLADENT_DB_DSN`, `MINGLADENT_DB_USER`, and `MINGLADENT_DB_PASSWORD` in the PHP process environment. Defaults preserve the existing local XAMPP settings (`localhost`, database `mingladent`, user `root`, empty password).
3. Run from the repository root:

   ```sh
   php database/migrate_registration.php
   ```

   The migration is CLI-only and repeatable. It adds missing columns, indexes, and foreign keys without deleting accounts or changing existing passwords, clinic statuses, or staff roles. It refuses duplicate identities, multiple memberships for one account, and orphaned staff/profiles; reconcile those records before retrying. MySQL DDL auto-commits, so a schema/permission error may leave completed additive steps in place. Fix the reported issue and rerun. Installations with an incompatible legacy schema must reconcile it first; this migration does not silently rename tables or guess owners.
4. Set `MINGLADENT_PRIVATE_STORAGE` to a writable directory **outside the application web root**. If omitted, the default is `mingladent-private/clinic-applications` beside the repository directory. The application creates directories with mode 0700 and files with mode 0600. Back up this directory together with the database. A public path is rejected by the application.
5. Configure PHP `upload_max_filesize` to at least the policy limit (default 5 MB) and `post_max_size` above the total allowed document upload size. The local validation server uses 6M and 16M respectively. Set `max_file_uploads` to cover your configured checklist.
6. Configure `database/registration_policy.php`: supported service areas, document labels/required flags, allowed extensions/MIME types, and maximum file size. The initial checklist asks for proof of ownership or authorization; it does not assert an official licensing requirement. Keep stable document keys. Stored submissions retain the document label used at submission time.
7. Serve over HTTPS. Apache 2.4 must honor the supplied `.htaccess` files. On Nginx or another server, deny direct access to `.git`, `database/`, `includes/`, `tests/`, `storage/`, SQL/backup/configuration files, and disable directory listings. Supporting clinic documents are outside the web root and downloadable only through the authorized PHP endpoint. A PHP development server does not read `.htaccess`; use an equivalent deny router for local previews.
8. Review the public Terms and Privacy text in `login/policies.php` and adjust it to the organization's published policies and contact process.

The migration creates no System Admin credentials. Use an existing active System Admin account. Existing clinics retain their status and staff assignments; ownership is left null where no explicit owner was previously recorded.

## Database changes

Reused tables:

- `auth_users`: existing identity and credentials; adds missing registration fields and `terms_agreed_at`; unique email/username constraints. `role` stays `patient`, `clinic`, or `admin`.
- `patient_profiles`: existing patient details and privacy consent; one profile per account, with a user foreign key. Created only if absent.
- `clinics`: adds `owner_user_id`, `description`, `services_offered`, `service_area`, `submitted_at`, `reviewed_at`, `reviewed_by`, and `rejection_reason`; adds `suspended` to the status enum if needed. Owner and reviewer foreign keys reference existing accounts. One owner account belongs to one clinic.
- `clinic_staff`: adds `is_dentist`, a unique user assignment, and user/clinic foreign keys. The single assignment follows the existing login/portal model. Owners remain `clinic_admin`; the extra flag allows the same staff ID to practice dentistry.
- `remember_tokens`: reused, or created if absent. New persistent login tokens are hashed in storage, revalidated against account status, and revoked on logout/password reset. Existing passwords and login emails are unchanged.

New tables:

- `clinic_application_documents`: clinic/uploader foreign keys, document key and label, original name, random private storage name, MIME type, byte size, and upload timestamp.
- `clinic_application_decisions`: clinic/reviewer foreign keys, previous status, decision, reason, and timestamp. Existing `activity_logs` also records decisions.

No tables, accounts, clinic records, or credentials are reset by this migration.

## Workflow

### Patients

`login/register_new.php` (also available at the existing `login/register.php` URL) offers Patient and Dental Clinic cards in the existing blue-and-white split layout. Patients provide names, birth date, phone, email, address, optional sex/username/emergency contact, passwords, and agreement to Terms and Privacy. Server validation rejects missing/invalid fields and duplicate identities. The account and patient profile are saved in one transaction with server-assigned `patient` authority. Success redirects to login, then the Patient Dashboard.

### Clinics

Clinic applicants provide representative/account details, clinic details and service area, optionally offered services and practicing-dentist status, plus the configured supporting documents. The server validates size, extension, detected MIME type, and image structure. It saves the account, clinic, membership, and documents together. Failed writes roll back database changes and remove moved files.

The account has the existing `clinic` role, its membership has `clinic_admin`, and the clinic status is `pending`. The owner signs into a Pending Approval page and can download their own submitted documents. Pending clinics are absent from public listings and cannot use clinic management, dentist, or assistant pages/actions.

### Review and status

System Admin → **Clinics** → **Review application** shows the representative, clinic details, requested dentist capability, uploaded documents, and decision history. The Admin can approve, reject, suspend, or return a clinic to pending. Rejection and suspension require a reason. Decisions, reviewer, timestamps, and audit records are saved transactionally. Only an active database-verified System Admin can decide. The legacy admin clinic URL routes to this same review process so it cannot bypass decision recording.

Approved owners reach the Clinic Admin Dashboard. Practicing owners can open the Dentist Dashboard using the same account and appear in dentist selectors; existing clinic staff management can create/edit dentists and assistants within that clinic. Offered services in the application are descriptive: after approval, use the existing service and fee editors to configure actual booking durations and prices.

Rejected owners see the reason and a link to contact MinglaDent about corrections/review. Suspended clinics see restricted access. Returning an application to pending requires another explicit approval. These states are checked on every protected request, including sessions opened before the status changed. Staff membership is always read from the database rather than a submitted clinic ID.

## Tests

Use a dedicated database with a name ending `_test`. The fixture initializer refuses a nonempty database and never drops data. It extracts the existing baseline CREATE statements and applies existing workflow migrations, then the new migration; its synthetic administrator exists only in the isolated test database.

```sh
# Set database environment variables to an empty, isolated *_test database.
php tests/bootstrap_database.php
# Run the application against that same test database, with appropriate upload limits.
# Then run the HTTP/SQL integration checks:
MINGLADENT_TEST_URL=http://localhost:8080 php tests/registration_integration.php
```

The test suite uses unique synthetic identities on each run and leaves them in the isolated database for inspection. Set `MINGLADENT_PRIVATE_STORAGE` consistently for both the server and the test command. The test uses temporary upload fixtures and removes them; its deliberate database-failure trigger is removed in a finally block. Do not run it against deployed data.

For a separate populated-legacy upgrade check, point the database environment at a second empty `*_test` database and run `php tests/migration_preservation.php`. It verifies preflight refusal of ambiguous memberships, preservation of existing rows and credential/routing behavior, the alternate patient profile layout, and repeatability.

Coverage includes validation, duplicate identities, password hashes, patient and clinic persistence, private uploads, pending/rejected/suspended restrictions, Admin decisions, existing session revocation, role tampering, clinic isolation, owner/dentist booking and clinical records, legacy URLs, migration repeatability, remember-me, and password reset token revocation. PHP lint and shared Chromium desktop/mobile checks complement the integration suite.

## Scope and configuration still required for deployment

The deployed database was not reachable in the task sandbox. Apply the migration there and verify against its actual schema and existing data before rollout. Local integration tests use actual MariaDB with synthetic test records.

Google/Facebook provider handshakes, payment providers, email/SMS delivery, Apache/Nginx production configuration, and HTTPS cookie behavior were not exercised by this registration test suite. Password-provider callbacks now share account-status checks and role routing. Existing password-reset code only logs a reset link; delivering reset emails remains an existing integration requirement. Clinic decisions are visible in the application; no new email/SMS notification service is implied.
