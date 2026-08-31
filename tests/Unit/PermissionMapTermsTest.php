<?php

use Ppcharlier\StatamicEditorApi\Permissions\PermissionMap;

it('maps term actions to vendor permission strings', function (string $action, string $expected) {
    expect(PermissionMap::terms($action, 'themes'))->toBe($expected);
})->with([
    ['view', 'view themes terms'],
    ['edit', 'edit themes terms'],
    ['create', 'create themes terms'],
    ['delete', 'delete themes terms'],
]);

it('rejects unknown term actions', function () {
    PermissionMap::terms('publish', 'themes');
})->throws(InvalidArgumentException::class);
