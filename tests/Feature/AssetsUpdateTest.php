<?php

use Illuminate\Http\UploadedFile;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsAssetFixtures;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;

uses(BuildsEntryFixtures::class, BuildsAssetFixtures::class);

beforeEach(function () {
    $this->container = $this->makeUploadsContainer();
    $this->token = $this->makeSuperToken();
    $this->container->makeAsset('photo.jpg')->upload(UploadedFile::fake()->image('photo.jpg'));
});

$withAltAndCaptionBlueprint = function () {
    Blueprint::make('uploads')
        ->setNamespace('assets')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'alt', 'field' => ['type' => 'text', 'display' => 'Alt']],
            ['handle' => 'caption', 'field' => ['type' => 'text', 'display' => 'Caption']],
        ]]]]]])
        ->save();
};

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

it('does not null out other blueprint fields on a partial data patch', function () use ($withAltAndCaptionBlueprint) {
    $withAltAndCaptionBlueprint();

    $asset = AssetContainer::findByHandle('uploads')->asset('photo.jpg');
    $asset->set('caption', 'Garder-moi')->save();

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/assets/uploads/photo.jpg', ['data' => ['alt' => 'New']])
        ->assertOk();

    $fresh = AssetContainer::findByHandle('uploads')->asset('photo.jpg');
    expect($fresh->get('alt'))->toBe('New');
    expect($fresh->get('caption'))->toBe('Garder-moi');
});

it('does not persist data when a combined operation is refused', function () {
    $token = $this->makeTokenWithPermissions(['view uploads assets', 'edit uploads assets']); // pas move

    $this->withToken($token)
        ->patchJson('/api/editor/v1/assets/uploads/photo.jpg', [
            'data' => ['alt' => 'Ne doit pas rester'],
            'folder' => 'ailleurs',
        ])->assertStatus(403);

    expect(AssetContainer::findByHandle('uploads')->asset('photo.jpg')->get('alt'))->toBeNull();
});

it('rejects an unknown data field', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/assets/uploads/photo.jpg', ['data' => ['atl' => 'typo']])
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'unknown_field']])
        ->assertJsonPath('error.errors.atl.0', 'This field is not in the blueprint.');
});
