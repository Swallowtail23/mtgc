# Scryfall Tags

Scryfall Oracle tags and art tags are imported from the Scryfall bulk-data API.
The regular data workflow runs them through:

```bash
php bulk/scryfall_bulk.php tags
```

Focused imports are also available:

```bash
php bulk/scryfall_bulk.php oracle-tags
php bulk/scryfall_bulk.php art-tags
```

## Tables

Fresh installs include these tables in `setup/mtg_new.sql`:

- `scryfall_tag_definitions`: tag metadata from Tagger, including label, slug, URI,
  description, parent IDs, child IDs, aliases, and a metadata content hash.
- `scryfall_tag_assignments`: tag-to-subject links. Oracle tags use `oracle_id` as
  `subject_id`; art tags use `illustration_id` as `subject_id`.

Existing databases must have both tables before `scryfall_bulk.php tags` is run.
The importer deliberately does not create schema at runtime.

## Search Notes

The current import preserves the tag data for later search work. Oracle tags can
be joined through `cards_scry.oracle_id`. Art tags can be joined through
`cards_scry.illustration_id`, `cards_scry.f1_illustration_id`, or
`cards_scry.f2_illustration_id`.
