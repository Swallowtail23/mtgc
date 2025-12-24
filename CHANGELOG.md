# Changelog

All notable changes to this project will be documented in this file.

## [v0.2.3-dev] - Unreleased

### Added
-

### Changed
- Docker init now confirms the admin email will be written to the mtg_new.ini email section.
- HTTP user agent strings are now built from the app version, site URL, and admin email.
- Card detail prices now refresh via an async Scryfall call after initial render.
- Card detail now logs micro-timing checkpoints to compare container vs bare metal performance.
- Card detail no longer disables image caching for admin views.
- Index no longer disables image caching for admin views.
- Card detail uses placeholder images when cardimg is missing and relies on async image refresh.
- Index uses placeholder images when cardimg is missing and relies on async image refresh.
- Card detail keeps the TCGPlayer link visible while async pricing loads.
- Flip buttons stay hidden until both card faces are available.
- Index flip now returns to the real front image after async updates.
- Index image refresh now throttles async checks and yields for user input.
- Async image swaps now fade in instead of snapping from the placeholder.
- Card detail flip button now respects viewport width when both faces are visible.
- Deck detail now uses async image loading with hover/tap priority and placeholders.
- Random draw hover now prioritises async image loading.

### Fixed
- Login already-logged-in page now renders with the login-style layout instead of a blank/unstyled view.
- Trust device prompt now requires the post-login flow flag so it can’t be opened directly.
- Trust device direct access now logs an error before redirecting.
- Fixed trust device flow to log access errors after autoload setup to avoid fatal exceptions.
- Deck detail async image loading now skips synchronous fetches to avoid long page loads.
- Deck detail hover images now rotate split/planar/siege cards in the main list and random draw.

### Security
-

### Infrastructure
- PHP config now suppresses display_errors and logs to mtgapp.log (container + bare metal).
- Added PHPUnit coverage for the UserAgent builder.
- Added PHPUnit coverage for async card price rendering.
- Added PHPUnit coverage for async price response payloads.
- UserAgent test now writes temp files in the workspace for CI compatibility.
- UserAgent test now stubs the expected values directly.

## [v0.2.2] - 2025-12-22

### Fixed
- VERSION increment only.

## [v0.2.1] - 2025-12-22

### Added
- Added: PHPUnit check to ensure `src/MTG` class names and namespaces match their file paths for PSR-4 autoloading.

### Fixed
- Resolve PasswordCheck autoloading by aligning the class filename with its namespace.

## [v0.2.0] - 2025-12-22

- Added: profile value history CSV download and weekly value history export email.
- Added: PHPUnit coverage for collection history CSV exports and core/auth/cards classes.
- Changed: weekly collection/value history/deck exports are delivered in one email, with value history attached.
- Changed: bulk card import now batches writes, logs progress, uses content/price hashes with conditional updates,
  splits update totals by hash type, and requires pre-existing hash columns (with schema updates in setup SQL).
- Changed: rulings import now uses content hashing with batched writes and cleanup of removed rulings; unique key now
  uses content_hash to allow same-day rulings per card/source.
- Changed: classes moved under `src/MTG` namespaces with call sites/tests updated; Composer autoload and docs aligned.
- Changed: profile currency selection is disabled when FX is globally off.
- Security: tightened escaping for GET parameters and rendered titles, and normalised `htmlspecialchars` usage.
- Fixed: bulk import mapping restored and null card faces guarded; rulings bulk JSON quarantine logging corrected.
- Fixed: email title handling corrected for HTML/plain text, CSV parsing uses explicit escapes, and error paths now throw
  exceptions for PHP 8.4 compatibility.
- Fixed: `symbolReplace` returns null on null input; TrustedDeviceManager loads Composer autoload when Message is
  unavailable.
- Infrastructure: Docker builds on PHP 8.4 with composer platform pinned to PHP 8.2.30, and entrypoint normalises
  `sets.sh` card image paths for container mounts.

## [v0.1.3] - 2025-12-16

- Maintenance release tagged 0.1.3 (no changelog was recorded at the time of release).

## [v0.1.0-pre] - 2025-12-03

- Initial Docker/Podman packaging with bootstrap scripts (`docker-init.sh/.bat`).
- Added cron templates, backup helper, logrotate config, and systemd unit.
- Documented upgrade playbook, security hardening, and email configuration.
- Includes full Scryfall bulk workflow, composer automation, and configurable dev bind-mounts.

> This is a pre-release tag used during testing; final tagging will occur after QA completes.
