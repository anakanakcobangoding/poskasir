<?php

namespace App\Enums;

enum OrderType: string
{
    case DLV = 'DLV';

    case DI = 'DI';

    case TA = 'TA';

    public function label(): string
    {
        return match ($this) {
            self::DLV => 'Delivery',
            self::DI => 'Dine In',
            self::TA => 'Takeaway',
        };
    }
}
