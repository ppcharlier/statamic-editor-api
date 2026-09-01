# Editor API — Documentation

Complete reference for the `ppcharlier/statamic-editor-api` addon: a REST **write**
API for Statamic 6, with drafts, revisions, publishing, assets and multi-site.

- [Installation](#installation)
- [Configuration](#configuration)
- [Authentication](#authentication)
- [Conventions](#conventions)
- [Drafts, publishing & conflicts](#drafts-publishing--conflicts)
- [Multi-site](#multi-site)
- [Permissions](#permissions)
- [Endpoint reference](#endpoint-reference)
- [Error codes](#error-codes)
- [Payload fidelity](#payload-fidelity)
- [Testing](#testing)

---

## Installation

```bash
composer require ppcharlier/statamic-editor-api
```

Requirements: **Statamic 6** and **PHP 8.3+**. The addon registers itself through
Laravel package discovery — there is nothing else to wire up.

To customise anything, publish the config file:

```bash
php artisan vendor:publish --tag=editor-api-config
```

### Works with and without Statamic Pro

The API never requires Pro, but it exposes more when Pro is present:

| Capability | Without Pro | With Pro |
| --- | --- | --- |
| Writing entries, terms, globals, navs, assets | ✅ | ✅ |
| Working copies & revisions | ❌ `PATCH` saves directly | ✅ when the collection has `revisions: true` |
| History & restore | ❌ `422 revisions_disabled` | ✅ |
| Multiple users / roles | ❌ single super user | ✅ |
| Multi-site | ❌ single site | ✅ |

Clients should read `GET /config` and adapt their UI per collection
(`revisions_enabled`) rather than assuming a mode.

---

## Configuration

`config/statamic/editor-api.php`:

```php
return [
    'route_prefix' => 'api/editor',

    'auth' => [
        'driver' => 'file',      // 'file' or 'sanctum'
        'token_ttl_days' => 90,  // null = tokens never expire
    ],

    'storage_path' => storage_path('statamic/editor-api/tokens'),

    'rate_limits' => [
        'auth' => 5,          // requests/minute on POST /auth/tokens, per IP
        'api' => 120,         // requests/minute on everything else, per token
        'api_per_ip' => 480,  // per-IP ceiling, on top of the per-token limit
    ],

    // true = every handle, ['a', 'b'] = allow-list, false = disabled
    'resources' => [
        'collections' => true,
        'assets' => true,
        'globals' => true,
        'taxonomies' => true,
        'navigations' => true,
        'forms' => true,
        'users' => false,
    ],
];
```

**`route_prefix`** — every route lives under `{route_prefix}/v1`. With the default
value that is `/api/editor/v1`.

**`resources`** — a disabled or non-allow-listed handle is reported as `404 not_found`,
never as a `403`: a client that may not touch a resource cannot tell it exists.

> ⚠️ Deployments that persist `storage/` on a separate volume (Fly.io, Docker with a
> named volume, …) must persist `storage/statamic/editor-api/tokens` too, or every
> deploy signs all clients out.

---

## Authentication

### Creating a token

```bash
curl -X POST https://example.com/api/editor/v1/auth/tokens \
  -H "Content-Type: application/json" \
  -d '{
        "email": "jane@example.com",
        "password": "secret",
        "device_name": "Jane’s iPhone"
      }'
```

| Field | Rules |
| --- | --- |
| `email` | required, valid email |
| `password` | required |
| `device_name` | required, max 100 characters |

`201 Created`:

```json
{
  "data": {
    "token": "3|kqZ…",
    "expires_at": "2026-11-29T10:12:00+00:00"
  }
}
```

The plain-text token is returned **once**. Bad credentials answer
`401 invalid_credentials`. This endpoint is rate-limited **per IP**
(`rate_limits.auth`, 5/min by default).

### Using a token

Send it as a bearer on every other request:

```
Authorization: Bearer 3|kqZ…
```

Failure modes: `401 unauthenticated` (missing, malformed, revoked or unknown token)
and `401 token_expired` (past its TTL — sign in again).

### Revoking

```bash
curl -X DELETE https://example.com/api/editor/v1/auth/tokens/current \
  -H "Authorization: Bearer 3|kqZ…"
```

`204 No Content`. Only the calling token is revoked.

### Storage drivers

| Driver | Storage | Requires |
| --- | --- | --- |
| `file` (default) | flat files under `storage_path` | nothing — works on a file-only site |
| `sanctum` | the `personal_access_tokens` table | `laravel/sanctum` + Eloquent users |

Tokens are hashed at rest, carry a `last_used_at` (throttled to one write per minute)
and are tied to a real Statamic user — so a user's roles and permissions apply
unchanged. There is no separate permission system to keep in sync.

---

## Conventions

### Envelopes

Success — single resource:

```json
{ "data": { … } }
```

Success — collection, with pagination:

```json
{
  "data": [ … ],
  "meta": { "total": 42, "current_page": 1, "per_page": 25, "last_page": 2 }
}
```

Error:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The given data was invalid.",
    "errors": { "slug": ["The slug field is required."] }
  }
}
```

`errors` is present only when there is per-field detail. Every route under the
prefix answers with this envelope, including 404s on unknown paths and 500s
(whose internal message is never leaked).

### Pagination

`?page=` and `?per_page=` (1–100, default 25) on every list endpoint.

### Sorting

`?sort=field` ascending, `?sort=-field` descending. The sortable set is
**allow-listed per resource** — an unknown field is a `422`, never a silent
fallback. Defaults: entries `-date` (dated collections) or `title`, terms `slug`,
form submissions `-id`.

### `data` vs top-level parameters

Blueprint field values live under `data`. Two handles are the exception —
`slug` and `date` are exposed as **top-level parameters** because they are
structural rather than content. They are flagged `"meta": true` in the blueprint
payload so a form renderer can skip them, and sending them inside `data` is a
`422 unknown_field`.

`published` is also refused inside `PATCH`: publishing has its own endpoints.

### Unknown fields

Any key in `data` that is not in the blueprint is rejected with
`422 unknown_field` — writes never silently drop content.

### Dates

Entry `date` is written as `Y-m-d` (strict) and read back as ISO 8601.
Timestamps (`last_modified`, revision `date`, submission `date`) are ISO 8601.

### Ids are strings

`id` is always a JSON string, including for Eloquent users and revisions
(whose id is the revision timestamp).

---

## Drafts, publishing & conflicts

The write model mirrors the Control Panel:

1. **`PATCH /entries/{id}` never publishes.** With revisions enabled on the
   collection it saves a *working copy*; without revisions it saves the entry
   directly (the published state is untouched either way).
2. **Publishing is explicit**: `POST /entries/{id}/published`, with an optional
   `message` (max 500 chars) recorded in the history. `DELETE` on the same URL
   unpublishes.
3. **`has_unpublished_changes`** on every entry payload tells a client whether a
   working copy is waiting.

### Conflict detection

Read an entry, keep its `last_modified`, and send it back on the next write:

```
X-Base-Modified: 2026-09-01T08:15:00+00:00
```

If the entry changed in the meantime the API answers `409 conflict` instead of
overwriting. The header is honoured on `PATCH /entries/{id}` **and**
`POST /entries/{id}/published` (publishing overwrites the live entry from the
working copy, so a stale client deserves the same protection).

Omitting the header means "overwrite, I know what I'm doing" — the check is
opt-in per request, so a deliberate force-write needs no extra endpoint.

### Revisions

`GET /entries/{id}/revisions` returns the history newest-first;
`POST /entries/{id}/revisions/{revision}/restore` restores one. Restoring
follows CP semantics: on a **published** entry the revision becomes the working
copy (so nothing goes live until you publish); on a **draft** it replaces the
draft in place.

Both answer `422 revisions_disabled` when the collection has no revisions.

---

## Multi-site

Requires Statamic Pro with multi-site enabled.

- `GET /config` lists the sites the token may use (`handle`, `name`, `url`,
  `locale`, `default`).
- Pass `?site=` on entry, term, global and navigation routes. It is a **query**
  parameter only — never a body value (the one exception is documented below).
- Omitting `?site=` means the resource's own default site: the global default when
  the resource is available there, otherwise its first site.
- An unknown handle, or one outside the resource's scope, is `422 validation_failed`
  with the detail under `errors.site`.
- Routes addressed by id (`/entries/{id}`, `/assets/{container}/{path}`) need no
  `?site=`: the id already identifies one localization.
- Assets are not localized.

### Localizations

Every entry payload carries a `localizations` map:

```json
"localizations": [
  { "site": "en", "id": "abc-123" },
  { "site": "fr", "id": "def-456" }
]
```

`POST /entries/{id}/localizations` with `{"site": "fr"}` creates a linked
localization (`409 conflict` if one already exists, with the existing id under
`errors.site`). This is the single endpoint that takes the site in the **body**.

> A fresh localization holds only its own overrides, so its `data` comes back
> empty even though `title`/`status` are inherited from the origin. Echoing that
> empty map straight into a `PATCH` will fail validation on inherited required
> fields — send real values.

---

## Permissions

Tokens carry no permissions of their own: every request is authorized against the
Statamic user's native roles.

| Resource | Permission string |
| --- | --- |
| Entries | `{view\|edit\|create\|delete\|publish} {collection} entries` |
| Terms | `{view\|edit\|create\|delete} {taxonomy} terms` |
| Assets | `{view\|upload\|edit\|move\|rename\|delete} {container} assets` |
| Globals | `edit {handle} globals` |
| Navigations | `{view\|edit} {nav} nav` |
| Form submissions | `{view\|delete} {form} form submissions` |

A missing permission is `403 forbidden`, with the exact permission named in the
message. Super users bypass the checks; `GET /me` reports `"permissions": ["*"]`
for them.

Index endpoints (`/globals`, `/navigations`, `/taxonomies`, `/forms`) list only
what the user may see, so a client can build its navigation from them directly.

---

## Endpoint reference

Base URL: `{route_prefix}/v1`, i.e. `/api/editor/v1` by default.

### Auth

| Method | Path | Notes |
| --- | --- | --- |
| `POST` | `/auth/tokens` | public, rate-limited per IP |
| `DELETE` | `/auth/tokens/current` | `204` |
| `GET` | `/me` | current user |

`GET /me`:

```json
{
  "data": {
    "id": "1",
    "name": "Jane Doe",
    "email": "jane@example.com",
    "avatar": null,
    "super": true,
    "permissions": ["*"]
  }
}
```

### Discovery

**`GET /config`** — everything a client needs to build its UI in one call:

```json
{
  "data": {
    "sites": [
      { "handle": "default", "name": "My Site", "url": "/", "locale": "en_US", "default": true }
    ],
    "collections": [
      {
        "handle": "articles",
        "title": "Articles",
        "revisions_enabled": true,
        "dated": true,
        "structured": false,
        "blueprints": ["article"],
        "sites": ["default"]
      }
    ],
    "asset_containers": [{ "handle": "assets", "title": "Assets" }],
    "taxonomies": [{ "handle": "topics", "title": "Topics", "blueprints": ["topic"], "sites": ["default"] }],
    "globals": [{ "handle": "footer", "title": "Footer", "blueprint": "footer", "sites": ["default"] }],
    "navigations": [{ "handle": "main", "title": "Main", "max_depth": 3, "expects_root": true, "sites": ["default"] }],
    "forms": [{ "handle": "contact", "title": "Contact", "store": true }]
  }
}
```

Disabled resource areas come back as empty arrays.

**`GET /collections/{collection}/blueprints`** and
**`GET /collections/{collection}/blueprints/{blueprint}`** return the *compact*
blueprint — enough to render a form, without the CP's internal payload:

```json
{
  "handle": "article",
  "title": "Article",
  "tabs": [
    {
      "handle": "main",
      "display": "Main",
      "fields": [
        {
          "handle": "title",
          "type": "text",
          "display": "Title",
          "instructions": null,
          "required": true,
          "rules": ["required", "max:200"],
          "meta": false,
          "config": { "placeholder": "…" }
        }
      ]
    }
  ]
}
```

`rules` is always an array (a pipe-syntax `validate` string is exploded for you).
`meta: true` marks a handle exposed as a top-level parameter — see
[`data` vs top-level parameters](#data-vs-top-level-parameters).

### Entries

| Method | Path |
| --- | --- |
| `GET` | `/collections/{collection}/entries` |
| `POST` | `/collections/{collection}/entries` |
| `GET` | `/entries/{id}` |
| `PATCH` | `/entries/{id}` |
| `DELETE` | `/entries/{id}` |

**List** — `?status=any\|published\|draft\|scheduled\|expired` (default `any`),
`?search=` (title, max 200 chars), `?sort=`, `?per_page=`, `?site=`.

Summary shape:

```json
{
  "id": "abc-123",
  "collection": "articles",
  "slug": "low-tide",
  "title": "Low tide at the Aber Wrac'h",
  "status": "published",
  "published": true,
  "date": "2026-08-30T00:00:00+00:00",
  "has_unpublished_changes": false,
  "last_modified": "2026-08-31T18:04:12+00:00"
}
```

**Detail** adds `blueprint`, `data`, `site` and `localizations`.

**Create** (`POST`):

| Field | Rules |
| --- | --- |
| `slug` | required, valid slug, must not produce a URI already taken (`422 uri_taken`) |
| `date` | `Y-m-d`; `422` on a non-dated collection; defaults to today when dated |
| `published` | boolean, default `false` — requires the `publish` permission |
| `message` | max 500, recorded in history when revisions are on |
| `data` | required object of blueprint values |

Returns `201` with the detail payload.

**Update** (`PATCH`): `slug` and `date` optional, `data` required (it is a full
replacement of the values you send, not a deep merge). Honours `X-Base-Modified`.
Sending `published` is a `422`.

**Delete**: `204`, working copy included.

### Publishing & revisions

| Method | Path | Body |
| --- | --- | --- |
| `POST` | `/entries/{id}/published` | `message` (optional, max 500) |
| `DELETE` | `/entries/{id}/published` | `message` (optional) |
| `GET` | `/entries/{id}/revisions` | — |
| `POST` | `/entries/{id}/revisions/{revision}/restore` | — |

Publishing with nothing to publish is `422 nothing_to_publish`; unpublishing a
draft is `422 nothing_to_unpublish`.

Revision shape:

```json
{
  "id": "1756656252",
  "action": "publish",
  "date": "2026-08-31T18:04:12+00:00",
  "message": "Fixed the tide times",
  "user": { "id": "1", "name": "Jane Doe", "email": "jane@example.com" }
}
```

`user` is `null` for a revision written without one. `id` is the revision
timestamp — pass it back verbatim to `/restore`.

### Localizations

`POST /entries/{id}/localizations` — body `{"site": "fr"}` → `201` with the new
localization's detail. See [Multi-site](#multi-site).

### Assets

| Method | Path |
| --- | --- |
| `GET` | `/assets/{container}` |
| `POST` | `/assets/{container}` |
| `GET` | `/assets/{container}/{path}` |
| `PATCH` | `/assets/{container}/{path}` |
| `DELETE` | `/assets/{container}/{path}` |

`{path}` may contain slashes (`photos/2026/beach.jpg`).

**List** — `?folder=` (default the root) and `?per_page=`. Folders and assets are
paginated **together**, so a client can render one scrolling list:

```json
{
  "data": {
    "assets": [ … ],
    "folders": ["photos", "documents"]
  },
  "meta": {
    "total": 42, "current_page": 1, "per_page": 25, "last_page": 2,
    "folders_total": 2, "folders_last_page": 1
  }
}
```

**Upload** — `multipart/form-data` with `file` (validated against the container's
own rules) and an optional `folder`. Filenames and paths are sanitised with
Statamic's own uploader. Returns `201`.

Asset shape:

```json
{
  "id": "assets::photos/beach.jpg",
  "path": "photos/beach.jpg",
  "url": "/assets/photos/beach.jpg",
  "filename": "beach",
  "basename": "beach.jpg",
  "extension": "jpg",
  "folder": "photos",
  "size": 184320,
  "mime_type": "image/jpeg",
  "is_image": true,
  "last_modified": "2026-08-31T18:04:12+00:00",
  "data": { "alt": "The beach at low tide" }
}
```

> `url` may be **relative**. Resolve it against the site URL from `GET /config`
> before loading it.

**Update** — send at least one of `filename`, `folder`, `data`. Each maps to its
own permission (`rename`, `move`, `edit`) and all permissions are checked *before*
any write, so a partially-authorized request changes nothing. Path traversal is
rejected.

Bard image nodes reference assets as `asset::{container}::{path}` — the same
convention the Control Panel uses, so renames follow.

**Delete** — `204`.

### Globals

| Method | Path |
| --- | --- |
| `GET` | `/globals` |
| `GET` | `/globals/{handle}` |
| `PATCH` | `/globals/{handle}` |

The detail payload embeds the compact blueprint, so a client can render the form
without a second call:

```json
{
  "data": {
    "handle": "footer",
    "title": "Footer",
    "site": "default",
    "blueprint": { "handle": "footer", "title": "Footer", "tabs": [ … ] },
    "values": { "copyright": "© 2026" }
  }
}
```

`PATCH` takes `{"data": { … }}`.

### Taxonomies & terms

| Method | Path |
| --- | --- |
| `GET` | `/taxonomies` |
| `GET` | `/taxonomies/{taxonomy}/terms` |
| `POST` | `/taxonomies/{taxonomy}/terms` |
| `PATCH` | `/taxonomies/{taxonomy}/terms/{slug}` |
| `DELETE` | `/taxonomies/{taxonomy}/terms/{slug}` |

`GET /taxonomies` returns each taxonomy with `blueprint` (the first of the set,
kept for compatibility) **and** `blueprints` (the full set) — both compact.

> This payload has no `sites` key; only `GET /config` carries site scoping.
> A strict decoder must not require it here.

Term shape:

```json
{
  "id": "topics::travel",
  "taxonomy": "topics",
  "blueprint": "topic",
  "slug": "travel",
  "title": "Travel",
  "published": true,
  "data": { "description": "…" }
}
```

`POST` takes `slug` (required, unique in the taxonomy for that site),
`blueprint` (optional, must belong to the taxonomy's set), `published`
(defaults to the taxonomy's default state) and `data`.
`PATCH` accepts the same fields — renaming the slug is supported and cleans up the
old record.

`DELETE` removes the term **and all of its localizations** (CP parity), but still
validates `?site=` so a caller who thinks they are deleting one site's version
gets a `422` rather than a silent `204`.

### Navigations

| Method | Path |
| --- | --- |
| `GET` | `/navigations` |
| `GET` | `/navigations/{handle}/tree` |
| `PATCH` | `/navigations/{handle}/tree` |

```json
{
  "data": {
    "handle": "main",
    "title": "Main",
    "max_depth": 3,
    "expects_root": true,
    "tree": [
      { "id": "…", "entry": "abc-123", "entry_title": "Home" },
      { "id": "…", "title": "Docs", "url": "https://example.com/docs",
        "children": [ … ] }
    ]
  }
}
```

`entry_title` is read-only, resolved for display. A node needs either an `entry`
reference or a `title`/`url` pair; ids are generated when missing, so a client can
send a freshly built branch. `PATCH` takes `{"tree": [ … ]}` and round-trips what
`GET` returned. `max_depth` is enforced server-side (the CP checks it in JS only),
and a rejected tree never reaches the shared cache.

### Forms

| Method | Path |
| --- | --- |
| `GET` | `/forms` |
| `GET` | `/forms/{form}/submissions` |
| `DELETE` | `/forms/{form}/submissions/{id}` |

Submissions sort on `id` only (submission ids *are* creation timestamps), default
`-id`. Shape: `{ "id": "…", "date": "…", "data": { … } }`. Delete answers `204`.

---

## Error codes

| Code | Status | When |
| --- | --- | --- |
| `invalid_credentials` | 401 | wrong email/password on `POST /auth/tokens` |
| `unauthenticated` | 401 | missing, malformed, revoked or unknown bearer token |
| `token_expired` | 401 | token past its TTL — sign in again |
| `forbidden` | 403 | the user lacks the named Statamic permission |
| `not_found` | 404 | unknown resource, **or** one disabled in `resources` |
| `revision_not_found` | 404 | unknown revision id |
| `conflict` | 409 | stale `X-Base-Modified`, or localization already exists |
| `validation_failed` | 422 | invalid payload, unknown `?site=`, non-sortable `?sort=` |
| `unknown_field` | 422 | a `data` key outside the blueprint, or a top-level param sent inside `data` |
| `uri_taken` | 422 | the slug produces a URI another entry already owns |
| `nothing_to_publish` | 422 | no unpublished changes |
| `nothing_to_unpublish` | 422 | the entry is already a draft |
| `revisions_disabled` | 422 | revisions are off for this collection |
| `rate_limited` | 429 | per-IP or per-token limit reached |
| `http_error` | 4xx | any other client error |
| `server_error` | 500 | unexpected failure — internals are never leaked |

---

## Payload fidelity

Laravel's global `TrimStrings` and `ConvertEmptyStringsToNull` middleware are
**skipped** for Editor API requests. They walk nested arrays, so on a Bard /
ProseMirror document they silently trim the spaces that carry meaning at mark
boundaries and turn empty strings into `null`.

The Control Panel escapes this by sending Bard as a serialized string; an API
client sends real JSON, so the middleware had to be skipped instead. The result:
ProseMirror documents round-trip **verbatim** — unknown node types, custom
attributes, empty containers and whitespace included. What a client sends is
byte-for-byte what the site stores.

No client-side normalisation is needed, and none should be added.

---

## Testing

```bash
vendor/bin/pest
```

The suite covers both modes (with and without revisions), both user repositories
(flat file and Eloquent), both token drivers (`file` and `sanctum`), and
single- and multi-site setups.

> Never run two Pest processes at once against this package: they share the
> Testbench skeleton directory and contaminate each other.

---

## License

[MIT](LICENSE.md)
