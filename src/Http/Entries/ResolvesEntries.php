<?php

namespace Ppcharlier\StatamicEditorApi\Http\Entries;

use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;
use Statamic\Facades\Entry;

trait ResolvesEntries
{
    private function findEntry(string $id)
    {
        $entry = Entry::find($id);

        if (! $entry) {
            throw new ApiException('not_found', 'Entry not found.', 404);
        }

        ResourceGate::collection($entry->collectionHandle());

        return $entry;
    }
}
