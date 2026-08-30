<?php

namespace Ppcharlier\StatamicEditorApi\Auth;

interface TokenRepository
{
    public function create(string $userId, string $deviceName): NewToken;

    public function findByPlainText(string $plainText): ?Token;

    public function touchLastUsed(Token $token): void;

    public function revoke(string $hash): void;
}
