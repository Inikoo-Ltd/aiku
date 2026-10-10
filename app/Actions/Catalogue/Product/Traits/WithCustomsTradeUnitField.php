<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Traits;

use App\Models\Goods\TradeUnit;
use Illuminate\Support\Collection;

trait WithCustomsTradeUnitField
{
    /**
     * @param Collection<int, TradeUnit> $tradeUnits
     */
    protected function customsTradeUnitField(Collection $tradeUnits, ?int $customsTradeUnitId): array
    {
        return [
            'type'        => 'select',
            'label'       => __('Main part for customs'),
            'information' => __('Invoices show only this part\'s tariff code and country of origin, so they can be used for export clearance. Leave empty to list every part\'s.'),
            'placeholder' => __('Every part'),
            'mode'        => 'single',
            'required'    => false,
            'options'     => $tradeUnits->map(fn (TradeUnit $tradeUnit) => [
                'value' => $tradeUnit->id,
                'label' => $tradeUnit->code.' · '.($tradeUnit->tariff_code ?: __('no tariff code')).' · '.($tradeUnit->country_of_origin ?: __('no origin')),
            ])->values()->all(),
            'value'       => $customsTradeUnitId,
        ];
    }
}
