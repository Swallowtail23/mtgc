# Test Coverage Report

## Summary
- PHPUnit status: 186 tests, 367 assertions (latest run).
- Focus: expanded unit coverage for refactored core helpers and high-risk business logic paths.

## Coverage by Class (test references)
- AdminSettings: `AdminSettingsTest.php`, `CssVersionCheckTest.php`
- AjaxResponse: `AjaxResponseTest.php` (fixtures)
- AppConfig: `AppConfigTest.php`, `AppContextTest.php`, `AdminSettingsTest.php`, `MessageTest.php` (plus test bootstraps)
- AppContext: `AppContextTest.php`
- CardUtils: `CardUtilsTest.php`, `SymbolReplaceTest.php`, `ColourTest.php`, `CardNotesEscapeTest.php`
- CollectionHistory: `CollectionHistoryTest.php`
- CollectionStats: `CollectionStatsTest.php`
- DateYMD: `DateYMDTest.php`
- DeckManager: `DeckManagerTest.php`, `DeckManagerHelpersTest.php`, `DeckOwnerAssertTest.php`, `DeckManagerActionsTest.php`,
  `DeckManagerCopyLimitTest.php`
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

## Remaining Gaps / Notes
- `ClassName` is still a template/example class and has no tests.
- Large service classes (e.g., `DeckManager`, `ImportExport`, `PriceManager`, `ScryfallImport`) now have broader coverage,
  but still contain untested branches (error handling and edge-case DB outcomes).
- `ErrorHandler`/`AjaxResponse` are covered via fixture subprocess tests; deeper branch coverage would require further
  harnessing or refactoring.

## Suggested Next Steps
1. Add targeted tests for remaining untested branches in `DeckManager` and `ImportExport` (error and edge cases).
2. Extend `ScryfallImport` coverage for content/price hash update paths using small JSON fixtures.
3. Evaluate whether `ClassName` should be removed or given a real implementation with tests.
