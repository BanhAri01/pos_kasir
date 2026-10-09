<?php

namespace App\Modules\Operations\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\User;
use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivableProof extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'customer_id', 'uuid', 'amount', 'note', 'file_path', 'file_mime', 'status',
        'approved_amount', 'reject_reason', 'reviewed_by', 'reviewed_at', 'ip_address',
    ];

    protected $hidden = ['file_path', 'ip_address'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'approved_amount' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPdf(): bool
    {
        return $this->file_mime === 'application/pdf';
    }
}
