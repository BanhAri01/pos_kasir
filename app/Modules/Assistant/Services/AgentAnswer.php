<?php

namespace App\Modules\Assistant\Services;

/**
 * Hasil satu pertanyaan ke Asisten Toko.
 *
 * status: ok | command | quota | offline | error | limit | plan | blocked | empty | duplicate
 */
final class AgentAnswer
{
    public function __construct(
        public readonly string $text,
        public readonly string $status,
        public readonly ?string $mode = null,
        public readonly float $cost = 0.0,
        public readonly array $tools = [],
        public readonly ?string $model = null,
    ) {}

    public function usedAi(): bool
    {
        return in_array($this->status, ['ok', 'limit'], true);
    }

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'status' => $this->status,
            'mode' => $this->mode,
            'cost' => $this->cost,
            'tools' => $this->tools,
        ];
    }
}
