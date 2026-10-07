<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\Ordering\PreOrder;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A paid pre-order with its goods here goes to the warehouse. The delivery note takes over
 * keeping the goods, so the pre-order stops reserving them only after it exists.
 */
class ReleasePreOrder implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(PreOrder $preOrder): string
    {
        return (string) $preOrder->id;
    }

    /**
     * Only a paid pre-order goes, and only once: the row lock makes a payment arriving while staff
     * press the button, or the allocation job, find it already released.
     *
     * @throws \Throwable
     */
    public function handle(PreOrder $preOrder): PreOrder
    {
        return DB::transaction(function () use ($preOrder) {
            $preOrder->lockInState(PreOrderStateEnum::open());

            if ($preOrder->order->refresh()->pay_status != OrderPayStatusEnum::PAID) {
                throw ValidationException::withMessages([
                    'pre_order' => __('The balance of this pre-order is not paid yet.'),
                ]);
            }

            $wasHoldingStock = in_array($preOrder->state, PreOrderStateEnum::holdingStock());

            $preOrder->update([
                'state'           => PreOrderStateEnum::RELEASED,
                'released_at'     => now(),
                'balance_paid_at' => $preOrder->balance_requested_at ? now() : null,
            ]);

            SendOrderToWarehouse::make()->action($preOrder->order, []);

            if ($wasHoldingStock) {
                HydratePreOrderReservedStock::run(HydratePreOrderReservedStock::make()->orgStockIds($preOrder));
            }

            return $preOrder;
        });
    }

    /**
     * Queued when a balance payment lands: staff may have released or cancelled it meanwhile.
     *
     * @throws \Throwable
     */
    public function asJob(PreOrder $preOrder): void
    {
        try {
            $this->handle($preOrder);
        } catch (ValidationException) {
            return;
        }
    }
}
