<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 May 2025 16:12:46 Central Indonesia Time, Sanur, change, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Helpers\NaturalLanguage;
use App\Http\Resources\Catalogue\TagResource;
use App\Http\Resources\HasSelfCall;
use App\Models\Catalogue\Product;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Helpers\ImageResource;
use Illuminate\Support\Facades\DB;

class WebBlockProductResourceEcom extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Product $product */
        $product = $this->resource;


        $tradeUnits = $product->tradeUnits;


        $tradeUnits->loadMissing(['ingredients']);

        $ingredients = $tradeUnits->flatMap(function ($tradeUnit) {
            return $tradeUnit->ingredients->pluck('name');
        })->unique()->values()->all();


        $specifications = [
            'country_of_origin' => NaturalLanguage::make()->country($product->country_of_origin),
            'ingredients'       => $ingredients,
            'gross_weight'      => $product->gross_weight,
            'marketing_weights' => $tradeUnits->pluck('marketing_weights')->flatten()->filter()->values()->all(),
            'barcode'           => $product->barcode,
            'dimensions'        => NaturalLanguage::make()->dimensions(json_encode($product->marketing_dimensions)),
            'cpnp'              => $product->cpnp_number,
            'net_weight'        => $product->marketing_weight,
            'unit'              => $product->unit,
        ];

        $favourite = false;
        $quantityOrdered = 0;
        $transactionId = null;
        if ($request->user()) {
            $customer = $request->user()->customer;
            if ($customer) {
                $favourite = $customer->favourites()->where('product_id', $product->id)->first();

                $basket = $customer->orderInBasket;
                if ($basket) {
                    $transaction = DB::table('transactions')->where('order_id', $basket->id)
                        ->where('model_id', $product->id)->where('model_type', 'Product')
                        ->whereNull('deleted_at')
                        ->select('id', 'quantity_ordered')
                        ->first();
                    if ($transaction) {
                        $quantityOrdered = $transaction->quantity_ordered;
                        $transactionId = $transaction->id;
                    }
                }

            }
        }

        return [
            'slug'              => $product->slug,
            'code'              => $product->code,
            'name'              => $product->name,
            'description'       => $product->description,
            'description_title' => $product->description_title,
            'description_extra' => $product->description_extra,
            'stock'             => $product->available_quantity,
            'specifications'    => $tradeUnits->count() > 0 ? $specifications : null,
            'contents'          => ModelHasContentsResource::collection($product->contents)->toArray($request),
            'id'                => $product->id,
            'image_id'          => $product->image_id,
            'currency_code'     => $product->currency->code,
            'rrp'               => $product->rrp,
            'price'             => $product->price,
            'status'            => $product->status,
            'state'             => $product->state,
            'units'             => $product->units,
            'unit'              => $product->unit,
            'web_images'        => $product->web_images,
            'created_at'        => $product->created_at,
            'updated_at'        => $product->updated_at,
            'images'            => ImageResource::collection($product->images)->toArray($request),
            'tags'              => TagResource::collection($product->tradeUnitTagsViaTradeUnits())->toArray($request),
            'transaction_id'      => $transactionId,
            'quantity_ordered'      => (int) $quantityOrdered,
            'quantity_ordered_new'  => (int) $quantityOrdered,  // To editable in Frontend
            'is_favourite'          => $favourite && !$favourite->unfavourited_at ?? false,
        ];
    }


}
