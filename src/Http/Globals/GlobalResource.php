<?php

namespace Ppcharlier\StatamicEditorApi\Http\Globals;

use Illuminate\Support\Arr;
use Ppcharlier\StatamicEditorApi\Http\Blueprints\CompactBlueprintSerializer;

final class GlobalResource
{
    public static function toArray($variables): array
    {
        $blueprint = $variables->blueprint();
        $handles = $blueprint->fields()->all()->keys()->all();

        return [
            'handle' => $variables->globalSet()->handle(),
            'title' => $variables->globalSet()->title(),
            'blueprint' => CompactBlueprintSerializer::serialize($blueprint),
            'values' => Arr::only($variables->data()->all(), $handles),
        ];
    }
}
