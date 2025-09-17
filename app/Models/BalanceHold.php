<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\HoldStatus;

class BalanceHold extends Model
{
    protected $fillable = [
        'hold_uuid',
        'user_id',
        'currency_id',
        'amount_micros',
        'status',
        'expires_at',
    ];
    protected $casts = [
        'amount_micros' => 'integer',
        'expires_at' => 'datetime',
        'status' => HoldStatus::class,
    ];
}
