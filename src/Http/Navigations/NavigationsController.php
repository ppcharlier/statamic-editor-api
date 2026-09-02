<?php

namespace Ppcharlier\StatamicEditorApi\Http\Navigations;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Support\ResourceConfig;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;
use Ppcharlier\StatamicEditorApi\Support\SiteResolver;
use Statamic\Facades\Nav;

final class NavigationsController
{
    public function index(Request $request)
    {
        $user = $request->user();

        $navs = Nav::all()
            ->filter(fn ($nav) => ResourceConfig::enabled('navigations', $nav->handle()))
            ->filter(fn ($nav) => Guard::allows($user, 'view', $nav))
            ->map(fn ($nav) => ['handle' => $nav->handle(), 'title' => $nav->title()])
            ->values()->all();

        return response()->json(['data' => $navs]);
    }

    public function show(Request $request, string $handle)
    {
        [$nav, $tree] = $this->resolve($request, $handle);
        Guard::authorize($request->user(), 'view', $tree);

        return response()->json(['data' => $this->payload($nav, $tree)]);
    }

    public function update(Request $request, string $handle)
    {
        [$nav, $tree, $site] = $this->resolve($request, $handle);
        Guard::authorize($request->user(), 'edit', $tree);

        $payload = $request->validate(['tree' => ['present', 'array']]);

        $normalized = NavTreeSerializer::fromApi($payload['tree']);

        // Server-side parity with the CP (where max_depth is enforced in JS only).
        if (($max = $nav->maxDepth()) && $this->depth($normalized) > $max) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tree' => ["The tree exceeds this navigation's max_depth of {$max}."],
            ]);
        }

        // Mutate a clone: a 422 must never leave the shared Stache-cached tree
        // carrying the rejected value (long-lived workers, same-process readers).
        $working = clone $tree;
        $working->tree($normalized);

        try {
            $working->tree(); // no-arg getter: triggers Structure::validateTree on the value just set
        } catch (\Exception $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['tree' => [$e->getMessage()]]);
        }

        $working->save(); // outside the catch: a genuine internal failure stays a clean 500

        return response()->json(['data' => $this->payload($nav, $nav->in($site))]);
    }

    private function depth(array $tree): int
    {
        $max = 0;

        foreach ($tree as $node) {
            $max = max($max, 1 + $this->depth($node['children'] ?? []));
        }

        return $max;
    }

    private function resolve(Request $request, string $handle): array
    {
        ResourceGate::navigation($handle);

        // Unscoped: navs declare trees per site, and the absence of a tree for the
        // requested site (checked below) is itself the scope check.
        $site = SiteResolver::resolve($request);

        $nav = Nav::findByHandle($handle);

        if (! $nav || ! ($tree = $nav->in($site))) {
            throw new ApiException('not_found', 'Navigation not found.', 404);
        }

        return [$nav, $tree, $site];
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
