<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Actions\GoodsIn\StockDelivery\StoreStockDelivery;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\OrgStock;
use App\Models\Ordering\Order;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class StorePartnerStockDeliveryFromDeliveryNote
{
    use AsAction;

    public function reference(DeliveryNote $deliveryNote, Organisation $buyerOrganisation): string
    {
        $isTaken = StockDelivery::where('organisation_id', $buyerOrganisation->id)
            ->whereRaw('lower(reference) = lower(?)', [$deliveryNote->reference])
            ->exists();

        if ($isTaken) {
            return GetSerialReference::run(
                container: $buyerOrganisation,
                modelType: SerialReferenceModelEnum::STOCK_DELIVERY
            );
        }

        return $deliveryNote->reference;
    }

    public function handle(DeliveryNote $deliveryNote): ?StockDelivery
    {
        $order      = $deliveryNote->orders()->first();
        $orgPartner = $this->resolveOrgPartner($deliveryNote);

        if (!$order || !$orgPartner) {
            return null;
        }

        $purchaseOrder = $this->auroraPurchaseOrder($orgPartner, $order);
        if ($purchaseOrder?->stockDeliveries()->exists()) {
            return null;
        }

        $buyerOrganisation = $orgPartner->organisation;

        $stockDelivery = StoreStockDelivery::make()->action(
            $orgPartner,
            [
                'reference'        => $this->reference($deliveryNote, $buyerOrganisation),
                'state'            => StockDeliveryStateEnum::CONFIRMED,
                'confirmed_at'     => now(),
                'date'             => now(),
                'delivery_note_id' => $deliveryNote->id,
            ],
            strict: false
        );

        if ($purchaseOrder) {
            $stockDelivery->purchaseOrders()->attach($purchaseOrder->id);
            $stockDelivery->update(['number_purchase_orders' => 1]);
        }

        $deliveryNoteItems          = $deliveryNote->deliveryNoteItems()->with(['transaction', 'orgStock'])->get();
        $unitsRequiredByTransaction = $deliveryNoteItems->groupBy('transaction_id')->map->sum('quantity_required');

        foreach ($deliveryNoteItems->groupBy(fn ($deliveryNoteItem) => $deliveryNoteItem->orgStock?->stock_id) as $stockId => $stockDeliveryNoteItems) {
            $buyerOrgStock = OrgStock::where('organisation_id', $buyerOrganisation->id)
                ->where('stock_id', $stockId)
                ->first();

            if (!$buyerOrgStock) {
                Log::warning('Intercompany stock delivery: no buyer org stock', [
                    'delivery_note_item_id' => $stockDeliveryNoteItems->first()->id,
                    'org_partner_id'        => $orgPartner->id,
                ]);
                continue;
            }

            $unitQuantity = 0;
            $netAmount    = 0;
            foreach ($stockDeliveryNoteItems as $deliveryNoteItem) {
                $unitQuantity     += (float) $deliveryNoteItem->quantity_required * (float) ($deliveryNoteItem->orgStock?->packed_in ?: 1);
                $transactionUnits = (float) $unitsRequiredByTransaction->get($deliveryNoteItem->transaction_id, 0);
                if ($transactionUnits > 0 && $deliveryNote->type !== DeliveryNoteTypeEnum::REPLACEMENT) {
                    $netAmount += round((float) $deliveryNoteItem->transaction?->net_amount * (float) $deliveryNoteItem->quantity_required / $transactionUnits, 2);
                }
            }

            StoreStockDeliveryItem::make()->action(
                $stockDelivery,
                null,
                $buyerOrgStock,
                [
                    'state'         => StockDeliveryItemStateEnum::CONFIRMED,
                    'unit_quantity' => $unitQuantity,
                    'net_amount'    => $netAmount,
                ],
                strict: false
            );
        }

        return $stockDelivery;
    }

    public function resolveOrgPartner(DeliveryNote $deliveryNote): ?OrgPartner
    {
        $order = $deliveryNote->orders()->first();
        if (!$order || (($order->source_id || $deliveryNote->source_id) && !$order->handled_in_aurora)) {
            return null;
        }

        return OrgPartner::where('partner_id', $deliveryNote->organisation_id)
            ->get()
            ->first(function (OrgPartner $orgPartner) use ($order) {
                return in_array($order->customer_id, data_get($orgPartner->data, 'intercompany_customers', []))
                    || ($order->customer?->as_organisation_id && $order->customer->as_organisation_id === $orgPartner->organisation_id);
            });
    }

    /**
     * Orders still in Aurora when the seller moved to aiku were bought on a purchase order named after the order: the delivery settles it once received.
     */
    public function auroraPurchaseOrder(OrgPartner $orgPartner, Order $order): ?PurchaseOrder
    {
        if (!$order->handled_in_aurora) {
            return null;
        }

        return $orgPartner->purchaseOrders()
            ->where('state', PurchaseOrderStateEnum::CONFIRMED)
            ->whereRaw('reference ~* ?', ['-'.preg_quote($order->reference).'(-|$)'])
            ->first();
    }
}
