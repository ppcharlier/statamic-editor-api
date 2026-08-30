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
}
