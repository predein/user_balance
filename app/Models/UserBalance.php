<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\Currency;

class UserBalance extends Model
{
    protected $fillable = ['user_id','currency_id','balance_micros'];

    protected $casts = [
        'currency_id' => Currency::class,
        'balance_micros' => 'integer',
    ];
}
