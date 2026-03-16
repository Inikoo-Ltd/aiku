<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Resource;

use App\Models\Catalogue\Product;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property string $name*@property mixed $id
 * @property mixed $media
 * @property mixed $price
 * @property mixed $id
 */
class ProductsApiResource extends JsonResource
{
    public function toArray($request): array
    {
        $product = Product::find($this->id);


        $product_details = [
            'id'    => $this->id,
            'slug'  => $this->slug,
            'code'  => $this->code,
            'image' => $product->imageSources(64, 64),
            'price' => $this->price,
            'current_stock' => $this->current_stock,
            'name'  => $this->name,
            'description' => $this->description,
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
            'gross_weight' => $this->gross_weight,
            'currency_code' => $this->currency_code,
            'currency_id' => $this->currency_id,
        ];
    }
}
