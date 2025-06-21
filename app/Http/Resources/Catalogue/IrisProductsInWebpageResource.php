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
 */
class IrisProductsInWebpageResource extends JsonResource
{
    use HasSelfCall;


    public function toArray($request): array
    {
        $image = null;
        if ($this->image_id) {
            $media = Media::find($this->image_id);
            $image = ImageResource::make($media)->getArray();
        }


        return [
            'id'         => $this->id,
            'image_id'   => $this->image_id,
            'code'       => $this->code,
            'name'       => $this->name,
            'stock'      => $this->available_quantity,
            'price'      => $this->price,
            'rrp'        => $this->rrp,
            'state'      => $this->state,
            'status'     => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'units'      => $this->units,
            'unit'       => $this->unit,
            'url'        => $this->url,
            'image'      => $image
        ];
    }


}
