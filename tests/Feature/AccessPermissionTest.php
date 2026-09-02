<?php

use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Statamic\Facades\Role;
use Statamic\Facades\User;

// Like `access cp` for the Control Panel: a non-super user needs the addon's own
// `access editor-api` permission before any token is issued or honoured.
beforeEach(function () {
    Role::make('mobile')->title('Mobile')->permissions(['access editor-api', 'view articles entries'])->save();
    Role::make('desk')->title('Desk only')->permissions(['view articles entries'])->save();
});

function loginAs(string $email): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/editor/v1/auth/tokens', [
        'email' => $email, 'password' => 'secret-123', 'device_name' => 'iPhone',
    ]);
}

it('refuses to issue a token to a user without access editor-api', function () {
    tap(User::make()->email('desk@example.com')->password('secret-123')->assignRole('desk'))->save();

    loginAs('desk@example.com')
        ->assertStatus(403)
        ->assertJson(['error' => ['code' => 'forbidden']])
        ->assertJsonMissingPath('data.token');
});

it('issues a token to a user holding access editor-api', function () {
    tap(User::make()->email('mobile@example.com')->password('secret-123')->assignRole('mobile'))->save();

    loginAs('mobile@example.com')->assertCreated();
});

it('refuses an existing token once the user loses access editor-api', function () {
    $user = tap(User::make()->email('mobile@example.com')->assignRole('mobile'))->save();
    $token = app(TokenRepository::class)->create($user->id(), 'iPhone')->plainText;

    $this->withToken($token)->getJson('/api/editor/v1/me')->assertOk();

    $user->removeRole('mobile')->assignRole('desk')->save();
    app(\Statamic\Auth\PermissionCache::class)->clear(); // per-request memo of the user's permissions; a real request starts clean

    $this->withToken($token)->getJson('/api/editor/v1/me')
        ->assertStatus(403)
        ->assertJson(['error' => ['code' => 'forbidden']]);
});

it('never asks a super user for it', function () {
    $user = tap(User::make()->email('super@example.com')->password('secret-123')->makeSuper())->save();

    loginAs('super@example.com')->assertCreated();
});

it('registers the permission so roles can be granted it in the CP', function () {
    // boot() is what the CP's BootPermissions middleware and the roles editor call.
    expect(\Statamic\Facades\Permission::boot()->flattened()->map->value()->all())->toContain('access editor-api');
});
