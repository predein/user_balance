<?php

namespace App\Enums;

enum HoldStatus: string
{
    case RESERVED = 'reserved';
    case CAPTURED = 'captured';
    case RELEASED = 'released';
    case EXPIRED = 'expired'; // @todo
}
