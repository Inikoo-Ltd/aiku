<?php

/*
 * Author Louis Perez
 * Created on 06-08-2026-14h-06m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Http\Resources\Catalogue;

use App\Enums\Catalogue\Product\ProductStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Carbon\Carbon;

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
 * @property mixed $rrp
 * @property mixed $gross_weight
 * @property mixed $images
 * @property mixed $unit
 * @property mixed $master_product_id
 * @property mixed $web_images
 * @property mixed $variant_slug
 * @property mixed $is_variant_leader
 * @property mixed $variant_code
 * @property mixed $webpage
 * @property mixed $is_for_sale
 * @property mixed $discontinued_at
 * @property mixed $health_rank
 *
 * @method imageSources(int $int, int $int1)
 */
class ProductsWithMismatchFamilyResource extends JsonResource
{
    public function toArray($request): array
    {
        $state = $this->state->stateIcon()[$this->state->value];
        if ($this->state != ProductStateEnum::DISCONTINUED && !$this->is_for_sale && !$this->is_bundle) {
            $state = [
                'tooltip' => __('Not for sale'),
                'icon'    => 'fas fa-thumbtack',
                'class'   => 'text-red-500',
                'color'   => 'red',
            ];
        }

        return [
            'id'                                => $this->id,
            'slug'                              => $this->slug,
            'code'                              => $this->code,
            'name'                              => $this->name,
            'state'                             => $state,
            'price'                             => $this->price,
            'rrp'                               => $this->rrp,
            'created_at'                        => Carbon::parse($this->created_at)->toDateTimeString(),
            'updated_at'                        => $this->updated_at,
            'discontinued_at'                   => $this->discontinued_at,
            'image_thumbnail'                   => Arr::get($this->web_images, 'main.thumbnail'),
            'available_quantity'                => trimDecimalZeros($this->available_quantity),
            'is_for_sale'                       => $this->is_for_sale,
            'units'                             => trimDecimalZeros($this->units),
            'unit'                              => $this->unit,
            'master_product_id'                 => $this->master_product_id,
            'organisation_slug'                 => $this->organisation_slug,
            'shop_slug'                         => $this->shop_slug,
            'shop_code'                         => $this->shop_code,
            'shop_name'                         => $this->shop_name,
            'family_slug'                       => $this->family_slug,
            'family_code'                       => $this->family_code,
            'master_family_id'                  => $this->master_family_id,
            'master_family_slug'                => $this->master_family_slug,
            'master_family_code'                => $this->master_family_code,
        ];
    }
}
