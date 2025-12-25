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
- Deck detail main-deck add-one now supports async updates via ajax.
- Deck detail main-deck delete and minus-one now support async updates via ajax.
- Deck detail derived sections now render via fragment includes and can refresh via ajax.
- Fixed deck detail fragment currency formatting to avoid clobbering mana counters.
- Documented deckdetail fragment dependencies and refresh flow.
- Moved additional deck detail UI handlers into `js/deckdetail.js`.
- Added fragment rendering tests for deck detail and the fragment renderer.
- Added mana and deck value fragment tests plus a fragment response structure test.
- Sideboard ajax inserts now honor preferred card display names (e.g., flavor name).
- Sideboard ajax inserts now respect copy limits when rendering add buttons.
- Main deck hover images no longer disappear after move-to-sideboard.
- Move-to-sideboard fragment refresh now skips decklist replacement for faster hovers.
- Deck detail hover handlers now clear previous bindings to avoid stacked delays after ajax updates.
- Fixed deck detail fragment totals when decklist rendering is skipped in ajax refresh.
- Random draw now renders via fragment and refreshes with ajax updates.
- Wishlist decks no longer enforce copy limits.
- Fixed deck detail fragment refresh handler so ajax updates trigger dependent fragments.

### Fixed
- Login already-logged-in page now renders with the login-style layout instead of a blank/unstyled view.
- Trust device prompt now requires the post-login flow flag so it can’t be opened directly.
- Trust device direct access now logs an error before redirecting.
- Fixed trust device flow to log access errors after autoload setup to avoid fatal exceptions.
- Deck detail async image loading now skips synchronous fetches to avoid long page loads.
- Deck detail hover images now rotate split/planar/siege cards in the main list and random draw.
- Deck duplicate now retains Commander and Partner/Background status when copying decks.
- Deck export now prefixes the deck name with "Deckname:" and imports ignore that header line.
- Commander deck quantity checks now consider f1_ability/f2_ability for any-quantity rules.
- Non-Commander decks now enforce copy limits (main+side combined) with any-quantity and up-to-N overrides.
- Deck detail move-to-sideboard now updates sideboard rows even when the sideboard section was empty.
- Commander deck sideboard inserts now match the column layout after move-to-sideboard.
- Commander deck async totals now update correctly after main-deck deletions.
- Commander deck totals now include commander zone cards during async updates.
- Sideboard ajax inserts now apply legality/colour-identity styling and hover image classes.
- Sideboard ajax inserts now attach hover divs correctly for touch/hover display.
- Deck detail sideboard rows now compute base card names safely when decks are empty.
- Deck import warnings now report when copy limits reduce or block line quantities.
- Deck quick add now reports when card copy limits block or cap single-line adds.
- Deck detail add-one buttons now reappear after async minus-one reduces copy totals.
- Deck detail copy-limit checks now use deckwide totals by base card name.
- Deck detail async updates now refresh section totals and main total counts.
- Deck detail lands rows now update async quantities and totals correctly.
- Deck detail move-to-sideboard now runs async and removes main-deck rows.
- Deck detail async move-to-sideboard now updates main and sideboard rows inline.
- 'Other' card types no longer break non-commander decktype lists.
- TCGPlayer buttons now does not render with unwanted padding (deckdetail).
- Deck detail fragment rendering now runs the deck list first to avoid stale or undefined totals.
- Deck detail fragment renderer now imports global scope so fragments receive deck data in ajax mode.
- Deck detail random draw fragment now loads its ajax include path correctly.
- Random draw ajax now respects include mode to avoid reloading init files during fragment renders.
- Deck detail sideboard actions now run via ajax without PRG refreshes.
- Deck detail fragment refresh now preserves FX currency formatting for deck value.
- Commander and partner moves now run via ajax without PRG refreshes.
- Random draw now binds reliably on first page load after fragment rendering.
- Colour identity fragment now hides when no commander is set after ajax updates.
- Deck type changes now run via ajax to avoid lingering GET state on refresh.
- Deck detail quick add now runs via ajax with live fragment refresh.
- Deck detail fragments now use a deck update timestamp to guard against stale ajax responses.
- Deck schema now includes `deck_updated_at` for versioned deck refreshes.
- Deck update timestamps now store microseconds for stricter ordering.
- Deck detail mutation endpoints now return fragments in the same response for single-step updates.
- Deck updated timestamps now use microseconds on mutation bumps for consistent ordering.
- Deck detail fragments now use a central registry for keys, IDs, and default inclusion.
- Deck detail handlers now use delegated events to survive fragment swaps.
- Deck detail fragment updates now fall back to a full reload on apply failures.
- Deck detail deck rename now runs via ajax and refreshes deck list fragments.
- Deck detail text/CSV imports now run via ajax with live fragment refresh.
- Deck detail photo upload/delete now runs via ajax handlers with delegated events.

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
