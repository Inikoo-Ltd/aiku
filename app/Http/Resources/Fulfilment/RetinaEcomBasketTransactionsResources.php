<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Fulfilment;

use App\Models\Catalogue\Product;
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
            'product_slug'        => $transaction->product_slug,
            'created_at'          => $transaction->created_at,
            'currency_code'       => $transaction->currency_code,
            'image'               => Product::find($transaction->product_id)->imageSources(200, 200),
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
                    'order'       => $transaction->order_id,
                    'transaction' => $transaction->id
                ],
                'method'     => 'patch'
            ],
        ];
    }
}
