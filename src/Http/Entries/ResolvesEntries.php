<?php

namespace Ppcharlier\StatamicEditorApi\Http\Entries;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Permissions\AuthorVisibility;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;
use Ppcharlier\StatamicEditorApi\Support\SiteResolver;
use Statamic\Facades\Entry;

trait ResolvesEntries
{
    private function findEntry(Request $request, string $id)
    {
        SiteResolver::resolve($request);

        $entry = Entry::find($id);

        if (! $entry) {
            throw new ApiException('not_found', 'Entry not found.', 404);
        }

        ResourceGate::collection($entry->collectionHandle());

        // Every entry-by-id route funnels through here, so hiding it once covers reads,
        // revisions, localizations and publishing alike. 404 and not 403, with the message
        // of a genuinely missing entry: an entry kept out of your listing has no business
        // being confirmed to exist. Writes on a VISIBLE entry keep answering 403 — that
        // refusal comes from the policies, further down.
        if (! AuthorVisibility::listsOtherAuthors($request->user(), $entry->collection())
            && AuthorVisibility::isAnotherAuthors($request->user(), $entry)) {
            throw new ApiException('not_found', 'Entry not found.', 404);
        }

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
