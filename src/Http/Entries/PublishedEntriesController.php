<?php

namespace Ppcharlier\StatamicEditorApi\Http\Entries;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Permissions\PermissionMap;
use Statamic\Facades\Entry;

final class PublishedEntriesController
{
    use ResolvesEntries;

    public function store(Request $request, string $id)
    {
        $entry = $this->guarded($request, $id);

        // Publishing overwrites the live entry from the working copy — a stale
        // client deserves the same 409 protection as PATCH.
        $this->guardAgainstConflict($request, $entry);

        if ($entry->published() && (! $entry->revisionsEnabled() || ! $entry->hasWorkingCopy())) {
            throw new ApiException('nothing_to_publish', 'There are no unpublished changes to publish.', 422);
        }

        $result = $entry->publish($this->options($request));

        if ($result === false) {
            throw new ApiException('server_error', 'The entry could not be published.', 500);
        }

        return response()->json(['data' => EntryResource::detail(Entry::find($id))]);
    }

    public function destroy(Request $request, string $id)
    {
        $entry = $this->guarded($request, $id);

        if (! $entry->published()) {
            throw new ApiException('nothing_to_unpublish', 'The entry is not published.', 422);
        }

        $result = $entry->unpublish($this->options($request));

        if ($result === false) {
            throw new ApiException('server_error', 'The entry could not be unpublished.', 500);
        }

        return response()->json(['data' => EntryResource::detail(Entry::find($id))]);
    }

    private function guarded(Request $request, string $id)
    {
        $entry = $this->findEntry($request, $id);
        Guard::check($request->user(), PermissionMap::entries('publish', $entry->collectionHandle()));

        return $entry;
    }

    private function options(Request $request): array
    {
        $message = $request->validate(['message' => ['nullable', 'string', 'max:500']])['message'] ?? null;

        return ['message' => $message, 'user' => $request->user()];
    }
}
