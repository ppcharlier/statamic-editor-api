<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Entry;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->makeArticlesCollection(withRevisions: true);
    $this->token = $this->makeSuperToken();
});

function entryWithTwoPublishedVersions($test)
{
    // V1 créée puis publiée, puis V2 en brouillon puis publiée → 2+ révisions
    $create = $test->withToken($test->token)->postJson('/api/editor/v1/collections/articles/entries', [
        'slug' => 'histo', 'date' => '2026-01-01', 'message' => 'Création',
        'data' => ['title' => 'V1'],
    ])->assertStatus(201);
    $id = $create->json('data.id');

    // Les révisions sont keyées par timestamp (seconde) : espacer chaque étape
    // pour éviter qu'une révision en écrase une autre créée dans la même seconde.
    $test->travel(1)->minutes();
    $test->withToken($test->token)->postJson("/api/editor/v1/entries/{$id}/published", ['message' => 'Publie V1'])->assertOk();
    $test->travel(1)->minutes();
    $test->withToken($test->token)->patchJson("/api/editor/v1/entries/{$id}", ['data' => ['title' => 'V2']])->assertOk();
    $test->withToken($test->token)->postJson("/api/editor/v1/entries/{$id}/published", ['message' => 'Publie V2'])->assertOk();

    return $id;
}

it('lists revisions newest first with author and message', function () {
    $id = entryWithTwoPublishedVersions($this);

    $response = $this->withToken($this->token)
        ->getJson("/api/editor/v1/entries/{$id}/revisions")
        ->assertOk();

    $revisions = $response->json('data');
    expect(count($revisions))->toBeGreaterThanOrEqual(2)
        ->and($revisions[0]['message'])->toBe('Publie V2')
        ->and($revisions[0]['action'])->toBe('publish')
        ->and($revisions[0]['user'])->toHaveKeys(['id', 'name', 'email'])
        ->and($revisions[0]['date'])->toBeString();
});

it('restores a revision into the working copy without publishing', function () {
    $id = entryWithTwoPublishedVersions($this);

    $revisions = $this->withToken($this->token)->getJson("/api/editor/v1/entries/{$id}/revisions")->json('data');
    $v1 = collect($revisions)->firstWhere('message', 'Publie V1');

    $this->withToken($this->token)
        ->postJson("/api/editor/v1/entries/{$id}/revisions/{$v1['id']}/restore")
        ->assertOk()
        ->assertJsonPath('data.has_unpublished_changes', true)
        ->assertJsonPath('data.data.title', 'V1');

    // le live n'a pas bougé
    expect(Entry::find($id)->value('title'))->toBe('V2');
});

it('404s an unknown revision reference', function () {
    $id = entryWithTwoPublishedVersions($this);

    $this->withToken($this->token)
        ->postJson("/api/editor/v1/entries/{$id}/revisions/999999999/restore")
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'revision_not_found']]);
});

it('422s revision operations on a collection without revisions', function () {
    config()->set('statamic.revisions.enabled', true); // global on…
    tap(\Statamic\Facades\Collection::findByHandle('articles')->revisionsEnabled(false))->save(); // …mais pas la collection

    $entry = tap(Entry::make()->collection('articles')->slug('sans')->date('2026-01-01')
        ->data(['title' => 'Sans révisions'])->published(true))->save();

    $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$entry->id().'/revisions')
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'revisions_disabled']]);
});

it('403s restore without the publish permission', function () {
    $id = entryWithTwoPublishedVersions($this);
    $token = $this->makeTokenWithPermissions(['view articles entries', 'edit articles entries']);

    $revisions = $this->withToken($this->token)->getJson("/api/editor/v1/entries/{$id}/revisions")->json('data');

    $this->withToken($token)
        ->postJson("/api/editor/v1/entries/{$id}/revisions/{$revisions[0]['id']}/restore")
        ->assertStatus(403);
});
