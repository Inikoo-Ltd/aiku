<?php

/*
 * author Arya Permana - Kirin
 * created on 16-06-2025-16h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\Web\ModelHasContentsResource;
use App\Models\Catalogue\Product;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $shop_slug
 * @property string $department_slug
 * @property mixed $state
 * @property string $code
 * @property string $name
 * @property string $description
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $shop_code
 * @property mixed $shop_name
 * @property mixed $department_code
 * @property mixed $department_name
 *
 */
class WorkshopProductsResource extends JsonResource
{
    public function toArray($request): array
    {
        $product = Product::find($this->id);
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'slug'               => $this->slug,
            'image'              => $product->imageSources(720, 480),
            'code'              => $this->code,
            'description'       => $this->description,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'contents'          => ModelHasContentsResource::collection($this->contents())->resolve(),
        ];
    }
}
