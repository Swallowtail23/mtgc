# Changelog

All notable changes to this project will be documented in this file.

## [v0.5.9-dev] - Unreleased

### Added

-

### Changed

- Collection import now supports ManaBox CSV rows in addition to MTGC and Delver Lens formats.
- ManaBox finish mapping now reads text values (`normal`, `foil`, `etched`) and assigns quantity to that finish type.
- Collection import help text now documents ManaBox CSV support and finish mapping behavior.
- Collection batch import SQL execution now chunks large prepared statements to stay under placeholder limits.

### Fixed

- Collection import now reports explicit ManaBox row warnings for unknown finish values, invalid quantities,
  and missing usable identifiers.
- UUID-driven collection import now performs a rudimentary name sanity check (when name is provided) and
  warns/skips on name mismatches while allowing set/collector-number differences.
- Batch import now validates import mode explicitly and treats row-level finish incompatibility as full-row skips
  for consistent actioned card totals.
- Collection import now fails fast when batch SQL execution fails, rather than continuing as a successful import
  summary path.
- Header rendering now tolerates missing `$ctx` in non-bootstrap pages (such as `error.php`) instead of fataling.
- UUID-based collection import now normalizes escaped quote sequences in incoming card names before DB cross-checks
  (for example `\"` in CSV), preventing false mismatch warnings for correctly matched quoted names.

### Security

-

### Infrastructure

- Added regression coverage for `includes/header.php` include paths that do not provide `$ctx`.
- Added collection import coverage for UUID rows with escaped quotes in card names.
- Added regression coverage to confirm batch import exceptions abort flow before orphan cleanup runs.

## [v0.5.8] - 2026-03-10

### Fixed - 2026-03-10

- Reduced index image-refresh lock contention by closing the PHP session in `ajaximagecheck.php` before running
  image diff/fetch work, so concurrent async card image checks no longer serialize on a single session lock.
- Reduced grid render overhead by reusing one `ImageManager` instance per index page render instead of creating
  one instance per card row.

## [v0.5.7] - 2026-02-27

### Added - 2026-02-27

- Added Quenya to 'pretty' language name support

## [v0.5.6] - 2026-02-27

### Changed - 2026-02-27

- Card detail now renders `qya` (Quenya) header names with local Alcarin Tengwar webfonts from `/fonts/alcarin/`.
- `cards_scry` schema definition now includes printed type/text fields for core and face records, and uses
  `MEDIUMTEXT` for core `ability` to align with face ability storage.
- Scryfall bulk import now maps non-empty `printed_type_line` and `printed_text` fields for core cards and
  card faces (`f1_`/`f2_`).
- Card detail now queries printed text fields and supports Oracle/printed toggles where relevant.
- Added local Phyrexian substitution webfonts (`Phi_horizontal_gbrsh_2.woff/woff2`) and wired `ph` header
  rendering to the `font-horizontal-phyrexian` class.
- Card detail printed abilities text now forces language-specific fonts for `qya` and `ph` across core/f1/f2
  ability sections.
- Planeswalker loyalty icon replacement now handles Phyrexian printed-text loyalty prefixes (roman numeral
  forms such as `+Ⅰ`, `-Ⅱ`, `-Ⅹ`) and `-X` variable costs.
- Arena-only cards now show links section for searching printings.

### Fixed - 2026-02-27

- Index/card images now force an async refresh with a cache-busted swap after image load errors.
- Image load error handling now deduplicates in-flight async refreshes per card to avoid duplicate race requests.
- Index/card image rendering now treats unreadable local images as missing so placeholders render instead of 404s.
- Card detail manual image refresh now forces a swap to the refreshed card image instead of remaining on the placeholder.
- Set image reload now correctly counts `ImageManager::refreshImage()` failures after the return contract changed to arrays.
- Prevented duplicate-key fatal errors in `scryfalljson` during card detail/API refresh races by using an upsert for Scryfall JSON writes.
- Bulk test-mode imports now rebuild `cards_scry_test` from `cards_scry` each run to avoid stale-schema failures
  after table definition changes.
- Card detail now skips the TCGPlayer link label for non-paper cards.

### Infrastructure - 2026-02-27

- Added deterministic ImageManager test coverage for unreadable local image handling (placeholder fallback).
- Added PHPUnit coverage for `ImageManager::refreshImage()` success/failure array contract to prevent caller regressions.
- Service worker static asset precache now includes local Alcarin Tengwar font files.
- Service worker static asset precache now includes local Phyrexian webfont files.
- Added `THIRD_PARTY_LICENSES.md` with attribution/license references for Alcarin Tengwar and Andrew Gioia
  icon fonts (Keyrune and Mana).

## [v0.5.5] - 2026-02-05

### Changed [v0.5.5]

- FX refresh now runs asynchronously and shows an updating state for missing rates.

### Fixed [v0.5.5]

- Long-running FX and price refresh AJAX calls now release the session lock before external requests.
- Deck hero image now resets or advances when the current hero card is removed.

## [v0.5.4] - 2026-02-04

### Added [v0.5.4]

- Added CardUtils::colourIdentity for Mana Icons color indicator output.

### Changed [v0.5.4]

- All pages now render mana and rules symbols using Mana Icons font replacements.
- Colour identity now renders the colorless icon when no colours are present.
- Card detail now shows colour identity in the title row.
- Colour identity icons now use the displayed colour combination for aria labels.
- Card detail no longer shows the color pip logo in the header.
- Fixed missing CardUtils import in the deck detail colour identity fragment.
- Colour identity now maps four-colour combos to Mana Icons class ordering.
- Planeswalker abilities now render loyalty up/down icons for +/− values in card detail.
- Colour identity now exposes a shared meta helper and raw normalizer for deck logic.

### Fixed [v0.5.4]

- Double-sided cards not displaying mana cost on index "List" view

### Infrastructure [v0.5.4]

- Added PHPUnit coverage for Mana Icons font replacement output.
- Added PHPUnit coverage for colourIdentity.

## [v0.5.3] - 2026-02-02

### Infrastructure [v0.5.3]

- CSS assets now include the service worker version query string to ensure cache busting on deploy.

## [v0.5.2] - 2026-02-02

### Added [v0.5.2]

- Decks list edit mode now offers an Export action for selected decks (single or bulk zip).
- Decks list edit mode now supports bulk deck type changes.

### Changed [v0.5.2]

- Deck export filenames now use a `{decknumber}-{deckname}.txt` format.
- Deck import now hides the Import button until a file is selected.
- Decks edit mode can now be dismissed with the Escape key.
- Decks export/delete actions now share a single selection panel.
- Bulk deck type changes now reload the decks list after completion.

### Fixed [v0.5.2]

- Deck imports now abort and remove newly created decks when no cards are added.
- Decks list export/delete buttons no longer clip drop shadows.
- Deck type updates now treat no-change updates as success.
- Decks edit action panel now accommodates the delete button.

## [v0.5.1] - 2026-01-14

### Added [v0.5.1]

- Deck list import button now creates decks from uploaded files, using embedded Deckname headers when present.
- Deck list import now supports clipboard paste input.
- Deck list edit toggle with checkbox selection for deletions.

### Fixed [v0.5.1]

- CSS minifier now preserves custom property names inside calc expressions.
- Deck imports now accept single-slash split card names (e.g. Moxfield) by normalizing to double slashes.
- Deck imports now ignore CSV header rows with UTF-8 BOMs to avoid false warning entries.
- Bootstrap direct log writes now honor configured timezone before emitting timestamps.

### Infrastructure [v0.5.1]

- Updated PHPMailer to v7.0.2

## [v0.5.0] - 2026-01-13

This release establishes a new internal baseline for MTG Collection, introducing a centralized bootstrap and configuration system, tightening security across all request flows, and significantly expanding automated test coverage.

### Added [v0.5.0]

- AppContext and AppConfig bootstrap layer with centralized initialization and error handling.
- Secure bootstrap (bootstrap_secure.php) that attaches authenticated SessionUser context to requests.
- Typed SessionUser accessor for all secure pages.
- Unified AJAX response helpers for consistent JSON/text output.
- Centralized CSRF and referrer validation helpers.
- Optional database override support for CLI and test bootstrap.
- AppContext meta storage (e.g. service worker version, CSS version suffix).
- Extensive new PHPUnit coverage across Auth, Cards, Import/Export, Utilities, and Bootstrap.

### Changed [v0.5.0]

- Application configuration is now accessed exclusively through AppConfig (no direct INI or global config access).
- Header/footer now rely on per-page config variables for title, tier, and copyright; CSS version suffix is provided via AppContext meta.
- Database connection and settings now derive from AppConfig instead of legacy DB_* constants.
- Game rules now load via a dedicated builder and are accessed through GameRules instead of globals.
- Secure pages now consume user/session state via SessionUser instead of ambient session globals.
- Secure bootstrap now returns SessionUser directly and no longer exports legacy session globals.
- Secure bootstrap now inlines the secure session setup (secpagesetup merged).
- Bulk scripts now read config/db/logging via AppContext locals instead of ambient bootstrap variables.
- Root utility scripts now read config/db/user info via AppContext locals instead of ambient variables.
- Bulk and CLI scripts now bootstrap through bootstrap.php and no longer use legacy includes.
- Scryfall bulk helpers have been moved into MTG\Bulk\ScryfallImport.
- Card and rules helpers (symbolReplace, cardTypes, promoLookup, colour helpers) moved into MTG\Cards\CardUtils.
- Auth helpers (CSRF, password checks, login stamps, forced password changes) moved into MTG\Auth classes.
- Internal include paths now consistently resolve from APP_ROOT.
- AJAX endpoints now bootstrap via $ctx locals instead of ambient globals.
- AJAX endpoints now share a session-user helper to avoid duplicated SessionManager blocks.

### Fixed [v0.5.0]

- Collection async refresh now seeds CSRF tokens before header scripts load.
- Deck detail sideboard notes no longer render whitespace when empty.
- AJAX quantity updates accept numeric strings and reload user context correctly.
- Secure bootstrap now injects SessionUser into AppContext to avoid null session access.
- Admin IP allowlist now treats empty values consistently to prevent unintended admin lockouts.
- Login now supports skipping trusted-device auto-login via a reset flow flag.
- Password reset now consistently renders the reset form, restarts a clean session, and preserves validation messages.
- Collection view floating toggle now renders correctly in search results and updates card styling on add.
- Deck detail data include now receives the collection table name from the session user.
- Template and issues pages now pass the session user email to the menu include.
- Maintenance status is now stored in AppContext meta for header rendering (search icon now displays correctly).
- Deck detail edit panel now keeps rename and type controls in sync after type changes.
- Deck detail now refreshes hero image and auto-refreshes random draw after deck imports.
- Sets admin image reload now shows a non-blocking status toast and resets the cursor once per request.
- Profile currency updates now flash success reliably with JSON responses.
- Maintenance stub now loads jQuery before header scripts to avoid "$ is not defined" errors.
- Admin reject page now bootstraps via bootstrap.php so header/footer dependencies are available.
- Error page now handles missing/invalid ini gracefully instead of fataling.
- Forced password change now halts execution after redirecting to profile.php.
- Weekly exports accept collection.php as a valid referrer and apply any-quantity deck rules.
- Scryfall set/rulings imports correctly initialize Message after namespace imports.
- Random draw now validates URLs, allowlists classes, and validates containers.
- Deck photo upload/delete messages now render as plain text to prevent HTML injection.
- Deck rename now pre-fills existing names and resets input after failures.
- GameRules::fromDefaults() now loads the correct rules file.
- PHPUnit bootstrap no longer resets global error handlers.

### Security [v0.5.0]

- CSRF and referrer validation centralized and applied across admin, profile, collection, index, and card detail flows.
- All remaining AJAX endpoints migrated to shared CSRF/referrer validation.
- CSV exports now validate table names and restrict non-admin users to their own collections.
- Card detail notes are HTML-escaped to prevent stored XSS.
- Deck detail and deck actions now enforce unified ownership checks to prevent IDOR.
- CSV export redirects now normalize same-origin referrers and fall back to profile.php.
- Hero images and deck links are sanitized before being applied to the DOM.
- Deck names are now rendered as text in headers and AJAX responses.

### Infrastructure [v0.5.0]

- Added test coverage for:
  - SessionManager CSRF handling and login stamps
  - PasswordCheck rules
  - PasswordCheck new-user email validation
  - AppConfig normalization and redaction
  - AppContext bootstrap and ini/db overrides
  - DeckManager legality, copy limits, and actions
  - ImportExport CSV parsing and builders
  - PriceManager updates
  - ScryfallImport bulk edge cases
  - Validation, UrlHelper, TextHelper, and filesystem helpers
  - AdminSettings maintenance mode
  - UserStatus login tracking
  - AjaxResponse and ErrorHandler exit paths
- Added PHPUnit coverage to validate bulk scripts load `bulk_ini.php` via local paths.
- Added PHPUnit coverage for the Scryfall bulk test-mode import path.
- Added PHPUnit coverage for ImportExport input parsing, DeckManager import warnings, profile currency updates,
  and ScryfallImport hash-path branch counts.
- Added test coverage reports and manual-check guidance.
- Bulk scripts now use bootstrap for logging and configuration.
- Legacy includes/ini.php entrypoint has been removed.
- Password reset flow now logs key events at DEBUG/NOTICE level for easier tracing.

## [v0.4.10] - 2026-01-01

### Added (v0.4.10)

- Added `ACCESSIBILITY.md` with minimal accessibility actions and review checklist.
- Deck detail decklist now separates Planes/Phenomena and Tokens after the sideboard.
- Added flip card handling to deck detail hero image.

### Changed (v0.4.10)

- Token rows now use the standard add/remove quantity controls for quick updates.
- Commander deck token and land rows now show quantities in the card name.
- Commander deck quantity prefixes now use "x" (e.g. "4 x Island").
- Commander deck totals now include commander/partner quantities after quick updates.
- Deck export lists now break out Planes/Phenomena and Tokens into their own sections.

### Infrastructure (v0.4.10)

-

### Removed (v0.4.10)

- Admin config no longer offers loglevel 4 bulk diagnostic mode; logging now only accepts levels 1-3.

## [v0.4.9] - 2025-12-30

### Added (v0.4.9)

- Manual test stub for bulk import fixtures (`tests/manual_bulk_import_test.php`).

### Changed (v0.4.9)

- Removed bulk diagnostic logging from Scryfall bulk import.
- Added test mode for bulk import to target `cards_scry_test`, which runs two fixture passes
and reports change buckets.
- Hash lookup now re-binds result columns per execution for prepared statement reliability.
- Bulk import summary now labels no-change rows as unchanged instead of other.

### Fixed (v0.4.9)

- Deck detail touch previews now allow taps on preview images to open card detail pages.
- Card detail flip rotate now targets the visible image instead of the hidden hover image.
- Card detail hover image rotation now tracks the main image rotation for flip cards.
- Bulk diagnostic logging now buckets added vs content/price changes with per-bucket limits.

## [v0.4.8] - 2025-12-29

### Infrastructure (v0.4.8)

- Append service worker version query strings to JS asset includes to bust CDN caches on deploy.

## [v0.4.7] - 2025-12-29

### Added (v0.4.7)

- Index grid now shows a card info placeholder when images are missing.

### Changed (v0.4.7)

- Rulings import now aborts if the content_hash column or unique key are missing instead of altering schema.

### Fixed (v0.4.7)

- Sets page now loads the requested page when opened with a `page` query parameter.
- Sets page now hides initial results when loading a non-first page to avoid visible jumps.
- Card detail async image refresh more robust to avoid skipping images.
- Index async image refresh now swaps placeholders through the DOM when a local card image exists even if
unchanged, to ensure all images are updated and loaded, even if loaded async in another tab.

## [v0.4.6] - 2025-12-28

### Changed (v0.4.6)

- Async image refresh now briefly highlights refreshed images via a CSS class.
- Service worker version now loads from `VERSION` in `bootstrap.php` and is shared across pages.
- Service worker update toast now shows old/new cache versions.

### Fixed (v0.4.6)

- Index async image refresh now triggers after IAS appends new results and skips already-seen cards.
- Index async image refresh now refreshes the base card image cache entry on change (front/back).
- Async image refresh now uses a shared helper across index, carddetail, and deckdetail pages.
- Async image refresh now busts cache when updating base image cache entries.
- Async image refresh now forces a cache-busted DOM swap when a changed image is detected.
- Async refresh highlight no longer blocks greyscale collection view styling.
- Collection view greyscale now reapplies after async image swaps.
- Collection view greyscale now rechecks across grid items after async swaps.
- Top button no longer forces visible state on IAS page events without paging.

## [v0.4.5] - 2025-12-28

### Changed (v0.4.5)

- Service worker cache version now derives from the registration query string.

### Fixed (v0.4.5)

- Index async image refresh now only swaps images when the backend detects a change and restores placeholders on load errors.
- Added debug logging for async image refresh change decisions.

## [v0.4.4] - 2025-12-28

### Added (v0.4.4)

- Index.php infinite scroll now supports loading previous pages when scrolling up (IAS v3.1.0).

### Changed (v0.4.4)

- Top button now hidden when the page parameter is missing or set to 1.

### Fixed (v0.4.4)

- Infinite scroll no longer appends timestamp cache-busters to index page URLs.

### Infrastructure (v0.4.4)

- Sample Apache configs now disable caching for `index.php` to prevent CDN or proxy caching of paged results.
- Documented Cloudflare cache bypass rules for dynamic app routes.

## [v0.4.3] - 2025-12-27

### Added (v0.4.3)

- Added loglevel 4 bulk diagnostic mode to emit verbose Scryfall bulk row diagnostics.

### Fixed (v0.4.3)

- Login flow now preserves requested destinations across failed logins and trust-device prompts.
- Deck detail random draw spacing and hover behavior corrected for single-column layouts and new draws.

## [v0.4.2] - 2025-12-26

### Changed (v0.4.2)

- Service worker now shows an update toast to allow immediate refresh after new deployments.
- Service worker registration now includes a version query to force revalidation on deploy.

### Fixed (v0.4.2)

- Service worker now avoids caching HTML/fragments and uses safer asset/image caching to prevent blank renders.
- Service worker now forces PHP requests to stay network-only for safety.
- Deck detail random draw hover now detaches previews from masonry flow to restore hover behavior.

## [v0.4.1] - 2025-12-26

### Fixed (v0.4.1)

- CSS minifier now preserves media query spacing to prevent rule breakage.

## [v0.4.0] - 2025-12-26

### Changed (v0.4.0)

- Deck detail now uses a masonry-style sidebar layout with responsive stacking for notes, stats, and actions.
- Deck detail moves Random Draw below the masonry group for tall, three-column layouts.
- Deck detail adds 'hero' image section on wider screen displays (1890px+).
- Deck detail uses a scrollable deck list when the masonry is side-by-side.
- Admin panel now regenerates `css/style-min.css` before enabling minified CSS.

### Fixed (v0.4.0)

- Sets pagination now restores the correct page when navigating back in history.

## [v0.3.0] - 2025-12-25

### Changed (v0.3.0)

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

### Fixed (v0.3.0)

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

### Security (v0.3.0)

- Deck detail ajax endpoints now require CSRF tokens; referrer checks removed from deck endpoints.
- Deck detail ajax responses and deck detail pages now disable caching to avoid CSRF token leakage.

### Infrastructure (v0.3.0)

- PHP config now suppresses display_errors and logs to mtgapp.log (container + bare metal).
- Added PHPUnit coverage for the UserAgent builder.
- Added PHPUnit coverage for async card price rendering.
- Added PHPUnit coverage for async price response payloads.
- UserAgent test now writes temp files in the workspace for CI compatibility.
- UserAgent test now stubs the expected values directly.
- DeckManager batch insert now tolerates DB stubs without execute_query for tests.

## [v0.2.2] - 2025-12-22

### Fixed (v0.2.2)

- VERSION increment only.

## [v0.2.1] - 2025-12-22

### Added (v0.2.1)

- Added: PHPUnit check to ensure `src/MTG` class names and namespaces match their file paths for PSR-4 autoloading.

### Fixed (v0.2.1)

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
