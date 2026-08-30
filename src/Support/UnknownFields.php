<?php

namespace Ppcharlier\StatamicEditorApi\Support;

use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;

final class UnknownFields
{
    /** @param list<string> $known */
    public static function reject(array $data, array $known): void
    {
        $unknown = array_values(array_diff(array_keys($data), $known));

        if ($unknown !== []) {
            throw new ApiException(
                'unknown_field',
                'Unknown fields: '.implode(', ', $unknown).'.',
                422,
                collect($unknown)->mapWithKeys(fn ($f) => [$f => ['This field is not in the blueprint.']])->all(),
            );
        }
    }
}
