<?php

namespace Ppcharlier\StatamicEditorApi\Support;

use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;

final class SortParam
{
    /**
     * Resolve a `?sort=` value (`-` prefix = descending) against an allow-list.
     * Unknown fields are a 422, not a silent no-op.
     *
     * @return array{0: string, 1: string} [column, direction]
     */
    public static function resolve(?string $sort, array $allowed, string $default): array
    {
        $sort = $sort ?? $default;
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            throw new ApiException(
                'validation_failed',
                'The given data was invalid.',
                422,
                ['sort' => ["Sort field [{$column}] is not sortable on this resource."]],
            );
        }

        return [$column, str_starts_with($sort, '-') ? 'desc' : 'asc'];
    }
}
