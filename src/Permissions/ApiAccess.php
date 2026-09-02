<?php

namespace Ppcharlier\StatamicEditorApi\Permissions;

use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;

/**
 * The addon's own gate, the counterpart of Statamic's `access cp`: a non-super user
 * needs `access editor-api` (granted per role in the Control Panel) before a token is
 * issued to them, and on every request afterwards — revoking the permission cuts off
 * tokens already in the wild.
 */
final class ApiAccess
{
    public const PERMISSION = 'access editor-api';

    public static function ensure($user): void
    {
        if ($user->isSuper() || $user->hasPermission(self::PERMISSION)) {
            return;
        }

        throw new ApiException('forbidden', 'This account may not use the Editor API.', 403);
    }
}
