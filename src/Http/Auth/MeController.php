<?php

namespace Ppcharlier\StatamicEditorApi\Http\Auth;

use Illuminate\Http\Request;

final class MeController
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        return response()->json(['data' => [
            'id' => $user->id(),
            'name' => $user->name(),
            'email' => $user->email(),
            'super' => $user->isSuper(),
            'permissions' => $user->isSuper() ? ['*'] : $user->permissions()->values()->all(),
        ]]);
    }
}
