# Manual Checklist Coverage Notes

This note maps the manual checklist to existing or feasible PHPUnit coverage and highlights what remains manual-only.
It is intentionally high level (not a 1:1 line mapping) to keep it maintainable.

## Already covered by PHPUnit (examples)
- Login/auth: bad login handling (UserStatus), login stamping (LoginHandlerStampTest), login flow branching (LoginHandlerTest),
  password reset rules (PasswordResetTest), password complexity (PasswordCheckValidationTest), session/csrf validation
  (SessionManagerTest), 2FA logic paths (TwoFactorManagerTest), trusted device token logic (TrustedDeviceManagerTest).
- Admin settings: maintenance mode behavior and admin bypass (AdminSettingsTest), CSS min mode (CssVersionCheckTest).
- Collection/deck rules: copy limits and deck actions (DeckManager* tests), ownership assertions (DeckOwnerAssertTest).
- CSV/export: CSV build behavior (ImportExportTest), collection stats/history (CollectionStatsTest/CollectionHistoryTest).
- Core utilities: Validation, UrlHelper, GameRules, AppConfig/AppContext, Filesystem, TextHelper, UserAgent.
- Scryfall/bulk: import edge cases and hashing (ScryfallImportTest, RulingsHasherTest).

## Added with this pass
- UserStatus now has explicit tests for incrementing bad logins, zeroing bad logins, and locking accounts
  (supports manual checks for bad login increments/resets and account lock behavior).

## Feasible PHPUnit additions (not covered yet)
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
