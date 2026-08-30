<?php

namespace Ppcharlier\StatamicEditorApi\Auth;

final class NewToken
{
    public function __construct(
        public readonly string $plainText,
        public readonly Token $token,
    ) {
    }
}
