Manual Bulk Import Test

Purpose
Verify the bulk import change detection using the two fixture files:
- tests/test_data/bulk_sample_10.json
- tests/test_data/bulk_sample_10_copy.json

What It Does
- Ensures `cards_scry_test` exists (creates from `cards_scry` if missing).
- Truncates `cards_scry_test`.
- Imports `bulk_sample_10.json` (baseline).
- Imports `bulk_sample_10_copy.json` (mutated).
- Reports change buckets from the second run.

Run
php bulk/scryfall_bulk.php test
php tests/manual_bulk_import_test.php

Expected Summary
Test summary: total 10, added 1, price only 2, content only 2, both 2

Notes
- This is a manual/local test only.
- The test will wipe all data in `cards_scry_test`.
