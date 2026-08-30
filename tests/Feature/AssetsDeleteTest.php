<?php

use Illuminate\Http\UploadedFile;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsAssetFixtures;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\AssetContainer;

uses(BuildsEntryFixtures::class, BuildsAssetFixtures::class);

beforeEach(function () {
    $this->container = $this->makeUploadsContainer();
    $this->token = $this->makeSuperToken();
    $this->container->makeAsset('a-supprimer.jpg')->upload(UploadedFile::fake()->image('a-supprimer.jpg'));
});

it('deletes an asset', function () {
    $this->withToken($this->token)
        ->deleteJson('/api/editor/v1/assets/uploads/a-supprimer.jpg')
        ->assertStatus(204);

    expect(AssetContainer::findByHandle('uploads')->asset('a-supprimer.jpg'))->toBeNull();
});

it('404s an unknown path', function () {
    $this->withToken($this->token)
        ->deleteJson('/api/editor/v1/assets/uploads/inexistant.jpg')
        ->assertStatus(404);
});

it('403s without the delete permission', function () {
    $token = $this->makeTokenWithPermissions(['view uploads assets', 'edit uploads assets']);

    $this->withToken($token)
        ->deleteJson('/api/editor/v1/assets/uploads/a-supprimer.jpg')
        ->assertStatus(403);

    expect(AssetContainer::findByHandle('uploads')->asset('a-supprimer.jpg'))->not->toBeNull();
});
