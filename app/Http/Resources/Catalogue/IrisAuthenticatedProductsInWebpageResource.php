<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 15:08:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Helpers\Media;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property mixed $image_id
 * @property string $code
 * @property string $name
 * @property mixed $available_quantity
 * @property mixed $price
 * @property mixed $state
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $units
 * @property mixed $unit
 * @property mixed $status
 * @property mixed $rrp
 * @property mixed $id
 * @property string $url
 * @property mixed $currency
 * @property mixed $currency_code
 * @property mixed $web_images
 * @property mixed $top_seller
 * @property mixed $parent_url
 * @property mixed $group_id
 * @property mixed $organisation_id
 * @property mixed $webpage_id
 * @property mixed $website_id
 * @property mixed $shop_id
 */
class IrisAuthenticatedProductsInWebpageResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $url = '';
        if ($this->parent_url) {
            $url = $this->parent_url.'/';
        }
        $url = '/'.$url.$this->url;

        $favourite = false;
        if ($request->user()) {
            $customer = $request->user()->customer;
            if ($customer) {
                $favourite = $customer?->favourites()?->where('product_id', $this->id)->first();
            }
        }

        $back_in_stock_id = null;
        $back_in_stock    = false;

        if ($request->user()) {
            $customer = $request->user()->customer;
            if ($customer) {
                $set_data_back_in_stock = $customer?->BackInStockReminder()
                    ?->where('product_id', $this->id)
                    ->first();

                if ($set_data_back_in_stock) {
                    $back_in_stock    = true;
                    $back_in_stock_id = $set_data_back_in_stock->id;
                }
            }
        }

        $media = null;
        if ($this->image_id) {
            $media = Media::find($this->image_id);
        }

        $oldLuigiIdentity=$this->group_id . ':' . $this->organisation_id . ':' . $this->shop_id . ':' . $this->website_id . ':' . $this->webpage_id;

        return [
            'id'                   => $this->id,
            'image_id'             => $this->image_id,
            'image'                => $this->image_id ? ImageResource::make($media)->getArray() : null,
            'code'                 => $this->code,
            'luigi_identity'       => $oldLuigiIdentity,
            'name'                 => $this->name,
            'stock'                => $this->available_quantity,
            'price'                => $this->price,
            'rrp'                  => $this->rrp,
            'state'                => $this->state,
            'status'               => $this->status,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
            'units'                => $this->units,
            'unit'                 => $this->unit,
            'url'                  => $url,
            'top_seller'           => $this->top_seller,
            'web_images'           => $this->web_images,
            'transaction_id'       => $this->transaction_id ?? null,
            'quantity_ordered'     => (int)$this->quantity_ordered ?? 0,
            'quantity_ordered_new' => (int)$this->quantity_ordered ?? 0,  // To editable in Frontend
            'is_favourite'         => $favourite && !$favourite->unfavourited_at ?? false,
            'is_back_in_stock'     => $back_in_stock,
            'back_in_stock_id'     => $back_in_stock_id
        ];
    }


}
