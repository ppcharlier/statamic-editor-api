<?php

namespace Ppcharlier\StatamicEditorApi\Http\Assets;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Permissions\PermissionMap;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;
use Ppcharlier\StatamicEditorApi\Support\UnknownFields;

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

        $perPage = (int) ($params['per_page'] ?? 25);

        $paginator = $container->queryAssets()
            ->where('folder', $folder)
            ->orderBy('path')
            ->paginate($perPage);

        // Folders share the assets' page/per_page: both lists advance together.
        $folders = $container->folders($folder, false)->sort()->values();
        $page = $paginator->currentPage();

        return response()->json([
            'data' => [
                'assets' => collect($paginator->items())->map(fn ($a) => AssetResource::toArray($a))->values()->all(),
                'folders' => $folders->slice(($page - 1) * $perPage, $perPage)->values()->all(),
            ],
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $page,
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'folders_total' => $folders->count(),
                'folders_last_page' => max(1, (int) ceil($folders->count() / $perPage)),
            ],
        ]);
    }

    public function store(Request $request, $container)
    {
        ResourceGate::assetContainer($handle = $container->handle());
        Guard::check($request->user(), PermissionMap::assets('upload', $handle));

        $request->validate([
            'file' => array_merge(['required', 'file', new \Statamic\Rules\AllowedFile], $container->validationRules()),
            'folder' => ['nullable', 'string', 'max:500'],
        ]);

        $rawFolder = trim($request->input('folder', ''), '/');
        $this->rejectTraversal($rawFolder);

        $file = $request->file('file');
        $basename = \Statamic\Assets\AssetUploader::getSafeFilename($file->getClientOriginalName());
        $folder = $rawFolder !== '' ? \Statamic\Assets\AssetUploader::getSafePath($rawFolder) : '';
        $path = ltrim(($folder !== '' ? $folder.'/' : '').$basename, '/');

        $asset = $container->makeAsset($path)->upload($file);

        return response()->json(['data' => AssetResource::toArray($asset)], 201);
    }

    public function update(Request $request, $container, string $path)
    {
        ResourceGate::assetContainer($handle = $container->handle());
        Guard::check($request->user(), PermissionMap::assets('view', $handle));

        $asset = $this->findAsset($container, $path);

        $payload = $request->validate([
            'filename' => ['sometimes', 'string', 'max:200'],
            'folder' => ['sometimes', 'string', 'max:500'],
            'data' => ['sometimes', 'array'],
        ]);

        if ($payload === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payload' => ['Provide at least one of: filename, folder, data.'],
            ]);
        }

        // toutes les permissions d'abord — aucune écriture avant un éventuel 403
        if (array_key_exists('filename', $payload)) {
            Guard::check($request->user(), PermissionMap::assets('rename', $handle));
        }
        if (array_key_exists('folder', $payload)) {
            $this->rejectTraversal($payload['folder']);
            Guard::check($request->user(), PermissionMap::assets('move', $handle));
        }
        if (array_key_exists('data', $payload)) {
            Guard::check($request->user(), PermissionMap::assets('edit', $handle));
        }

        if (array_key_exists('data', $payload)) {
            $blueprint = $container->blueprint();
            $this->rejectUnknownFields($payload['data'], $blueprint);

            $fields = $blueprint->fields()->addValues($payload['data']);
            $fields->validator()->validate();
            $asset->merge(Arr::only($fields->process()->values()->all(), array_keys($payload['data'])));
            $asset->save();
        }

        if (array_key_exists('folder', $payload) || array_key_exists('filename', $payload)) {
            $folder = array_key_exists('folder', $payload)
                ? (\Statamic\Assets\AssetUploader::getSafePath(trim($payload['folder'], '/')) ?: '/')
                : $asset->folder();
            $filename = array_key_exists('filename', $payload)
                ? \Statamic\Assets\AssetUploader::getSafeFilename($payload['filename'])
                : null;

            $asset->move($folder, $filename);
        }

        return response()->json(['data' => AssetResource::toArray($container->asset($asset->path()))]);
    }

    public function destroy(Request $request, $container, string $path)
    {
        ResourceGate::assetContainer($handle = $container->handle());
        Guard::check($request->user(), PermissionMap::assets('delete', $handle));

        $asset = $this->findAsset($container, $path);

        $asset->delete();

        return response()->noContent();
    }

    private function rejectUnknownFields(array $data, $blueprint): void
    {
        UnknownFields::reject($data, $blueprint->fields()->all()->keys()->all());
    }
}
