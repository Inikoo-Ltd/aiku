<?php

/*
 * author Louis Perez
 * created on 28-01-2026-15h-35m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Catalogue\ExternalShop;

use App\Helpers\NaturalLanguage;
use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\ImageResource;
use App\Http\Resources\Traits\HasPriceMetrics;
use App\Models\Catalogue\Product;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductInExternalShopResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Product $product */
        $product = $this;

        
        $pickingFactor = [];
        foreach ((is_string($product->product_org_stocks) ? json_decode($product->product_org_stocks) : []) as $orgStock) {
            $pickingFactor[] = [
                'org_stock_id'   => $orgStock->id,
                'org_stock_code' => $orgStock->code,
                'org_stock_name' => $orgStock->name,
                'note'           => $orgStock->note,
                'is_on_demand'   => $orgStock->is_on_demand,
                'picking_factor' => riseDivisor(
                    divideWithRemainder(findSmallestFactors($orgStock->quantity)),
                    $orgStock->packed_in
                )
            ];
        }

        return [
            'id'                            => $product->id,
            'slug'                          => $product->slug,
            'code'                          => $product->code,
            'name'                          => $product->name,
            'state'                         => $product->state,
            'units'                         => trimDecimalZeros($product->units),
            'unit'                          => $product->unit,
            'price'                         => $product->price,
            'rrp'                           => $product->rrp,
            'created_at'                    => $product->created_at,
            'updated_at'                    => $product->updated_at,
            'stock'                         => $product->available_quantity,
            'currency_code'                 => $product->currency_code,
            'product_org_stocks'            => $pickingFactor,
        ];
    }
}
