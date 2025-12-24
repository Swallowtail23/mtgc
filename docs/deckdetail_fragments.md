# Deck Detail Fragments

This page uses server-rendered fragments to keep derived sections in sync after AJAX deck updates.
The source of truth is still PHP; the client asks for updated fragments and swaps them into the DOM.

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

`js/deckdetail.js` calls `ajax/ajaxdeckfragments.php` to request updated fragments. The default
fragment list is set in `window.mtgDeckDetailConfig.fragments` inside `deckdetail.php`.

## Flow map

1) `deckdetail.php` renders the initial page, loads `includes/deckdetail_data.php`,
   injects `window.mtgDeckDetailConfig`, and includes the fragments for first paint.
2) User actions in `js/deckdetail.js` call `ajax/ajaxdeckcard.php` to mutate the deck.
3) On success, `js/deckdetail.js` calls `refreshDeckFragments()` to refresh dependent sections.
4) `ajax/ajaxdeckfragments.php` validates the session/referrer, loads `includes/deckdetail_data.php`
   and `includes/fragments/deckdetail_mana_data.php`, then calls the fragment renderer.
5) `ajax/ajaxdeckfragments_lib.php` renders the requested fragment includes in `includes/fragments/`
   and returns the HTML payload.
6) `js/deckdetail.js` swaps fragment wrappers in the DOM and rebinds events/refreshes images.

## Server rendering

Fragment HTML is rendered via:
- `ajax/ajaxdeckfragments.php` (fragment endpoint)
- Fragment includes in `includes/fragments/`
- Shared data/calculation logic in `includes/deckdetail_data.php`

## Adding new derived sections

1) Create a fragment include with a wrapper element and ID.
2) Add it to the fragment map in `ajax/ajaxdeckfragments_lib.php`.
3) Add the ID replacement in `js/deckdetail.js`.
4) Add it to the `window.mtgDeckDetailConfig.fragments` list.
