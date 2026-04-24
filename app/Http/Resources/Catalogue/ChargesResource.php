<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 08:19:49 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $shop_slug
 * @property string $code
 * @property string $name
 * @property string $description
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $shop_code
 * @property mixed $shop_name
 * @property mixed $customers_invoiced
 * @property mixed $invoices
 * @property mixed $sales_grp_currency_external
 * @property mixed $sales_grp_currency_external_ly
 */
class ChargesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                              => $this->slug,
            'shop_slug'                         => $this->shop_slug,
            'shop_code'                         => $this->shop_code,
            'shop_name'                         => $this->shop_name,
            'code'                              => $this->code,
            'name'                              => $this->name,
            'description'                       => $this->description,
            'currency_code'                     => $this->currency_code,
            'state_icon'                        => $this->state->stateIcon()[$this->state->value],
            'created_at'                        => $this->created_at,
            'updated_at'                        => $this->updated_at,
            'customers_invoiced'                => $this->customers_invoiced ?? 0,
            'invoices'                          => $this->invoices ?? 0,
            'sales_grp_currency_external'       => $this->sales_grp_currency_external ?? 0,
            'sales_grp_currency_external_ly'    => $this->sales_grp_currency_external_ly ?? 0,
            'sales_grp_currency_external_delta' => $this->calculateDelta($this->sales_grp_currency_external ?? 0, $this->sales_grp_currency_external_ly ?? 0),
            'organisation_name'                 => $this->organisation_name,
            'organisation_slug'                 => $this->organisation_slug,
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
