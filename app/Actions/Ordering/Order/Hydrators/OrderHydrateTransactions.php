<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:53:53 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrderHydrateTransactions implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Order $order): string
    {
        return $order->id;
    }

    public function handle(Order $order): void
    {

        $stats = [
            'number_transactions' => $order->transactions()->count(),
        ];

        if ($order->state == OrderStateEnum::CREATING) {
            $stats['number_transactions_at_submission' ] = $order->transactions()->count();

        }


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'transactions',
                field: 'state',
                enum: TransactionStateEnum::class,
                models: Transaction::class,
                where: function ($q) use ($order) {
                    $q->where('order_id', $order->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'transactions',
                field: 'status',
                enum: TransactionStatusEnum::class,
                models: Transaction::class,
                where: function ($q) use ($order) {
                    $q->where('order_id', $order->id);
                }
            )
        );



        $stats['number_current_transactions'] = $stats['number_transactions'] - $stats['number_transactions_state_cancelled'];

        $order->stats()->update($stats);
    }

}
