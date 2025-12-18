<?php

namespace App\Enums\Accounting\Intrastat;

use App\Enums\EnumHelperTrait;

enum IntrastatDeliveryTermsEnum: string
{
    use EnumHelperTrait;

    case EXW = 'EXW';
    case FCA = 'FCA';
    case FAS = 'FAS';
    case FOB = 'FOB';
    case CFR = 'CFR';
    case CIF = 'CIF';
    case CPT = 'CPT';
    case CIP = 'CIP';
    case DAP = 'DAP';
    case DPU = 'DPU';
    case DDP = 'DDP';

    public function label(): string
    {
        return match ($this) {
            self::EXW => 'Ex Works',
            self::FCA => 'Free Carrier',
            self::FAS => 'Free Alongside Ship',
            self::FOB => 'Free on Board',
            self::CFR => 'Cost and Freight',
            self::CIF => 'Cost, Insurance and Freight',
            self::CPT => 'Carriage Paid To',
            self::CIP => 'Carriage and Insurance Paid To',
            self::DAP => 'Delivered at Place',
            self::DPU => 'Delivered at Place Unloaded',
            self::DDP => 'Delivered Duty Paid',
        };
    }
}
