<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    protected $fillable = [
        'transfer_uuid',
        'from_user_id',
        'to_user_id',
        'balance_micros',
        'currency_id',
        'status',
    ];

    public function markFailed(string $reason): void
    {
        $this->status = TransferStatus::FAILED;
        $this->reason = $reason;
        $this->save();
    }
}
