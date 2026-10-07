# Workly — Job Finder & Recruitment Management System

A PHP, MySQL, HTML, CSS, and small vanilla JavaScript job marketplace designed to run locally with XAMPP. No framework or third-party PHP packages are required.

## Requirements

- XAMPP with Apache, PHP 8.1+ and MySQL/MariaDB
- PHP PDO MySQL, Fileinfo, and session extensions enabled

## Install with XAMPP

1. Place this folder under `C:\xampp\htdocs\Job finder system`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`, select **Import**, and import `database.sql`. This creates the `job_finder_system` database and tables.
4. The default connection is `127.0.0.1`, database `job_finder_system`, user `root`, and a blank password. To use different credentials, set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` in the PHP/Apache environment or update `config/database.php`.
5. The SQL import creates a default admin account:

   - Email: `admin@gmail.com`
   - Password: `admin@123`

   Sign in at `http://localhost/Job%20finder%20system/login.php` and change the password before using this outside a local demo. To use a different initial admin, remove the default admin `INSERT` at the end of `database.sql` before importing, then run the CLI command below:

   ```text
   cd C:\xampp\htdocs\Job finder system
   C:\xampp\php\php.exe bin\create_admin.php
   ```

   Follow the prompts. The installer is CLI-only and refuses to create a second admin.
6. Visit `http://localhost/Job%20finder%20system/`.

The `uploads` directory and its subdirectories must be writable by Apache/PHP. CVs are restricted to PDF, DOC, or DOCX and 5 MB. Work submissions accept up to five documents, photos, or videos, up to 25 MB each and 30 MB combined. Payment screenshots accept JPG, PNG, GIF, or WEBP up to 5 MB. Work chat messages can include one supported file attachment up to 25 MB. PHP's `upload_max_filesize` and `post_max_size` must allow the desired upload size; XAMPP commonly sets a 40 MB post limit.

## If phpMyAdmin says it cannot connect to MySQL

An error such as `HY000/2002` or “target machine actively refused it” means the MySQL server is not accepting connections. It is not an error in the SQL import.

1. Open the **XAMPP Control Panel** and click **Start** on the **MySQL** row. Wait until its status shows **Running**.
2. Refresh `http://localhost/phpmyadmin/`.
3. If MySQL does not start, check the XAMPP Control Panel log for a port-conflict message. MySQL normally uses port `3306`; another MySQL/MariaDB service may already be using it. Stop the other service from Windows Services if you recognize it, or configure XAMPP MySQL and phpMyAdmin to use the same unused port.
4. Once phpMyAdmin connects, use **Import** to import `database.sql`. The file creates and selects `job_finder_system` itself; you do not need to create the database first.

## Roles and features

- **Admin:** dashboard and reports, approve/reject employers' companies and job posts, browse seekers and applications.
- **Job seeker:** register, maintain and edit a profile and CV, add a unique Ethiopian mobile number and bank or Telebirr payout account, filter approved opportunities, view exact job pins on a map, apply once per job, track applications, submit documents/photos/videos, chat about work orders, and view invoice/payment proof.
- **Employer:** register a company (admin approval required), add a unique Ethiopian mobile number, edit the company profile, post/edit/delete jobs with an optional exact map pin, estimated duration, and application deadline, review applicants, accept applicants into work orders with an agreed amount, review deliverables, request revisions, approve work to issue an invoice, chat with workers/admins, and record external invoice payments with an optional screenshot.

Job seekers and employers register with an email and password, then sign in from the login page; registration does not create a login session. Successful login stores the authenticated user ID and role in the session and sends them to their role-specific dashboard. Role-protected routes deny access to users with the wrong role, and logout clears the session.

Administrators can change their name, email, and password from **Admin account** after confirming their current password. All roles can request a password reset by email. Reset links are single-use, expire after one hour, and are stored as token hashes. To deliver reset emails, configure PHP `mail()` in XAMPP (`php.ini` `sendmail_path` and the corresponding `sendmail.ini` SMTP relay settings); set `APP_MAIL_FROM` in the Apache environment or configure a valid `sendmail_from` in `php.ini`. Mail delivery failures are written to the PHP error log. Import the updated `database_work_orders.sql` to add the reset-token table to an existing database.

New job posts require admin approval. Pending companies may submit job posts, but public listings only show approved jobs belonging to approved companies. On work approval, an invoice is created for the agreed work-order amount. Payments are manual: the company pays outside this application and records a payment reference to complete the work order; no payment gateway is connected. Re-importing `database.sql` creates any new tables without replacing existing tables or data.

## Work and payment flow

1. An employer accepts an applicant and enters the agreed project amount and scope. This creates a work order. The worker can add a Commercial Bank of Ethiopia, Telebirr, Awash, Dashen, Bank of Abyssinia, Oromia International, Cooperative Bank of Oromia, Hibret, Wegagen, Zemen, Nib International, or another payout account in their profile; the details are visible to that employer on the accepted work order.
2. The worker opens **My work** and submits one to five deliverables: documents, photos, videos, or other supported files (25 MB per item, 30 MB combined).
3. The employer reviews the files and either requests revisions with feedback or approves the delivery.
4. Approval issues an invoice for the agreed amount. The employer pays outside the website and records the payment reference and, optionally, uploads a screenshot of the payment.
5. Recording payment marks the invoice paid and the work order complete. No card details or online payment are handled by this system.
6. The worker, company, and admin can message each other in the work-order chat. The admin can open any chat from **Work chats**. Either party can also report a payment, quality, or agreement issue; administrators can review the report, reply, and mark it open, in review, or resolved from **Work order issues**.

If you already imported an earlier version of `database.sql`, select the existing `job_finder_system` database in phpMyAdmin and import `database_work_orders.sql`. This safely adds missing work-order, payment-proof, chat, company-phone, issue, job deadline, exact job-coordinate, and payout-profile fields without dropping or replacing existing tables or data. The migration is safe to re-import and does not require creating a stored procedure.

Phone fields accept Ethiopian mobile numbers such as `0912345678` and `+251912345678` (including common spaces or hyphens) and save them in a normalized format. A phone number, email, or payout account number cannot be reused by another account. The migration adds database uniqueness indexes when existing values allow them; it preserves existing duplicate profile values rather than changing account data. No national-ID validation is required.

Employers and administrators can click the map or enter latitude and longitude to save an exact job pin. Existing listings remain valid without coordinates, and remote roles can leave both fields blank. Maps and map tiles are provided by Leaflet and OpenStreetMap; loading the map requires an internet connection.

Submitted names, job titles, locations, budgets, account details, and other free-text fields are validated on the server. Descriptive textareas must contain at least five letters or numbers when provided; messages and notes allow concise replies such as “OK” while rejecting repeated-character spam, repeated patterns, excessive symbols, and number-only content. Optional notes can be left blank. Browser validation gives immediate feedback, but server validation is authoritative.

## Security notes

- Passwords use PHP's `password_hash`/`password_verify`.
- Database operations use PDO prepared statements.
- State-changing forms require a session CSRF token; role checks protect dashboards and employer resources.
- For a production deployment, configure HTTPS, set secure session cookie options, set non-default database credentials, and use a web server rule to keep application and SQL files out of public access.
