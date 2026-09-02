<?php

use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Statamic\Facades\Collection;
use Statamic\Facades\Role;
use Statamic\Facades\User;

function tokenFor($user): string
{
    return app(TokenRepository::class)->create($user->id(), 'iPhone')->plainText;
}

beforeEach(function () {
    // Statamic's RouteServiceProvider globally binds any {collection} route parameter under an
    // "api/..." prefix (Illuminate\Routing\Middleware\SubstituteBindings, part of the "api"
    // middleware group) to a real Collection model *before* our route middleware runs. Without
    // a matching collection existing in the stache, that binding throws its own
    // NotFoundHttpException and the request never reaches editor-api.can — the collection must
    // exist for the fixture route to be reachable at all.
    Collection::make('articles')->save();
});

it('allows a super admin', function () {
    $user = tap(User::make()->email('super@example.com')->makeSuper())->save();

    $this->withToken(tokenFor($user))->getJson('/api/editor/v1/_guarded/articles')->assertOk();
});

it('allows a user holding the mapped permission', function () {
    Role::make('editor')->permissions(['access editor-api', 'view articles entries'])->save();
    $user = tap(User::make()->email('writer@example.com')->assignRole('editor'))->save();

    $this->withToken(tokenFor($user))->getJson('/api/editor/v1/_guarded/articles')->assertOk();
});

it('rejects a user lacking the permission with 403 forbidden', function () {
    $user = tap(User::make()->email('nobody@example.com'))->save();

    $this->withToken(tokenFor($user))->getJson('/api/editor/v1/_guarded/articles')
        ->assertStatus(403)
        ->assertJson(['error' => ['code' => 'forbidden']]);
});
