<?php

namespace Ppcharlier\StatamicEditorApi\Permissions;

use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;

final class Guard
{
    public static function check($user, string $permission): void
    {
        if (self::allows($user, $permission)) {
            return;
        }

        throw new ApiException('forbidden', "Missing permission: {$permission}.", 403);
    }

    public static function allows($user, string $permission): bool
    {
        return $user->isSuper() || $user->hasPermission($permission);
    }

    /**
     * Ask Statamic's own policy (EntryPolicy, ...) whether $user may perform
     * $ability on $resource. Unlike check(), this honours the per-resource
     * nuances the Control Panel applies — "edit other authors" and site
     * access above all — so the API never grants more than the CP would.
     */
    public static function authorize($user, string $ability, $resource): void
    {
        if ($user->can($ability, $resource)) {
            return;
        }

        throw new ApiException('forbidden', "Not authorized to {$ability} this resource.", 403);
    }
}
