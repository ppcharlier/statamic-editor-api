<?php

namespace Ppcharlier\StatamicEditorApi\Permissions;

/**
 * The `can` block carried by every payload: the same policies that would refuse a
 * write, asked ahead of time for the current user, so a client greys out what it may
 * not do instead of discovering it through a 403.
 */
final class Capabilities
{
    /**
     * @param  array<string, string>  $abilities  payload key => policy ability
     * @param  mixed  $resource  a model, or `[Contract::class, ...arguments]` for class-level abilities
     */
    public static function of($resource, array $abilities): array
    {
        $user = request()->user();

        return collect($abilities)
            ->map(fn ($ability) => $user ? Guard::allows($user, $ability, $resource) : false)
            ->all();
    }
}
