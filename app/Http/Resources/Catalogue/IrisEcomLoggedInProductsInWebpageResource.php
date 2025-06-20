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
 * @property mixed $slug
 * @property mixed $image_id
 * @property mixed $code
 * @property mixed $name
 * @property mixed $available_quantity
 * @property mixed $price
 * @property mixed $state
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $units
 * @property mixed $unit
 * @property mixed $status
 * @property mixed $rrp
 * @property mixed $currency_code
 * @property mixed $id
 */
class IrisEcomLoggedInProductsInWebpageResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $media = null;
        if ($this->image_id) {
            $media = Media::find($this->image_id);
        }


        return [
            'id'            => $this->id,
            'slug'          => $this->slug,
            'image_id'      => $this->image_id,
            'code'          => $this->code,
            'name'          => $this->name,
            'stock'         => $this->available_quantity,
            'price'         => $this->price,
            'state'         => $this->state,
            'currency_code' => $this->currency_code,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
            'units'         => $this->units,
            'unit'          => $this->unit,
            'status'        => $this->status,
            'rrp'           => $this->rrp,
            'url'                         => $this->url,
            'image'         => $this->image_id ? ImageResource::make($media)->getArray() : null,

        ];
    }

}
