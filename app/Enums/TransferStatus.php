<?php

namespace App\Enums;

enum TransferStatus: string
{
    case PENDING = 'pending';
    case DEBITED = 'debited';
    case CREDITED = 'credited';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case REJECTED = 'rejected';
}
