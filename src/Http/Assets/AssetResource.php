<?php

namespace Ppcharlier\StatamicEditorApi\Http\Assets;

use Ppcharlier\StatamicEditorApi\Permissions\Capabilities;

final class AssetResource
{
    public static function toArray($asset): array
    {
        return [
            'id' => $asset->id(),
            'path' => $asset->path(),
            'url' => $asset->url(),
            'filename' => $asset->filename(),
            'basename' => $asset->basename(),
            'extension' => $asset->extension(),
            'folder' => $asset->folder(),
            'size' => $asset->size(),
            'mime_type' => $asset->mimeType(),
            'is_image' => $asset->isImage(),
            'last_modified' => $asset->lastModified()?->toIso8601String(),
            'data' => $asset->data()->all(),
            'can' => Capabilities::of($asset, ['edit' => 'edit', 'move' => 'move', 'rename' => 'rename', 'delete' => 'delete']),
        ];
    }
}
