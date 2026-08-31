<?php

namespace Ppcharlier\StatamicEditorApi\Http\Terms;

use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Statamic\Facades\Site;
use Statamic\Facades\Term;

trait ResolvesTerms
{
    private function findTerm($taxonomy, string $slug, ?string $site = null)
    {
        $term = Term::find($taxonomy->handle().'::'.$slug);

        if (! $term) {
            throw new ApiException('not_found', 'Term not found.', 404);
        }

        return $term->in($site ?? Site::default()->handle());
    }
}
