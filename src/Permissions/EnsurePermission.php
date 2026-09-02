<?php

namespace Ppcharlier\StatamicEditorApi\Permissions;

use Closure;
use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;

/**
 * Route middleware `editor-api.can:{ability},{routeParam}` — authorizes $ability against
 * the model bound to the route parameter, through Statamic's own policy for that model
 * (`editor-api.can:view,collection` asks CollectionPolicy::view, exactly as the CP does).
 */
final class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $ability, string $routeParam)
    {
        $resource = $request->route($routeParam);

        if (! is_object($resource)) {
            throw new ApiException('not_found', 'Not found.', 404);
        }

        Guard::authorize($request->user(), $ability, $resource);

        return $next($request);
    }
}
