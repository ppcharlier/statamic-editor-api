<?php

namespace Ppcharlier\StatamicEditorApi\Http\Assets;

use Illuminate\Validation\ValidationException;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;

trait ResolvesAssets
{
    private function findAsset($container, string $path)
    {
        $this->rejectTraversal($path);

        $asset = $container->asset($path);

        if (! $asset) {
            throw new ApiException('not_found', 'Asset not found.', 404);
        }

        return $asset;
    }

    private function rejectTraversal(string $value): void
    {
        if (str_contains($value, '..')) {
            throw ValidationException::withMessages(['path' => ['Path traversal is not allowed.']]);
        }
    }
}
