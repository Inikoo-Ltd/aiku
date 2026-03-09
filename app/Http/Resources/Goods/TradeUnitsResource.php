<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Mar 2024 20:31:47 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Goods;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $slug
 * @property string $type
 * @property string $name
 * @property mixed $number_current_stocks
 * @property mixed $number_current_products
 * @property mixed $id
 * @property mixed $status
 * @property mixed $quantity
 * @property mixed $marketing_weight
 * @property mixed $sales_grp_currency_external
 * @property mixed $sales_grp_currency_external_ly
 * @property mixed $invoices
 * @property mixed $invoices_ly
 */
class TradeUnitsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                    => $this->slug,
            'code'                    => $this->code,
            'name'                    => $this->name,
            'marketing_weight'        => $this->marketing_weight !== null ? ($this->marketing_weight).' g' : null,
            'type'                    => $this->type,
            'number_current_stocks'   => $this->number_current_stocks,
            'number_current_products' => $this->number_current_products,
            'id'                      => $this->id,
            'quantity'                => trimDecimalZeros($this->quantity),
            'status'                  => $this->status,
            'status_icon'             => $this->status ? $this->status->icon()[$this->status->value] : null,
            'media'                   => null,
            'sales_grp_currency_external'       => $this->sales_grp_currency_external ?? 0,
            'sales_grp_currency_external_ly'    => $this->sales_grp_currency_external_ly ?? 0,
            'sales_grp_currency_external_delta' => $this->calculateDelta($this->sales_grp_currency_external ?? 0, $this->sales_grp_currency_external_ly ?? 0),
            'invoices'                          => $this->invoices ?? 0,
            'invoices_ly'                       => $this->invoices_ly ?? 0,
            'invoices_delta'                    => $this->calculateDelta($this->invoices ?? 0, $this->invoices_ly ?? 0),
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
