<?php

use Illuminate\Http\UploadedFile;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsAssetFixtures;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;

uses(BuildsEntryFixtures::class, BuildsAssetFixtures::class);

it('runs the full iOS flow: upload, list, tag, rename, delete', function () {
    $this->makeUploadsContainer();
    $token = $this->makeSuperToken();

    // upload
    $uploaded = $this->withToken($token)
        ->post('/api/editor/v1/assets/uploads', [
            'file' => UploadedFile::fake()->image('Article Hero.png', 200, 100),
            'folder' => 'articles',
        ], ['Accept' => 'application/json'])->assertStatus(201);

    $path = $uploaded->json('data.path');
    expect($path)->toBe('articles/article-hero.png');

    // visible dans la liste du dossier
    $list = $this->withToken($token)->getJson('/api/editor/v1/assets/uploads?folder=articles')->assertOk();
    expect(collect($list->json('data.assets'))->pluck('path'))->toContain($path);

    // métadonnée alt
    $this->withToken($token)
        ->patchJson("/api/editor/v1/assets/uploads/{$path}", ['data' => ['alt' => 'Illustration de une']])
        ->assertOk()
        ->assertJsonPath('data.data.alt', 'Illustration de une');

    // renommage
    $renamed = $this->withToken($token)
        ->patchJson("/api/editor/v1/assets/uploads/{$path}", ['filename' => 'hero-final'])
        ->assertOk();
    $newPath = $renamed->json('data.path');
    expect($newPath)->toBe('articles/hero-final.png')
        ->and($renamed->json('data.data.alt'))->toBe('Illustration de une'); // la méta survit au renommage

    // suppression
    $this->withToken($token)->deleteJson("/api/editor/v1/assets/uploads/{$newPath}")->assertStatus(204);

    $final = $this->withToken($token)->getJson('/api/editor/v1/assets/uploads?folder=articles')->assertOk();
    expect(collect($final->json('data.assets'))->pluck('path'))->not->toContain($newPath);
});
