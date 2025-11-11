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
use App\Http\Resources\Helpers\ImageResource;

class IrisProductsInBasketResource extends JsonResource
{
    public function toArray($request): array
    {

        $media = null;
        if ($this->image_id) {
            $media = Media::find($this->image_id);
        }

        return [
            'transaction_id'        => $this->transaction_id,
            'quantity_ordered'          => (int) $this->quantity_ordered,
            'quantity_ordered_new'      => (int) $this->quantity_ordered,
            'product_id'            => $this->product_id,
            'image'                 => $this->image_id ? ImageResource::make($media)->getArray() : null,
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
