<?php

namespace Ppcharlier\StatamicEditorApi\Http\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Statamic\Facades\User;

final class TokensController
{
    public function __construct(private readonly TokenRepository $tokens)
    {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::findByEmail($data['email']);

        if (! $user || ! Hash::check($data['password'], $user->password())) {
            throw new ApiException('invalid_credentials', 'Invalid email or password.', 401);
        }

        $new = $this->tokens->create($user->id(), $data['device_name']);

        return response()->json(['data' => [
            'token' => $new->plainText,
            'expires_at' => $new->token->expiresAt?->toIso8601String(),
        ]], 201);
    }

    public function destroyCurrent(Request $request)
    {
        $token = $request->attributes->get('editor-api.token');

        $this->tokens->revoke($token->hash);

        return response()->noContent();
    }
}
