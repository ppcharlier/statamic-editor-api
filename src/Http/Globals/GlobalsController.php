<?php

namespace Ppcharlier\StatamicEditorApi\Http\Globals;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Permissions\PermissionMap;
use Ppcharlier\StatamicEditorApi\Support\ResourceConfig;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;
use Ppcharlier\StatamicEditorApi\Support\SiteResolver;
use Ppcharlier\StatamicEditorApi\Support\UnknownFields;
use Statamic\Facades\GlobalSet;

final class GlobalsController
{
    public function index(Request $request)
    {
        SiteResolver::resolve($request);

        $user = $request->user();

        $sets = GlobalSet::all()
            ->filter(fn ($set) => ResourceConfig::enabled('globals', $set->handle()))
            ->filter(fn ($set) => Guard::allows($user, PermissionMap::globals($set->handle())))
            ->map(fn ($set) => ['handle' => $set->handle(), 'title' => $set->title()])
            ->values()->all();

        return response()->json(['data' => $sets]);
    }

    public function show(Request $request, $global)
    {
        return response()->json(['data' => GlobalResource::toArray($this->guarded($request, $global))]);
    }

    public function update(Request $request, $global)
    {
        $variables = $this->guarded($request, $global);

        $payload = $request->validate(['data' => ['required', 'array']]);

        $blueprint = $variables->blueprint();
        UnknownFields::reject($payload['data'], $blueprint->fields()->all()->keys()->all());

        $fields = $blueprint->fields()->addValues($payload['data']);
        $fields->validator()->validate();

        $variables->data($fields->process()->values());
        $variables->save();

        return response()->json(['data' => GlobalResource::toArray($variables)]);
    }

    /**
     * Resolves the site and the localization this request operates on, without trusting
     * whatever the `{global}` route binder handed us.
     *
     * The vendor binder (RouteServiceProvider::bindGlobalSets) picks the localization from
     * `request()->input('site')` — which reads the request BODY as well as the query string,
     * body winning — and only binds at all when the route looks like a CP/API route (a
     * `route_prefix` outside `api/` makes it hand back the raw handle string instead).
     * Neither matches this API's contract, where `?site=` is a QUERY parameter and nothing
     * else. So we take the set back out of whatever we were given, resolve the site ourselves
     * through SiteResolver (scoped to the set's own sites) and re-derive the localization.
     */
    private function guarded(Request $request, $global)
    {
        $set = $this->resolveSet($global);

        $site = SiteResolver::resolve($request, $set->sites()->all());
        ResourceGate::global($handle = $set->handle());
        Guard::check($request->user(), PermissionMap::globals($handle));

        if (! $variables = $set->in($site)) {
            throw new ApiException('not_found', 'Not found.', 404);
        }

        return $variables;
    }

    private function resolveSet($global)
    {
        // A bound `{global}` is a Variables (a localization) that knows its set; an unbound
        // one is the raw handle string straight out of the URL.
        $set = is_string($global)
            ? GlobalSet::findByHandle($global)
            : (method_exists($global, 'globalSet') ? $global->globalSet() : $global);

        if (! $set) {
            throw new ApiException('not_found', 'Not found.', 404);
        }

        return $set;
    }
}
