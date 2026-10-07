<?php

namespace App\Modules\WhatsApp\Support;

final class SendResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
    ) {}

    public static function ok(?string $messageId = null): self
    {
        return new self(true, $messageId);
    }

    public static function failed(string $error): self
    {
        return new self(false, null, $error);
    }
}
