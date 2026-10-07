<?php

namespace App\Modules\Sync\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SyncBatch extends Model
{
    use BelongsToTenant;

    protected $fillable = ['device_id', 'user_id', 'item_count', 'ok_count', 'conflict_count', 'error_count'];
}
