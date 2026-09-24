<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * The customer asks for more once the warehouse has finished picking, so the extra items cannot
 * join the delivery note any more. They go on an order of their own, and both orders carry a note
 * the warehouse reads so the two leave in one parcel instead of relying on somebody typing it.
 */
class StoreFollowUpOrder extends OrgAction
{
    use WithOrderingEditAuthorisation;

    public const string SHIP_TOGETHER_MARKER = '📦';

    public static function offersFollowUp(Order $order): bool
    {
        return SaveOrderModification::isEditedInAiku($order)
            && in_array($order->state, [OrderStateEnum::PICKED, OrderStateEnum::PACKING, OrderStateEnum::PACKED, OrderStateEnum::FINALISED]);
    }

    /**
     * @throws \Throwable
     */
    public function handle(Order $order): Order
    {
        if (!self::offersFollowUp($order)) {
            abort(422, __('A follow-up order is only for orders the warehouse has picked and not dispatched yet'));
        }

        return DB::transaction(function () use ($order) {
            $followUpOrder = StoreOrder::make()->action($order->customerClient ?? $order->customer, []);

            $this->shipTogether($followUpOrder, $order);
            $this->shipTogether($order, $followUpOrder);

            return $followUpOrder->refresh();
        });
    }

    /**
     * @throws \Throwable
     */
    protected function shipTogether(Order $order, Order $otherOrder): void
    {
        UpdateOrder::make()->action($order, [
            'private_warehouse_note' => collect([
                $order->private_warehouse_note,
                self::SHIP_TOGETHER_MARKER.' '.__('Send together with order :reference', ['reference' => $otherOrder->reference]),
            ])->filter()->implode(' — ')
        ]);
    }

    /**
     * @return array{reference: string, url: string}
     */
    public function jsonResponse(Order $order): array
    {
        return [
            'reference' => $order->reference,
            'url'       => route('grp.org.shops.show.crm.customers.show.orders.show', [
                $order->organisation->slug,
                $order->shop->slug,
                $order->customer->slug,
                $order->slug
            ]),
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order);
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order): Order
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order);
    }
}
