# Changelog

All notable changes to **Editor API** are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [2.2.0] — 2026-09-03

### Added

- **Two optional permissions to hide other authors' entries** — `editor-api list other
  authors {collection} entries` and its child `editor-api view other authors of
  {collection} entries`, both per collection, in the role editor's "Editor API" group.
  They go deliberately BELOW Control Panel parity: the CP lists every entry with its
  author to anyone who may view the collection, and only writing takes an "other authors"
  permission. A mobile editor often needs stricter, so this is now a site's choice.

  Without the first, a listing is narrowed in the query (`meta.total` counts what the user
  sees) and any entry-by-id route — read, revisions, localizations, publishing — answers
  `404 not_found` rather than confirming the entry exists. Without the second,
  `author.name` comes back `null` while `author.id` stays, so a client can still tell
  "somebody else's" from "no author".

  Super users, collections whose blueprint has no `author` field, and roles holding
  `edit other authors {collection} entries` are never restricted — whoever may edit
  another author's entry already sees it in the CP.

- **`enforce_author_visibility` config key**, default `false`. Off, nothing changes and
  the permissions lie dormant; the key is read with a fallback, so an installation whose
  published config file predates it behaves exactly as before after an update. The
  permissions are registered either way, so a `roles.yaml` never carries a handle that
  only exists when a config file says so.

## [2.1.1] — 2026-09-02

### Fixed

- **An entry created without `author` was born ownerless.** The CP fills a `users` field
  declared `default: current` with the signed-in user when the form opens; the API applied
  no blueprint default, and `EntryPolicy` treats an entry with no author as someone
  else's — so a writer creating from the app was immediately refused on their own entry.
  `POST /collections/{collection}/entries` now defaults `author` to the current user
  whenever the blueprint has that field and the payload omits it. An explicit value is
  kept as sent; blueprints without an `author` field are untouched.

## [2.1.0] — 2026-09-02

### Added

- **`access editor-api` permission.** The counterpart of Statamic's `access cp`: a
  non-super user must hold it (granted per role in the CP, under the new "Editor API"
  group) before a token is issued, and on every request afterwards — revoking it cuts off
  tokens already in the wild. Super users never need it. Refusals are `403 forbidden`,
  after the password check so they reveal nothing about unknown accounts.

- **`can` blocks on every payload.** The same policies that would refuse a write are
  asked ahead of time for the current user, so a client greys out what it may not do
  instead of discovering it through a 403:

  | Payload | Keys |
  | --- | --- |
  | entry (list and detail) | `edit`, `delete`, `publish` |
  | term | `edit`, `delete` |
  | asset | `edit`, `move`, `rename`, `delete` |
  | global set | `edit` |
  | `/config` collection | `create`, `publish` |
  | `/config` taxonomy | `create` |
  | `/config` asset container | `upload` |

- **`author` on entries** (list and detail): the first user of the blueprint's `author`
  field as `{ "id", "name" }` — the display name only, never the email — or `null` when
  the blueprint has no such field.

### Upgrade

- Grant `access editor-api` to every role that uses the app. On a free-edition site
  nothing changes: the single super user bypasses it.

## [2.0.4] — 2026-09-02

### Fixed

- **Every authorization now goes through Statamic's own policies**, finishing what
  2.0.3 started for entries. Until now the API compared bare permission strings, which
  diverged from the Control Panel in both directions:

  - *More permissive* on a multi-site install: nothing checked `access {site} site`.
    A user confined to one site could read, list, create or localize entries, edit
    globals, read navigation trees and create terms in the other sites. Every site
    resolved from `?site=` (or the localization payload) is now checked against
    `SitePolicy`, as the CP's site switcher does, and each per-site policy re-checks it.
    Single-site installs are unaffected.
  - *Stricter* than the CP: `configure collections`, `configure globals`,
    `configure navs`, `configure taxonomies`, `configure forms` and
    `configure asset containers` open their whole area in the CP (the policies'
    `before()` hook) but were 403 here. Likewise `edit … entries` implies `view` in the
    CP but not here. Both now match.

  Each endpoint asks exactly what its CP counterpart asks: `view`/`create`/`publish` on
  the collection or entry, `edit` on the origin entry when localizing, `edit` on the
  global set's localization, `view`/`edit` on the navigation tree, `view` on the taxonomy
  and `create`/`update`/`delete` on the term, `view`/`store` on the asset container and
  `view`/`edit`/`move`/`rename`/`delete` on the asset, `view` on the form and `delete` on
  the submission. Index endpoints filter with the same `view` policies.

### Changed

- The `403 forbidden` message reads `Not authorized to {ability} this resource.` (or
  `Not authorized to access site [{handle}].`) instead of naming a permission string.
- On a single resource, an unknown path or localization is a `404` before the `403`,
  as in the CP: the policy needs the resource to decide.
- The route middleware became `editor-api.can:{ability},{routeParam}`; the internal
  `PermissionMap` class is gone. Neither was part of the HTTP contract.

## [2.0.3] — 2026-09-02

### Fixed

- **The API could grant more than the Control Panel on another author's entry.**
  `PATCH /entries/{id}`, `DELETE /entries/{id}`, publishing, unpublishing and
  restoring a revision only checked the bare `edit|delete|publish {collection} entries`
  permission. Statamic's own `EntryPolicy` additionally requires
  `edit|delete|publish other authors {collection} entries` when the entry's `author`
  field names someone else — so a writer refused in the CP on a colleague's article
  was accepted by the API.

  Those per-entry actions now go through Statamic's policy (`$user->can('update', $entry)`
  and friends), which yields exactly the CP's verdict, site access included. The
  collection-level checks (listing, creating) are unchanged. Sites whose blueprints carry
  no `author` field see no behavioural difference. Error messages on these routes now read
  `Not authorized to update this resource.` instead of naming a permission string.

## [2.0.2] — 2026-09-02

### Fixed

- **A relationship field with `max_items: 1` could not be written back.** Statamic
  stores such a field as a scalar (`serie: ma-serie`) but validates it with `array`,
  so a client echoing `GET` data into `PATCH` — the iOS app changing only the slug —
  was rejected on a field it never touched, with "must be an array" and "must not have
  more than 1 items". Had validation passed, `process()` would have read `$value[0]`
  off that string and stored its first letter.

  Writes now run relationship fields through their own `preProcess()`, as assets
  fields already did in 1.2.x. The array shape a Control-Panel-style client sends keeps
  working, `max_items` is still enforced, and a `terms` field on a single taxonomy
  accepts both `"php"` and `"topics::php"`.

- **The same reconciliation now reaches nested fields**, inside a `group`, a `grid`
  row, a `replicator` set or a Bard set — a relationship or assets field down there
  failed identically, under its full path. Assets errors now name that full path
  (`blocs.0.hero`). Bard's verbatim round-trip is untouched: only relationship and
  assets values inside sets are normalised.

## [2.0.1] — 2026-09-01

### Fixed

- **`documentation.md` still said the addon was MIT licensed**, contradicting
  `LICENSE.md`, `composer.json` and the README. Its License section now states the
  commercial terms, and the installation section says a licence is required per
  production site — local, staging and CI environments are not.

Documentation only: no code, endpoint or payload changed.

## [2.0.0] — 2026-09-01

### Changed

- **Editor API is now a commercial addon**, sold on the
  [Statamic Marketplace](https://statamic.com/marketplace) at $50 per site.
  The licence changes from MIT to a source-available commercial licence: the code
  stays public and readable, you may audit it and adapt it to the sites your
  licence covers, but redistribution and unlicensed production use are not
  permitted. Local, staging and CI environments need no licence, so evaluating
  the addon remains free.

  **The API surface is unchanged** — no endpoint, payload or header differs from
  1.2.2. This is a major version because the terms of use changed, not the code.

  Versions up to and including 1.2.2 were published under MIT. That grant is
  irrevocable for those versions; their tags have been removed so they are no
  longer distributed under those terms. See [LICENSE.md](LICENSE.md).

## [1.2.2] — 2026-09-01

### Fixed

- **`GET /entries/{id}` mixed two versions of the same entry.** With a working copy
  present, `data` came from the draft but the top-level `slug`, `date` and `title`
  came from the live entry — so a client was editing a draft body against live
  metadata, and had no way to recover the draft values it was never sent. In an
  editor this showed up as a date silently reverting after a successful save.
  All editable fields now come from the working copy, matching `data` and matching
  what publishing applies. `status`, `published` and `has_unpublished_changes`
  stay live: the draft is precisely what is *not* published.

## [1.2.1] — 2026-09-01

### Fixed

- **`assets` fields could not be saved back.** Statamic stores an assets field as bare
  paths — a scalar rather than an array when `max_files` is 1 — but its fieldtype
  consumes the shape the Control Panel submits: an array of `container::path` ids.
  This API returns stored data verbatim, so a client echoing `GET` data back into
  `PATCH` was rejected with *"must be an array"*, and one sending an array of bare
  paths crashed `Asset::findOrFail()` with a `500`. In practice: **any entry with a
  cover image failed to save.** Writes now normalize assets fields through the
  fieldtype's own `preProcess()`, so the stored shape round-trips. Clients already
  sending `container::path` ids are unaffected — that shape passes through unchanged.
- An asset path that does not exist in its container is now a `422` naming the field,
  instead of being silently dropped (the Control Panel's behaviour) or surfacing as a
  `500`.

## [1.2.0] — 2026-09-01

First public release. The API surface is complete and stable for clients;
1.0 and 1.1 were developed before publication and are summarised below so the
history reads honestly.

### Added

- **Multi-site support.** `GET /config` lists the sites a token may use; `?site=`
  scopes entries, terms, globals and navigations; entries expose a
  `localizations` map, and `POST /entries/{id}/localizations` creates a linked
  localization. Requires Statamic Pro with multi-site enabled.
- **Multi-blueprint taxonomy terms.** Terms can be created and updated against
  any blueprint in the taxonomy's set; `GET /taxonomies` lists the whole set.
- `GET /assets/{container}/{path}` — resolve a single asset, so clients can
  render `asset::` references.

### Fixed

- **Payload fidelity.** Laravel's global `TrimStrings` and
  `ConvertEmptyStringsToNull` middleware are now skipped for Editor API
  requests. They walked nested Bard/ProseMirror documents and mangled the
  whitespace and empty strings that carry meaning at mark boundaries;
  documents now round-trip verbatim.
- Integer ids from Eloquent users and revisions are cast to strings, matching
  the documented contract.
- Navigation `max_depth` is enforced server-side (the Control Panel checks it in
  JavaScript only), and a rejected tree no longer leaves the shared cache
  carrying the invalid value.

## [1.1.0] — 2026-08-31

### Added

- **Sanctum token driver** (`auth.driver = 'sanctum'`) storing tokens in
  `personal_access_tokens`, alongside the default flat-file driver.
- `X-Base-Modified` conflict detection on `POST /entries/{id}/published`, not
  just on `PATCH`.
- Allow-listed `?sort=` on every list endpoint — an unknown field is a `422`
  rather than a silent fallback.
- `uri_taken` validation: a slug that would produce a URI another entry already
  owns is rejected.

### Changed

- Token hardening: throttled `last_used_at` writes, a per-IP rate ceiling on top
  of the per-token limit, an honest `token_ttl_days = 0`, and verified writes.

## [1.0.0] — 2026-08-30

### Added

- Token authentication against real Statamic users, with native roles and
  permissions enforced on every request.
- Entries: list, read, create, update, delete — with working copies, explicit
  publishing, revision history and restore.
- Assets: list, upload, read, update, delete, with per-action permissions.
- Taxonomy terms, globals, navigation trees and form submissions.
- Compact blueprints, so clients can render forms dynamically.
- A single error envelope for every failure, with stable machine-readable codes.
