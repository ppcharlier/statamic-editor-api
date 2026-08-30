<?php

use Illuminate\Http\UploadedFile;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsAssetFixtures;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;

uses(BuildsEntryFixtures::class, BuildsAssetFixtures::class);

beforeEach(function () {
    $this->container = $this->makeUploadsContainer();
    $this->token = $this->makeSuperToken();

    // deux assets à la racine, un dans un dossier
    $this->container->makeAsset('photo-a.jpg')->upload(UploadedFile::fake()->image('photo-a.jpg'));
    $this->container->makeAsset('photo-b.jpg')->upload(UploadedFile::fake()->image('photo-b.jpg'));
    $this->container->makeAsset('vacances/plage.jpg')->upload(UploadedFile::fake()->image('plage.jpg'));
});

it('lists root assets and folders non-recursively', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/assets/uploads')
        ->assertOk();

    $paths = collect($response->json('data.assets'))->pluck('path');
    expect($paths)->toContain('photo-a.jpg', 'photo-b.jpg')
        ->not->toContain('vacances/plage.jpg')
        ->and($response->json('data.folders'))->toContain('vacances')
        ->and($response->json('meta.total'))->toBe(2);

    $asset = collect($response->json('data.assets'))->firstWhere('path', 'photo-a.jpg');
    expect($asset)->toHaveKeys(['id', 'path', 'url', 'filename', 'basename', 'extension', 'folder', 'size', 'mime_type', 'is_image', 'last_modified', 'data'])
        ->and($asset['id'])->toBe('uploads::photo-a.jpg')
        ->and($asset['is_image'])->toBeTrue()
        ->and($asset['url'])->not->toBeNull();
});

it('navigates into a folder', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/assets/uploads?folder=vacances')
        ->assertOk();

    expect(collect($response->json('data.assets'))->pluck('path'))->toContain('vacances/plage.jpg')
        ->and($response->json('meta.total'))->toBe(1);
});

it('paginates assets', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/assets/uploads?per_page=1&page=2')
        ->assertOk();

    expect($response->json('data.assets'))->toHaveCount(1)
        ->and($response->json('meta.per_page'))->toBe(1)
        ->and($response->json('meta.total'))->toBe(2);
});

it('returns a null url for an asset in a private container', function () {
    // A container is "private" when its disk's filesystem config has no 'url' key
    // (see Statamic\Assets\AssetContainer::accessible()/private()) — Asset::url()
    // then short-circuits to null instead of assembling a URL.
    config()->set('filesystems.disks.private_disk', [
        'driver' => 'local',
        'root' => storage_path('app/private-test'),
    ]);
    \Illuminate\Support\Facades\Storage::fake('private_disk', config('filesystems.disks.private_disk'));

    $private = tap(\Statamic\Facades\AssetContainer::make('private')->title('Private')->disk('private_disk'))->save();
    $private->makeAsset('secret.jpg')->upload(UploadedFile::fake()->image('secret.jpg'));

    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/assets/private')
        ->assertOk();

    $asset = collect($response->json('data.assets'))->firstWhere('path', 'secret.jpg');
    expect($asset)->not->toBeNull()
        ->and($asset['url'])->toBeNull();
});

it('rejects a double-encoded folder traversal', function () {
    // %252e%252e survives Laravel's one URL-decode pass as the literal string "%2e%2e",
    // which itself decodes to ".." — this is the vector that a single-decode guard misses.
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/assets/uploads?folder=%252e%252e')
        ->assertStatus(422);
});

it('404s an unknown container with the standard envelope', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/assets/nope')
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'not_found']]);
});

it('404s a container excluded by the whitelist', function () {
    config()->set('statamic.editor-api.resources.assets', ['autre']);

    $this->withToken($this->token)
        ->getJson('/api/editor/v1/assets/uploads')
        ->assertStatus(404);
});

it('403s without the view permission', function () {
    $token = $this->makeTokenWithPermissions(['upload uploads assets']);

    $this->withToken($token)
        ->getJson('/api/editor/v1/assets/uploads')
        ->assertStatus(403)
        ->assertJson(['error' => ['code' => 'forbidden']]);
});
