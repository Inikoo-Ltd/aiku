<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Mar 2024 21:11:53 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property number $quantity_in_locations
 * @property number $number_location
 * @property number $unit_value
 * @property string $slug
 * @property string $description
 * @property string $family_slug
 * @property string $family_code
 * @property string $name
 * @property string $discontinued_in_organisation_at
 * @property mixed $state
 * @property mixed $quantity
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $warehouse_slug
 * @property mixed $packed_in
 * @property mixed $quantity_available
 * @property mixed $id
 * @property mixed $organisation_code
 * @property mixed $value_in_locations
 * @property mixed $revenue
 * @property mixed $dispatched
 * @property mixed $sales_grp_currency_external
 * @property mixed $sales_grp_currency_external_ly
 * @property mixed $invoices
 * @property mixed $invoices_ly
 */
class OrgStocksResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                              => $this->id,
            'slug'                            => $this->slug,
            'code'                            => $this->code,
            'state'                           => $this->state->stateIcon()[$this->state->value],
            'name'                            => $this->name,
            'quantity'                        => $this->quantity,
            'quantity_available'              => trimDecimalZeros($this->quantity_available),
            'quantity_in_locations'           => trimDecimalZeros($this->quantity_in_locations),
            'unit_value'                      => $this->unit_value,
            'number_locations'                => $this->number_location,
            'quantity_locations'              => $this->quantity_in_locations,
            'family_slug'                     => $this->family_slug,
            'family_code'                     => $this->family_code,
            'discontinued_in_organisation_at' => $this->discontinued_in_organisation_at,
            'organisation_name'               => $this->organisation_name,
            'organisation_code'               => $this->organisation_code,
            'organisation_slug'               => $this->organisation_slug,
            'warehouse_slug'                  => $this->warehouse_slug,
            'packed_in'                       => trimDecimalZeros($this->packed_in),
            'pick_fractional'                 => ($this->quantity && $this->packed_in) ? riseDivisor(divideWithRemainder(findSmallestFactors($this->quantity)), $this->packed_in) : [],
            'value_in_locations'              => $this->value_in_locations,
            'revenue'                               => $this->revenue,
            'dispatched'                            => $this->dispatched,
            'is_on_demand'                          => $this->is_on_demand,
            'sales_grp_currency_external'           => $this->sales_grp_currency_external ?? 0,
            'sales_grp_currency_external_ly'        => $this->sales_grp_currency_external_ly ?? 0,
            'sales_grp_currency_external_delta'     => $this->calculateDelta($this->sales_grp_currency_external ?? 0, $this->sales_grp_currency_external_ly ?? 0),
            'invoices'                              => $this->invoices ?? 0,
            'invoices_ly'                           => $this->invoices_ly ?? 0,
            'invoices_delta'                        => $this->calculateDelta($this->invoices ?? 0, $this->invoices_ly ?? 0),
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
