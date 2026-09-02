<?php

use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Statamic\Facades\Collection;
use Statamic\Facades\Role;
use Statamic\Facades\User;

beforeEach(function () {
    $this->articles = tap(Collection::make('articles'))->save();
});

it('lets a super admin through', function () {
    $user = tap(User::make()->email('s@example.com')->makeSuper())->save();

    Guard::authorize($user, 'view', $this->articles);

    expect(Guard::allows($user, 'view', $this->articles))->toBeTrue();
});

it('asks the Statamic policy: a permission holder passes, others get a 403', function () {
    Role::make('reader')->permissions(['view articles entries'])->save();
    $holder = tap(User::make()->email('h@example.com')->assignRole('reader'))->save();
    $nobody = tap(User::make()->email('n@example.com'))->save();

    Guard::authorize($holder, 'view', $this->articles);

    expect(fn () => Guard::authorize($nobody, 'view', $this->articles))
        ->toThrow(ApiException::class, 'Not authorized to view this resource.');
});

it('honours the policy before() hook, as the CP does', function () {
    Role::make('configurator')->permissions(['configure collections'])->save();
    $user = tap(User::make()->email('c@example.com')->assignRole('configurator'))->save();

    expect(Guard::allows($user, 'view', $this->articles))->toBeTrue();
});
