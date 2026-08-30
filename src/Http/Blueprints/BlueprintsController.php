<?php

namespace Ppcharlier\StatamicEditorApi\Http\Blueprints;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;
use Ppcharlier\StatamicEditorApi\Support\SiteGuard;

final class BlueprintsController
{
    public function index(Request $request, $collection)
    {
        SiteGuard::check($request);
        ResourceGate::collection($collection->handle());

        return response()->json(['data' => $collection->entryBlueprints()
            ->map(fn ($b) => CompactBlueprintSerializer::serialize($b))->values()->all()]);
    }

    public function show(Request $request, $collection, string $blueprint)
    {
        SiteGuard::check($request);
        ResourceGate::collection($collection->handle());

        $found = $collection->entryBlueprints()->first(fn ($b) => $b->handle() === $blueprint);

        if (! $found) {
            throw new ApiException('not_found', 'Blueprint not found.', 404);
        }

        return response()->json(['data' => CompactBlueprintSerializer::serialize($found)]);
    }
}
