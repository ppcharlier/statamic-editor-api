<?php

use Illuminate\Http\UploadedFile;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsAssetFixtures;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\AssetContainer;

uses(BuildsEntryFixtures::class, BuildsAssetFixtures::class);

beforeEach(function () {
    $this->container = $this->makeUploadsContainer();
    $this->token = $this->makeSuperToken();
});

it('uploads a file and returns the asset resource', function () {
    $response = $this->withToken($this->token)
        ->post('/api/editor/v1/assets/uploads', [
            'file' => UploadedFile::fake()->image('Ma Photo.JPG', 100, 80),
        ], ['Accept' => 'application/json'])
        ->assertStatus(201);

    // nom sanitisé (minuscules, espaces remplacés)
    expect($response->json('data.path'))->toBe('ma-photo.jpg')
        ->and($response->json('data.url'))->not->toBeNull()
        ->and($response->json('data.id'))->toBe('uploads::ma-photo.jpg');

    expect(AssetContainer::findByHandle('uploads')->asset('ma-photo.jpg'))->not->toBeNull();
});

it('uploads into a folder', function () {
    $response = $this->withToken($this->token)
        ->post('/api/editor/v1/assets/uploads', [
            'file' => UploadedFile::fake()->image('plage.jpg'),
            'folder' => 'vacances',
        ], ['Accept' => 'application/json'])
        ->assertStatus(201);

    expect($response->json('data.path'))->toBe('vacances/plage.jpg')
        ->and($response->json('data.folder'))->toBe('vacances');
});

it('suffixes on collision instead of overwriting', function () {
    $this->container->makeAsset('doc.jpg')->upload(UploadedFile::fake()->image('doc.jpg'));

    $response = $this->withToken($this->token)
        ->post('/api/editor/v1/assets/uploads', [
            'file' => UploadedFile::fake()->image('doc.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertStatus(201);

    expect($response->json('data.path'))->not->toBe('doc.jpg')
        ->and($response->json('data.path'))->toStartWith('doc-');

    // l'original n'a pas bougé
    expect(AssetContainer::findByHandle('uploads')->asset('doc.jpg'))->not->toBeNull();
});

it('rejects disallowed file types', function () {
    $this->withToken($this->token)
        ->post('/api/editor/v1/assets/uploads', [
            'file' => UploadedFile::fake()->create('malware.php', 10),
        ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['file']]]);
});

it('applies container validation rules', function () {
    tap(AssetContainer::findByHandle('uploads')->validationRules(['max:1']))->save(); // 1 Ko max

    $this->withToken($this->token)
        ->post('/api/editor/v1/assets/uploads', [
            'file' => UploadedFile::fake()->create('gros.jpg', 500), // 500 Ko
        ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']]);
});

it('rejects folder traversal', function () {
    $this->withToken($this->token)
        ->post('/api/editor/v1/assets/uploads', [
            'file' => UploadedFile::fake()->image('x.jpg'),
            'folder' => '../secrets',
        ], ['Accept' => 'application/json'])
        ->assertStatus(422);
});

it('403s without the upload permission', function () {
    $token = $this->makeTokenWithPermissions(['view uploads assets']);

    $this->withToken($token)
        ->post('/api/editor/v1/assets/uploads', [
            'file' => UploadedFile::fake()->image('x.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertStatus(403);
});
