<?php

namespace Ppcharlier\StatamicEditorApi\Permissions;

use InvalidArgumentException;

final class PermissionMap
{
    private const ENTRY_ACTIONS = ['view', 'edit', 'create', 'delete', 'publish'];

    private const ASSET_ACTIONS = ['view', 'upload', 'edit', 'move', 'rename', 'delete'];

    private const TERM_ACTIONS = ['view', 'edit', 'create', 'delete'];

    private const NAV_ACTIONS = ['view', 'edit'];

    private const FORM_SUBMISSION_ACTIONS = ['view', 'delete'];

    public static function entries(string $action, string $collection): string
    {
        self::assertAction($action, self::ENTRY_ACTIONS);

        return "{$action} {$collection} entries";
    }

    public static function terms(string $action, string $taxonomy): string
    {
        self::assertAction($action, self::TERM_ACTIONS);

        return "{$action} {$taxonomy} terms";
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

    public static function navs(string $action, string $nav): string
    {
        self::assertAction($action, self::NAV_ACTIONS);

        return "{$action} {$nav} nav";
    }

    public static function formSubmissions(string $action, string $form): string
    {
        self::assertAction($action, self::FORM_SUBMISSION_ACTIONS);

        return "{$action} {$form} form submissions";
    }

    private static function assertAction(string $action, array $allowed): void
    {
        if (! in_array($action, $allowed, true)) {
            throw new InvalidArgumentException("Unknown permission action [{$action}].");
        }
    }
}
