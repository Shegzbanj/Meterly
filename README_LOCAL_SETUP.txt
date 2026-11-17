METERLY - LOCAL XAMPP SETUP
===========================
1. Copy the Meterly folder into C:\xampp\htdocs\meterly.
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Open http://localhost/phpmyadmin. If this is a fresh installation, create/select meterly_db and import database/meterly_db.sql from this package.
4. If you already have meterly_db with your existing records, DO NOT import the full dump again. Select your existing meterly_db and import only database/meterly_additions.sql. This preserves your existing records while adding the activity table and refreshing overdue statuses. The additions file is safe to run more than once.
5. Confirm config/database.php uses the local XAMPP values (host localhost, database meterly_db, user root, blank password) unless you have changed them.
6. Open http://localhost/meterly/.

IMPORTANT CONFIGURATION
- Keep Paystack and Gmail secrets outside htdocs, in C:\xampp\private\paystack.php and C:\xampp\private\meterly_mail.php respectively.
- Do not publish secret keys or upload them in a project ZIP.
- Paystack checkout requires cURL enabled in PHP and a valid secret key.
- Email reminders require the Gmail SMTP configuration and a scheduled CLI run; the browser cannot run a protected CLI script.
- The included database/meterly_additions.sql adds the persistent administrator activity table and marks already-past unpaid bills overdue.

DEFAULT APP URL
http://localhost/meterly/

PHP EXTENSIONS
- Enable pdo_mysql for MySQL database access.
- Enable curl for Paystack API requests.
- Enable openssl for secure SMTP and HTTPS connections.
- The XAMPP PHP version bundled with your installation should be PHP 8.0 or newer.

PRESENTATION LIMITATIONS TO EXPLAIN HONESTLY
- Meterly stores users, utility accounts, bills, payment attempts, reminders and consumption records in MySQL.
- Paystack checkout is a real payment gateway flow when the private key is configured. Paystack confirmation updates the Meterly bill record; it does not automatically vend electricity units or settle the provider account.
- Internet/data bundle checkout, live meter readings, automatic provider account validation, and live utility vending are not integrated with external providers and remain demonstration-only.
- Browser notifications require browser permission and the page to remain open. Email reminders require the SMTP configuration and scheduled server-side execution.
