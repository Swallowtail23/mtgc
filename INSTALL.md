**NOTE: Docker/Podman is the recommended install - see DOCKER.md**

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
   php_admin_value[upload_max_filesize] = 32M
   php_admin_value[post_max_size] = 32M
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
`sudo logrotate -f /etc/logrotate.d/mtgc` for an immediate rotation. Schedule
it (root crontab) to mirror the container defaults:

```
5 0 * * * /usr/sbin/logrotate /etc/logrotate.d/mtgc
```

## Database setup

- Create the `mtg_new` database (see `setup/mtg_new.sql`).
- Provision a MySQL user with appropriate privileges and update the ini with the
  database credentials/host.
- Import `setup/mtg_new.sql` into the database.
- Run the bulk scripts from the `bulk/` directory (in order) to populate data.

  ```bash
  php scryfall_bulk.php all
  php scryfall_bulk.php default
  php scryfall_sets.php
  php scryfall_rulings.php
  php scryfall_migrations.php
  ```
  The double run of scryfall_bulk.php is required for initial setup;
  The first `all` pass writes every card record; the second `default` pass
  marks the primary language. See also Images section.
  The first `scryfall_bulk.php all` run can take a long time.

## User setup

- Add a first (admin) user in MySQL. setup/initial.php can be used to create a
  hashed password.

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
- Obtain a FreecurrencyAPI key (optional). Leaving the key blank or set to
  'disabled' disables FX.

## Cron / scheduled tasks

Copy the helper scripts to `/opt/mtg/scripts` (as described earlier) and use the
sample schedule in `setup/cron_mtgc.crond` as a starting point.
Recommended frequencies:

- `bulk_all.sh` (weekly): refreshes the entire Scryfall dataset without
  downloading images.
- `sets.sh` (daily): syncs set metadata so new releases appear promptly.
- `migrations.sh` (daily): applies incremental data fixes or extra inserts.
- `rulings.sh` (3× weekly): updates oracle rulings from Scryfall.
- `bulk.sh` (nightly): reprocesses the default-language subset and downloads
  any new images.
- `weekly.sh` (weekly): runs the weekly export helper scripts.
- `collection_snapshots.sh` (daily): records collection value history for charts.
- `logrotate` (daily): rotates `/var/log/mtg/*.log` using `/etc/logrotate.d/mtgc`.

Install the cron file (adjusting the user, script path, and log locations):

```bash
sudo cp setup/cron_mtgc.crond /opt/mtg/cron_mtgc
sudo sed -i 's|/opt/mtg|/your/script/path|' /opt/mtg/cron_mtgc
sudo sed -i 's|/var/log/mtg|/your/log/path|' /opt/mtg/cron_mtgc
sudo mv /opt/mtg/cron_mtgc /etc/cron.d/mtgc
sudo systemctl reload crond    # or the cron service on your distro
```

Ensure the cron user can execute PHP, access `/var/www/mtgnew`, and write to the
log directory referenced in the cron file.

## Final checks

- Confirm Apache can read/write `/opt/mtg/mtg_new.ini`, the log directory, and
  `ImgLocation`.
- Verify file permissions on card images and JSON cache directories.
- Log in, configure the admin email/SMTP via the UI, and run through the initial
  bulk data validation.

## Images

- The first setup pass (`php bulk/scryfall_bulk.php all`) loads all cards without
  downloading the ~90k images; the second pass (`php bulk/scryfall_bulk.php default`)
  marks the primary language and only downloads images for truly new rows.
  Subsequent bulk runs download images as new cards appear.
- Image sets can be downloaded for specific sets from the Sets page.
- Bare metal installs should follow the same command order above to avoid
  triggering a full image download. If you run only `php scryfall_bulk.php default`,
  on an empty database it will download images for all cards inserted during that run.
