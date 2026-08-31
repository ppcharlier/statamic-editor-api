<?php

namespace Ppcharlier\StatamicEditorApi\Http\Terms;

use Illuminate\Support\Arr;

final class TermResource
{
    public static function toArray($term): array
    {
        $handles = $term->blueprint()->fields()->all()->keys()->all();

        return [
            'id' => $term->id(),
            'taxonomy' => $term->taxonomy()->handle(),
            'blueprint' => $term->blueprint()->handle(),
            'slug' => $term->slug(),
            'title' => $term->title(),
            'published' => (bool) $term->published(),
            'data' => Arr::only($term->data()->all(), array_diff($handles, ['slug'])),
        ];
    }
}
