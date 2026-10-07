<?php

namespace App\Modules\Expense\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Pos\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = ['outlet_id', 'uuid', 'expense_category_id', 'spent_on', 'amount', 'payment_method_id', 'cash_movement_id', 'note', 'user_id'];

    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'amount' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
