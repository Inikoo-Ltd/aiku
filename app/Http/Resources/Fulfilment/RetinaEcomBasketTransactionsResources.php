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
 * @property mixed $id
 * @property mixed $date
 * @property mixed $name
 * @property mixed $reference
 * @property mixed $slug
 * @property mixed $state
 * @property mixed $number_item_transactions
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

            $webpageUrl = $webpage->getUrl();
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
            // 'image'               => $transaction->product_id ? Product::find($transaction->product_id)->imageSources(200, 200) : null,
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
