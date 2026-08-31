<?php

namespace Ppcharlier\StatamicEditorApi\Http\Terms;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Blueprints\CompactBlueprintSerializer;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Permissions\PermissionMap;
use Ppcharlier\StatamicEditorApi\Support\ResourceConfig;
use Statamic\Facades\Taxonomy;

final class TaxonomiesController
{
    public function index(Request $request)
    {
        $user = $request->user();

        $taxonomies = Taxonomy::all()
            ->filter(fn ($taxonomy) => ResourceConfig::enabled('taxonomies', $taxonomy->handle()))
            ->filter(fn ($taxonomy) => Guard::allows($user, PermissionMap::terms('view', $taxonomy->handle())))
            ->map(fn ($taxonomy) => [
                'handle' => $taxonomy->handle(),
                'title' => $taxonomy->title(),
                'blueprint' => CompactBlueprintSerializer::serialize($taxonomy->termBlueprints()->first()),
            ])
            ->values()->all();

        return response()->json(['data' => $taxonomies]);
    }
}
