<?php

namespace Ppcharlier\StatamicEditorApi\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Stores editor tokens in Sanctum's personal_access_tokens table — for sites
 * with Eloquent users and a shared database (multi-server, central revocation).
 * Plain-text tokens carry no "id|" prefix, so Sanctum's findToken() resolves
 * them by their sha256 hash, exactly like the file driver's lookup.
 */
final class SanctumTokenRepository implements TokenRepository
{
    public function create(string $userId, string $deviceName): NewToken
    {
        $plainText = Str::random(64);
        $ttlDays = config('statamic.editor-api.auth.token_ttl_days');
        $expiresAt = $ttlDays !== null ? CarbonImmutable::now()->addDays((int) $ttlDays) : null;

        $model = PersonalAccessToken::forceCreate([
            'tokenable_type' => $this->userModel(),
            'tokenable_id' => $userId,
            'name' => $deviceName,
            'token' => hash('sha256', $plainText),
            'abilities' => ['editor-api'],
            'expires_at' => $expiresAt,
        ]);

        return new NewToken($plainText, $this->toToken($model));
    }

    public function findByPlainText(string $plainText): ?Token
    {
        $model = PersonalAccessToken::findToken($plainText);

        return $model ? $this->toToken($model) : null;
    }

    public function touchLastUsed(Token $token): void
    {
        // Same minute-precision throttle as the file driver.
        if ($token->lastUsedAt?->gt(CarbonImmutable::now()->subMinute())) {
            return;
        }

        PersonalAccessToken::where('token', $token->hash)->update(['last_used_at' => now()]);
    }

    public function revoke(string $hash): void
    {
        PersonalAccessToken::where('token', $hash)->delete();
    }

    private function toToken(PersonalAccessToken $model): Token
    {
        return new Token(
            hash: $model->token,
            userId: (string) $model->tokenable_id,
            name: $model->name,
            createdAt: CarbonImmutable::parse($model->created_at),
            lastUsedAt: $model->last_used_at ? CarbonImmutable::parse($model->last_used_at) : null,
            expiresAt: $model->expires_at ? CarbonImmutable::parse($model->expires_at) : null,
        );
    }

    private function userModel(): string
    {
        $guard = config('statamic.users.guards.cp', 'web');
        $provider = config("auth.guards.{$guard}.provider", 'users');

        return config("auth.providers.{$provider}.model");
    }
}
