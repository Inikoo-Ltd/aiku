<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\TradeUnit;

use App\Enums\EnumHelperTrait;

/**
 * Materials of what comes with a unit but is not packaging (printed instructions, manuals, leaflets), declared in grams per unit.
 */
enum TradeUnitNonPackagingMaterialEnum: string
{
    use EnumHelperTrait;

    case GLASS = 'glass';
    case PET = 'pet';
    case PE = 'pe';
    case PP = 'pp';
    case PS = 'ps';
    case PVC = 'pvc';
    case PA = 'pa';
    case PAPER = 'paper';

    public static function labels(): array
    {
        return [
            'glass' => __('Glass'),
            'pet'   => __('Plastics').' – PET',
            'pe'    => __('Plastics').' – PE',
            'pp'    => __('Plastics').' – PP',
            'ps'    => __('Plastics').' – PS',
            'pvc'   => __('Plastics').' – PVC',
            'pa'    => __('Plastics').' – PA',
            'paper' => __('Paper'),
        ];
    }

    public function field(): string
    {
        return 'non_packaging_'.$this->value;
    }
}
