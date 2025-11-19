<?php

/*
 * author Louis Perez
 * created on 11-11-2025-08h-46m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Http\Resources\Ordering;

use App\Models\Helpers\Media;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Helpers\ImageResource;
use Illuminate\Support\Arr;

/**
 * @property mixed $transaction_id
 * @property mixed $quantity_ordered
 * @property mixed $product_id
 * @property mixed $image_id
 * @property mixed $web_images
 * @property mixed $code
 * @property mixed $group_id
 * @property mixed $organisation_id
 * @property mixed $shop_id
 * @property mixed $name
 * @property mixed $available_quantity
 * @property mixed $price
 * @property mixed $rrp
 * @property mixed $product_state
 * @property mixed $product_status
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $units
 * @property mixed $unit
 * @property mixed $top_seller
 * @property mixed $url
 * @property mixed $canonical_url
 * @property mixed $website_id
 * @property mixed $webpage_id
 * @property mixed $currency_code
 * @property mixed $offers_data
 */
class IrisProductsInBasketResource extends JsonResource
{

    public function toArray($request): array
    {
        
        $imageData = is_string($this->web_images) ? json_decode($this->web_images, true) : $this->web_images;
        $webImageThumbnail = Arr::get($imageData, 'main.thumbnail');
        
        return [
            'transaction_id'        => $this->transaction_id,
            'quantity_ordered'      => (int) $this->quantity_ordered ?? 0,
            'quantity_ordered_new'  => (int) $this->quantity_ordered ?? 0,
            'product_id'            => $this->product_id,
            'web_image_thumbnail'  => $webImageThumbnail,
            
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
            'url'                   => $this->url,
            'canonical_url'         => $this->canonical_url,
            'website_id'            => $this->website_id,
            'webpage_id'            => $this->webpage_id,
            'currency_code'         => $this->currency_code,
            'offers_data'           => $this->offers_data,
        ];
    }
}
