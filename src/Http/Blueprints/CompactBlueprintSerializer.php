<?php

namespace Ppcharlier\StatamicEditorApi\Http\Blueprints;

use Illuminate\Support\Arr;
use Ppcharlier\StatamicEditorApi\Support\MetaFields;
use Statamic\Fieldtypes\HasSelectOptions;

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

    // Laravel semantics: a validate STRING is pipe syntax, an array is taken as-is
    // (which is also where regex rules containing `|` must live).
    private static function rules(string|array $validate): array
    {
        return is_string($validate) ? explode('|', $validate) : $validate;
    }

    private static function field($field): array
    {
        $config = $field->config();

        // Choice fieldtypes (select, radio, checkboxes, button_group…) keep their options as
        // a `{value: label}` map, whose order is the blueprint's — and JSON object key order
        // is exactly what Foundation on iOS does NOT preserve when decoding. Send the ordered
        // `[{value, label}]` list Statamic itself builds for the CP (`preload()`), for the
        // plain `[value…]` form too. Every other config key stays verbatim.
        $fieldtype = $field->fieldtype();
        if (in_array(HasSelectOptions::class, class_uses_recursive($fieldtype), true)) {
            $config['options'] = $fieldtype->preload()['options'] ?? [];
        }

        return [
            'handle' => $field->handle(),
            'type' => $field->type(),
            'display' => $field->display(),
            'instructions' => $config['instructions'] ?? null,
            'required' => $field->isRequired(),
            'rules' => self::rules($config['validate'] ?? []),
            // True for handles (slug, date) the API exposes as top-level request/response
            // params rather than inside `data` — the client still gets the field's display
            // config here, but should route its value through the top-level param.
            'meta' => in_array($field->handle(), MetaFields::HANDLES, true),
            'config' => Arr::except($config, ['display', 'instructions', 'validate', 'type']),
        ];
    }
}
