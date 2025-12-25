# Deck Detail Fragments

This page uses server-rendered fragments to keep derived sections in sync after AJAX deck updates.
The source of truth is still PHP; the client applies fragment HTML returned from mutation requests.

## Fragments and IDs

- Deck list: `#decklist-fragment` (includes main + sideboard tables and hover divs)
- Colour identity: `#deck-colour-identity-fragment`
- Warnings: `#deck-warnings-fragment`
- Mana value chart: `#deck-mana-value-fragment`
- Mana costs/sources: `#deck-mana-costs-fragment`
- Deck value: `#deck-value-fragment`
- Deck lists section (export/missing/buy): `#deck-lists-fragment`
- Export list rows: `#deck-export-fragment`
- Missing list rows: `#deck-missing-fragment`
- Buy missing rows: `#deck-buy-fragment`
- Random draw: `#deck-random-draw-fragment`

## Dependency overview

Any change to the deck list (add, remove, move to sideboard, commander/partner changes) can affect:
- Colour identity and colour legality styling
- Warnings
- Mana value chart
- Mana costs/sources
- Deck value
- Export/missing/buy lists
- The deck list itself
- Random draw pool

Because of these dependencies, all deck mutations should trigger a fragment refresh.

## Client refresh

Mutation requests include a fragment list. The default list is set in
`window.mtgDeckDetailConfig.fragments` inside `deckdetail.php`, sourced from the fragment registry.

## Flow map

1) `deckdetail.php` renders the initial page, loads `includes/deckdetail_data.php`,
   injects `window.mtgDeckDetailConfig`, and includes the fragments for first paint.
2) User actions in `js/deckdetail.js` call `ajax/ajaxdeckcard.php` (or another mutation endpoint).
3) The mutation endpoint validates the session + CSRF, loads `includes/deckdetail_data.php`
   and `includes/fragments/deckdetail_mana_data.php`, then renders fragments.
4) `ajax/ajaxdeckfragments_lib.php` renders the requested fragment includes in `includes/fragments/`
   and returns the HTML payload alongside the mutation response.
5) `js/deckdetail.js` swaps fragment wrappers in the DOM and rebinds events/refreshes images.

## Versioning and request validation

- Deck changes update `decks.deck_updated_at` (TIMESTAMP(6)), returned as `version` in microseconds from
  deck mutation endpoints (and `ajax/ajaxdeckfragments.php` for manual refreshes).
- The client tracks `lastAppliedVersion` and ignores stale fragment responses.
- Deck ajax endpoints require a CSRF token (`csrf_token`) alongside session + ownership checks.

## Server rendering

Fragment HTML is rendered via:
- `ajax/ajaxdeckfragments.php` (fragment endpoint)
- Fragment includes in `includes/fragments/`
- Shared data/calculation logic in `includes/deckdetail_data.php`

## Fragment registry

All fragment metadata lives in `ajax/ajaxdeckfragments_lib.php`:
- Fragment key
- Wrapper ID
- Include file
- Default inclusion

`deckdetail.php` uses the registry to populate `window.mtgDeckDetailConfig.fragments` and
`window.mtgDeckDetailConfig.fragmentTargets`, which the client uses when applying responses.

## Adding new derived sections

1) Create a fragment include with a wrapper element and ID.
2) Add a registry entry in `ajax/ajaxdeckfragments_lib.php`.
3) Confirm the wrapper ID matches the registry entry.
