<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Fulfilment;

use App\Http\Resources\Helpers\ImageResource;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Media;
use App\Models\Web\Webpage;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Properties expected on the underlying Transaction resource.
 * @property int $id
 * @property string $state
 * @property string $status
 * @property int $quantity_ordered
 * @property int $quantity_bonus
 * @property int $quantity_dispatched
 * @property int $quantity_fail
 * @property int $quantity_cancelled
 * @property numeric-string|int|float|null $gross_amount
 * @property numeric-string|int|float|null $net_amount
 * @property string|null $asset_code
 * @property string|null $asset_name
 * @property string|null $asset_type
 * @property numeric-string|int|float|null $price
 * @property string|null $product_slug
 * @property int|null $product_image_id
 * @property int|null $product_id
 * @property string|null $model_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property numeric-string|int|float|null $available_quantity
 * @property string|null $currency_code
 */
class RetinaEcomBasketTransactionsResources extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        $transaction = $this;
        $media = null;
        if ($transaction->product_image_id) {
            $media = Media::find($transaction->product_image_id);
        }

        $webpageUrl = null;
        if ($transaction->model_type === class_basename(Product::class)) {
            $webpage = Webpage::where('model_id', $transaction->product_id)
            ->where('model_type', class_basename(Product::class))->first();

            $webpageUrl = $webpage?->getCanonicalUrl();
        }

        return [
            'id'                  => $transaction->id,
            'state'               => $transaction->state,
            'status'              => $transaction->status,
            'quantity_ordered'    => intVal($transaction->quantity_ordered),
            'quantity_bonus'      => intVal($transaction->quantity_bonus),
            'quantity_dispatched' => intVal($transaction->quantity_dispatched),
            'quantity_fail'       => intVal($transaction->quantity_fail),
            'quantity_cancelled'  => intVal($transaction->quantity_cancelled),
            'gross_amount'        => $transaction->gross_amount,
            'net_amount'          => $transaction->net_amount,
            'asset_code'          => $transaction->asset_code,
            'asset_name'          => $transaction->asset_name,
            'asset_type'          => $transaction->asset_type,
            'price'               => $transaction->price,
            'product_slug'        => $transaction->product_slug,
            'image'               => $transaction->product_image_id ? ImageResource::make($media)->getArray() : null,
            'created_at'          => $transaction->created_at,
            'available_quantity'    => $transaction->available_quantity,
            'currency_code'       => $transaction->currency_code,
            'webpage_url'         => $webpageUrl,
            'offers_data'         => $transaction->offers_data,
            'deleteRoute' => [
                'name'       => 'retina.models.transaction.delete',
                'parameters' => [
                    'transaction' => $transaction->id
                ],
                'method'     => 'delete'
            ],
            'updateRoute' => [
                'name'       => 'retina.models.transaction.update',
                'parameters' => [
                    'transaction' => $transaction->id
                ],
                'method'     => 'patch'
            ],
        ];
    }
}
