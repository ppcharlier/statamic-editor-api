<?php

namespace Ppcharlier\StatamicEditorApi\Http\Entries;

use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Ppcharlier\StatamicEditorApi\Support\MetaFields;
use Statamic\Facades\Blink;
use Statamic\Facades\Site;

final class EntryResource
{
    public static function summary($entry): array
    {
        return [
            'id' => $entry->id(),
            'collection' => $entry->collectionHandle(),
            'slug' => $entry->slug(),
            'title' => $entry->value('title'),
            'status' => $entry->status(),
            'published' => (bool) $entry->published(),
            'date' => $entry->collection()->dated() ? $entry->date()?->toIso8601String() : null,
            'has_unpublished_changes' => $entry->revisionsEnabled() && $entry->hasWorkingCopy(),
            'last_modified' => self::effectiveLastModified($entry)?->toIso8601String(),
        ];
    }

    public static function detail($entry): array
    {
        $working = $entry->fromWorkingCopy();

        // Only surface blueprint field data, minus the meta handles (slug, date) that
        // are already exposed as top-level params. This keeps internal bookkeeping keys
        // (e.g. updated_by, updated_at) out of the response.
        //
        // `data` holds the entry's OWN values, not its effective ones: a linked
        // localization carries only its own overrides, so a freshly created one comes back
        // with `data: {}` even though its top-level title/status are inherited from the
        // origin (locked by tests/MultiSite/LocalizationsTest.php, ruling in spec §9).
        // Echoing GET data straight back into PATCH data is therefore safe for an ordinary
        // entry, but NOT for a fresh localization — replaying an empty map there fails
        // validation on the inherited required fields.
        $allowed = $working->blueprint()->fields()->all()->keys()->diff(MetaFields::HANDLES)->all();

        return array_merge(self::summary($entry), [
            // Tout ce qui est ÉDITABLE doit venir de la même version que `data` : la working
            // copy est ce que le client modifie, et c'est elle que publish applique. Renvoyer un
            // `data` de brouillon avec un `slug`/`date`/`title` live faisait éditer un mélange de
            // deux versions — et aucun client ne peut rattraper des valeurs qu'on ne lui envoie
            // jamais. `status`, `published` et `has_unpublished_changes` restent LIVE : le
            // brouillon est précisément ce qui n'est pas publié.
            'slug' => $working->slug(),
            'title' => $working->value('title'),
            'date' => $entry->collection()->dated() ? $working->date()?->toIso8601String() : null,
            'blueprint' => $working->blueprint()->handle(),
            'data' => Arr::only($working->data()->all(), $allowed),
            'site' => $entry->locale(),
            'localizations' => self::localizations($entry),
        ]);
    }

    /**
     * The RevisionsTest hang this guards against was a stale Blink cache, NOT a cyclic
     * origin chain (a mono-site entry has no origin at all, so root() could never loop).
     * Bisected mechanism: reading an entry primed Blink under 'entry-descendants-{id}' via
     * vendor Entry::directDescendants() (Entry.php:862, a Blink::once); a later
     * Entry::save() replayed that memo in its descendant cascade
     * (`$this->directDescendants()->each->save()`, Entry.php:452) because save() only
     * invalidates the key for its ANCESTORS (Entry.php:441), never its own — so the entry
     * kept re-saving a stale snapshot of itself.
     *
     * Two guards, both kept:
     *  - mono-site short-circuits entirely (one localization: the entry itself), so the
     *    memo is never primed on the flow that broke;
     *  - the multi-site branch forgets the key it just primed on the root, so nothing this
     *    read-only serializer caches can leak into a later save cascade.
     *
     * safeRoot() stays too: it guards genuine origin-chain walks, which multi-site does
     * perform, against vendor HasOrigin::root()'s unguarded `while ($e->hasOrigin())`.
     */
    private static function localizations($entry): array
    {
        if (! Site::hasMultiple()) {
            return [['site' => $entry->locale(), 'id' => $entry->id()]];
        }

        $root = self::safeRoot($entry);

        $localizations = $root->descendants()
            ->push($root)
            ->map(fn ($loc) => ['site' => $loc->locale(), 'id' => $loc->id()])
            ->unique('site')->values()->all();

        Blink::forget('entry-descendants-'.$root->id());

        return $localizations;
    }

    /**
     * Cycle-safe equivalent of HasOrigin::root() — ascends the origin chain but
     * stops (instead of looping forever) if an id repeats. descendants() itself is
     * safe to call on the result: vendor's Entry::descendants() already tracks a
     * $seen list of visited ids over its breadth-first walk.
     */
    private static function safeRoot($entry)
    {
        $seen = [$entry->id() => true];
        $current = $entry;

        while ($current->hasOrigin()) {
            $next = $current->origin();

            if (isset($seen[$next->id()])) {
                break;
            }

            $seen[$next->id()] = true;
            $current = $next;
        }

        return $current;
    }

    public static function effectiveLastModified($entry): ?CarbonInterface
    {
        $fileModified = $entry->lastModified();
        $workingCopyDate = $entry->revisionsEnabled() ? $entry->workingCopy()?->date() : null;

        if (! $workingCopyDate) {
            return $fileModified;
        }

        if (! $fileModified) {
            return $workingCopyDate;
        }

        return $workingCopyDate->gt($fileModified) ? $workingCopyDate : $fileModified;
    }
}
