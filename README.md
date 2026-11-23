# EVENTAURA website: setup guide

The public site is HTML, CSS and JS. Registration, login and the admin dashboard use **PHP 8 + MySQL**.

## Folder structure

```
index.html            Public website (French)
inscription.php       Member registration form
connexion.php         Member login (saves last-login time)
espace.php            Member area (after login)
deconnexion.php       Member logout
admin/
  setup.php           Create the FIRST admin account (delete after use)
  connexion.php       Admin login
  index.php           Dashboard: stats + member list + filters
  export.php          Download members as CSV (opens in Excel)
  parametres.php      Add/remove admins, change password
includes/
  config.php          ← DATABASE SETTINGS (edit this)
  bootstrap.php       DB connection, security, helpers
  layout.php          Shared header/footer
assets/app.css        Styles for PHP pages
sql/database.sql      Import this in phpMyAdmin
```

## Install on hosting (cPanel / Hostinger / InfinityFree …)

1. **Create a database.** In cPanel, open *MySQL Databases*, create a database and a user, and give the user *All privileges* on it.
2. **Import the tables.** Open phpMyAdmin, select the database, go to the **Import** tab, choose `sql/database.sql` and click **Go**. This creates 4 tables: `members`, `login_logs`, `admins` and `login_attempts`.
3. **Edit `includes/config.php`.** Fill in `db_host`, `db_name`, `db_user` and `db_pass` from step 1. Hosting usually uses `localhost`. Some hosts, InfinityFree for example, use something like `sql123.infinityfree.com`.
4. **Upload all files** to `public_html/` with the File Manager or FTP. The `sql/` folder doesn't need to be uploaded.
5. **Create the admin account.** Open `https://your-site.com/admin/setup.php` and create the first admin.
6. **Delete `admin/setup.php`** from the server. It locks itself after the first admin is created, but deleting it is safer.
7. Log in at `https://your-site.com/admin/`.

**Requirements:** PHP 8.1+ with PDO MySQL (standard on all hosts) and MySQL 5.7+ or MariaDB 10.3+. Use HTTPS; most hosts offer free SSL.

## What the dashboard shows

- Total members and new members in the last 30 days
- **Logged in at least once vs. never logged in**, with a donut chart and a "never logged in" list
- Active members in the last 7 days and the number of logins this week
- Registrations per day over the last 14 days
- Members per pôle (registered vs. logged in)
- The 8 most recent logins
- A member table with search, filters (pôle, logged in / never / active, status), sorting and pagination
- Suspend, reactivate or delete a member
- Export to CSV (uses `;` so Excel opens it correctly, with accents)

## Security built in

- Passwords hashed with `password_hash()` (bcrypt)
- All queries use PDO prepared statements, which prevents SQL injection
- CSRF tokens on every form
- Brute-force protection: 8 failed logins lock that IP for 15 minutes
- Admin session expires after 2 hours of inactivity
- `includes/` and `.sql` files are blocked from the browser (`.htaccess`, Apache)
- Bot honeypot on the registration form
- CSV export protected against Excel formula injection

## Notes

- **Forgotten passwords:** there is no automatic email reset, because that needs SMTP setup. Members are asked to email the club. An admin can delete the account so the member registers again, or set a new hash in phpMyAdmin.
- **Changing pôles or study years:** edit the `POLES` and `ANNEES` lists at the top of `includes/bootstrap.php`.
- If the host uses **Nginx** instead of Apache, `.htaccess` is ignored. Ask the host to deny access to `/includes` and `/sql`.
