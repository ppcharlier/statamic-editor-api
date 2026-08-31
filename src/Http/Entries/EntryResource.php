<?php

namespace Ppcharlier\StatamicEditorApi\Http\Entries;

use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Ppcharlier\StatamicEditorApi\Support\MetaFields;
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
        // (e.g. updated_by, updated_at) out of the response, so GET data is always safe
        // to echo straight back into PATCH data.
        $allowed = $working->blueprint()->fields()->all()->keys()->diff(MetaFields::HANDLES)->all();

        return array_merge(self::summary($entry), [
            'blueprint' => $working->blueprint()->handle(),
            'data' => Arr::only($working->data()->all(), $allowed),
            'site' => $entry->locale(),
            'localizations' => self::localizations($entry),
        ]);
    }

    /**
     * Mono-site short-circuits without ever touching root()/descendants(): those
     * walk the origin/localization graph via vendor Entry::save()'s own recursive
     * directDescendants()->each->save() machinery, which — at least in the
     * revisions/working-copy flow exercised here — can leave the graph in a state
     * where HasOrigin::root()'s unguarded `while ($e->hasOrigin()) $e = $e->origin();`
     * (vendor/statamic/cms/src/Data/HasOrigin.php) never terminates. A mono-site
     * entry has exactly one localization: itself.
     */
    private static function localizations($entry): array
    {
        if (! Site::hasMultiple()) {
            return [['site' => $entry->locale(), 'id' => $entry->id()]];
        }

        $root = self::safeRoot($entry);

        return $root->descendants()
            ->push($root)
            ->map(fn ($loc) => ['site' => $loc->locale(), 'id' => $loc->id()])
            ->unique('site')->values()->all();
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
