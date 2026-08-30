<?php

namespace Ppcharlier\StatamicEditorApi\Support;

final class ResourceConfig
{
    public static function enabled(string $resource, ?string $handle = null): bool
    {
        $value = config("statamic.editor-api.resources.{$resource}", false);

        if ($value === true) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        return $handle === null || in_array($handle, $value, true);
    }
}
