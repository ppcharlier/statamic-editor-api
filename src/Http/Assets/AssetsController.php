<?php

namespace Ppcharlier\StatamicEditorApi\Http\Assets;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Permissions\PermissionMap;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;

final class AssetsController
{
    use ResolvesAssets;

    public function index(Request $request, $container)
    {
        ResourceGate::assetContainer($handle = $container->handle());
        Guard::check($request->user(), PermissionMap::assets('view', $handle));

        $params = $request->validate([
            'folder' => ['sometimes', 'string', 'max:500'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $folder = trim($params['folder'] ?? '/', '/') ?: '/';
        $this->rejectTraversal($folder);

        $paginator = $container->queryAssets()
            ->where('folder', $folder)
            ->orderBy('path')
            ->paginate((int) ($params['per_page'] ?? 25));

        return response()->json([
            'data' => [
                'assets' => collect($paginator->items())->map(fn ($a) => AssetResource::toArray($a))->values()->all(),
                'folders' => $container->folders($folder, false)->values()->all(),
            ],
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
