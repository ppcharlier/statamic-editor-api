<?php

namespace Ppcharlier\StatamicEditorApi\Support;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Statamic\Facades\Site;

final class SiteResolver
{
    /**
     * Resolves the ?site= param: absent means the default site; an unknown
     * handle, or one outside $allowed (the resource's own sites), is a 422.
     */
    public static function resolve(Request $request, ?array $allowed = null): string
    {
        $site = $request->query('site') ?? Site::default()->handle();

        if (! Site::all()->map->handle()->contains($site)) {
            throw new ApiException('validation_failed', 'The given data was invalid.', 422,
                ['site' => ["Unknown site [{$site}]."]]);
        }

        if ($allowed !== null && ! in_array($site, $allowed, true)) {
            throw new ApiException('validation_failed', 'The given data was invalid.', 422,
                ['site' => ["This resource is not available in site [{$site}]."]]);
        }

        return $site;
    }
}
