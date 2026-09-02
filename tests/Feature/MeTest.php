<?php

use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Statamic\Facades\Role;
use Statamic\Facades\User;

it('returns the profile and wildcard permissions for a super admin', function () {
    $user = tap(User::make()->email('pp@example.com')->data(['name' => 'Pierre-Philippe'])->makeSuper())->save();
    $token = app(TokenRepository::class)->create($user->id(), 'iPhone');

    $this->withToken($token->plainText)->getJson('/api/editor/v1/me')
        ->assertOk()
        ->assertJson(['data' => [
            'email' => 'pp@example.com',
            'name' => 'Pierre-Philippe',
            'super' => true,
            'permissions' => ['*'],
        ]])
        ->assertJsonStructure(['data' => ['avatar']]);
});

it('returns resolved role permissions for a regular user', function () {
    Role::make('editor')->title('Editor')->permissions(['access editor-api', 'view articles entries', 'edit articles entries'])->save();
    $user = tap(User::make()->email('writer@example.com')->assignRole('editor'))->save();
    $token = app(TokenRepository::class)->create($user->id(), 'iPhone');

    $response = $this->withToken($token->plainText)->getJson('/api/editor/v1/me')->assertOk();

    expect($response->json('data.super'))->toBeFalse()
        ->and($response->json('data.permissions'))->toContain('view articles entries', 'edit articles entries');
});
