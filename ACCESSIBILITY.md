/*
Version:     1.0
Date:        30/12/25
Name:        ACCESSIBILITY.md
Purpose:     Minimal accessibility actions for core pages without layout/CSS changes.
Notes:       Focus on semantics, labels, and keyboard access; avoid blanket ARIA.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

# Accessibility (Minimal Actions)

This guidance targets a reasonable baseline without changing layout or CSS and without
blanket ARIA labeling.

## Principles
- Prefer native semantics (button, link, form label) over ARIA whenever possible.
- Only add ARIA when an element has no accessible name or role.
- Avoid global `aria-live`; use it only for user-triggered, dynamic feedback.
- Ensure every interactive control is reachable by keyboard and has a name.

## Minimal Actions (Site-Wide)
- Icon-only controls: add a clear accessible name (`aria-label`) if no visible text exists.
- Images: add meaningful `alt` text for logos and content images; use empty `alt=""` only
  for purely decorative images.
- Inputs and textareas: ensure they have an accessible name. Prefer `label for="..."`
  or `aria-labelledby` pointing to nearby headings; use `aria-label` as a last resort.
- Clickable `div`/`span`: convert to `<button>` or `<a>` where possible. If changing tags
  is not feasible, add `role="button"` and `tabindex="0"` plus keyboard handling.
- Modal/help boxes: ensure the close control is a real button or has a clear label and
  can be focused.
- Status messages: only add `role="status"`/`role="alert"` for messages triggered by a
  user action and not otherwise announced.

## Page-Specific Minimal Notes
- `deckdetail.php`: name the icon-only actions (delete/edit/duplicate/help/save/close);
  ensure the deck photo `img` has descriptive `alt` text; tie notes/quickadd textareas
  to their headings via `aria-labelledby`.
- `index.php`: icon-only help/flip controls need names; list/grid item containers should
  be keyboard reachable if they act as links; provide meaningful `alt` text for card images.
- `carddetail.php`: ensure flip/rotate controls are keyboard accessible; improve `alt`
  text for card images (use card name instead of UUID/URL).
- `profile.php`: label password and 2FA inputs; give switches a name with `aria-label`
  or `aria-labelledby`; make copy-to-clipboard a button.
- `collection.php`: make dismiss/close actions a button; add brief text alternative for
  the chart (e.g., summary below the canvas).
- `admin/admin.php`: add form labels where missing (date, update notes).

## Quick Review Checklist
- Keyboard: can I reach every interactive control and activate it with Enter/Space?
- Names: does every control have a visible label or a single, clear accessible name?
- Images: is `alt` meaningful and not just a file name or UUID?
- Dynamic updates: only use live regions where needed and only for user-triggered updates.
