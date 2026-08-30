<?php

namespace Ppcharlier\StatamicEditorApi\Auth;

use Closure;
use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Statamic\Facades\User;

final class AuthenticateEditorApi
{
    public function __construct(private readonly TokenRepository $tokens)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $plainText = $request->bearerToken();

        if (! $plainText) {
            throw new ApiException('unauthenticated', 'Missing bearer token.', 401);
        }

        $token = $this->tokens->findByPlainText($plainText);

        if (! $token) {
            throw new ApiException('unauthenticated', 'Invalid token.', 401);
        }

        if ($token->isExpired()) {
            throw new ApiException('token_expired', 'This token has expired. Please sign in again.', 401);
        }

        $user = User::find($token->userId);

        if (! $user) {
            throw new ApiException('unauthenticated', 'Invalid token.', 401);
        }

        $this->tokens->touchLastUsed($token);

        $request->attributes->set('editor-api.token', $token);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
