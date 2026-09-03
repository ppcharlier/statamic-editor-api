<?php

namespace Ppcharlier\StatamicEditorApi\Permissions;

/**
 * Hides other authors' entries from the API — the one place where this addon is
 * deliberately STRICTER than the Control Panel, which lists every entry (author column
 * included) to anyone who may view the collection. Statamic has no permission for it:
 * `CorePermissions` only knows `edit|publish|delete other authors {collection} entries`.
 *
 * Off by default, and read with a fallback so an installation whose published config file
 * predates this key keeps its behaviour after a `composer update` (the same guard as
 * `rate_limits.api_per_ip` — `mergeConfigFrom` merges top-level keys only).
 */
final class AuthorVisibility
{
    public const LIST = 'editor-api list other authors {collection} entries';

    public const IDENTIFY = 'editor-api view other authors of {collection} entries';

    /** May the user see entries written by somebody else listed in this collection? */
    public static function listsOtherAuthors($user, $collection): bool
    {
        return self::granted($user, $collection, self::LIST);
    }

    /** May the user know WHO wrote another author's entry in this collection? */
    public static function identifiesOtherAuthors($user, $collection): bool
    {
        return self::granted($user, $collection, self::IDENTIFY);
    }

    /**
     * Somebody else's entry, in the sense EntryPolicy gives it: no `author` field means no
     * ownership at all, and an entry with the field but no author counts as another's.
     */
    public static function isAnotherAuthors($user, $entry): bool
    {
        if (! $entry->blueprint()->hasField('author')) {
            return false;
        }

        return ! $entry->authors()->contains($user->id());
    }

    /**
     * Narrows a listing to the user's own entries. Applied to the QUERY rather than to the
     * page it returns, so `meta.total` counts what the user can actually see.
     *
     * Both storage shapes are matched: a `users` field with `max_items: 1` holds a scalar,
     * without it a list — which is why `Entry::authors()` wraps `value('author')` in a
     * collection. `whereJsonContains` ignores scalars and `where` ignores arrays, so the two
     * clauses never overlap.
     */
    public static function constrainToOwn($query, $user): void
    {
        $id = $user->id();

        $query->where(fn ($q) => $q->where('author', $id)->orWhereJsonContains('author', $id));
    }

    private static function granted($user, $collection, string $permission): bool
    {
        if (! self::enforced()) {
            return true;
        }

        // No `author` field in the collection's blueprint: nothing owns anything here.
        if (! $collection->entryBlueprint()?->hasField('author')) {
            return true;
        }

        if ($user->isSuper()) {
            return true;
        }

        $handle = $collection->handle();

        // Whoever may edit another author's entry already sees it in the CP; hiding it in
        // the app would be absurd, and would silently break existing editor roles.
        return $user->hasPermission(str_replace('{collection}', $handle, $permission))
            || $user->hasPermission("edit other authors {$handle} entries");
    }

    private static function enforced(): bool
    {
        return (bool) config('statamic.editor-api.enforce_author_visibility', false);
    }
}
