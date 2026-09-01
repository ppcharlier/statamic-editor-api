# Changelog

All notable changes to **Editor API** are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

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
