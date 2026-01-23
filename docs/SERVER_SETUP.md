# Server Setup & Configuration (Defense Notes)

## Baseline stack
- Web server: Apache (XAMPP supported)
- Runtime: PHP
- Database: MySQL/MariaDB

## Required environment configuration
Environment variables are loaded via: [env.php](file:///c:/xampp/htdocs/tmm/includes/env.php)

Recommended `.env` keys:
- DB connection: host/user/pass/name
- Treasury integration keys (optional but recommended): `TMM_TREASURY_INTEGRATION_KEY` or `TMM_TREASURY_CALLBACK_TOKEN`
- reCAPTCHA + OTP settings (if enabled): see [RECAPTCHA_AND_OTP_SETUP.md](file:///c:/xampp/htdocs/tmm/docs/RECAPTCHA_AND_OTP_SETUP.md)

## Database bootstrap
The app auto-creates/updates required tables when pages load through: [db.php](file:///c:/xampp/htdocs/tmm/admin/includes/db.php)

If you need a fresh baseline schema, the repair script includes core tables:
- [repair_missing_tables_from_tmm_tmm_3.sql](file:///c:/xampp/htdocs/tmm/sql/repair_missing_tables_from_tmm_tmm_3.sql)

## Production hardening checklist (presentation-ready)
- Enable HTTPS (TLS certificate)
- Disable directory listing in Apache
- Configure PHP `display_errors=Off` and log to file
- Set secure session cookies (HttpOnly/SameSite, Secure if HTTPS)
- Least-privilege DB user (SELECT/INSERT/UPDATE/DELETE only)
- Regular DB backups + restore test
- Rotate integration tokens periodically
