<?php

namespace Ppcharlier\StatamicEditorApi\Http\Entries;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;
use Ppcharlier\StatamicEditorApi\Support\SiteGuard;
use Statamic\Facades\Entry;

trait ResolvesEntries
{
    private function findEntry(Request $request, string $id)
    {
        SiteGuard::check($request);

        $entry = Entry::find($id);

        if (! $entry) {
            throw new ApiException('not_found', 'Entry not found.', 404);
        }

        ResourceGate::collection($entry->collectionHandle());

        return $entry;
    }

    private function guardAgainstConflict(Request $request, $entry): void
    {
        if (! $header = $request->header('X-Base-Modified')) {
            return;
        }

        try {
            $base = \Carbon\CarbonImmutable::parse($header);
        } catch (\Throwable) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'X-Base-Modified' => ['Must be a valid ISO-8601 datetime.'],
            ]);
        }

        $current = EntryResource::effectiveLastModified($entry);

        if ($current && $current->startOfSecond()->gt($base->startOfSecond())) {
            throw new ApiException(
                'conflict',
                'The entry was modified since your last read. Reload it or overwrite by resending without the header.',
                409,
            );
        }
    }
}
