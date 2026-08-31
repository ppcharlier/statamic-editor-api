<?php

use Ppcharlier\StatamicEditorApi\Auth\FileTokenRepository;
use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;

beforeEach(function () {
    $this->repo = app(TokenRepository::class);
});

it('resolves the file implementation from the container', function () {
    expect($this->repo)->toBeInstanceOf(FileTokenRepository::class);
});

it('creates a token and finds it back by plain text', function () {
    $new = $this->repo->create('user-1', 'iPhone de Pierre');

    expect(strlen($new->plainText))->toBeGreaterThanOrEqual(48);

    $found = $this->repo->findByPlainText($new->plainText);

    expect($found)->not->toBeNull()
        ->and($found->userId)->toBe('user-1')
        ->and($found->name)->toBe('iPhone de Pierre')
        ->and($found->hash)->toBe(hash('sha256', $new->plainText));
});

it('never stores the plain text on disk', function () {
    $new = $this->repo->create('user-1', 'iPhone');

    $dir = config('statamic.editor-api.storage_path');
    $contents = collect(glob($dir.'/*.yaml'))->map(fn ($f) => file_get_contents($f))->implode("\n");

    expect($contents)->not->toContain($new->plainText);
});

it('returns null for an unknown token', function () {
    expect($this->repo->findByPlainText('nope'))->toBeNull();
});

it('applies the configured ttl', function () {
    config()->set('statamic.editor-api.auth.token_ttl_days', 1);

    $new = $this->repo->create('user-1', 'iPhone');

    expect($new->token->expiresAt->isSameDay(now()->addDay()))->toBeTrue()
        ->and($new->token->isExpired())->toBeFalse();

    $this->travel(2)->days();
    expect($this->repo->findByPlainText($new->plainText)->isExpired())->toBeTrue();
});

it('supports tokens without expiration', function () {
    config()->set('statamic.editor-api.auth.token_ttl_days', null);

    expect($this->repo->create('user-1', 'iPhone')->token->expiresAt)->toBeNull();
});

it('touches last_used_at', function () {
    $new = $this->repo->create('user-1', 'iPhone');
    expect($new->token->lastUsedAt)->toBeNull();

    $this->repo->touchLastUsed($new->token);

    expect($this->repo->findByPlainText($new->plainText)->lastUsedAt)->not->toBeNull();
});

it('revokes a token', function () {
    $new = $this->repo->create('user-1', 'iPhone');

    $this->repo->revoke($new->token->hash);

    expect($this->repo->findByPlainText($new->plainText))->toBeNull();
});

it('returns null for a corrupted token file instead of throwing', function () {
    $new = $this->repo->create('user-1', 'iPhone');
    file_put_contents(config('statamic.editor-api.storage_path').'/'.$new->token->hash.'.yaml', "{invalid: [yaml");

    expect($this->repo->findByPlainText($new->plainText))->toBeNull();
});

it('does not resurrect a revoked token on touchLastUsed', function () {
    $new = $this->repo->create('user-1', 'iPhone');
    $this->repo->revoke($new->token->hash);

    $this->repo->touchLastUsed($new->token);

    expect($this->repo->findByPlainText($new->plainText))->toBeNull();
});

it('treats a ttl of 0 as immediate expiry, not as no expiration', function () {
    config()->set('statamic.editor-api.auth.token_ttl_days', 0);

    $new = $this->repo->create('user-1', 'iPhone');

    expect($new->token->expiresAt)->not->toBeNull()
        ->and($new->token->isExpired())->toBeTrue();
});

it('throttles touchLastUsed to at most one write per minute', function () {
    $new = $this->repo->create('user-1', 'iPhone');

    $this->repo->touchLastUsed($new->token);
    $first = $this->repo->findByPlainText($new->plainText)->lastUsedAt;

    $this->travel(10)->seconds();
    $this->repo->touchLastUsed($this->repo->findByPlainText($new->plainText));
    expect($this->repo->findByPlainText($new->plainText)->lastUsedAt->toIso8601String())
        ->toBe($first->toIso8601String()); // < 1 min : pas de réécriture

    $this->travel(2)->minutes();
    $this->repo->touchLastUsed($this->repo->findByPlainText($new->plainText));
    expect($this->repo->findByPlainText($new->plainText)->lastUsedAt->toIso8601String())
        ->not->toBe($first->toIso8601String());
});

it('throws instead of failing silently when the storage dir is not writable', function () {
    $dir = config('statamic.editor-api.storage_path');
    mkdir($dir, 0755, true);
    chmod($dir, 0555);

    try {
        $this->repo->create('user-1', 'iPhone');
        $failed = false;
    } catch (\RuntimeException) {
        $failed = true;
    } finally {
        chmod($dir, 0755);
    }

    expect($failed)->toBeTrue();
})->skipOnWindows();
