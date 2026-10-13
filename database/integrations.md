# MinglaDent Integrations

## Database migrations

For an existing MinglaDent database, apply `clinic_workflows.sql` once, then `patient_platform.sql` once. These migrations preserve existing rows while adding patient profiles, clinic-scoped records and services, invoices, notifications, and audit logs. Do not run the legacy `setup.php` against a populated database; it drops and recreates clinic-related tables.

## Appointment and receipt notifications

Bookings, cancellations, reminders, and payment receipts are stored in `notification_queue`. Configure a sender before running the worker:

- Email: set `MINGLADENT_MAIL_FROM` and configure PHP `mail()` with an SMTP relay in `php.ini` or the host mail service.
- SMS: set `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, and `TWILIO_FROM_NUMBER` in the Apache/PHP environment.

Schedule `php clinic_portal/send_notifications.php` every five minutes. The worker is CLI-only. Check `notification_queue.status` and `last_error` for delivery results. Local XAMPP does not send email or SMS until a provider is configured.

## PayMongo sandbox

Online checkout requires a PayMongo test secret key in `PAYMONGO_SECRET_KEY`, plus a publicly reachable HTTPS callback URL and webhook verification before production. Never put secret keys in source files, browser JavaScript, or this database. Cash, in-clinic card, and manually referenced GCash/bank-transfer receipts continue to work without online checkout credentials.

## Privacy and deployment

Patient X-rays and documents are stored under `storage/patient_documents`, blocked from direct Apache access, and served through authenticated endpoints. Keep regular encrypted database/file backups, configure HTTPS, and restrict access to the XAMPP host before using real patient data.