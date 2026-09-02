<?php

namespace Ppcharlier\StatamicEditorApi\Support;

use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;

/**
 * Reconciles what Statamic STORES with what its fieldtypes CONSUME on write.
 *
 * Two fieldtype families store one shape and accept another, because the Control Panel's
 * JavaScript submits the richer one and process() narrows it down on the way to disk:
 *
 * - `assets` stores bare paths — a scalar rather than an array when `max_files` is 1 — but
 *   consumes an array of `container::path` ids (Statamic\Fieldtypes\Assets\Assets).
 * - every relationship fieldtype (`entries`, `terms`, `users`, `taxonomies`, `navs`… — anything
 *   extending Statamic\Fieldtypes\Relationship) validates with `array`, yet process() unwraps
 *   the array to a scalar when `max_items` is 1. Its rules then reject the very value it wrote:
 *   "must be an array" plus, since Laravel picks the message variant from the `array` RULE and
 *   not from the value, "must not have more than 1 items" on a string of two characters or more.
 *   Worse, process() would then read `$value[0]` off that string and store its first letter.
 *
 * This API exposes stored data verbatim, so a client echoing back what GET returned — the iOS
 * app resending an untouched field while changing the slug — was rejected on a field it never
 * edited. Running those fieldtypes' own preProcess(), and only theirs, makes the stored shape
 * acceptable while leaving the verbatim round-trip of Bard and everything else untouched.
 *
 * Both are idempotent for Control-Panel-shaped input: Arr::wrap() leaves arrays alone, terms'
 * single-taxonomy prefixing and assets' valueToId() pass anything containing '::' through.
 *
 * Nested fields need the same treatment — a relationship inside a Grid row is validated as
 * `blocs.0.serie` and fails identically — so the walk descends into the containers whose stored
 * shape is known: `group`, `grid`, `replicator` and Bard's sets. Anything else is left alone,
 * as is any value not shaped like its container expects: Statamic's own validation is a better
 * place to complain about that than a crash in here.
 */
final class FieldShape
{
    /** @param  \Statamic\Fields\Blueprint  $blueprint */
    public static function normalize(array $data, $blueprint): array
    {
        return self::walk($data, $blueprint->fields());
    }

    /** @param  \Statamic\Fields\Fields  $fields */
    private static function walk(array $data, $fields, string $prefix = ''): array
    {
        foreach ($fields->all() as $handle => $field) {
            if (array_key_exists($handle, $data)) {
                $data[$handle] = self::value($field, $data[$handle], $prefix.$handle);
            }
        }

        return $data;
    }

    /** @param  \Statamic\Fields\Field  $field */
    private static function value($field, $value, string $path)
    {
        $fieldtype = $field->fieldtype();

        if ($field->type() === 'assets') {
            return self::assets($path, $field, $value);
        }

        if ($fieldtype->isRelationship()) {
            return $fieldtype->preProcess($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        return match ($field->type()) {
            'group' => self::walk($value, $fieldtype->fields(), $path.'.'),
            'grid' => self::rows($value, $path, fn ($row, $i) => $fieldtype->fields($i)),
            'replicator' => self::rows($value, $path, fn ($row, $i) => isset($row['type'])
                ? $fieldtype->fields($row['type'], $i)
                : null),
            'bard' => self::bardSets($value, $path, $fieldtype),
            default => $value,
        };
    }

    /** @param  \Closure  $fields  Resolves a row's Fields, or null when it matches no set. */
    private static function rows(array $rows, string $path, $fields): array
    {
        foreach ($rows as $i => $row) {
            if (is_array($row) && ($rowFields = $fields($row, $i))) {
                $rows[$i] = self::walk($row, $rowFields, $path.'.'.$i.'.');
            }
        }

        return $rows;
    }

    /**
     * Bard stores a ProseMirror document; its sets are top-level nodes carrying their values
     * under `attrs.values`, keyed by set handle in `type` (Statamic\Fieldtypes\Bard::processRow).
     * A `save_html` Bard stores a string and never reaches here.
     *
     * @param  \Statamic\Fieldtypes\Bard  $fieldtype
     */
    private static function bardSets(array $nodes, string $path, $fieldtype): array
    {
        foreach ($nodes as $i => $node) {
            if (! is_array($node) || ($node['type'] ?? null) !== 'set') {
                continue;
            }

            $values = $node['attrs']['values'] ?? null;

            if (is_array($values) && isset($values['type'])) {
                $nodes[$i]['attrs']['values'] = self::walk(
                    $values, $fieldtype->fields($values['type'], $i), $path.'.'.$i.'.attrs.values.'
                );
            }
        }

        return $nodes;
    }

    /** @param  \Statamic\Fields\Field  $field */
    private static function assets(string $path, $field, $submitted): array
    {
        $ids = $field->fieldtype()->preProcess($submitted);

        // preProcess() SILENTLY drops what it cannot resolve. The Control Panel can afford
        // that — its picker only ever submits assets it just listed — but an API cannot: a
        // mistyped path would erase the field without a word. Count the survivors instead.
        $expected = $submitted === null ? 0 : count((array) $submitted);

        if (count($ids) !== $expected) {
            throw new ApiException('validation_failed', 'The given data was invalid.', 422, [
                $path => ['One or more of these assets do not exist in this container.'],
            ]);
        }

        return $ids;
    }
}
