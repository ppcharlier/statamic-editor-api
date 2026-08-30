<?php

use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\User;

beforeEach(function () {
    config()->set('statamic.revisions.enabled', true);

    Collection::make('articles')->title('Articles')->dated(true)->revisionsEnabled(true)->save();
    Collection::make('pages')->title('Pages')->save();
    AssetContainer::make('uploads')->title('Uploads')->disk('local')->save();

    $user = tap(User::make()->email('pp@example.com')->makeSuper())->save();
    $this->plainToken = app(TokenRepository::class)->create($user->id(), 'iPhone')->plainText;
});

it('describes collections with their capabilities', function () {
    $response = $this->withToken($this->plainToken)->getJson('/api/editor/v1/config')->assertOk();

    $articles = collect($response->json('data.collections'))->firstWhere('handle', 'articles');

    expect($articles)->not->toBeNull()
        ->and($articles['title'])->toBe('Articles')
        ->and($articles['revisions_enabled'])->toBeTrue()
        ->and($articles['dated'])->toBeTrue()
        ->and($articles['blueprints'])->toBeArray();

    expect(collect($response->json('data.asset_containers'))->firstWhere('handle', 'uploads'))->not->toBeNull();
});

it('omits collections excluded by the resource whitelist', function () {
    config()->set('statamic.editor-api.resources.collections', ['articles']);

    $response = $this->withToken($this->plainToken)->getJson('/api/editor/v1/config')->assertOk();

    expect(collect($response->json('data.collections'))->pluck('handle'))
        ->toContain('articles')->not->toContain('pages');
});

it('requires authentication', function () {
    $this->getJson('/api/editor/v1/config')->assertStatus(401);
});
