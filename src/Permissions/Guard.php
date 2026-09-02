<?php

namespace Ppcharlier\StatamicEditorApi\Permissions;

use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;

/**
 * The single authorization path of the API. Every decision is delegated to Statamic's
 * own policies (EntryPolicy, AssetPolicy, ...) through the Gate, so the verdict is
 * exactly the Control Panel's: per-resource nuances such as "edit other authors",
 * site access, and the `configure ...` permissions that open a whole area.
 */
final class Guard
{
    public static function authorize($user, string $ability, $resource): void
    {
        if (self::allows($user, $ability, $resource)) {
            return;
        }

        throw new ApiException('forbidden', "Not authorized to {$ability} this resource.", 403);
    }

    /**
     * $resource is what the Gate expects: a model, or `[Contract::class, ...arguments]`
     * for abilities that take no instance yet (`create` on a collection and site,
     * `store` on an asset container, `publish` on a collection).
     */
    public static function allows($user, string $ability, $resource): bool
    {
        return $user->can($ability, $resource);
    }
}
