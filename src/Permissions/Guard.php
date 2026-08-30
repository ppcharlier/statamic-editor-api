<?php

namespace Ppcharlier\StatamicEditorApi\Permissions;

use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;

final class Guard
{
    public static function check($user, string $permission): void
    {
        if ($user->isSuper() || $user->hasPermission($permission)) {
            return;
        }

        throw new ApiException('forbidden', "Missing permission: {$permission}.", 403);
    }
}
