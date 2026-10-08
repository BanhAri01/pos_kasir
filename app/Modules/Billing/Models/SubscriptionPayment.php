<?php

namespace App\Modules\Billing\Models;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Billing\Services\Plans;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'reference', 'gateway', 'plan', 'months', 'channel', 'base_amount',
        'fee_amount', 'amount', 'status', 'method', 'gateway_ref', 'redirect_url', 'period_from',
        'period_until', 'note', 'paid_at', 'last_payload',
    ];

    protected $hidden = ['last_payload'];

    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'base_amount' => 'integer',
            'fee_amount' => 'integer',
            'amount' => 'integer',
            'period_from' => 'date',
            'period_until' => 'date',
            'paid_at' => 'datetime',
            'last_payload' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function summary(): array
    {
        return [
            'reference' => $this->reference,
            'plan' => Plans::label($this->plan),
            'months' => $this->months,
            'base_amount' => $this->base_amount,
            'fee_amount' => $this->fee_amount,
            'amount' => $this->amount,
            'status' => $this->status,
            'status_label' => match ($this->status) {
                'paid' => 'Lunas',
                'pending' => 'Menunggu dibayar',
                'expired' => 'Kedaluwarsa',
                default => 'Gagal',
            },
            'channel' => $this->channel,
            'method' => $this->method,
            'period_until' => $this->period_until?->translatedFormat('j F Y'),
            'created_at' => $this->created_at?->translatedFormat('j M Y H:i'),
            'redirect_url' => $this->status === 'pending' ? $this->redirect_url : null,
            'note' => $this->note,
        ];
    }
}
