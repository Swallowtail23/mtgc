# Test Environment and Coverage Plan

## Test Environment

- PHP: 8.2+ (8.4 in Docker), with `mysqli`, `mbstring`, `intl`, `gd`, `curl`.
- Database: MySQL 8+.
- Config: `/opt/mtg/mtg_new.ini` must point to a test database and writable log location.
- PHPUnit: `vendor/bin/phpunit` (after `composer install`).
- Autoload: Composer PSR-4 for classes under `src/MTG/`.
- Bulk opt-in test: `MTG_BULK_TEST_INI` (defaults to `/opt/mtg/mtg_new.ini`), runs only when ini `general.tier=dev`,
  and skips in CI (`CI`/`GITHUB_ACTIONS`).

## Current PHPUnit Coverage Summary

Latest local verification (2026-02-23):

- Full run (`vendor/bin/phpunit --configuration phpunit.xml`):
  - 228 tests, 470 assertions, 1 failure.
  - Failure: `BulkScryfallImportTest::testScryfallBulkScriptTestModeRuns` in this environment due log/db permissions.
- Core deterministic run (excluding the opt-in bulk integration test):
  - 227 tests, 469 assertions, 0 failures.

Focus remains on core helpers, import paths, auth/security flows, and high-risk business logic.

## Coverage by Class/Area (test references)

- Admin settings/bootstrap:
  - `AdminSettingsTest.php`
  - `CssVersionCheckTest.php`
  - `BulkScriptBootstrapTest.php`
- Ajax/http response handling:
  - `AjaxResponseTest.php`
  - `PriceAjaxResponseTest.php`
- App config/context/core:
  - `AppConfigTest.php`
  - `AppContextTest.php`
  - `IniTest.php`
  - `IniDebugTest.php`
  - `MessageTest.php`
  - `FilesystemTest.php`
  - `SrcClassMapTest.php`
- Cards/helpers/presentation:
  - `CardUtilsTest.php`
  - `CardNotesEscapeTest.php`
  - `ColourTest.php`
  - `ColourIdentityTest.php`
  - `SymbolReplaceTest.php`
  - `SymbolReplaceFontTest.php`
  - `PlaneswalkerLoyaltyReplaceTest.php`
- Search/index:
  - `IndexTest.php`
  - `InputInterpreterTest.php`
- Deck/domain logic:
  - `DeckManagerTest.php`
  - `DeckManagerHelpersTest.php`
  - `DeckManagerActionsTest.php`
  - `DeckManagerCopyLimitTest.php`
  - `DeckManagerProcessInputTest.php`
  - `DeckOwnerAssertTest.php`
  - `DeckDetailFragmentsTest.php`
  - `DeckDetailFragmentRenderTest.php`
- Image handling:
  - `ImageManagerTest.php`
- Import/export and collection:
  - `ImportExportTest.php`
  - `ImportExportEmailTest.php`
  - `CollectionStatsTest.php`
  - `CollectionHistoryTest.php`
- Price:
  - `PriceDisplayTest.php`
  - `PriceManagerTest.php`
  - `PriceManagerUpdateTest.php`
- Auth/security/session:
  - `LoginHandlerTest.php`
  - `LoginHandlerStampTest.php`
  - `PasswordCheckValidationTest.php`
  - `PasswordCheckNewUserTest.php`
  - `PasswordResetTest.php`
  - `SessionManagerTest.php`
  - `TrustedDeviceManagerTest.php`
  - `TwoFactorManagerTest.php`
  - `UserStatusTest.php`
- Utilities:
  - `ValidationTest.php`
  - `UrlHelperTest.php`
  - `TextHelperTest.php`
  - `DateYMDTest.php`
  - `UserAgentTest.php`
- Scryfall/import hashing:
  - `ScryfallImportTest.php`
  - `ScryfallImportBranchPathsTest.php`
  - `RulingsHasherTest.php`
  - `BulkScryfallImportTest.php` (opt-in integration test)

## Manual Checklist Coverage Notes

- Login/auth:
  - bad login handling (`UserStatusTest.php`)
  - login stamping and submission flow (`LoginHandlerStampTest.php`, `LoginHandlerTest.php`)
  - password reset/validation (`PasswordResetTest.php`, `PasswordCheckValidationTest.php`, `PasswordCheckNewUserTest.php`)
  - session/CSRF validation (`SessionManagerTest.php`)
  - trusted device and 2FA (`TrustedDeviceManagerTest.php`, `TwoFactorManagerTest.php`)
- Admin/settings:
  - maintenance mode and admin behavior (`AdminSettingsTest.php`)
  - CSS mode fallback (`CssVersionCheckTest.php`)
- Deck/collection rules:
  - deck card limits/actions/ownership and fragment rendering (`DeckManager*`, `DeckOwnerAssertTest.php`,
    `DeckDetailFragmentsTest.php`, `DeckDetailFragmentRenderTest.php`)
  - collection stats/history (`CollectionStatsTest.php`, `CollectionHistoryTest.php`)
- Import/scryfall:
  - import parsing and branching (`InputInterpreterTest.php`, `ImportExportTest.php`, `ScryfallImport*`)
  - hashing and bulk bootstrap paths (`RulingsHasherTest.php`, `BulkScriptBootstrapTest.php`)

## Coverage Additions Reflected

- Explicit `ImageManager::refreshImage()` contract coverage (success/failure array response paths).
- Deterministic unreadable-image fallback coverage in `ImageManagerTest.php`.
- Scryfall branch-path coverage in `ScryfallImportBranchPathsTest.php`.
- Deck fragment render/structure coverage in `DeckDetailFragmentRenderTest.php` and `DeckDetailFragmentsTest.php`.
- Added font-symbol and loyalty-replace coverage (`SymbolReplaceFontTest.php`, `PlaneswalkerLoyaltyReplaceTest.php`).
- Added new-user password validation coverage (`PasswordCheckNewUserTest.php`).

## Remaining Gaps / Notes

- `src/MTG/Core/ClassName.php` remains a template/example class with no dedicated tests.
- Large service classes (`DeckManager`, `ImportExport`, `PriceManager`, `ScryfallImport`) still have untested
  failure/edge branches.
- `ErrorHandler`/`AjaxResponse` use fixture subprocess tests; deeper branch coverage would likely require refactoring.
- JS behavior (including `js/deckdetail.js` sanitizers and async image race handling) is still not under an automated JS
  harness yet.
- `BulkScryfallImportTest.php` is environment-sensitive by design (test ini tier/dev + local permissions/db access).

## Feasible PHPUnit Additions

- Admin users/cards workflows:
  - bad email validation
  - update notice persistence
  - user status mutation edge cases
- Import/export edge behavior:
  - dedupe/invalid row paths not yet covered
- Deck mutation error branches:
  - more DB failure and rollback scenarios
- Search criteria building:
  - header/advanced query mapping helpers beyond `IndexTest.php` smoke coverage

## Manual-only / E2E-only (Keep on Checklist)

- UI visuals/layout and responsive behavior.
- Browser/session runtime behaviors (incognito reopen/logout timing).
- External integration behavior (SMTP delivery, 2FA app/email flow, live image fetch timing).
- Complex front-end interactions (pagination widget behavior, deck graph interactions, live async updates).

## Suggested Next Steps

1. Add targeted failure-path tests in `DeckManager` and `ImportExport`.
2. Add missing branch tests in `PriceManager` and `ScryfallImport` error paths.
3. Decide whether `ClassName` should be removed or replaced with real implementation/tests.
4. Introduce a JS test harness (Jest/jsdom) to cover `js/deckdetail.js` sanitizers and async image race behavior.
