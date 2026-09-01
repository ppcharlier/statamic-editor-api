<?php

namespace Ppcharlier\StatamicEditorApi\Support;

use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;

/**
 * Reconciles the two shapes Statamic's `assets` fieldtype uses.
 *
 * It STORES bare paths — and a scalar rather than an array when `max_files` is 1 — but its
 * process() consumes the shape the Control Panel's JavaScript submits: an array of
 * `container::path` ids (Statamic\Fieldtypes\Assets\Assets::preProcess/process). This API
 * exposes stored data verbatim, so a client echoing back what GET returned would hit
 * "must be an array" (scalar) or an uncaught Asset::findOrFail() (array of bare paths).
 *
 * Running the fieldtype's own preProcess() on assets fields — and only those, so the verbatim
 * round-trip of Bard and everything else is untouched — makes the stored shape acceptable.
 * It is idempotent for clients that already send ids: valueToId() returns anything containing
 * '::' unchanged.
 */
final class AssetsFieldShape
{
    /** @param  \Statamic\Fields\Blueprint  $blueprint */
    public static function normalize(array $data, $blueprint): array
    {
        foreach ($blueprint->fields()->all() as $handle => $field) {
            if ($field->type() !== 'assets' || ! array_key_exists($handle, $data)) {
                continue;
            }

            $submitted = $data[$handle];
            $ids = $field->fieldtype()->preProcess($submitted);

            // preProcess() SILENTLY drops what it cannot resolve. The Control Panel can afford
            // that — its picker only ever submits assets it just listed — but an API cannot: a
            // mistyped path would erase the field without a word. Count the survivors instead.
            $expected = $submitted === null ? 0 : count((array) $submitted);

            if (count($ids) !== $expected) {
                throw new ApiException('validation_failed', 'The given data was invalid.', 422, [
                    $handle => ['One or more of these assets do not exist in this container.'],
                ]);
            }

            $data[$handle] = $ids;
        }

        return $data;
    }
}
