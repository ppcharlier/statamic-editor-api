<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

uses(BuildsEntryFixtures::class);

// Guards against Laravel's global TrimStrings / ConvertEmptyStringsToNull middleware
// (application-level, not Statamic) mangling editor API payloads. The CP's own web
// forms escape this because they serialize rich-text fields (bard) as a single JSON
// string; our API sends real nested JSON, so those middleware used to walk every leaf
// and rewrite it before Statamic ever saw it — trimming whitespace (including NBSP) off
// every bard text node, and silently turning "" into null. See ServiceProvider::bootAddon().
beforeEach(function () {
    Collection::make('verbatim')->title('Verbatim')->dated(true)->save();

    Blueprint::make('verbatim')
        ->setNamespace('collections.verbatim')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Titre', 'validate' => ['required']]],
            ['handle' => 'note', 'field' => ['type' => 'text', 'display' => 'Note']],
            ['handle' => 'content', 'field' => ['type' => 'bard', 'display' => 'Contenu']],
        ]]]]]])
        ->save();

    $this->token = $this->makeSuperToken();
});

function verbatimBardContent(string $suffix = ''): array
{
    $nbsp = "\u{00A0}";

    return [
        [
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => '  avant espaces  '.$suffix],
                ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => ' gras avec espaces '],
                ['type' => 'text', 'text' => 'nbsp'.$nbsp.'interne'],
                ['type' => 'text', 'text' => '  double  espace  interne  '],
            ],
        ],
    ];
}

it('preserves a bard content payload verbatim through POST and GET, including edge whitespace and internal NBSP', function () {
    $content = verbatimBardContent();

    $create = $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/verbatim/entries', [
            'slug' => 'verbatim-post', 'date' => '2026-08-31',
            'data' => ['title' => 'Verbatim', 'note' => '', 'content' => $content],
        ])->assertStatus(201);

    $id = $create->json('data.id');

    $entry = Entry::find($id);
    expect($entry->value('content'))->toBe($content)
        ->and($entry->value('note'))->toBe('');

    $show = $this->withToken($this->token)->getJson('/api/editor/v1/entries/'.$id)->assertOk();

    expect($show->json('data.data.content'))->toBe($content)
        ->and($show->json('data.data.note'))->toBe('')
        ->and($show->json('data.data.note'))->not->toBeNull();
});

it('preserves a bard content payload verbatim through PATCH and GET', function () {
    $entry = tap(
        Entry::make()->collection('verbatim')->slug('verbatim-patch')->date('2026-08-31')
            ->data(['title' => 'Initial', 'note' => 'x', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'placeholder']]]]])
    )->save();

    $content = verbatimBardContent(' patch');

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$entry->id(), [
            'data' => ['title' => 'Modifié', 'note' => '', 'content' => $content],
        ])->assertOk();

    $show = $this->withToken($this->token)->getJson('/api/editor/v1/entries/'.$entry->id())->assertOk();

    expect($show->json('data.data.content'))->toBe($content)
        ->and($show->json('data.data.note'))->toBe('')
        ->and($show->json('data.data.note'))->not->toBeNull();
});

it('still rejects an empty required title even though "" is no longer converted to null', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/verbatim/entries', [
            'slug' => 'verbatim-required', 'date' => '2026-08-31',
            'data' => ['title' => '', 'note' => 'x', 'content' => []],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['title']]]);
});
