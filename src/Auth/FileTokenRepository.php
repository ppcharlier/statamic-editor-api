<?php

namespace Ppcharlier\StatamicEditorApi\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Statamic\Facades\YAML;

final class FileTokenRepository implements TokenRepository
{
    public function create(string $userId, string $deviceName): NewToken
    {
        $plainText = Str::random(64);
        $ttlDays = config('statamic.editor-api.auth.token_ttl_days');

        $token = new Token(
            hash: hash('sha256', $plainText),
            userId: $userId,
            name: $deviceName,
            createdAt: CarbonImmutable::now(),
            lastUsedAt: null,
            expiresAt: $ttlDays ? CarbonImmutable::now()->addDays((int) $ttlDays) : null,
        );

        $this->write($token);

        return new NewToken($plainText, $token);
    }

    public function findByPlainText(string $plainText): ?Token
    {
        $hash = hash('sha256', $plainText);
        $path = $this->pathFor($hash);

        try {
            return Token::fromArray($hash, YAML::parse(File::get($path)));
        } catch (\Throwable) {
            return null;
        }
    }

    public function touchLastUsed(Token $token): void
    {
        // Don't resurrect a revoked token
        if (! File::exists($this->pathFor($token->hash))) {
            return;
        }

        $this->write(new Token(
            hash: $token->hash,
            userId: $token->userId,
            name: $token->name,
            createdAt: $token->createdAt,
            lastUsedAt: CarbonImmutable::now(),
            expiresAt: $token->expiresAt,
        ));
    }

    public function revoke(string $hash): void
    {
        File::delete($this->pathFor($hash));
    }

    private function write(Token $token): void
    {
        $storagePath = $this->storagePath();
        File::ensureDirectoryExists($storagePath);

        $destination = $this->pathFor($token->hash);
        $temporary = $destination.'.tmp.'.uniqid();

        File::put($temporary, YAML::dump($token->toArray()));
        rename($temporary, $destination);
    }

    private function pathFor(string $hash): string
    {
        return $this->storagePath().'/'.$hash.'.yaml';
    }

    private function storagePath(): string
    {
        return rtrim(config('statamic.editor-api.storage_path'), '/');
    }
}
