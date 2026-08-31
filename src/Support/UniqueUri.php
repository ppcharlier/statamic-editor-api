<?php

namespace Ppcharlier\StatamicEditorApi\Support;

use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Statamic\Contracts\Routing\UrlBuilder;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;

final class UniqueUri
{
    /**
     * Mirrors the CP's validateUniqueUri (non-structured path): build the
     * would-be URI and 422 when another entry already owns it. Structured
     * collections are skipped — v1 does not manage tree placement, which is
     * what determines their URIs.
     */
    public static function guard($entry): void
    {
        if ($entry->collection()->hasStructure() || ! $entry->route()) {
            return;
        }

        $uri = app(UrlBuilder::class)
            ->content($entry)
            ->merge(['id' => $entry->id() ?? Stache::generateId()])
            ->build($entry->route());

        if (! $uri) {
            return;
        }

        $existing = Entry::findByUri($uri, $entry->locale());

        if ($existing && $existing->id() !== $entry->id()) {
            throw new ApiException('uri_taken', 'This slug produces a URI already used by another entry.', 422,
                ['slug' => ['This slug produces a URI already used by another entry.']]);
        }
    }
}
