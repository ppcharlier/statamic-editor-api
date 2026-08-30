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
        // AssetUploader::getSafePath()/getSafeFilename() urldecode() each path segment
        // downstream, so a percent-encoded (or double-percent-encoded) value can pass
        // a raw-string check here and still resolve to a traversal afterwards. Decode
        // until stable — one pass matches what getSafePath does, the loop also catches
        // double-encoding — and check both the raw and the fully-decoded value.
        $decoded = $value;

        do {
            $previous = $decoded;
            $decoded = urldecode($previous);
        } while ($decoded !== $previous);

        if (str_contains($value, '..') || str_contains($decoded, '..')) {
            throw ValidationException::withMessages(['path' => ['Path traversal is not allowed.']]);
        }
    }
}
