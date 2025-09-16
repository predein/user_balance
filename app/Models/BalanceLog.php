<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BalanceLog extends Model
{
    protected $fillable = [
        'operation_uuid',
        'user_id',
        'balance_micros',
        'currency_id',
        'status',
    ];
}
