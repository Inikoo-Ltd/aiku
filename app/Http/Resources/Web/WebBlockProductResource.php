<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 May 2025 16:12:46 Central Indonesia Time, Sanur, change, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\Catalogue\TagResource;
use App\Http\Resources\HasSelfCall;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\CRM\Favourite;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Helpers\ImageResource;

class WebBlockProductResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        $favourites = [];
        if (auth()->check()) {
            $customer = $request->user()->customer;

            $portfolioChannelIds = $customer->portfolios()->where('item_id', $product->id)
                ->where('item_type', class_basename(Product::class))
                ->distinct()
                ->pluck('customer_sales_channel_id')
                ->toArray();

            /** @var Favourite $favourite */
            $favourite = $customer->favourites()->where('product_id', $product->id)->first();

            $favourites = [
                'exist_in_portfolios_channel' => $portfolioChannelIds,
                'is_exist_in_all_channel'     => $this->checkExistInAllChannels($customer),
                'is_favourite'                => $favourite && !$favourite->unfavourited_at,
            ];
        }

        return [
            'slug'        => $product->slug,
            'code'        => $product->code,
            'name'        => $product->name,
            'description' => $product->description,
            'stock'       => $product->available_quantity,
            'contents'    => ModelHasContentsResource::collection($product->contents)->toArray($request),
            'id'              => $product->id,
            'slug'            => $product->slug,
            'image_id'        => $product->image_id,
            'code'            => $product->code,
            'name'            => $product->name,
            'price'           => $product->price,
            'currency_code'   => $product->currency->code,
            'description'     => $product->description,
            'state'           => $product->state,
            'rrp'             => $product->rrp,
            'price'           => $product->price,
            'status'           => $product->status,
            'state'           => $product->state,
            'description'     => $product->description,
            'units'           => $product->units,
            'unit'            => $product->unit,
            'created_at'      => $product->created_at,
            'updated_at'      => $product->updated_at,
            'images'          => ImageResource::collection($product->images)->toArray($request),
            'tags' => TagResource::collection($product->tradeUnitTagsViaTradeUnits())->toArray($request),
            ...$favourites
        ];
    }

    public function checkExistInAllChannels(Customer $customer): bool
    {
        // Get all available channels
        $totalChannels = $customer->customerSalesChannels->count();

        // Count how many portfolios this product has across all channels
        $portfolioChannels = $customer->portfolios()->where('item_id', $this->id)
            ->where('item_type', class_basename(Product::class))
            ->distinct('customer_sales_channel_id')
            ->count();

        return $portfolioChannels === $totalChannels;
    }
}
