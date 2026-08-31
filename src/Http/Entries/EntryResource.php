<?php

namespace Ppcharlier\StatamicEditorApi\Http\Entries;

use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Ppcharlier\StatamicEditorApi\Support\MetaFields;

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
            'localizations' => $entry->root()->descendants()
                ->push($entry->root())
                ->map(fn ($loc) => ['site' => $loc->locale(), 'id' => $loc->id()])
                ->unique('site')->values()->all(),
        ]);
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
