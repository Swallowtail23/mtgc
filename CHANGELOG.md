# Changelog

All notable changes to this project will be documented in this file.

## [v0.4.5] - 2025-12-28

### Changed
- Service worker cache version now derives from the registration query string.

### Fixed
- Index async image refresh now only swaps images when the backend detects a change and restores placeholders on load errors.
- Added debug logging for async image refresh change decisions.

## [v0.4.4] - 2025-12-28

### Added
- Index.php infinite scroll now supports loading previous pages when scrolling up (IAS v3.1.0).

### Changed
- Top button now hidden when the page parameter is missing or set to 1.

### Fixed
- Infinite scroll no longer appends timestamp cache-busters to index page URLs.

### Infrastructure
- Sample Apache configs now disable caching for `index.php` to prevent CDN or proxy caching of paged results.
- Documented Cloudflare cache bypass rules for dynamic app routes.

## [v0.4.3] - 2025-12-27

### Added
- Added loglevel 4 bulk diagnostic mode to emit verbose Scryfall bulk row diagnostics.

### Fixed
- Login flow now preserves requested destinations across failed logins and trust-device prompts.
- Deck detail random draw spacing and hover behavior corrected for single-column layouts and new draws.

## [v0.4.2] - 2025-12-26

### Changed
- Service worker now shows an update toast to allow immediate refresh after new deployments.
- Service worker registration now includes a version query to force revalidation on deploy.

### Fixed
- Service worker now avoids caching HTML/fragments and uses safer asset/image caching to prevent blank renders.
- Service worker now forces PHP requests to stay network-only for safety.
- Deck detail random draw hover now detaches previews from masonry flow to restore hover behavior.

## [v0.4.1] - 2025-12-26

### Fixed
- CSS minifier now preserves media query spacing to prevent rule breakage.

## [v0.4.0] - 2025-12-26

### Changed
- Deck detail now uses a masonry-style sidebar layout with responsive stacking for notes, stats, and actions.
- Deck detail moves Random Draw below the masonry group for tall, three-column layouts.
- Deck detail adds 'hero' image section on wider screen displays (1890px+).
- Deck detail uses a scrollable deck list when the masonry is side-by-side.
- Admin panel now regenerates `css/style-min.css` before enabling minified CSS.

### Fixed
- Sets pagination now restores the correct page when navigating back in history.

## [v0.3.0] - 2025-12-25

### Changed
- Docker init now confirms the admin email will be written to the mtg_new.ini email section.
- HTTP user agent strings are now built from the app version, site URL, and admin email.
- Card detail prices now refresh via an async Scryfall call after initial render.
- Card detail and index no longer disable image caching for admin views.
- Card detail and index use placeholder images when cardimg is missing and relies on async image refresh.
- Card detail keeps the TCGPlayer link visible while async pricing loads.
- Flip buttons stay hidden until both card faces are available.
- Index image refresh now throttles async checks and yields for user input.
- Async image swaps fade in instead of snapping from the placeholder.
- Card detail JS handlers now live in `js/carddetail.js`.
- Deck detail now uses async image loading with hover/tap priority and placeholders.
- Random draw hover now prioritises async image loading.
- Deck detail functions all migrated to async updates via ajax.
- Documented deckdetail fragment dependencies and refresh flow.
- Added fragment rendering tests for deck detail and the fragment renderer.

### Fixed
- Login already-logged-in page now renders with the login-style layout instead of a blank/unstyled view.
- Trust device prompt now requires the post-login flow flag so it can’t be opened directly.
- Trust device direct access now logs an error before redirecting.
- Fixed trust device flow to log access errors after autoload setup to avoid fatal exceptions.
- Deck detail hover images now rotate split/planar/siege cards in the main list and random draw.
- Deck duplicate now retains Commander and Partner/Background status when copying decks.
- Deck export now prefixes the deck name with "Deckname:" and imports ignore that header line.
- Commander deck quantity checks now consider f1_ability/f2_ability for any-quantity rules.
- Non-Commander decks now enforce copy limits (main+side combined) with any-quantity and up-to-N overrides.
- Deck detail fragments now use a central registry and single-step mutation responses with stale-response protection
  and reload fallback.
- Deck schema now includes `deck_updated_at` (microsecond precision) for versioned deck refreshes.
- Copy-limit enforcement now uses deckwide totals; imports/quick add report capped or blocked quantities.
- CSV input parsing now avoids HTML escaping during import for accurate name matching.
- Random draw now validates inputs and escapes output to prevent client-side injection.
- Deck list sections now default to collapsed with per-section toggles and a fold/unfold-all control.
- Deck detail hover/touch and image-loading JS now lives in `js/deckdetail.js`.
- Card detail navigation now centralizes arrow key handling and adds swipe navigation.

### Security
- Deck detail ajax endpoints now require CSRF tokens; referrer checks removed from deck endpoints.
- Deck detail ajax responses and deck detail pages now disable caching to avoid CSRF token leakage.

### Infrastructure
- PHP config now suppresses display_errors and logs to mtgapp.log (container + bare metal).
- Added PHPUnit coverage for the UserAgent builder.
- Added PHPUnit coverage for async card price rendering.
- Added PHPUnit coverage for async price response payloads.
- UserAgent test now writes temp files in the workspace for CI compatibility.
- UserAgent test now stubs the expected values directly.
- DeckManager batch insert now tolerates DB stubs without execute_query for tests.

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
- Changed: rulings import now uses content hashing with batched writes and cleanup of removed rulings; unique key
  uses content_hash to allow multiple same-day rulings per card/source.
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

## [v0.1.0] - 2025-12-03

- Initial Docker/Podman packaging with bootstrap scripts (`docker-init.sh/.bat`).
- Added cron templates, backup helper, logrotate config, and systemd unit.
- Documented upgrade playbook, security hardening, and email configuration.
- Includes full Scryfall bulk workflow, composer automation, and configurable dev bind-mounts.
