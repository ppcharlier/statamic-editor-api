<?php

use Illuminate\Http\UploadedFile;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsAssetFixtures;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\AssetContainer;

uses(BuildsEntryFixtures::class, BuildsAssetFixtures::class);

beforeEach(function () {
    $this->container = $this->makeUploadsContainer();
    $this->token = $this->makeSuperToken();
    $this->container->makeAsset('photo.jpg')->upload(UploadedFile::fake()->image('photo.jpg'));
});

it('renames an asset', function () {
    $response = $this->withToken($this->token)
        ->patchJson('/api/editor/v1/assets/uploads/photo.jpg', ['filename' => 'renommee'])
        ->assertOk();

    expect($response->json('data.path'))->toBe('renommee.jpg');
    expect(AssetContainer::findByHandle('uploads')->asset('renommee.jpg'))->not->toBeNull()
        ->and(AssetContainer::findByHandle('uploads')->asset('photo.jpg'))->toBeNull();
});

it('moves an asset into a folder', function () {
    $response = $this->withToken($this->token)
        ->patchJson('/api/editor/v1/assets/uploads/photo.jpg', ['folder' => 'archives'])
        ->assertOk();

    expect($response->json('data.path'))->toBe('archives/photo.jpg');
});

it('updates metadata through the container blueprint', function () {
    $response = $this->withToken($this->token)
        ->patchJson('/api/editor/v1/assets/uploads/photo.jpg', ['data' => ['alt' => 'Une photo de test']])
        ->assertOk();

    expect($response->json('data.data.alt'))->toBe('Une photo de test');
    expect(AssetContainer::findByHandle('uploads')->asset('photo.jpg')->get('alt'))->toBe('Une photo de test');
});

it('handles a path with a folder segment', function () {
    $this->container->makeAsset('dossier/img.jpg')->upload(UploadedFile::fake()->image('img.jpg'));

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/assets/uploads/dossier/img.jpg', ['data' => ['alt' => 'ok']])
        ->assertOk();
});

it('404s an unknown asset path', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/assets/uploads/inexistant.jpg', ['filename' => 'x'])
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'not_found']]);
});

it('enforces per-operation permissions', function () {
    $renameOnly = $this->makeTokenWithPermissions(['view uploads assets', 'rename uploads assets']);

    // rename passe
    $this->withToken($renameOnly)
        ->patchJson('/api/editor/v1/assets/uploads/photo.jpg', ['filename' => 'ok'])
        ->assertOk();

    // move refuse (403) — et rien n'a bougé
    $this->withToken($renameOnly)
        ->patchJson('/api/editor/v1/assets/uploads/ok.jpg', ['folder' => 'ailleurs'])
        ->assertStatus(403);

    expect(AssetContainer::findByHandle('uploads')->asset('ok.jpg'))->not->toBeNull();
});

it('rejects an empty payload', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/assets/uploads/photo.jpg', [])
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']]);
});
