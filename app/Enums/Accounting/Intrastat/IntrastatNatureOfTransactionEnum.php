<?php

namespace App\Enums\Accounting\Intrastat;

use App\Enums\EnumHelperTrait;

enum IntrastatNatureOfTransactionEnum: string
{
    use EnumHelperTrait;

    case OUTRIGHT_PURCHASE = '11';
    case PRIVATE_CONSUMER = '12';
    case RETURN_REPLACEMENT = '21';
    case TEMPORARY_EXPORT = '31';
    case LEASING = '51';

    public function label(): string
    {
        return match ($this) {
            self::OUTRIGHT_PURCHASE => 'Outright Purchase/Sale',
            self::PRIVATE_CONSUMER => 'Direct Sale to Private Consumer',
            self::RETURN_REPLACEMENT => 'Return of Goods/Replacement',
            self::TEMPORARY_EXPORT => 'Temporary Export for Processing',
            self::LEASING => 'Financial Leasing',
        };
    }
}
