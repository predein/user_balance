<?php

namespace App\Enums;

enum BalanceLogStatus: string
{
    case PENDING = 'pending';
    case SUCCEEDED = 'succeeded';
    case REJECTED = 'rejected';
}
