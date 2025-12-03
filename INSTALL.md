# Bare Metal / Native Installation

## Overview

These steps assume an Apache/PHP/MySQL stack (RHEL/CentOS style paths shown).
Adjust paths/commands for your platform.

1. Clone the repo into your web root (e.g. `/var/www/mtgnew`).
2. Ensure Apache (or your web server) serves that directory and grants the PHP
   process ownership of writable locations (card images, logs, ini file, etc.).
3. Copy the sample Apache vhost from `setup/mtgc.conf` (or
   `docker/mtgc_ctr.conf` for a trimmed version) and adapt paths, SSL certs, and
   access restrictions. Bulk/setup folders are restricted to localhost by
   default—update if needed.
4. Configure php-fpm (if applicable). On RHEL edit `/etc/php-fpm.d/www.conf`:

   ```
   php_admin_value[session.auto_start] = 0
   php_admin_value[session.use_cookies] = 1
   php_admin_value[session.use_only_cookies] = 1
   php_admin_value[session.cookie_httponly] = 1
   php_admin_value[session.cookie_secure] = 1
   php_admin_value[session.cookie_samesite] = Strict
   ```

   Optionally set a custom session name by copying
   `includes/sessionname_template.php` to `includes/sessionname.local.php`.
5. Check other security hardening settings (firewall, TLS, headers, etc.).

## File/Directory setup

- Create `/opt/mtg` (or your preferred config location).
- Copy `setup/mtg_new.ini` to `/opt/mtg/mtg_new.ini` and edit per your
  environment (see README for key settings).
- Copy the helper shell scripts from `setup/*.sh` into `/opt/mtg/scripts/` and
  update bulk script paths (default `/var/www/mtgnew/bulk`).
- Ensure the log file path specified in the ini exists and is writable (e.g.
  `/var/log/mtg/mtgapp.log`).
- Ensure `ImgLocation` (in the ini) exists, is writable, and contains a writable
  `json` folder for Scryfall downloads. Many admins symlink this to a large
  storage volume.

## Log rotation

Rotate `/var/log/mtg/*.log` so the application logs do not fill the disk. A
basic logrotate entry looks like:

```bash
sudo tee /etc/logrotate.d/mtgc >/dev/null <<'EOF'
/var/log/mtg/*.log {
    daily
    rotate 14
    missingok
    notifempty
    compress
    delaycompress
    copytruncate
}
EOF
```

Adjust the frequency or retention as needed and run
`sudo logrotate -f /etc/logrotate.d/mtgc` for an immediate rotation.

## Database setup

- Create the `mtg_new` database (see `setup/mtg_new.sql`).
- Provision a MySQL user with appropriate privileges and update the ini with the
  credentials/host.
- Import `setup/mtg_new.sql` into the database.
- Run the bulk scripts from the `bulk/` directory (in order) to populate data:

  ```bash
  php scryfall_bulk.php all
  php scryfall_sets.php
  php scryfall_rulings.php
  php scryfall_migrations.php
  ```

  The first `scryfall_bulk.php all` run can take a long time; ensure `ImgLocation`
  is writable and has ample space.

## Composer dependencies

Install the required packages as the web user (example assumes Apache):

```bash
sudo -Hu apache composer install
```

This pulls in JSONMachine, PHPMailer, Turnstile, FX API, OTPHP, QR code libs,
and PHPUnit (dev).

## Email / Disqus / Turnstile / FX setup

- Configure SMTP credentials in the ini if you plan to send email. You may need
  SPF/DKIM/DMARC records for deliverability.
- Enable Disqus by providing your shortname and enabling it in the ini.
- Obtain Cloudflare Turnstile keys and set them in the ini to enable login
  protection (dev tier uses dummy keys).
- Obtain a FreecurrencyAPI key (optional). Leaving the key blank disables FX.

## Cron / scheduled tasks

- Schedule the helper scripts in `/opt/mtg/scripts` to run at desired intervals
  (bulk refresh, weekly exports, etc.).
- Ensure the cron user can access PHP, the web root, and `/opt/mtg`.

## Final checks

- Confirm Apache can read/write `/opt/mtg/mtg_new.ini`, the log directory, and
  `ImgLocation`.
- Verify file permissions on card images and JSON cache directories.
- Log in, configure the admin email/SMTP via the UI, and run through the initial
  bulk data validation.
