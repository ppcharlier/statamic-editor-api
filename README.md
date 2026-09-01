# Editor API

> The write API that puts a pocket editor on your Statamic site.

**Editor API** adds a clean, complete REST *write* API to your Statamic 6 site —
built as the backend of the **Statamic Editor** app for iPhone, and open to any
client you want to build. No hosted service, no sync, no middleman: your site is
the only backend.

[![tests](https://github.com/ppcharlier/statamic-editor-api/actions/workflows/tests.yml/badge.svg)](https://github.com/ppcharlier/statamic-editor-api/actions/workflows/tests.yml)
[![Statamic 6](https://img.shields.io/badge/Statamic-6-FF269E?style=flat-square)](https://statamic.com)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3+-777BB4?style=flat-square)](https://php.net)
[![License: commercial](https://img.shields.io/badge/license-commercial-d4ff4c?style=flat-square)](LICENSE.md)

---

## Why

Statamic's Control Panel is excellent — on a laptop. Everything else (a phone, a
tablet, a script, a custom editor) needs an API that can *write*, and that means
more than `POST` and hope: drafts that don't go live, revisions you can restore,
permissions that already match your team, and payloads that come back exactly as
you sent them.

That's what this addon is.

## Features

- **Control Panel parity, the safe way.** Everything goes through Statamic's
  public facades — the same path the CP uses. No private controllers, no fragile
  workarounds.
- **Drafts by default, publishing on purpose.** `PATCH` saves a working copy;
  publishing is an explicit action with its own endpoint and message.
- **Revisions & working copies.** Full history and semantic restore, when
  revisions are enabled on the collection.
- **Your permissions, untouched.** Tokens are tied to real Statamic users and
  their native roles and permissions. Nothing to duplicate, nothing to drift.
- **Byte-faithful Bard.** ProseMirror documents round-trip verbatim — unknown
  node types, custom attributes and whitespace included. What your editor sends
  is exactly what your site stores.
- **Conflict detection.** `X-Base-Modified` guards every write — two editors
  can't silently overwrite each other (`409` on a stale base).
- **The whole surface.** Entries, assets (upload included), taxonomy terms,
  globals, navigations and form submissions — plus blueprints, so clients can
  render forms dynamically.
- **Multi-site ready.** Localized entries, linked localizations, per-site globals
  and terms.

## Requirements

- Statamic 6
- PHP 8.3+

Works **with and without Statamic Pro**: without Pro the API degrades gracefully
(direct saves instead of working copies, a single user). Revisions, multiple
users and multi-site require Pro. Clients read `GET /config` and adapt their UI
per collection.

## Installation

```bash
composer require ppcharlier/statamic-editor-api
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=editor-api-config
```

That's it — the routes are live under `/api/editor/v1`.

## Quick start

**1. Sign in** with a Statamic user's credentials to get a token:

```bash
curl -X POST https://example.com/api/editor/v1/auth/tokens \
  -H "Content-Type: application/json" \
  -d '{"email": "jane@example.com", "password": "secret", "device_name": "iPhone"}'
```

```json
{ "data": { "token": "3|kqZ…", "expires_at": "2026-11-29T10:12:00+00:00" } }
```

**2. Discover the site** — one call gives a client everything it needs to build
its UI (sites, collections, blueprints, containers, revision flags):

```bash
curl https://example.com/api/editor/v1/config \
  -H "Authorization: Bearer 3|kqZ…"
```

**3. Save a draft**, then publish it on purpose:

```bash
curl -X PATCH https://example.com/api/editor/v1/entries/abc-123 \
  -H "Authorization: Bearer 3|kqZ…" \
  -H "Content-Type: application/json" \
  -H "X-Base-Modified: 2026-09-01T08:15:00+00:00" \
  -d '{"data": {"title": "Low tide at the Aber Wrac’h", "body": [ … ]}}'

curl -X POST https://example.com/api/editor/v1/entries/abc-123/published \
  -H "Authorization: Bearer 3|kqZ…" \
  -d '{"message": "Fixed the tide times"}'
```

The `X-Base-Modified` header is the `last_modified` you read. If someone else
changed the entry in between, you get a `409` instead of overwriting their work.

## The API at a glance

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

Every resource area can be disabled, or restricted to an allow-list of handles,
in the config — a disabled resource answers `404`, so a client can't even tell it
exists.

📖 **[Full documentation](documentation.md)** — authentication and token drivers,
every endpoint with its payloads, error codes, multi-site, permissions.

## Security

Tokens are hashed at rest, revocable, expire after 90 days by default, and are
rate-limited per token *and* per IP. Two storage drivers ship with the addon:

- `file` (default) — tokens on disk, no database required.
- `sanctum` — tokens in the `personal_access_tokens` table (requires
  `laravel/sanctum` and Eloquent users).

Found a vulnerability? Please report it privately rather than opening a public
issue.

## Testing

```bash
vendor/bin/pest
```

## License

[Commercial](LICENSE.md) — $50 per site on the [Statamic Marketplace](https://statamic.com/marketplace). Source-available: read it, audit it, adapt it to your sites.
