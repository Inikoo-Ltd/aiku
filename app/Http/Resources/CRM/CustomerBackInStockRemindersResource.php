<?php

/*
 * author Arya Permana - Kirin
 * created on 16-10-2024-10h-59m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\CRM;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Helpers\Media;
use App\Http\Resources\Helpers\ImageResource;

/**
 * @property string $ulid
 * @property string $reference
 * @property string $name
 * @property string $contact_name
 * @property string $company_name
 * @property string $email
 * @property string $phone
 * @property string $created_at
 * @property string $updated_at
 */
class CustomerBackInStockRemindersResource extends JsonResource
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
                $favourite = $customer->favourites()?->where('product_id', $this->id)->first();
            }
        }




        $media = null;
        if ($this->image_id) {
            $media = Media::find($this->image_id);
        }

        return [
            'id'                    => $this->id,
            'image_id'              => $this->image_id,
            'image'                 => $this->image_id ? ImageResource::make($media)->getArray() : null,
            'code'                  => $this->code,
            'name'                  => $this->name,
            'stock'                 => $this->available_quantity,
            'price'                 => $this->price,
            'rrp'                   => $this->rrp,
            'state'                 => $this->state,
            'status'                => $this->status,
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
            'units'                 => $this->units,
            'unit'                  => $this->unit,
            'url'                   => $url,
            'web_images'            => $this->web_images,
            'transaction_id'        => $this->transaction_id ?? null,
            // 'quantity_ordered' => (int) $this->quantity_ordered ?? 0,
            // 'quantity_ordered_new' => (int) $this->quantity_ordered ?? 0,  // To editable in Frontend
            'is_favourite'          => $favourite && !$favourite->unfavourited_at ?? false,
        ];
    }
}
