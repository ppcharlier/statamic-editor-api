<?php

namespace Ppcharlier\StatamicEditorApi\Http\Terms;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Blueprints\CompactBlueprintSerializer;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Support\ResourceConfig;
use Statamic\Facades\Taxonomy;

final class TaxonomiesController
{
    public function index(Request $request)
    {
        $user = $request->user();

        $taxonomies = Taxonomy::all()
            ->filter(fn ($taxonomy) => ResourceConfig::enabled('taxonomies', $taxonomy->handle()))
            ->filter(fn ($taxonomy) => Guard::allows($user, 'view', $taxonomy))
            ->map(fn ($taxonomy) => [
                'handle' => $taxonomy->handle(),
                'title' => $taxonomy->title(),
                // 'blueprint' (premier du set) reste pour compat ; 'blueprints' expose le set complet.
                'blueprint' => CompactBlueprintSerializer::serialize($taxonomy->termBlueprints()->first()),
                'blueprints' => $taxonomy->termBlueprints()
                    ->map(fn ($bp) => CompactBlueprintSerializer::serialize($bp))->values()->all(),
            ])
            ->values()->all();

        return response()->json(['data' => $taxonomies]);
    }
}
