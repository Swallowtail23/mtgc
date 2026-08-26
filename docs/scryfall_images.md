# Scryfall Image Handling

This document defines the source selection, local cache naming, download
triggers, and deployment requirements for Scryfall card images.

## Source Selection

Card imports select image URLs in this order for both top-level cards and
separate card faces:

1. `image_uris.grid` (`image/webp`, 488 x 680)
2. `image_uris.normal` (`image/jpeg`, 488 x 680)

The fallback keeps older Scryfall bulk files and records without a `grid` image
usable. The existing `image_uri`, `f1_image_uri`, and `f2_image_uri` database
columns store either format and require no schema migration.

## Local Cache Contract

Scryfall images retain the existing set/card naming scheme:

```text
cardimg/<set>/<card-id>.webp
cardimg/<set>/<card-id>_b.webp
```

Every lookup checks WebP first, followed by the legacy paths:

```text
cardimg/<set>/<card-id>.jpg
cardimg/<set>/<card-id>_b.jpg
```

JPEG fallback is per face, so a two-faced card may temporarily use different
formats for its front and back. Other JPEG assets, including placeholders and
deck photos, are outside this contract and remain JPEG.

## Phase-One Download Policy

Network downloads are limited to deliberate creation or refresh paths:

- A missing image encountered while rendering or asynchronously resolving a UI
  card image.
- A newly inserted row during a Default Cards import.
- The explicit image refresh action on Card Detail.
- An explicit primary-language or all-language set refresh from Sets.

Page and deck rendering initially return a placeholder for an empty cache, then
the asynchronous image resolver downloads the preferred source and replaces the
placeholder. If either a WebP or legacy JPEG cache file already exists, normal
rendering and asynchronous checks use it without downloading or migration. A
successful explicit WebP refresh is written before its superseded JPEG is
removed.

Administrator JPEG uploads on Card Detail remain supported. A successful
upload removes the corresponding Scryfall WebP cache entry so that the uploaded
JPEG remains visible until an administrator explicitly refreshes it again.

## Browser And Server Caching

Card images remain cache-first service-worker resources. Phase one uses a new
`mtg-images-webp1-<version>` cache namespace, which removes the older image
cache during service-worker activation. When a refreshed image changes format,
the shared client helper removes the alternate extension from the active cache.

Apache must map `.webp` to `image/webp`, apply the same expiry policy as JPEG,
and exclude WebP from output compression. The supplied native and container
virtual-host configurations include these rules.

## Deployment

Container deployments must rebuild the web image because `docker/Dockerfile`
adds `libwebp-dev` and configures GD with `--with-webp`. Bare-metal deployments
should confirm WebP support with:

```bash
php -r '$info = gd_info(); var_export($info["WebP Support"] ?? false); echo PHP_EOL;'
```

Deploy the application, Apache/service-worker changes, and rebuilt container
before running any explicit image refresh. Phase one does not include a bulk
conversion command; existing JPEGs should not be deleted manually.

For an existing native host, follow the complete
[bare-metal WebP upgrade checklist](../INSTALL.md#existing-bare-metal-webp-upgrade).
It covers runtime and vhost changes, the one-time non-destructive card URL
re-import, cache rollout, and verification in deployment order.
