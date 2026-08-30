<?php

namespace Ppcharlier\StatamicEditorApi\Auth;

use Carbon\CarbonImmutable;

final class Token
{
    public function __construct(
        public readonly string $hash,
        public readonly string $userId,
        public readonly string $name,
        public readonly CarbonImmutable $createdAt,
        public readonly ?CarbonImmutable $lastUsedAt,
        public readonly ?CarbonImmutable $expiresAt,
    ) {
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt->isPast();
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'name' => $this->name,
            'created_at' => $this->createdAt->toIso8601String(),
            'last_used_at' => $this->lastUsedAt?->toIso8601String(),
            'expires_at' => $this->expiresAt?->toIso8601String(),
        ];
    }

    public static function fromArray(string $hash, array $data): self
    {
        return new self(
            hash: $hash,
            userId: $data['user_id'],
            name: $data['name'],
            createdAt: CarbonImmutable::parse($data['created_at']),
            lastUsedAt: isset($data['last_used_at']) ? CarbonImmutable::parse($data['last_used_at']) : null,
            expiresAt: isset($data['expires_at']) ? CarbonImmutable::parse($data['expires_at']) : null,
        );
    }
}
