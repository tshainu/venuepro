# VPS Reinstall Deployment Task

## Status: IN PROGRESS

## Done
- VPS reinstalled (Ubuntu 22.04), new root pass: Br9@QA5#Tu6
- Installed: nginx, mysql-server, php8.2-fpm + extensions, fail2ban
- Created DB `venuepro`, user `venuepro`/`VenuePro@2026`
- Added missing tables to database.sql: activity_logs, sa_businesses
- Added missing columns to users table: user_id, username
- Added 'Manager' role to seed
- Fixed super admin seed user (user_id=SA001, username=admin, password=password)
- Cloned repo to /var/www/venuepro -- discovered a NEWER commit (e1522b9) pushed
  by another session/agent with MASSIVE new features: expenses module, staff module,
  owner_dashboard.php, mobile_validation, big superadmin/vpsa rewrites
- Merged origin/main into local, database.sql edits preserved

## Missing DB tables discovered (from new code scan)
- expense_categories (id, business_id, name, color, is_active, created_at, updated_at)
- expenses (id, expense_ref, branch_id, category_id, title, description, amount,
  expense_date, payment_method, reference_number, status, created_by, created_at)

## TODO
1. Scan owner_dashboard.php, modules/staff/*.php, vpsa/create_owner.php, superadmin/index.php,
   core/BirthdayAnniversaryManager.php, core/EventTimeHelper.php for more missing tables/columns
2. Add expense_categories + expenses tables to database.sql
3. Check bookings table for new columns (edit.php grew 587 lines - likely event_type, times etc via EventTimeHelper)
4. Re-import updated database.sql to server
5. Write config/config.php or .env on server with DB credentials
6. Configure nginx site for port 8082 with /vpsa, /venuepro, /owner_dashboard routes
7. Set proper file permissions (www-data)
8. Smoke test: /login.php, /vpsa/login.php, main dashboard, create booking flow
9. Fix any errors that surface (missing columns) reactively
10. Deliver final URL to user

## Credentials
- VPS: root@69.169.97.195 / Br9@QA5#Tu6
- DB: venuepro/VenuePro@2026, db name venuepro
- Super admin login: SA001 / admin / password (CHANGE AFTER FIRST LOGIN)
- Deploy path: /var/www/venuepro
- Nginx port: 8082
