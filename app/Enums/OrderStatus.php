<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';

    case Preparing = 'preparing';

    case Ready = 'ready';

    case Completed = 'completed';

    case Cancelled = 'cancelled';
}
