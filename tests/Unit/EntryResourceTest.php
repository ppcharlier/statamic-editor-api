<?php

use Ppcharlier\StatamicEditorApi\Http\Entries\EntryResource;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Entry;

uses(BuildsEntryFixtures::class);

it('uses the working copy date when newer than the file for effectiveLastModified', function () {
    $this->makeArticlesCollection(withRevisions: true);

    $entry = tap(Entry::make()->collection('articles')->slug('wc')->date('2026-01-01')
        ->data(['title' => 'V1'])->published(true))->save();

    $entry = Entry::find($entry->id());
    $before = EntryResource::effectiveLastModified($entry);

    $this->travel(5)->minutes();
    $entry->makeWorkingCopy()->save();
    $entry = Entry::find($entry->id());

    $after = EntryResource::effectiveLastModified($entry);

    expect($after->gt($before))->toBeTrue()
        ->and(EntryResource::summary($entry)['has_unpublished_changes'])->toBeTrue();
});

it('falls back to the file date without a working copy', function () {
    $this->makeArticlesCollection();

    $entry = tap(Entry::make()->collection('articles')->slug('nowc')->date('2026-01-01')
        ->data(['title' => 'V1'])->published(true))->save();

    $entry = Entry::find($entry->id());

    expect(EntryResource::effectiveLastModified($entry))->not->toBeNull()
        ->and(EntryResource::summary($entry)['has_unpublished_changes'])->toBeFalse();
});
