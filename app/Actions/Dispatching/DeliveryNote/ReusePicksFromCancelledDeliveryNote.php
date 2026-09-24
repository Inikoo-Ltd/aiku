<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\GoodsIn\ReturnDeliveryNoteItem\CalculateReturnDeliveryNoteItemTotalSowed;
use App\Actions\GoodsIn\Sowing\StoreSowing;
use App\Actions\OrgAction;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;

class ReusePicksFromCancelledDeliveryNote extends OrgAction
{
    /**
     * HELP-3329. A delivery note cancelled with a return leaves its picked goods off the shelf,
     * waiting on the cancellation return to be put away. When the order is sent to the warehouse
     * again those goods are already on the trolley and go straight into the new note, so picking
     * them from the shelf a second time took the stock out twice.
     *
     * Whatever is still outstanding on the order's open cancellation returns is put away and
     * picked again at the same location in one step. The shelf nets to zero, the return line is
     * handled, and the new pick is an ordinary pick, so undoing, trimming or cancelling it again
     * behaves like any other. A line whose original location is gone, or with nobody to credit
     * the pick to, is left on the return for the warehouse to put away by hand.
     *
     * @throws \Throwable
     */
    public function handle(Order $order, DeliveryNote $deliveryNote): void
    {
        DB::transaction(function () use ($order, $deliveryNote) {
            $returnItems = ReturnDeliveryNoteItem::query()
                ->whereHas('returnDeliveryNote', fn ($query) => $query
                    ->where('order_id', $order->id)
                    ->where('type', ReturnDeliveryNoteTypeEnum::CANCELLATION)
                    ->whereIn('state', [ReturnDeliveryNoteStateEnum::RECEIVED, ReturnDeliveryNoteStateEnum::RETURNING]))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($returnItems as $returnItem) {
                $this->reuse($returnItem, $deliveryNote);
            }
        });
    }

    private function reuse(ReturnDeliveryNoteItem $returnItem, DeliveryNote $deliveryNote): void
    {
        $onTrolley = $this->outstandingOnReturn($returnItem);
        if ($onTrolley <= 0) {
            return;
        }

        /** @var Picking|null $originalPicking */
        $originalPicking = Picking::query()
            ->where('delivery_note_item_id', $returnItem->delivery_note_items_id)
            ->where('type', PickingTypeEnum::PICK)
            ->whereNotNull('location_id')
            ->orderBy('id')
            ->first();

        $picker = $originalPicking?->picker;
        if (!$picker) {
            return;
        }

        $locationOrgStock = LocationOrgStock::where('location_id', $originalPicking->location_id)
            ->where('org_stock_id', $returnItem->org_stock_id)
            ->first();
        if (!$locationOrgStock || (float)$locationOrgStock->quantity < 0) {
            return;
        }

        $deliveryNoteItems = $deliveryNote->deliveryNoteItems()
            ->where('org_stock_id', $returnItem->org_stock_id)
            ->orderBy('id')
            ->get();

        foreach ($deliveryNoteItems as $deliveryNoteItem) {
            $quantity = min($onTrolley, $this->leftToPick($deliveryNoteItem));
            if ($quantity <= 0) {
                continue;
            }

            StoreSowing::make()->action($returnItem, $picker, [
                'location_org_stock_id' => $locationOrgStock->id,
                'quantity'              => $quantity,
                'sower_user_id'         => $picker->id,
            ]);
            CalculateReturnDeliveryNoteItemTotalSowed::make()->action($returnItem);

            StorePicking::make()->action($deliveryNoteItem, $picker, [
                'location_org_stock_id' => $locationOrgStock->id,
                'quantity'              => $quantity,
                'picker_user_id'        => $picker->id,
            ]);

            $onTrolley -= $quantity;
            if ($onTrolley <= 0) {
                return;
            }
        }
    }

    private function outstandingOnReturn(ReturnDeliveryNoteItem $returnItem): float
    {
        return (float)$returnItem->total_expected_qty
            - (float)$returnItem->total_item_returned
            - (float)$returnItem->total_item_not_returned
            - (float)$returnItem->total_item_damaged;
    }

    private function leftToPick(DeliveryNoteItem $deliveryNoteItem): float
    {
        $deliveryNoteItem->refresh();

        return (float)$deliveryNoteItem->quantity_required
            - (float)$deliveryNoteItem->quantity_picked
            - (float)$deliveryNoteItem->quantity_waiting_warehouse
            - (float)$deliveryNoteItem->quantity_waiting_crm;
    }

    public function action(Order $order, DeliveryNote $deliveryNote): void
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, []);

        $this->handle($order, $deliveryNote);
    }
}
