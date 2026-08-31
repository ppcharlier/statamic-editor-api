<?php

namespace Ppcharlier\StatamicEditorApi\Http\Entries;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Permissions\PermissionMap;
use Statamic\Facades\Site;

final class LocalizationsController
{
    use ResolvesEntries;

    public function store(Request $request, string $id)
    {
        $entry = $this->findEntry($request, $id);
        Guard::check($request->user(), PermissionMap::entries('create', $entry->collectionHandle()));

        $site = $request->validate(['site' => ['required', 'string']])['site'];

        if (! Site::all()->map->handle()->contains($site)
            || ! $entry->collection()->sites()->contains($site)) {
            throw new ApiException('validation_failed', 'The given data was invalid.', 422,
                ['site' => ["Unknown site [{$site}] or collection not available in it."]]);
        }

        if ($entry->existsIn($site)) {
            throw new ApiException('conflict', 'A localization already exists for this site.', 409,
                ['site' => [$entry->in($site)->id()]]);
        }

        $localization = $entry->makeLocalization($site);
        $localization->save();

        return response()->json(['data' => EntryResource::detail($localization)], 201);
    }
}
