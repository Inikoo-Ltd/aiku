<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 09:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\DeliveryNote\UpdateState\FinaliseAndDispatchDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\StartHandlingDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStatePacked;
use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateShipments;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Shipper;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Staging only: walks a paid dropshipping test order through the warehouse (pick, pack, a shipment
 * with a test tracking number on a shipper with no carrier API, finalise and dispatch), so a
 * customer testing the API on the staging mirror can see a dispatched order with tracking
 * (PRE-88, CUS-1790). Everything that could stop it half way is checked before the first write.
 */
class DispatchTestOrder
{
    use AsAction;

    public string $commandSignature = 'order:dispatch_test {order : Order id (not reference: staging references clash with production)} {user : Username doing the warehouse steps} {--tracking= : Test tracking number}';

    public function handle(Order $order, User $user, string $tracking): Order
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Test orders are only dispatched outside production');
        }

        if ($order->shop->type != ShopTypeEnum::DROPSHIPPING) {
            throw new RuntimeException('Only dropshipping test orders are dispatched this way');
        }

        if ($order->pay_status != OrderPayStatusEnum::PAID) {
            throw new RuntimeException('The order is not paid');
        }

        $shipper = $this->getShipper($order);

        $deliveryNotes = $order->deliveryNotes()
            ->whereNotIn('delivery_notes.state', [DeliveryNoteStateEnum::DISPATCHED, DeliveryNoteStateEnum::CANCELLED])
            ->get();

        if ($deliveryNotes->isEmpty()) {
            throw new RuntimeException('The order has no delivery note left to dispatch');
        }

        foreach ($deliveryNotes as $deliveryNote) {
            $this->ensureDispatchable($deliveryNote);
        }

        foreach ($deliveryNotes as $deliveryNote) {
            $this->dispatchDeliveryNote($deliveryNote, $user, $shipper, $tracking);
        }

        OrderHydrateShipments::run($order->id);

        return $order->refresh();
    }

    private function getShipper(Order $order): Shipper
    {
        if ($order->is_shipper_locked && $order->shipper_id) {
            $shipper = $order->shipper;
            if ($shipper->api_shipper) {
                throw new RuntimeException("The customer chose $shipper->name, which calls a carrier API");
            }

            return $shipper;
        }

        $shipper = Shipper::where('organisation_id', $order->organisation_id)
            ->where('status', true)
            ->whereNull('api_shipper')
            ->orderBy('id')
            ->get()
            ->first(fn (Shipper $shipper) => !$shipper->isSupervisorOnly());

        if (!$shipper) {
            throw new RuntimeException('The organisation has no active shipper without a carrier API');
        }

        return $shipper;
    }

    private function ensureDispatchable(DeliveryNote $deliveryNote): void
    {
        if ($deliveryNote->hasBlockingItems()) {
            throw new RuntimeException("$deliveryNote->reference has items waiting for the warehouse or customer services");
        }

        foreach ($this->itemsToPick($deliveryNote) as $deliveryNoteItem) {
            if ($this->stockLocations($deliveryNote, $deliveryNoteItem)->sum('quantity') < $this->outstanding($deliveryNoteItem)) {
                throw new RuntimeException("Not enough stock in the warehouse to pick an item of $deliveryNote->reference");
            }
        }
    }

    private function dispatchDeliveryNote(DeliveryNote $deliveryNote, User $user, Shipper $shipper, string $tracking): void
    {
        $deliveryNote = StartHandlingDeliveryNote::make()->action($deliveryNote, $user);

        foreach ($this->itemsToPick($deliveryNote) as $deliveryNoteItem) {
            foreach ($this->stockLocations($deliveryNote, $deliveryNoteItem) as $locationOrgStock) {
                $quantity = min($this->outstanding($deliveryNoteItem), (float)$locationOrgStock->quantity);
                if ($quantity <= 0) {
                    break;
                }

                StorePicking::make()->action($deliveryNoteItem, $user, [
                    'location_org_stock_id' => $locationOrgStock->id,
                    'quantity'              => $quantity,
                    'picker_user_id'        => $user->id,
                ]);
                $deliveryNoteItem->refresh();
            }
        }

        $deliveryNote = UpdateDeliveryNoteStatePacked::make()->action($deliveryNote->refresh(), $user);

        if (!$deliveryNote->shipments()->exists()) {
            StoreShipment::make()->action($deliveryNote, $shipper, ['tracking' => $tracking]);
        }

        if ($deliveryNote->refresh()->state != DeliveryNoteStateEnum::DISPATCHED) {
            FinaliseAndDispatchDeliveryNote::make()->action($deliveryNote);
        }
    }

    /**
     * @return Collection<int, DeliveryNoteItem>
     */
    private function itemsToPick(DeliveryNote $deliveryNote): Collection
    {
        return $deliveryNote->deliveryNoteItems()
            ->where('state', '!=', DeliveryNoteItemStateEnum::CANCELLED)
            ->get()
            ->filter(fn (DeliveryNoteItem $deliveryNoteItem) => $this->outstanding($deliveryNoteItem) > 0);
    }

    private function outstanding(DeliveryNoteItem $deliveryNoteItem): float
    {
        return (float)$deliveryNoteItem->quantity_required
            - (float)$deliveryNoteItem->quantity_picked
            - (float)$deliveryNoteItem->quantity_waiting_warehouse
            - (float)$deliveryNoteItem->quantity_waiting_crm;
    }

    /**
     * @return Collection<int, LocationOrgStock>
     */
    private function stockLocations(DeliveryNote $deliveryNote, DeliveryNoteItem $deliveryNoteItem): Collection
    {
        return LocationOrgStock::where('org_stock_id', $deliveryNoteItem->org_stock_id)
            ->where('warehouse_id', $deliveryNote->warehouse_id)
            ->where('quantity', '>', 0)
            ->orderByDesc('quantity')
            ->get();
    }

    public function asCommand(Command $command): int
    {
        $order = Order::findOrFail($command->argument('order'));
        $user  = User::where('username', $command->argument('user'))->firstOrFail();

        $order = $this->handle($order, $user, $command->option('tracking') ?: 'TEST-'.$order->reference);

        $command->info("$order->reference is {$order->state->value}, tracking: $order->tracking_number");

        return 0;
    }
}
