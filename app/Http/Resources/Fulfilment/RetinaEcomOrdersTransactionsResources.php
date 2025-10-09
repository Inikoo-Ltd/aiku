<?php

/*
 * Author: Vika Aqordi
 * Created on 08-10-2025-15h-31m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

namespace App\Http\Resources\Fulfilment;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Media;
use App\Models\Web\Webpage;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $date
 * @property mixed $name
 * @property mixed $reference
 * @property mixed $slug
 * @property mixed $state
 * @property mixed $number_item_transactions
 */
class RetinaEcomOrdersTransactionsResources extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        // dd($this);
        $order = $this;
        $media = null;
        if ($order->product_image_id) {
            $media = Media::find($order->product_image_id);
        }

        $webpageUrl = null;
        if ($order->model_type === class_basename(Product::class)) {
            $webpage = Webpage::where('model_id', $order->product_id)
            ->where('model_type', class_basename(Product::class))->first();

            $webpageUrl = $webpage->getUrl();
        }
        return [
            'id'                            => $order->id,
            'slug'                          => $order->slug,
            'state'                         => $order->state,
            'state_icon'                    => OrderStateEnum::stateIcon()[$order->state->value],
            "reference"                     => $order->reference,
            "state"                         => $order->state,
            "customer_reference"            => $order->customer_reference,
            "is_premium_dispatch"           => $order->is_premium_dispatch,
            "has_extra_packing"             => $order->has_extra_packing,
            "has_insurance"                 => $order->has_insurance,
            "number_item_transactions"      => $order->number_item_transactions,
            "date"                          => $order->date,
            "total_amount"                  => $order->total_amount,
            "payment_amount"                => $order->payment_amount,
        ];
    }
}
