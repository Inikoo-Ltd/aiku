<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 15:08:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\HasSelfCall;
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
 * @property mixed $description
 * @property mixed $web_images
 */
class ProductsWebpageResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {

        if (!is_array($this->web_images)) {
            $webImages = json_decode(trim($this->web_images, '"'), true) ?? [];
        } else {
            $webImages = $this->web_images;
        }
        return [
            'id'          => $this->id,
            'slug'        => $this->slug,
            'image_id'    => $this->image_id,
            'code'        => $this->code,
            'name'        => $this->name,
            'stock'       => $this->available_quantity,
            'price'       => $this->price,
            'description' => $this->description,
            'state'       => $this->state,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
            'units'       => $this->units,
            'unit'        => $this->unit,
            'status'      => $this->status,
            'rrp'         => $this->rrp,
            'web_images'  => $webImages
        ];
    }
}
