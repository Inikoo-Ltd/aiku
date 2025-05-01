<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 09:40:37 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Ordering;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property string $state
 * @property string $shop_slug
 * @property string $date
 * @property string $reference
 * @property mixed $id
 * @property mixed $quantity_ordered
 * @property mixed $status
 * @property mixed $quantity_bonus
 * @property mixed $quantity_dispatched
 * @property mixed $quantity_fail
 * @property mixed $quantity_cancelled
 * @property mixed $gross_amount
 * @property mixed $net_amount
 * @property string $asset_code
 * @property string $asset_name
 * @property string $asset_type
 * @property string $product_slug
 * @property string $currency_code
 * @property mixed $order_id
 */
class TransactionsResource extends JsonResource
{
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

            'deleteRoute' => [
                'name'       => 'grp.models.transaction.delete',
                'parameters' => [
                    'transaction' => $transaction->id
                ],
                'method'     => 'delete'
            ],
            'updateRoute' => [
                'name'       => 'grp.models.transaction.update',
                'parameters' => [
                    'transaction' => $transaction->id
                ],
                'method'     => 'patch'
            ],
        ];
    }
}
