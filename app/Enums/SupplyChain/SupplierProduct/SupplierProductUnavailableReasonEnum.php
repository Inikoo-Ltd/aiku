<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\SupplyChain\SupplierProduct;

use App\Enums\EnumHelperTrait;

enum SupplierProductUnavailableReasonEnum: string
{
    use EnumHelperTrait;

    case PRICE_INCREASE = 'price_increase';
    case PRICE_CHANGE_PROPOSED = 'price_change_proposed';
    case SUPPLIER_PROBLEM = 'supplier_problem';
    case RAW_MATERIAL_SHORTAGE = 'raw_material_shortage';
    case LOW_SEASON = 'low_season';
    case HIGH_SEASON = 'high_season';
    case TOO_HOT = 'too_hot';
    case TOO_COLD = 'too_cold';
    case FACTORY_HOLIDAY = 'factory_holiday';
    case QUALITY = 'quality';
    case OTHER = 'other';

    public static function labels(): array
    {
        return [
            'price_increase'        => __('Price increase'),
            'price_change_proposed' => __('Price change proposed by the supplier'),
            'supplier_problem'      => __('Supplier problem'),
            'raw_material_shortage' => __('Raw material shortage'),
            'low_season'            => __('Low season'),
            'high_season'           => __('High season'),
            'too_hot'               => __('Too hot'),
            'too_cold'              => __('Too cold'),
            'factory_holiday'       => __('Factory holiday'),
            'quality'               => __('Quality'),
            'other'                 => __('Other'),
        ];
    }
}
