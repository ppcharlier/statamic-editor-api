<?php

namespace Ppcharlier\StatamicEditorApi\Permissions;

use InvalidArgumentException;

final class PermissionMap
{
    private const ENTRY_ACTIONS = ['view', 'edit', 'create', 'delete', 'publish'];

    private const ASSET_ACTIONS = ['view', 'upload', 'edit', 'move', 'rename', 'delete'];

    public static function entries(string $action, string $collection): string
    {
        self::assertAction($action, self::ENTRY_ACTIONS);

        return "{$action} {$collection} entries";
    }

    public static function assets(string $action, string $container): string
    {
        self::assertAction($action, self::ASSET_ACTIONS);

        return "{$action} {$container} assets";
    }

    public static function globals(string $handle): string
    {
        return "edit {$handle} globals";
    }

    private static function assertAction(string $action, array $allowed): void
    {
        if (! in_array($action, $allowed, true)) {
            throw new InvalidArgumentException("Unknown permission action [{$action}].");
        }
    }
}
