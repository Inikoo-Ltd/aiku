<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-07-2025, Bali, Indonesia
 * GitHub: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Resource;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property string $slug
 * @property string $code
 * @property string $name*@property mixed $id
 * @property mixed $media
 * @property mixed $price
 * @property mixed $id
 * @property mixed $current_stock
 * @property mixed $barcode
 * @property mixed $description
 * @property mixed $gross_weight
 * @property mixed $currency_code
 * @property mixed $currency_id
 * @property mixed $web_images
 */
class ProductsApiResource extends JsonResource
{

    public function toArray($request): array
    {
        $product_details = [
            'id'            => $this->id,
            'slug'          => $this->slug,
            'code'          => $this->code,
            'image'         => Arr::get($this->web_images, 'main.gallery'),
            'price'         => $this->price,
            'current_stock' => $this->current_stock,
            'name'          => $this->name,
            'ean_barcode'   => $this->barcode,
            'description'   => $this->description,
        ];

        if (isset($this->department_name)) {
            data_set($product_details, 'department_name', $this->department_name);
        }
        if (isset($this->sub_department_name)) {
            data_set($product_details, 'sub_department_name', $this->sub_department_name);
        }
        if (isset($this->family_name)) {
            data_set($product_details, 'family_name', $this->family_name);
        }

        return [
            ...$product_details,
            'gross_weight'  => $this->gross_weight,
            'currency_code' => $this->currency_code,
            'currency_id'   => $this->currency_id,
        ];
    }
}
