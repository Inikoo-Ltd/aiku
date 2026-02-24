<?php

/*
 * author Louis Perez
 * created on 09-02-2026-14h-13m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Catalogue;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductIndexPendingBackInStockReminderResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        return [
            'product_id'                    => $product->product_id,
            'number_of_distinct_reminders'  => $product->number_of_distinct_reminders,
            'code'                          => $product->code,
            'name'                          => $product->name,
            'state'                         => ProductStateEnum::stateIcon()[$this->state],
            'price'                         => $product->price,
            'created_at'                    => $product->created_at,
            'updated_at'                    => $product->updated_at,
            'slug'                          => $product->slug,
        ];
    }
}
