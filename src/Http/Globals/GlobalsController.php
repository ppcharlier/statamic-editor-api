<?php

namespace Ppcharlier\StatamicEditorApi\Http\Globals;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Permissions\PermissionMap;
use Ppcharlier\StatamicEditorApi\Support\ResourceConfig;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;
use Ppcharlier\StatamicEditorApi\Support\SiteGuard;
use Ppcharlier\StatamicEditorApi\Support\UnknownFields;
use Statamic\Facades\GlobalSet;

final class GlobalsController
{
    public function index(Request $request)
    {
        SiteGuard::check($request);

        $user = $request->user();

        $sets = GlobalSet::all()
            ->filter(fn ($set) => ResourceConfig::enabled('globals', $set->handle()))
            ->filter(fn ($set) => $user->isSuper() || $user->hasPermission(PermissionMap::globals($set->handle())))
            ->map(fn ($set) => ['handle' => $set->handle(), 'title' => $set->title()])
            ->values()->all();

        return response()->json(['data' => $sets]);
    }

    public function show(Request $request, $variables)
    {
        $this->guarded($request, $variables);

        return response()->json(['data' => GlobalResource::toArray($variables)]);
    }

    public function update(Request $request, $variables)
    {
        $this->guarded($request, $variables);

        $payload = $request->validate(['data' => ['required', 'array']]);

        $blueprint = $variables->blueprint();
        UnknownFields::reject($payload['data'], $blueprint->fields()->all()->keys()->all());

        $fields = $blueprint->fields()->addValues($payload['data']);
        $fields->validator()->validate();

        $variables->data($fields->process()->values());
        $variables->save();

        return response()->json(['data' => GlobalResource::toArray($variables)]);
    }

    private function guarded(Request $request, $variables): void
    {
        SiteGuard::check($request);
        ResourceGate::global($handle = $variables->globalSet()->handle());
        Guard::check($request->user(), PermissionMap::globals($handle));
    }
}
