<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property mixed $state
 * @property string $shop_slug
 * @property mixed $shop_code
 * @property mixed $shop_name
 * @property mixed $department_slug
 * @property mixed $department_code
 * @property mixed $department_name
 * @property mixed $family_slug
 * @property mixed $family_code
 * @property mixed $family_name
 * @property mixed $organisation_name
 * @property mixed $organisation_code
 * @property mixed $organisation_slug
 * @property mixed $price
 * @property mixed $image_thumbnail
 * @property mixed $current_historic_asset_id
 * @property mixed $asset_id
 * @property mixed $available_quantity
 * @property mixed $customers_invoiced_all
 * @property mixed $invoices_all
 * @property mixed $sales_all
 * @property mixed $id
 * @property mixed $units
 * @property mixed $currency_code
 *
 */
class ProductsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                        => $this->id,
            'slug'                      => $this->slug,
            'code'                      => $this->code,
            'name'                      => $this->name,
            'state'                     => $this->state->stateIcon()[$this->state->value],
            'created_at'                => $this->created_at,
            'updated_at'                => $this->updated_at,
            'shop_slug'                 => $this->shop_slug,
            'shop_code'                 => $this->shop_code,
            'shop_name'                 => $this->shop_name,
            'organisation_name'         => $this->organisation_name,
            'organisation_code'         => $this->organisation_code,
            'organisation_slug'         => $this->organisation_slug,
            'department_slug'           => $this->department_slug,
            'department_code'           => $this->department_code,
            'department_name'           => $this->department_name,
            'family_slug'               => $this->family_slug,
            'family_code'               => $this->family_code,
            'family_name'               => $this->family_name,
            'price'                     => $this->price,
            'units'                     => $this->units,
            'image_thumbnail'           => $this->image_thumbnail,
            'current_historic_asset_id' => $this->current_historic_asset_id,
            'asset_id'                  => $this->asset_id,
            'available_quantity'        => $this->available_quantity,
            'gross_weight'              => $this->gross_weight,
            'rrp'                       => $this->rrp,
            'customers_invoiced_all'    => $this->customers_invoiced_all,
            'invoices_all'              => $this->invoices_all,
            'sales_all'                 => $this->sales_all,
            'currency_code'             => $this->currency_code
        ];
    }
}
