<?php

namespace Ppcharlier\StatamicEditorApi\Http\Blueprints;

use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;

final class BlueprintsController
{
    public function index($collection)
    {
        ResourceGate::collection($collection->handle());

        return response()->json(['data' => $collection->entryBlueprints()
            ->map(fn ($b) => CompactBlueprintSerializer::serialize($b))->values()->all()]);
    }

    public function show($collection, string $blueprint)
    {
        ResourceGate::collection($collection->handle());

        $found = $collection->entryBlueprints()->first(fn ($b) => $b->handle() === $blueprint);

        if (! $found) {
            throw new ApiException('not_found', 'Blueprint not found.', 404);
        }

        return response()->json(['data' => CompactBlueprintSerializer::serialize($found)]);
    }
}
