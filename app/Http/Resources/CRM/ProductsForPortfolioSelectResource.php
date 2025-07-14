<?php

namespace App\Http\Resources\CRM;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property string $slug
 * @property string $code
 * @property string $name*@property mixed $id
 * @property mixed $media
 * @property mixed $price
 * @property mixed $id
 * @property mixed $gross_weight
 * @property mixed $currency_code
 * @property mixed $currency_id
 * @property mixed $web_images
 */
class ProductsForPortfolioSelectResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'slug'          => $this->slug,
            'code'          => $this->code,
            'image'         => Arr::get($this->web_images, 'main.gallery'),
            'price'         => $this->price,
            'name'          => $this->name,
            'gross_weight'  => $this->gross_weight,
            'currency_code' => $this->currency_code,
            'currency_id'   => $this->currency_id,
        ];
    }
}
