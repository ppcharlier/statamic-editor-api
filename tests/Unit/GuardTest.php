<?php

use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Statamic\Facades\Role;
use Statamic\Facades\User;

it('lets a super admin through', function () {
    $user = tap(User::make()->email('s@example.com')->makeSuper())->save();

    Guard::check($user, 'edit articles entries');

    expect(true)->toBeTrue();
});

it('lets a permission holder through and blocks others', function () {
    Role::make('editor')->permissions(['edit articles entries'])->save();
    $holder = tap(User::make()->email('h@example.com')->assignRole('editor'))->save();
    $nobody = tap(User::make()->email('n@example.com'))->save();

    Guard::check($holder, 'edit articles entries');

    expect(fn () => Guard::check($nobody, 'edit articles entries'))
        ->toThrow(ApiException::class);
});
