<?php

namespace Ppcharlier\StatamicEditorApi\Http\Entries;

use Carbon\CarbonInterface;

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

        return array_merge(self::summary($entry), [
            'blueprint' => $working->blueprint()->handle(),
            'data' => $working->data()->all(),
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
