<?php

namespace Ppcharlier\StatamicEditorApi\Http\Auth;

use Illuminate\Http\Request;

final class MeController
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        return response()->json(['data' => [
            'id' => (string) $user->id(), // Eloquent users have integer keys; the contract is a string id.
            'name' => $user->name(),
            'email' => $user->email(),
            'avatar' => $user->avatar(),
            'super' => $user->isSuper(),
            'permissions' => $user->isSuper() ? ['*'] : $user->permissions()->values()->all(),
        ]]);
    }
}
