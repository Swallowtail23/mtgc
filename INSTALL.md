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
   php_admin_value[display_errors] = Off
   php_admin_value[display_startup_errors] = Off
   php_admin_value[log_errors] = On
   php_admin_value[error_log] = /var/log/mtg/mtgapp.log
   ```
   Note: `error_log` applies to the entire php-fpm pool. On bare metal with multiple sites, omit it unless you
   want all pool errors routed into the MTG log.

   Optionally set a custom session name by copying
   `includes/sessionname_template.php` to `includes/sessionname.local.php`.
5. Check other security hardening settings (firewall, TLS, headers, etc.).

## File/Directory setup

- Keep `/opt/mtg` outside the web root. The ini contains database and optional
  third-party credentials, so do not put it in the repository, a web-served
  directory, or an unencrypted shared backup.
- Create the directory and copy the ini with restrictive permissions. Substitute
  the PHP-FPM/Apache account and group for `www-data` where necessary (on RHEL,
  these are commonly `apache`):

  ```bash
  sudo install -d -o root -g www-data -m 0750 /opt/mtg
  sudo install -o www-data -g www-data -m 0600 setup/mtg_new.ini /opt/mtg/mtg_new.ini
  ```

  This permits the application service account to read and update the existing
  ini through the Admin UI while preventing other local users from reading it.
  If the Admin UI configuration editor is disabled, prefer a root-owned,
  read-only deployment file instead:

  ```bash
  sudo chown root:www-data /opt/mtg/mtg_new.ini
  sudo chmod 0640 /opt/mtg/mtg_new.ini
  ```

  Verify the application account has the access required by the selected mode:

  ```bash
  sudo -u www-data test -r /opt/mtg/mtg_new.ini
  # Required only when Admin UI configuration editing is enabled:
  sudo -u www-data test -w /opt/mtg/mtg_new.ini
  ```

- Edit `/opt/mtg/mtg_new.ini` per your environment (see README for key settings).
- Copy `setup/data_updates.sh` into `/opt/mtg/scripts/`, make it executable, and set `MTG_APP_ROOT` to the application
  directory when invoking it from that separate location.
- Ensure the log file path specified in the ini exists and is writable (e.g.
  `/var/log/mtg/mtgapp.log`).
- Ensure `ImgLocation` specified in the ini exists, is writable, and contains a
  writable `json` folder for Scryfall downloads. Card and rulings bulk caches
  are stored there as `.jsonl.gz` files. Ideally, symlink this to a large
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
it (root crontab):

```
5 0 * * * /usr/sbin/logrotate /etc/logrotate.d/mtgc
```

## Database setup

- Create the `mtg_new` database (see `setup/mtg_new.sql`).
- Provision a MySQL user with appropriate privileges and update the ini with the
  database credentials/host.
- Import `setup/mtg_new.sql` into the database.
- Run the data update wrapper to populate Scryfall-managed data.

  ```bash
  MTG_APP_ROOT=/path/to/mtgnew /opt/mtg/scripts/data_updates.sh new
  ```
  The `new` run performs the supported first-load sequence: sets, all-cards
  bulk import, default-cards bulk import, rulings, migrations, manifest
  metadata, and sync-state backfill. See also Images section.
  The `data_updates.sh new` run can take a long time.

## User setup

- Add a first (admin) user in MySQL. setup/initial.php can be used to create a
  hashed password.

## Composer dependencies

Run Composer as the owner of the source checkout. Do not make the application
root writable by the Apache/PHP service account solely so it can manage
dependencies. For a root-owned production checkout, explicitly acknowledge
Composer's superuser mode and install the versions recorded in `composer.lock`:

```bash
cd /var/www/mtgnew
COMPOSER_ALLOW_SUPERUSER=1 composer install \
  --no-dev --no-interaction --prefer-dist --optimize-autoloader
```

This pulls in JSONMachine (still used for Scryfall sets/migrations/manifest and
legacy JSON reads), PHPMailer, Turnstile, FX API, OTPHP, and QR code libs. On a
development checkout, omit `--no-dev` to also install PHPUnit, PHPCS, and the
PHP compatibility rules. A non-root source owner can run the same commands
without `COMPOSER_ALLOW_SUPERUSER=1`.

### Composer dependency maintenance

Resolve dependency updates in a trusted source checkout, test them, and commit
the resulting `composer.lock`. Production deployments should run
`composer install`, never `composer update`, so every server receives the
reviewed versions from the lock file.

1. Check direct dependencies and audit both the complete development set and
   the production-only set:

   ```bash
   COMPOSER_ALLOW_SUPERUSER=1 composer outdated --direct
   COMPOSER_ALLOW_SUPERUSER=1 composer audit
   COMPOSER_ALLOW_SUPERUSER=1 composer audit --no-dev
   ```

2. Update only the intended packages. Replace the example names with actual
   package names reported by Composer:

   ```bash
   COMPOSER_ALLOW_SUPERUSER=1 composer update \
     vendor/package-one vendor/package-two \
     --with-all-dependencies --minimal-changes
   ```

   Use an unrestricted `composer update --with-all-dependencies` only for a
   deliberate full dependency refresh. If a constraint in `composer.json`
   must change, use `composer require vendor/package:^VERSION` (or add `--dev`
   for a development-only dependency) and review both Composer files.

3. Validate the resolved dependencies and run the quality gates:

   ```bash
   COMPOSER_ALLOW_SUPERUSER=1 composer validate --strict
   COMPOSER_ALLOW_SUPERUSER=1 composer audit
   COMPOSER_ALLOW_SUPERUSER=1 composer audit --no-dev
   vendor/bin/phpunit --configuration phpunit.xml
   vendor/bin/phpcs --report=summary
   ```

4. Review `git diff -- composer.json composer.lock`, add a changelog entry for
   meaningful dependency or security changes, and commit `composer.lock` plus
   `composer.json` when its constraints changed. Never commit `vendor/`.

After pulling that commit on a root-owned production server, rerun the
`composer install --no-dev` command above and restart Apache/PHP-FPM when the
release or local deployment process requires it.

## Email / Disqus / Turnstile / FX setup

- Configure SMTP credentials in the ini if you plan to send email.
- Enable Disqus by providing your shortname and enabling it in the ini.
- Obtain Cloudflare Turnstile keys and set them in the ini to enable login
  protection (dev tier uses dummy keys).
- Obtain a FreecurrencyAPI key (optional). Leaving the key blank or set to
  'disabled' disables FX.

## Cloudflare cache rules

If you use Cloudflare in front of the app, bypass caching for dynamic resources.
Recommended Cache Rules:

- `http.request.uri.path eq "/service-worker.js"`
- `http.request.uri.path eq "/manifest.json"`
- `http.request.uri.path eq "/index.php" and http.request.uri.query ne ""`

Set each rule action to `Cache: Bypass`.

## Cron / scheduled tasks

Copy `setup/data_updates.sh` to `/opt/mtg/scripts` (as described earlier) and
use the sample schedule in `setup/cron_mtgc.crond` as a starting point.
Recommended frequencies:

- `data_updates.sh nightly` (nightly Monday-Saturday): syncs sets, default
  cards, rulings, migrations, manifest timestamps, collection snapshots, and
  expired trusted-device cleanup.
- `data_updates.sh weekly` (weekly Sunday): runs the all-cards refresh before
  the normal nightly flow, then runs weekly exports, collection snapshots, and
  expired trusted-device cleanup.
- `data_updates.sh refresh --confirm` (manual only): truncates Scryfall-managed
  data tables and repopulates them from scratch.
- `logrotate` (daily): rotates `/var/log/mtg/*.log` using `/etc/logrotate.d/mtgc`.

Install the cron file (adjusting the user, script path, and log locations):

```bash
sudo cp setup/cron_mtgc.crond /opt/mtg/cron_mtgc
sudo sed -i 's|/opt/mtg|/your/script/path|' /opt/mtg/cron_mtgc
sudo sed -i 's|/var/www/mtgnew|/your/application/path|' /opt/mtg/cron_mtgc
sudo sed -i 's|/var/log/mtg|/your/log/path|' /opt/mtg/cron_mtgc
sudo mv /opt/mtg/cron_mtgc /etc/cron.d/mtgc
sudo systemctl reload crond    # or the cron service on your distro
```

Ensure the cron user can execute PHP, access the configured `MTG_APP_ROOT`, and write to the log directory referenced
in the cron file.

## Existing bare-metal WebP upgrade

Use this checklist on every existing development, staging, and production
bare-metal host. It upgrades image handling without deleting card data or
converting the existing JPEG cache in bulk.

### 1. Verify the PHP runtime

Confirm that the PHP installation used by the application has GD WebP support:

```bash
php -r '$info = gd_info(); var_export($info["WebP Support"] ?? false); echo PHP_EOL;'
```

The result must be `true`. On a RHEL-family host, install the GD and WebP
packages that match the configured PHP or Remi stream if support is absent, then
restart PHP-FPM. Do not mix packages from a different PHP stream.

```bash
sudo dnf install php-gd libwebp
sudo systemctl restart php-fpm
```

### 2. Merge the Apache changes

Do not copy `setup/mtgc.conf` wholesale over an existing vhost: its addresses,
hostnames, certificates, and paths are examples. Merge these directives into
the active vhost, substituting the host's actual image and application paths.

Add WebP MIME handling to the aliased card-image directory:

```apache
<Directory /mnt/data/cardimg/>
    AddType image/webp .webp
    Require all granted
</Directory>
```

Inside the application document-root `<Directory>` block, add:

```apache
AddType image/webp .webp
ExpiresByType image/webp "access plus 1 months"
```

Add `webp` to the existing image compression exclusion:

```apache
SetEnvIfNoCase Request_URI \.(?:exe|t?gz|zip|iso|tar|bz2|sit|rar|png|jpg|gif|jpeg|webp|flv|swf|mp3)$ no-gzip dont-vary
```

Validate the required modules and configuration before reloading Apache:

```bash
sudo httpd -M | grep -E '(mime|expires|deflate|headers)_module'
sudo apachectl configtest
sudo systemctl reload httpd
```

Existing image-directory ownership and SELinux rules do not depend on the file
extension. If the application can already create JPEGs there, no WebP-specific
permission change is required.

### 3. Deploy and restart the application runtime

Deploy the PHP, JavaScript, `service-worker.js`, and documentation changes
together. Ensure the deployed `VERSION` differs from the previous release so
trusted browsers request the new static assets and service worker. For repeated
deployments using the same development version, disable the browser cache and
hard reload.

```bash
sudo systemctl restart php-fpm
```

An untrusted development HTTPS origin cannot run a service worker. Missing-image
downloads still work through AJAX there; test service-worker cache replacement
on a trusted HTTPS staging/production origin or on `localhost`.

### 4. Remap existing database image URLs

Existing rows retain the previously imported `normal` JPEG URLs until the card
mapper runs again. Mark only the two card sources for re-import:

```sql
USE mtg_new;
UPDATE scryfall_bulk_sources
SET status = 'failed'
WHERE source_type IN ('all_cards', 'default_cards');
```

Then run the focused card refresh as the normal application or scheduled-task
account:

```bash
cd /var/www/mtgnew
php bulk/scryfall_bulk.php refresh
```

This direct command is non-destructive. It runs the All Cards pass followed by
the Default Cards pass automatically, so do not run a separate default pass.
The All Cards pass does not download images, and the following Default Cards
pass updates rows that already exist, so this operation does not bulk-download
the image catalogue.

Do **not** run `setup/data_updates.sh refresh --confirm` for this upgrade. That
workflow deliberately resets Scryfall-managed data before repopulating it.

### 5. Verify the host

Confirm both imports completed and that WebP URLs were mapped:

```sql
SELECT source_type, status, last_import_completed_at
FROM scryfall_bulk_sources
WHERE source_type IN ('all_cards', 'default_cards');

SELECT id, setcode, COALESCE(image_uri, f1_image_uri) AS image_uri
FROM cards_scry
WHERE COALESCE(image_uri, f1_image_uri) LIKE '%.webp%'
LIMIT 10;
```

Exercise these UI cases:

- A missing search/index image downloads as WebP and replaces its placeholder.
- An existing JPEG loads without being converted.
- Explicit Card Detail refresh replaces the applicable JPEG face or faces with
  WebP.
- Primary-language and all-language set reloads retain their selected scope.

Verify a downloaded response:

```bash
curl -sI --compressed https://host/cardimg/set-code/card-id.webp
```

Expect `Content-Type: image/webp`, the configured cache headers, and no gzip
`Content-Encoding`. On a trusted origin, also confirm browser Cache Storage uses
`mtg-images-webp1-<VERSION>` and does not retain an alternate JPEG after an
explicit refresh.

Keep the existing `.jpg` files. Phase one has no bulk conversion or deletion
step; WebP files are added for cache misses and replace JPEGs only during an
explicit refresh of the affected card or set.

## Final checks

- Confirm the application account can read `/opt/mtg/mtg_new.ini`; it requires
  write access only when Admin UI configuration editing is enabled. Also confirm
  the log directory and `ImgLocation` are writable.
- Verify file permissions on card images and JSON cache directories.
- Log in, configure the admin email/SMTP via the UI, and run through the initial
  bulk data validation.

## Images

- The initial `data_updates.sh new` run loads all cards before the default-card
  pass. The first bulk pass avoids downloading the full image catalogue; the
  second pass marks the primary language and only downloads images for truly new
  rows.
  Subsequent bulk runs download WebP images as new cards appear.
- Cached Scryfall images are resolved as WebP first and legacy JPEG second.
  A missing image encountered through the UI is downloaded as WebP where
  available. Existing JPEGs remain usable and normal page/deck checks do not
  replace them.
- Card Detail image refresh and set downloads from the Sets page explicitly
  replace affected cache entries using Scryfall WebP where available.
- Ensure GD has JPEG and WebP support and that Apache serves `image/webp` without
  applying compression. The supplied `setup/mtgc.conf` contains the expected
  MIME, expiry, and compression exclusions.
- Bare metal installs should use `data_updates.sh new` for first load to avoid
  triggering a full image download. If you run only `php bulk/scryfall_bulk.php default`
  on an empty database, it will download images for all cards inserted during that run.
- See `docs/scryfall_images.md` before changing cache naming or download triggers.
