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
}
