<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-15h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairTransactionSubmittedQuantityOrdered
{
    use WithActionUpdate;


    public function handle(Order $order): void
    {
        foreach ($order->transactions as $transaction) {
            $transaction->update([
                'submitted_quantity_ordered' => $transaction->quantity_ordered
            ]);
        }
    }


    public string $commandSignature = 'repair:transaction_submitted_quantity';

    public function asCommand(Command $command): void
    {
        $count = Order::whereNotIn('state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Order::whereNotIn('state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])->orderBy('id')
            ->chunk(100, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });

    }

}
