<?php

namespace Ppcharlier\StatamicEditorApi\Permissions;

use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $area, string $action, string $routeParam)
    {
        $handle = (string) $request->route($routeParam);

        $permission = match ($area) {
            'entries' => PermissionMap::entries($action, $handle),
            'assets' => PermissionMap::assets($action, $handle),
            'globals' => PermissionMap::globals($handle),
            default => throw new InvalidArgumentException("Unknown permission area [{$area}]."),
        };

        Guard::check($request->user(), $permission);

        return $next($request);
    }
}
