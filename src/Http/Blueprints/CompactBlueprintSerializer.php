<?php

namespace Ppcharlier\StatamicEditorApi\Http\Blueprints;

use Illuminate\Support\Arr;

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
            'config' => Arr::except($config, ['display', 'instructions', 'validate', 'type']),
        ];
    }
}
