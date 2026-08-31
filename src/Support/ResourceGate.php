<?php

namespace Ppcharlier\StatamicEditorApi\Support;

use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;

final class ResourceGate
{
    public static function collection(string $handle): void
    {
        if (! ResourceConfig::enabled('collections', $handle)) {
            throw new ApiException('not_found', 'Not found.', 404);
        }
    }

    public static function assetContainer(string $handle): void
    {
        if (! ResourceConfig::enabled('assets', $handle)) {
            throw new ApiException('not_found', 'Not found.', 404);
        }
    }

    public static function global(string $handle): void
    {
        if (! ResourceConfig::enabled('globals', $handle)) {
            throw new ApiException('not_found', 'Not found.', 404);
        }
    }

    public static function taxonomy(string $handle): void
    {
        if (! ResourceConfig::enabled('taxonomies', $handle)) {
            throw new ApiException('not_found', 'Not found.', 404);
        }
    }

    public static function navigation(string $handle): void
    {
        if (! ResourceConfig::enabled('navigations', $handle)) {
            throw new ApiException('not_found', 'Not found.', 404);
        }
    }
}
