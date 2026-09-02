<?php

namespace Ppcharlier\StatamicEditorApi\Http\Entries;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Support\SiteResolver;

final class LocalizationsController
{
    use ResolvesEntries;

    public function store(Request $request, string $id)
    {
        $entry = $this->findEntry($request, $id);
        // The CP's LocalizeEntryController authorizes `edit` on the origin entry.
        Guard::authorize($request->user(), 'edit', $entry);

        $site = $request->validate(['site' => ['required', 'string']])['site'];

        // Same checks as SiteResolver::resolve(), but on a BODY value — this is the one
        // endpoint where the site is payload rather than a query param — hence
        // resolveValue() with its own message: from the client's side the two failure
        // modes (unknown handle / out of the collection's scope) are one and the same here.
        // The user must also be allowed into the target site: in the CP, localizing lands
        // you on that site's edit form, which SitePolicy would refuse.
        SiteResolver::resolveValue($site, $entry->collection()->sites()->all(),
            "Unknown site [{$site}] or collection not available in it.", $request->user());

        if ($entry->existsIn($site)) {
            throw new ApiException('conflict', 'A localization already exists for this site.', 409,
                ['site' => [$entry->in($site)->id()]]);
        }

        $localization = $entry->makeLocalization($site);
        $localization->save();

        return response()->json(['data' => EntryResource::detail($localization)], 201);
    }
}
