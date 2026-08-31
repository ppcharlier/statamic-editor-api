<?php

namespace Ppcharlier\StatamicEditorApi\Http\Navigations;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Permissions\PermissionMap;
use Ppcharlier\StatamicEditorApi\Support\ResourceConfig;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;
use Statamic\Facades\Nav;
use Statamic\Facades\Site;

final class NavigationsController
{
    public function index(Request $request)
    {
        $user = $request->user();

        $navs = Nav::all()
            ->filter(fn ($nav) => ResourceConfig::enabled('navigations', $nav->handle()))
            ->filter(fn ($nav) => Guard::allows($user, PermissionMap::navs('view', $nav->handle()))
                || Guard::allows($user, PermissionMap::navs('edit', $nav->handle())))
            ->map(fn ($nav) => ['handle' => $nav->handle(), 'title' => $nav->title()])
            ->values()->all();

        return response()->json(['data' => $navs]);
    }

    public function show(Request $request, string $handle)
    {
        [$nav, $tree] = $this->resolve($handle);
        $this->guardView($request, $handle);

        return response()->json(['data' => $this->payload($nav, $tree)]);
    }

    public function update(Request $request, string $handle)
    {
        [$nav, $tree] = $this->resolve($handle);
        Guard::check($request->user(), PermissionMap::navs('edit', $handle));

        $payload = $request->validate(['tree' => ['present', 'array']]);

        $normalized = NavTreeSerializer::fromApi($payload['tree']);

        try {
            $tree->tree($normalized)->save();
        } catch (\Throwable $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['tree' => [$e->getMessage()]]);
        }

        return response()->json(['data' => $this->payload($nav, $nav->in(Site::default()->handle()))]);
    }

    private function resolve(string $handle): array
    {
        ResourceGate::navigation($handle);

        $nav = Nav::findByHandle($handle);

        if (! $nav || ! ($tree = $nav->in(Site::default()->handle()))) {
            throw new ApiException('not_found', 'Navigation not found.', 404);
        }

        return [$nav, $tree];
    }

    private function guardView(Request $request, string $handle): void
    {
        $user = $request->user();

        if (Guard::allows($user, PermissionMap::navs('view', $handle))
            || Guard::allows($user, PermissionMap::navs('edit', $handle))) {
            return;
        }

        throw new ApiException('forbidden', 'Missing permission: '.PermissionMap::navs('view', $handle).'.', 403);
    }

    private function payload($nav, $tree): array
    {
        return [
            'handle' => $nav->handle(),
            'title' => $nav->title(),
            'max_depth' => $nav->maxDepth(),
            'expects_root' => (bool) $nav->expectsRoot(),
            'tree' => NavTreeSerializer::toApi($tree->tree()),
        ];
    }
}
