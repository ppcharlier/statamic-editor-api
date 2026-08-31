# Editor API

> The write API that puts a pocket editor on your Statamic site.

**Editor API** adds a clean, complete REST *write* API to your Statamic 6 site —
built as the backend of the **Statamic Editor** app for iPhone, and open to any
client you want to build. No hosted service, no sync, no middleman: your site
is the only backend.

## Features

- **Control Panel parity, the safe way.** Everything goes through Statamic's
  public facades — the same path the CP uses. No private controllers, no
  fragile workarounds.
- **Drafts by default, publishing on purpose.** `PATCH` saves a working copy;
  publishing is an explicit action with its own endpoint.
- **Revisions & working copies.** Full history and semantic restore, when
  revisions are enabled on the collection.
- **Your permissions, untouched.** Tokens are tied to real Statamic users and
  their native roles and permissions. Nothing to duplicate, nothing to drift.
- **Byte-faithful Bard.** ProseMirror documents round-trip verbatim — unknown
  node types, custom attributes and whitespace included. What your editor
  sends is exactly what your site stores.
- **Conflict detection.** `X-Base-Modified` guards every write — two editors
  can't silently overwrite each other (`409` on stale base).
- **The whole surface.** Entries, assets (upload included), taxonomy terms,
  globals, navigations and form submissions — plus blueprints, so clients can
  render forms dynamically.
- **Multi-site ready.** Localized entries, linked localizations, per-site
  globals and terms.

## Requirements

- Statamic 6
- PHP 8.3+

Works with and without Statamic Pro: without Pro the API degrades gracefully
(direct saves instead of working copies, single user). Revisions, multiple
users and multi-site require Pro. Clients can read `GET /config` to adapt
their UI per collection.

## Installation

```bash
composer require ppcharlier/statamic-editor-api
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=editor-api-config
```

## Authentication

Create a token by signing in with a Statamic user's credentials:

```bash
curl -X POST https://example.com/api/editor/v1/auth/tokens \
  -H "Content-Type: application/json" \
  -d '{"email": "jane@example.com", "password": "secret"}'
```

Use the returned token as a bearer on every request. Tokens are revocable
(`DELETE /auth/tokens/current`), expire after 90 days by default, and are
rate-limited per token *and* per IP. Two storage drivers are available:

- `file` (default) — tokens stored on disk, no database required.
- `sanctum` — tokens in the `personal_access_tokens` table (requires
  `laravel/sanctum` and Eloquent users).

## Endpoints

All routes live under `/api/editor/v1` (prefix configurable).

| Area | Endpoints |
| --- | --- |
| Auth | `POST /auth/tokens` · `DELETE /auth/tokens/current` · `GET /me` |
| Discovery | `GET /config` · `GET /collections/{collection}/blueprints[/{blueprint}]` |
| Entries | `GET·POST /collections/{collection}/entries` · `GET·PATCH·DELETE /entries/{id}` |
| Publishing | `POST·DELETE /entries/{id}/published` |
| Revisions | `GET /entries/{id}/revisions` · `POST /entries/{id}/revisions/{revision}/restore` |
| Localizations | `POST /entries/{id}/localizations` |
| Assets | `GET·POST /assets/{container}` · `GET·PATCH·DELETE /assets/{container}/{path}` |
| Globals | `GET /globals` · `GET·PATCH /globals/{handle}` |
| Taxonomies | `GET /taxonomies` · `GET·POST /taxonomies/{taxonomy}/terms` · `PATCH·DELETE /taxonomies/{taxonomy}/terms/{slug}` |
| Navigations | `GET /navigations` · `GET·PATCH /navigations/{handle}/tree` |
| Forms | `GET /forms` · `GET /forms/{form}/submissions` · `DELETE /forms/{form}/submissions/{id}` |

Every resource area can be disabled or restricted to an allow-list of handles
in the config (`resources` key). Users endpoints are off by default.

## Drafts, publishing & conflicts

- `PATCH /entries/{id}` saves a **working copy** when revisions are enabled on
  the collection, and saves directly otherwise. It never publishes.
- `POST /entries/{id}/published` publishes (with an optional message);
  `DELETE` unpublishes.
- Writes accept an `X-Base-Modified` header carrying the `last_modified` you
  read; if the entry changed in between, the API answers `409` instead of
  overwriting.

## Multi-site

Pass `?site=` on entry, term, global and navigation routes. `GET /config`
lists the sites the token may use; entries expose a `localizations` map and
`POST /entries/{id}/localizations` creates a linked localization.

## Configuration

`config/statamic/editor-api.php`:

- `route_prefix` — defaults to `api/editor`.
- `auth.driver` — `file` or `sanctum`; `auth.token_ttl_days` (default 90,
  `null` = no expiry).
- `rate_limits` — per-minute limits for auth (per IP) and the API (per token,
  plus a per-IP ceiling).
- `resources` — enable/disable or allow-list collections, assets, globals,
  taxonomies, navigations, forms.

## A note on payload fidelity

Laravel's global `TrimStrings` and `ConvertEmptyStringsToNull` middleware are
skipped for Editor API requests: they would walk nested Bard/ProseMirror
documents and mangle whitespace and empty strings. Editors need byte-for-byte
round-trips; with Editor API, they get them.

## Testing

```bash
vendor/bin/pest
```

## License

[MIT](LICENSE.md)
