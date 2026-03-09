<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Mar 2024 20:31:47 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Goods;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $id
 * @property mixed $number_trade_units
 * @property mixed $number_trade_units_status_in_process
 * @property mixed $number_trade_units_status_active
 * @property mixed $number_trade_units_status_discontinued
 * @property mixed $number_trade_units_status_anomality
 * @property mixed $sales_grp_currency_external
 * @property mixed $sales_grp_currency_external_ly
 * @property mixed $invoices
 * @property mixed $invoices_ly
 */
class TradeUnitFamiliesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                                   => $this->slug,
            'code'                                   => $this->code,
            'name'                                   => $this->name,
            'id'                                     => $this->id,
            'number_trade_units'                     => $this->number_trade_units,
            'number_trade_units_status_in_process'   => $this->number_trade_units_status_in_process,
            'number_trade_units_status_active'       => $this->number_trade_units_status_active,
            'number_trade_units_status_discontinued' => $this->number_trade_units_status_discontinued,
            'number_trade_units_status_anomality'    => $this->number_trade_units_status_anomality,
            'sales_grp_currency_external'            => $this->sales_grp_currency_external ?? 0,
            'sales_grp_currency_external_ly'         => $this->sales_grp_currency_external_ly ?? 0,
            'sales_grp_currency_external_delta'      => $this->calculateDelta($this->sales_grp_currency_external ?? 0, $this->sales_grp_currency_external_ly ?? 0),
            'invoices'                               => $this->invoices ?? 0,
            'invoices_ly'                            => $this->invoices_ly ?? 0,
            'invoices_delta'                         => $this->calculateDelta($this->invoices ?? 0, $this->invoices_ly ?? 0),
        ];
    }

    private function calculateDelta(float $current, float $previous): ?array
    {
        if (!$previous || $previous == 0) {
            return null;
        }

        $delta = (($current - $previous) / $previous) * 100;

        return [
            'value'       => $delta,
            'formatted'   => number_format($delta, 1).'%',
            'is_positive' => $delta > 0,
            'is_negative' => $delta < 0,
        ];
    }
}
