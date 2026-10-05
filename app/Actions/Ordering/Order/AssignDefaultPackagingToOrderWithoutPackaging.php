<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Ordering\Order;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AssignDefaultPackagingToOrderWithoutPackaging
{
    use AsAction;

    public function handle(Order $order): bool
    {
        if (!$this->isEligible($order)) {
            return false;
        }

        return DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first();

            if (!$order || !$this->isEligible($order)) {
                return false;
            }

            $defaultPackaging = $order->shop->defaultPackaging();

            UpdateOrderPackaging::make()->action($order, [
                'packaging_id' => $defaultPackaging->id,
                'leaflet_ids'  => $order->insert_types ?? [],
            ]);

            $this->orderDeliveryNote($order)?->update(['packaging_id' => $defaultPackaging->id]);

            CalculateOrderTotalAmounts::run($order->refresh());

            return true;
        });
    }

    public function forDeliveryNote(DeliveryNote $deliveryNote): bool
    {
        if ($deliveryNote->type !== DeliveryNoteTypeEnum::ORDER) {
            return false;
        }

        $order = $deliveryNote->orders()->first();

        return $order && $this->handle($order);
    }

    private function isEligible(Order $order): bool
    {
        $shop = $order->shop;

        if (!$shop?->hasPackagingAndInserts() || !$shop->defaultPackaging()) {
            return false;
        }

        if ($order->state === OrderStateEnum::CREATING || !$order->state->acceptsPackagingFallback()) {
            return false;
        }

        if ($order->packaging_id || $order->transactions()->where('model_type', 'Packaging')->exists()) {
            return false;
        }

        if ($order->invoices()->where('type', InvoiceTypeEnum::INVOICE)->where('in_process', false)->exists()) {
            return false;
        }

        $deliveryNote = $this->orderDeliveryNote($order);

        return !$deliveryNote
            || (!$deliveryNote->packaging_id && $deliveryNote->state->acceptsPackagingFallback());
    }

    private function orderDeliveryNote(Order $order): ?DeliveryNote
    {
        return $order->deliveryNotes()->where('type', DeliveryNoteTypeEnum::ORDER)->first();
    }
}
