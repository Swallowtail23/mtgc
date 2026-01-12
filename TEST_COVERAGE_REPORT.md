# Test Environment and Coverage Plan

## Test Environment
- PHP: 8.2+ (8.4 in Docker), with mysqli/mbstring/intl/gd/curl.
- Database: MySQL 8+.
- Config: `/opt/mtg/mtg_new.ini` must point to a test database and logging location.
- PHPUnit: `vendor/bin/phpunit` (after `composer install`).
- Autoload: Composer PSR-4 for classes under `src/MTG/`.

## Current PHPUnit Coverage Summary
- Status: 186 tests, 367 assertions (latest run).
- Focus: core helpers and high-risk business logic paths.

## Coverage by Class (test references)
- AdminSettings: `AdminSettingsTest.php`, `CssVersionCheckTest.php`
- AjaxResponse: `AjaxResponseTest.php` (fixtures)
- AppConfig: `AppConfigTest.php`, `AppContextTest.php`, `AdminSettingsTest.php`, `MessageTest.php` (plus test bootstraps)
- AppContext: `AppContextTest.php`
- CardUtils: `CardUtilsTest.php`, `SymbolReplaceTest.php`, `ColourTest.php`, `CardNotesEscapeTest.php`
- CollectionHistory: `CollectionHistoryTest.php`
- CollectionStats: `CollectionStatsTest.php`
- DateYMD: `DateYMDTest.php`
- DeckManager: `DeckManagerTest.php`, `DeckManagerHelpersTest.php`, `DeckOwnerAssertTest.php`,
  `DeckManagerActionsTest.php`, `DeckManagerCopyLimitTest.php`
- ErrorHandler: `ErrorHandlerTest.php` (fixtures)
- Filesystem: `FilesystemTest.php`
- GameRules: `GameRulesTest.php`
- ImageManager: `ImageManagerTest.php`
- ImportExport: `InputInterpreterTest.php`, `ImportExportTest.php`, `ImportExportEmailTest.php`
- INI: `IniTest.php`
- IniDebug: `IniDebugTest.php`
- LoginHandler: `LoginHandlerTest.php`, `LoginHandlerStampTest.php`
- Message: `MessageTest.php`
- MyPHPMailer: `MyPHPMailerTest.php`, `ImportExportEmailTest.php`
- PasswordCheck: `PasswordCheckValidationTest.php`, `PasswordResetTest.php`
- PriceDisplay: `PriceDisplayTest.php`, `PriceAjaxResponseTest.php`
- PriceManager: `PriceManagerTest.php`, `PriceManagerUpdateTest.php`
- RemoteFileChecker: covered via `ImageManagerTest.php`
- RulingsHasher: `RulingsHasherTest.php`
- ScryfallImport: `ScryfallImportTest.php`
- SessionManager: `SessionManagerTest.php`
- TextHelper: `TextHelperTest.php`
- TrustedDeviceManager: `TrustedDeviceManagerTest.php`
- TwoFactorManager: `TwoFactorManagerTest.php`
- UrlHelper: `UrlHelperTest.php`
- UserAgent: `UserAgentTest.php`
- UserStatus: `UserStatusTest.php`
- Validation: `ValidationTest.php`

## Manual Checklist Coverage Notes
- Login/auth: bad login handling (UserStatus), login stamping (LoginHandlerStampTest), login flow branching
  (LoginHandlerTest), password reset rules (PasswordResetTest), password complexity
  (PasswordCheckValidationTest), session/CSRF validation (SessionManagerTest), 2FA logic paths
  (TwoFactorManagerTest), trusted device token logic (TrustedDeviceManagerTest).
- Admin settings: maintenance mode behavior and admin bypass (AdminSettingsTest), CSS min mode
  (CssVersionCheckTest).
- Collection/deck rules: copy limits and deck actions (DeckManager* tests), ownership assertions
  (DeckOwnerAssertTest).
- CSV/export: CSV build behavior (ImportExportTest), collection stats/history
  (CollectionStatsTest/CollectionHistoryTest).
- Core utilities: Validation, UrlHelper, GameRules, AppConfig/AppContext, Filesystem, TextHelper, UserAgent.
- Scryfall/bulk: import edge cases and hashing (ScryfallImportTest, RulingsHasherTest).

## Coverage Additions in This Pass
- UserStatus now has explicit tests for incrementing bad logins, zeroing bad logins, and locking accounts
  (supports manual checks for bad login increments/resets and account lock behavior).

## Remaining Gaps / Notes
- `ClassName` is still a template/example class and has no tests.
- Large service classes (e.g., `DeckManager`, `ImportExport`, `PriceManager`, `ScryfallImport`) now have broader coverage,
  but still contain untested branches (error handling and edge-case DB outcomes).
- `ErrorHandler`/`AjaxResponse` are covered via fixture subprocess tests; deeper branch coverage would require further
  harnessing or refactoring.

## Feasible PHPUnit Additions (not covered yet)
- Admin users/cards workflows: input validation for bad email, update notice persistence, and user status changes,
  using DB stubs and isolated helpers.
- Profile preferences: currency changes persisting and updateCollectionValues invocation (with stubs).
- Search criteria building: header/advanced search criteria parsing (unit-level), beyond IndexTest smoke coverage.
- Deck imports: more import path tests on validation and dedupe rules (unit-level).

## Manual-only / E2E-only (keep on checklist)
- UI visuals and layout: fonts, background image, hover colors, full-page render checks.
- Browser/session behavior: incognito, close browser auto-login, logout redirect timing.
- Email delivery and external integrations: SMTP delivery, 2FA email/app QR flows, image reload HTTP fetch.
- Complex UI behavior: pagination widgets, front-end interactions, deck UI graphs/controls, live updates.

## Suggested Next Steps
1. Add targeted tests for remaining untested branches in `DeckManager` and `ImportExport` (error and edge cases).
2. Extend `ScryfallImport` coverage for content/price hash update paths using small JSON fixtures.
3. Decide whether `ClassName` should be removed or given a real implementation with tests.
