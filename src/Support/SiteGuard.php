<?php

namespace Ppcharlier\StatamicEditorApi\Support;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Statamic\Facades\Site;

final class SiteGuard
{
    /**
     * Multi-site is not implemented in v1 (see spec §3.6): the contract reserves an
     * optional `?site=` param on entries/blueprints/revisions endpoints without acting
     * on it, so a client asking for a non-default site gets an explicit 422 instead of
     * being silently served the default site's data.
     */
    public static function check(Request $request): void
    {
        $site = $request->query('site');

        if ($site !== null && $site !== Site::default()->handle()) {
            throw new ApiException(
                'not_supported',
                'Multi-site is not supported yet. Omit ?site= or pass the default site handle.',
                422,
                ['site' => ['Only the default site is supported.']],
            );
        }
    }
}
