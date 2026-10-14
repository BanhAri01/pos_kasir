<?php

namespace App\Modules\Assistant\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nomor WhatsApp yang boleh bertanya ke Asisten Toko. Satu nomor hanya untuk satu usaha.
 * Baru dilayani setelah kode dari WhatsApp dimasukkan, dan berhenti dilayani bila pendaftarnya tidak aktif lagi.
 */
class AssistantPhone extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'phone', 'label', 'verified_at', 'code_hash', 'code_expires_at', 'code_attempts'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'code_expires_at' => 'datetime', 'code_attempts' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function codePending(): bool
    {
        return ! $this->isVerified() && $this->code_expires_at?->isFuture() === true;
    }

    public function ownerActive(): bool
    {
        return $this->user !== null && $this->user->is_active;
    }

    public function serviceable(): bool
    {
        return $this->isVerified() && $this->ownerActive();
    }
}
