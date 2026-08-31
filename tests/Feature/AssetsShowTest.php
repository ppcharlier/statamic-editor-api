<?php

use Illuminate\Http\UploadedFile;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsAssetFixtures;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blueprint;

uses(BuildsEntryFixtures::class, BuildsAssetFixtures::class);

beforeEach(function () {
    $this->container = $this->makeUploadsContainer();
    $this->token = $this->makeSuperToken();

    Blueprint::make('uploads')
        ->setNamespace('assets')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'alt', 'field' => ['type' => 'text', 'display' => 'Alt']],
        ]]]]]])
        ->save();

    $this->container->makeAsset('vacances/plage.jpg')->upload(UploadedFile::fake()->image('plage.jpg'));
    $this->container->asset('vacances/plage.jpg')->set('alt', 'Une plage au soleil')->save();
});

it('shows an asset with all resource keys', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/assets/uploads/vacances/plage.jpg')
        ->assertOk();

    $asset = $response->json('data');
    expect($asset)->toHaveKeys(['id', 'path', 'url', 'filename', 'basename', 'extension', 'folder', 'size', 'mime_type', 'is_image', 'last_modified', 'data'])
        ->and($asset['id'])->toBe('uploads::vacances/plage.jpg')
        ->and($asset['path'])->toBe('vacances/plage.jpg')
        ->and($asset['folder'])->toBe('vacances')
        ->and($asset['is_image'])->toBeTrue()
        ->and($asset['url'])->not->toBeNull()
        ->and($asset['data']['alt'])->toBe('Une plage au soleil');
});

it('404s an unknown asset path', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/assets/uploads/inexistant.jpg')
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'not_found']]);
});

it('rejects a percent-encoded traversal in the path', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/assets/uploads/..%2fsecrets')
        ->assertStatus(422);
});

it('403s without the view permission', function () {
    $token = $this->makeTokenWithPermissions(['upload uploads assets']);

    $this->withToken($token)
        ->getJson('/api/editor/v1/assets/uploads/vacances/plage.jpg')
        ->assertStatus(403)
        ->assertJson(['error' => ['code' => 'forbidden']]);
});
