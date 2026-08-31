<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->collection = $this->makeArticlesCollection(withRevisions: true);
    $this->token = $this->makeSuperToken();

    $this->entry = tap(
        Entry::make()->collection('articles')->slug('premier')->date('2026-01-01')
            ->data(['title' => 'Version publiée', 'body' => 'Original'])->published(true)
    )->save();
});

it('shows an entry with its data', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertOk();

    expect($response->json('data.data.title'))->toBe('Version publiée')
        ->and($response->json('data.blueprint'))->toBe('article')
        ->and($response->json('data.has_unpublished_changes'))->toBeFalse();
});

it('returns working copy values when one exists', function () {
    $wc = $this->entry->makeWorkingCopy();
    $wc->attributes(array_merge($wc->attributes(), ['data' => array_merge($wc->attributes()['data'] ?? [], ['title' => 'Brouillon en cours'])]));
    $wc->save();

    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertOk();

    expect($response->json('data.data.title'))->toBe('Brouillon en cours')
        ->and($response->json('data.has_unpublished_changes'))->toBeTrue()
        ->and($response->json('data.published'))->toBeTrue();
});

it('404s an unknown id', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/definitely-not-an-id')
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'not_found']]);
});

it('403s without view permission on the entry collection', function () {
    $this->withToken($this->makeTokenWithPermissions(['edit articles entries']))
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertStatus(403);
});

it('404s an entry of a collection excluded by the whitelist', function () {
    config()->set('statamic.editor-api.resources.collections', ['pages']);

    $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertStatus(404);
});

it('never leaks internal bookkeeping keys into data', function () {
    $draft = tap(Entry::make()->collection('articles')->slug('brouillon')->date('2026-01-01')
        ->data(['title' => 'Brouillon'])->published(false))->save();

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$draft->id(), ['data' => ['title' => 'Modifié']])
        ->assertOk();

    // Confirms the bookkeeping key really is on the entry (updateLastModified writes it
    // straight into data), so filtering it out of the response is what's under test here.
    expect(Entry::find($draft->id())->get('updated_at'))->not->toBeNull();

    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$draft->id())
        ->assertOk();

    expect($response->json('data.data'))->not->toHaveKeys(['updated_by', 'updated_at', 'slug', 'date']);
});

it('locks the mono-site localizations shape to just itself', function () {
    // Regression test for the EntryResource::detail() hang fixed at commit 0c05202:
    // mono-site must short-circuit through EntryResource::localizations() without ever
    // calling root()/descendants(), returning the entry as its own sole localization.
    // This is the branch that protects the whole (mono-site) Feature suite from that hang.
    //
    // A companion regression test constructing an actual persisted cyclic origin chain
    // (multi-site) was attempted and parked: a genuinely cyclic origin, once persisted,
    // already breaks vendor Statamic itself before any of our code runs — Statamic\Data\
    // HasOrigin::value()/keys()/values() (vendor/statamic/cms/src/Data/HasOrigin.php)
    // unconditionally recurse through origin() on every read, so any entry with a cyclic
    // origin chain crashes on the very first field access, regardless of our fix. That
    // makes an HTTP-level "200 with well-formed localizations" test for a live cyclic
    // chain infeasible — Statamic cannot deliver that response for such an entry at all.
    // The safeRoot()/localizations() guard added in 0c05202 still matters (it protects the
    // specific graph shape seen in the original RevisionsTest hang — see task-3-report.md
    // for the A/B evidence), it's just that a literal mutual-origin cycle isn't a
    // reachable-via-HTTP scenario to assert 200 on.
    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertOk();

    expect($response->json('data.site'))->toBe(Site::default()->handle())
        ->and($response->json('data.localizations'))->toBe([
            ['site' => Site::default()->handle(), 'id' => $this->entry->id()],
        ]);
});

it('round-trips GET data straight into PATCH data', function () {
    $show = $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertOk();

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), [
            'data' => $show->json('data.data'),
        ])->assertOk();
});
