<?php

namespace Ppcharlier\StatamicEditorApi\Support;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Statamic\Facades\Site;

final class SiteResolver
{
    /**
     * Resolves the ?site= QUERY param (never the body): absent means the resource's own
     * default site; an unknown handle, or one outside $allowed (the resource's own sites),
     * is a 422.
     */
    public static function resolve(Request $request, ?array $allowed = null): string
    {
        $site = $request->query('site');

        if ($site === null) {
            return self::resolveValue(self::defaultFor($allowed), $allowed, null, $request->user());
        }

        // `?site[]=en&site[]=fr` hands us an array; reject it before string interpolation
        // turns it into an "Array to string conversion" notice.
        if (! is_string($site)) {
            throw self::invalid('The site parameter must be a single handle.');
        }

        return self::resolveValue($site, $allowed, null, $request->user());
    }

    /**
     * Validates an explicit site handle, whatever its source: the query string above, or a
     * request BODY value for the one endpoint that takes the site as payload
     * (POST /entries/{id}/localizations). $message overrides the failure text so each
     * caller keeps its own wording.
     *
     * With a $user, the site must also be one they may access: the Control Panel's site
     * switcher enforces `access {site} site` (SitePolicy) before anything else, so an
     * unreachable site is a 403 here too — after the 422s, so a bad handle stays a bad handle.
     */
    public static function resolveValue(string $site, ?array $allowed = null, ?string $message = null, $user = null): string
    {
        if (! Site::all()->map->handle()->contains($site)) {
            throw self::invalid($message ?? "Unknown site [{$site}].");
        }

        if ($allowed !== null && ! in_array($site, $allowed, true)) {
            throw self::invalid($message ?? "This resource is not available in site [{$site}].");
        }

        if ($user && ! $user->can('view', Site::get($site))) {
            throw new ApiException('forbidden', "Not authorized to access site [{$site}].", 403);
        }

        return $site;
    }

    /**
     * The site an absent ?site= stands for. Normally the global default — but a resource
     * can be scoped to sites that exclude it (a collection available in 'fr' only), and
     * the CP still lets you browse such a resource without picking a site: it falls back
     * to the resource's first site rather than erroring out. Only this implicit case falls
     * back; an explicitly supplied out-of-scope handle still 422s.
     */
    private static function defaultFor(?array $allowed): string
    {
        $default = Site::default()->handle();

        if ($allowed === null || $allowed === [] || in_array($default, $allowed, true)) {
            return $default;
        }

        return (string) array_values($allowed)[0];
    }

    private static function invalid(string $message): ApiException
    {
        return new ApiException('validation_failed', 'The given data was invalid.', 422,
            ['site' => [$message]]);
    }
}
