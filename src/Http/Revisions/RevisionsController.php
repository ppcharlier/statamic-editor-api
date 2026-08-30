<?php

namespace Ppcharlier\StatamicEditorApi\Http\Revisions;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Entries\EntryResource;
use Ppcharlier\StatamicEditorApi\Http\Entries\ResolvesEntries;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Permissions\PermissionMap;
use Statamic\Facades\Entry;

final class RevisionsController
{
    use ResolvesEntries;

    public function index(Request $request, string $id)
    {
        $entry = $this->revisable($request, $id, 'view');

        return response()->json(['data' => $entry->revisions()
            ->reverse()->values()
            ->map(fn ($r) => RevisionResource::toArray($r))
            ->all()]);
    }

    public function restore(Request $request, string $id, string $revision)
    {
        $entry = $this->revisable($request, $id, 'publish');

        $target = $entry->revision($revision);

        if (! $target) {
            throw new ApiException('revision_not_found', 'Revision not found.', 404);
        }

        if ($entry->published()) {
            $target->toWorkingCopy()->date(now())->user($request->user())->save();
        } else {
            $entry->makeFromRevision($target)->published(false)->updateLastModified($request->user())->save();
        }

        return response()->json(['data' => EntryResource::detail(Entry::find($id))]);
    }

    private function revisable(Request $request, string $id, string $action)
    {
        $entry = $this->findEntry($request, $id);
        Guard::check($request->user(), PermissionMap::entries($action, $entry->collectionHandle()));

        if (! $entry->revisionsEnabled()) {
            throw new ApiException('revisions_disabled', 'Revisions are not enabled for this collection.', 422);
        }

        return $entry;
    }
}
