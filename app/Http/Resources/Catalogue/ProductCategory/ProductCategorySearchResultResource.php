<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 May 2026 11:18:32 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue\ProductCategory;

use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use JsonSerializable;

class ProductCategorySearchResultResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var ProductCategory $productCategory */
        $productCategory = $this;

        return [
            'id'    => $productCategory->id,
            'code'  => $productCategory->code,
            'name'  => $productCategory->name,
            'image' => Arr::get($productCategory->web_images, 'main.thumbnail'),

        ];
    }
}
