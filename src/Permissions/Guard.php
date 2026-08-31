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
}
