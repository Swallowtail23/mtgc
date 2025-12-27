# Changelog

All notable changes to this project will be documented in this file.

## [v0.3.1-dev] - Unreleased

### Added
-

### Changed
- Deck detail now uses a masonry-style sidebar layout with responsive stacking for notes, stats, and actions.
- Deck detail quick add and import blocks stay grouped in the masonry layout.
- Deck detail adds a wide-screen masonry column placeholder for layout tuning.
- Deck detail hover images use the wide-screen placeholder column at 2160px+ instead of floating overlays.
- Deck detail preloads the first deck card image and fades the wide-screen hero column on hover.
- Deck detail moves Random Draw below the masonry group for tall, three-column layouts.
- Deck detail uses a scrollable deck list when the masonry is side-by-side and tightens footer spacing.
- Deck detail deck list max width reduced to 380px.
- Deck detail now reserves scrollbar gutter space for the deck list to prevent layout shifts when scrollbars appear.
- Deck detail shows Random Draw as an overlapping card strip when docked in the sidebar footer.
- Deck detail Random Draw docked strip now uses an icon refresh button and large hover previews.
- Deck detail Random Draw strip now requires hover/touch to navigate and supports touch-first previews.
- Deck detail hero preview now links to the hovered card detail page.
- Deck detail masonry sidebar now keeps 1890px-2159px columns tight against the hero column.
- Deck detail hero placeholder now remains visible at 2160px+.
- Deck detail hero preview now auto-loads from the first card image on touch layouts.
- Deck detail hero auto-load now applies to the 1890px+ layout as soon as the hero column appears.
- Deck detail temporarily shows a viewport width banner for mobile diagnostics.
- Deck detail width banner now updates on DOMContentLoaded and window load for mobile.
- Deck detail width banner now updates inline for mobile diagnostics.
- Deck detail width banner now displays only for dev tier.
- Deck detail width banner now updates on window resize.
- Deck detail narrows masonry column width to 300px at 980px and below for layout testing.
<<<<<<< ours
<<<<<<< ours
- Deck detail dev banner now shows grid column template diagnostics.
- Deck detail width banner now keeps grid diagnostics when JS refreshes it.
=======
>>>>>>> theirs
=======
>>>>>>> theirs
- Deck detail Random Draw strip spacing and rotated hover alignment tuned for docked display.
- Deck detail Random Draw header margin now matches masonry headings when inline.
- Deck detail Random Draw docked hover now rotates around the right edge and uses a larger refresh icon.
- Deck detail Random Draw docked hover now delays and lifts further for better readability.
- Deck detail Random Draw rotated hover now uses a single transform to avoid hover oscillation.
- Deck detail Random Draw hover lift increased for docked previews.
- Deck detail Random Draw docked hover hitbox now extends to prevent hover flicker.
- Deck detail Random Draw docked strip padding reduced to remove excess spacing below the card row.
- Deck detail Random Draw rotated hover now returns smoothly without a transform-origin jump.
- Deck detail touch hover previews now clear on any non-row interaction and on scroll.
- Deck detail Random Draw touch previews now clear on any outside interaction and scroll.
- Deck detail Random Draw touch preview now de-zooms before dropping its z-index.
- Deck detail Random Draw touch preview now clears on touchend interactions outside the strip.
- Deck detail Random Draw touch preview now de-zooms the previous card before activating a new one.
- Deck detail Random Draw touch preview now clears on pointerdown interactions outside the strip.
- Deck detail Random Draw touch mode now disables sticky hover styling.
- Deck detail Random Draw mouse hover now keeps z-index until de-zoom completes.
- Deck detail Random Draw touch mode no longer resets on synthetic mousemove events.
- Deck detail Random Draw now defaults to touch mode on non-hover devices to prevent sticky hover on load.
- Deck detail Random Draw hover-out now preserves the standard delay in mouse mode.
- Admin panel now regenerates `css/style-min.css` before enabling minified CSS.
- Deck detail hero now auto-loads the first card when resizing into the wide layout.
- Deck detail hero image now rotates split/planar-style cards after a short hover delay.
- Deck detail hero image rotation now triggers only on hover, and the Add cards help icon is aligned with its header.
- Deck detail hero hover now sits above other panels and unrotates after a short delay.
- Deck detail Random Draw now fades/raises in new draws for smoother refresh.
- Deck detail Random Draw refresh animation now eases in more gently.
- Deck detail hero hover z-index now only elevates while rotated and Random Draw animation timing is stabilized.

### Fixed
- Admin CSS minify no longer fails when logging file paths.
- Deck detail width banner no longer appears outside dev tier.

### Security
-

### Infrastructure
-

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
