# Changelog

All notable changes to **Editor API** are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

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
