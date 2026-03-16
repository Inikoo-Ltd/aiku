<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 25 Oct 2022 08:17:00 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property number $state
 * @property string $name
 * @property string $description
 * @property string $number_current_org_stocks
 * @property mixed $number_out_of_stock_org_stocks
 * @property mixed $woc
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $currency_code
 * @property mixed $stock_value
 * @property mixed $on_the_way_po_value
 * @property mixed $on_the_way_po_count
 * @property mixed $sales_grp_currency_external
 * @property mixed $sales_grp_currency_external_ly
 * @property mixed $invoices
 * @property mixed $invoices_ly
 * @property mixed $health_rank
 *
 */
class OrgStockFamiliesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                              => $this->slug,
            'code'                              => $this->code,
            'state'                             => $this->state,
            'name'                              => $this->name,
            'number_current_org_stocks'         => $this->number_current_org_stocks,
            'number_out_of_stock_org_stocks'    => $this->number_out_of_stock_org_stocks ?? 0,
            'woc'                               => $this->woc !== null ? round((float) $this->woc, 1) : null,
            'created_at'                        => $this->created_at,
            'updated_at'                        => $this->updated_at,
            'organisation_name'                 => $this->organisation_name,
            'organisation_slug'                 => $this->organisation_slug,
            'warehouse_slug'                    => $this->warehouse_slug,
            'currency_code'                     => $this->currency_code,
            'stock_value'                       => $this->stock_value ?? 0,
            'on_the_way_po_value'               => $this->on_the_way_po_value ?? 0,
            'on_the_way_po_count'               => $this->on_the_way_po_count ?? 0,
            'sales_grp_currency_external'       => $this->sales_grp_currency_external ?? 0,
            'sales_grp_currency_external_ly'    => $this->sales_grp_currency_external_ly ?? 0,
            'sales_grp_currency_external_delta' => $this->calculateDelta($this->sales_grp_currency_external ?? 0, $this->sales_grp_currency_external_ly ?? 0),
            'invoices'                          => $this->invoices ?? 0,
            'invoices_ly'                       => $this->invoices_ly ?? 0,
            'invoices_delta'                    => $this->calculateDelta($this->invoices ?? 0, $this->invoices_ly ?? 0),
            'health_rank'                  => $this->health_rank ? $this->health_rank->stateIcon()[$this->health_rank->value] : null,
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
