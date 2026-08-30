<?php

use Ppcharlier\StatamicEditorApi\Permissions\PermissionMap;

it('maps entry actions to statamic permissions', function (string $action, string $expected) {
    expect(PermissionMap::entries($action, 'articles'))->toBe($expected);
})->with([
    ['view', 'view articles entries'],
    ['edit', 'edit articles entries'],
    ['create', 'create articles entries'],
    ['delete', 'delete articles entries'],
    ['publish', 'publish articles entries'],
]);

it('maps asset and global permissions', function () {
    expect(PermissionMap::assets('upload', 'uploads'))->toBe('upload uploads assets')
        ->and(PermissionMap::globals('footer'))->toBe('edit footer globals');
});

it('rejects unknown actions loudly', function () {
    PermissionMap::entries('frobnicate', 'articles');
})->throws(InvalidArgumentException::class);
