# Changelog

All notable changes to this project will be documented in this file.

## [v0.1.4-dev] - Unreleased

- Hardened the Scryfall bulk import workflow with stricter argument handling, clearer CLI output, and single-pass
  refresh logic for the all/default datasets.
- Added run-start logging to setup/bulk shell helpers and mirrored key progress messages to CLI output across bulk
  scripts for easier monitoring in cron/containers.
- Profile: disable local currency selection whenever FX is turned off globally to avoid invalid choices.
- Security hardening: tightened escaping for GET parameters and rendered titles, and normalised `htmlspecialchars`
  usage to specify charset/flags explicitly.
- Maintenance: introduced/updated Dependabot configuration and cleaned minor formatting issues.
- Fixed: corrected the revised `downloadBulk` call in bulk scripts to avoid misfires.
- Fixed: corrected an inaccurate log message in bulk scripts to reflect the correct workflow step.
- Fixed: set the JSON quarantine rename result before logging to avoid incorrect error reporting.
- Infrastructure: documented changelog update requirements in `AGENTS.md`.
- Changed: added NOTICE-level progress logging every 2,500 records during Scryfall bulk imports.
- Fixed: return null from `symbolReplace` when given null input.
- Infrastructure: batch Scryfall bulk imports in 5,000-row transactions and log batch commits.
- Changed: bulk import uses content/price hashes with conditional updates to skip full writes when card content is unchanged.
- Changed: bulk import summary now splits updates by content-hash changes vs price-hash changes.
- Infrastructure: entrypoint normalises `sets.sh` to use the mounted card image path in the container.
- Fixed: repair and normalise `sets.sh` card image path rewrite in entrypoint.
- Infrastructure: added content_hash/price_hash columns in `setup/mtg_new.sql` and on-demand schema checks in bulk import.
- Fixed: guard null `f1_` fields in card detail rendering to avoid errors.
- Fixed: guard null `f2_` fields in card detail rendering to avoid errors.
- Fixed: restore card face and all_parts field mapping during Scryfall bulk import.
- Fixed: escape site title in collection history HTML email content.
- Fixed: keep site title raw in email subjects and plain-text bodies.
- Fixed: replace E_USER_ERROR trigger_error usage with exceptions for PHP 8.4 compatibility.
- Fixed: pass explicit CSV escape characters for fputcsv/str_getcsv to avoid future default changes.
- Infrastructure: Docker now builds on PHP 8.4 with composer platform pinned to PHP 8.2.30, and docker-init pulls base images on rebuild.
- Changed: log elapsed time for refresh bulk runs after downloading bulk files.
- Changed: instantiate `\MTG\Core\Message` with fully-qualified names after moving the class into `src/MTG`.
- Changed: update PasswordCheck usage to the namespaced `\MTG\Auth\PasswordCheck` class after relocating it to `src/MTG`.
- Fixed: correct namespace resolution for Exception/MyPHPMailer usage in `\MTG\Auth\PasswordCheck`.
- Changed: update TwoFactorManager usage to the namespaced `\MTG\Auth\TwoFactorManager` class after relocating it to `src/MTG`.
- Changed: update DateYMD usage to the namespaced `\MTG\Core\DateYMD` class after relocating it to `src/MTG`.
- Changed: update CollectionHistory usage to the namespaced `\MTG\Cards\CollectionHistory` class after relocating it to `src/MTG`.
- Changed: update CollectionStats usage to the namespaced `\MTG\Cards\CollectionStats` class after relocating it to `src/MTG`.
- Changed: update UserStatus usage to the namespaced `\MTG\Auth\UserStatus` class after relocating it to `src/MTG`.
- Changed: move remaining legacy classes into `src/MTG` namespaces and update all call sites/tests accordingly.
- Infrastructure: update docs to reflect namespaced `src/MTG` class layout and Composer autoloading.
- Added: profile value history CSV download and weekly value history export email.
- Infrastructure: added PHPUnit coverage for collection history CSV exports.
- Changed: weekly value history export now attaches to the weekly collection export email.
- Changed: weekly collection, value history, and deck exports are delivered in one email.
- Infrastructure: update PHPUnit bootstrap to load Composer autoload and align Message log level globals.
- Fixed: load Composer autoload in TrustedDeviceManager when Message class is not yet available.
- Changed: drop the global Message class alias now that all references are fully qualified.

## [v0.1.3] - 2025-12-16

- Maintenance release tagged 0.1.3 (no changelog was recorded at the time of release).

## [v0.1.0-pre] - 2025-12-03

- Initial Docker/Podman packaging with bootstrap scripts (`docker-init.sh/.bat`).
- Added cron templates, backup helper, logrotate config, and systemd unit.
- Documented upgrade playbook, security hardening, and email configuration.
- Includes full Scryfall bulk workflow, composer automation, and configurable dev bind-mounts.

> This is a pre-release tag used during testing; final tagging will occur after QA completes.
