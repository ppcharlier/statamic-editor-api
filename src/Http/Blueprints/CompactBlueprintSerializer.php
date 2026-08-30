<?php

namespace Ppcharlier\StatamicEditorApi\Http\Blueprints;

use Illuminate\Support\Arr;
use Ppcharlier\StatamicEditorApi\Support\MetaFields;

final class CompactBlueprintSerializer
{
    public static function serialize($blueprint): array
    {
        return [
            'handle' => $blueprint->handle(),
            'title' => $blueprint->title(),
            'tabs' => $blueprint->tabs()->map(fn ($tab) => [
                'handle' => $tab->handle(),
                'display' => $tab->display(),
                'fields' => $tab->fields()->all()->map(fn ($field) => self::field($field))->values()->all(),
            ])->values()->all(),
        ];
    }

    private static function field($field): array
    {
        $config = $field->config();

        return [
            'handle' => $field->handle(),
            'type' => $field->type(),
            'display' => $field->display(),
            'instructions' => $config['instructions'] ?? null,
            'required' => $field->isRequired(),
            'rules' => (array) ($config['validate'] ?? []),
            // True for handles (slug, date) the API exposes as top-level request/response
            // params rather than inside `data` — the client still gets the field's display
            // config here, but should route its value through the top-level param.
            'meta' => in_array($field->handle(), MetaFields::HANDLES, true),
            'config' => Arr::except($config, ['display', 'instructions', 'validate', 'type']),
        ];
    }
}
