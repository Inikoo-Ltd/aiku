<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\TradeUnit;

use App\Enums\EnumHelperTrait;

/**
 * Chemistry of the portable batteries (up to 5 kg) and accumulators a unit contains or comes with.
 */
enum TradeUnitBatteryTypeEnum: string
{
    use EnumHelperTrait;

    case ZNC = 'znc';
    case ALMN = 'almn';
    case LI = 'li';
    case LI_SOCL2 = 'li_socl2';
    case LI_ION = 'li_ion';
    case NIMH = 'nimh';
    case AGO = 'ago';
    case ZNAIR = 'znair';
    case PB = 'pb';
    case NICD = 'nicd';
    case OTHER = 'other';

    public static function labels(): array
    {
        return [
            'znc'      => __('Zinc-carbon / Zinc-chloride').' – ZnC / ZnCl',
            'almn'     => __('Alkaline-manganese / Nickel-zinc').' – AlMn / NiZn',
            'li'       => __('Lithium').' – Li',
            'li_socl2' => __('Lithium-thionyl chloride').' – Li-SoCl2',
            'li_ion'   => __('Lithium-ion / Lithium-polymer').' – Li-Ion / Li-Pol',
            'nimh'     => __('Nickel-metal hydride').' – NiMH',
            'ago'      => __('Silver-oxide').' – AgO',
            'znair'    => __('Zinc-air').' – ZnAir',
            'pb'       => __('Lead-acid').' – Pb',
            'nicd'     => __('Nickel-cadmium').' – NiCd',
            'other'    => __('Other'),
        ];
    }

    public static function options(): array
    {
        return array_map(
            fn (self $type) => ['value' => $type->value, 'label' => self::labels()[$type->value]],
            self::cases()
        );
    }
}
