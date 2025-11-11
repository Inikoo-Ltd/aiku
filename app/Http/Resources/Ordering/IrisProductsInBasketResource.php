<?php
/*
 * author Louis Perez
 * created on 11-11-2025-08h-46m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Http\Resources\Ordering;

use App\Models\Catalogue\Product;
use App\Models\Helpers\Media;
use App\Models\Web\Webpage;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Properties expected on the underlying IrisProductInBasketResource
 * @property itn transaction_id
 * @property numeric|nullquantity_ordered
 * @property intid
 * @property int|nullimage_id
 * @property string
 * @property intgroup_id
 * @property intorganisation_id
 * @property int|nullshop_id
 * @property string|nullname
 * @property int|null available_quantity
 * @property numeric|null price
 * @property numeric|null RRP
 * @property ProductStatusEnumstatus
 * @property ProductStateEnumstate
 * @property \Illuminate\Support\Carbon|null created_at
 * @property \Illuminate\Support\Carbon|null updated_at
 * @property string units
 * @property string unit
 * @property int|null top_seller
 * @property array<array-key, mixed> web_images
 * @property string|null url
 * @property string|null canonical_url
 * @property string|null website_id
 * @property string|null webpage_id
 * @property string|null currency_code
 */
class IrisProductsInBasketResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'transaction_id'        => $this->transaction_id,
            'quantity_ordered'      => $this->quantity_ordered,
            'product_id'            => $this->product_id,
            'image_id'              => $this->image_id,
            'code'                  => $this->code,
            'group_id'              => $this->group_id,
            'organisation_id'       => $this->organisation_id,
            'shop_id'               => $this->shop_id,
            'name'                  => $this->name,
            'available_quantity'    => $this->available_quantity,
            'price'                 => $this->price,
            'rrp'                   => $this->rrp,
            'product_state'         => $this->product_state,
            'product_status'        => $this->product_status,
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
            'units'                 => $this->units,
            'unit'                  => $this->unit,
            'top_seller'            => $this->top_seller,
            'web_images'            => $this->web_images,
            'url'                   => $this->url,
            'canonical_url'         => $this->canonical_url,
            'website_id'            => $this->website_id,
            'webpage_id'            => $this->webpage_id,
            'currency_code'         => $this->currency_code,
        ];
    }
}
