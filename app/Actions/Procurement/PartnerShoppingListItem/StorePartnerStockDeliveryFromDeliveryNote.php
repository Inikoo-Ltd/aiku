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
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class StorePartnerStockDeliveryFromDeliveryNote
{
    use AsAction;

    public function handle(DeliveryNote $deliveryNote): ?StockDelivery
    {
        $order      = $deliveryNote->orders()->first();
        $orgPartner = $this->resolveOrgPartner($deliveryNote);

        if (!$order || !$orgPartner) {
            return null;
        }

        $buyerOrganisation = $orgPartner->organisation;

        $stockDelivery = StoreStockDelivery::make()->action(
            $orgPartner,
            [
                'reference'        => GetSerialReference::run(
                    container: $buyerOrganisation,
                    modelType: SerialReferenceModelEnum::STOCK_DELIVERY
                ),
                'state'            => StockDeliveryStateEnum::CONFIRMED,
                'confirmed_at'     => now(),
                'date'             => now(),
                'delivery_note_id' => $deliveryNote->id,
            ],
            strict: false
        );

        $orderNetAmount     = (float) $order->net_amount;
        $totalUnitsRequired = (float) $deliveryNote->deliveryNoteItems()->sum('quantity_required');

        foreach ($deliveryNote->deliveryNoteItems as $deliveryNoteItem) {
            $buyerOrgStock = OrgStock::where('organisation_id', $buyerOrganisation->id)
                ->where('stock_id', $deliveryNoteItem->orgStock?->stock_id)
                ->first();

            if (!$buyerOrgStock) {
                Log::warning('Intercompany stock delivery: no buyer org stock', [
                    'delivery_note_item_id' => $deliveryNoteItem->id,
                    'org_partner_id'        => $orgPartner->id,
                ]);
                continue;
            }

            $unitQuantity = (float) $deliveryNoteItem->quantity_required;
            // ponytail: pro-rate order value by units, per-line pricing if transfer pricing ever diverges
            $netAmount = $totalUnitsRequired > 0
                ? round($orderNetAmount * $unitQuantity / $totalUnitsRequired, 2)
                : 0;

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
        if (!$order || $order->salesChannel?->code !== 'intercompany') {
            return null;
        }

        return OrgPartner::where('partner_id', $deliveryNote->organisation_id)
            ->get()
            ->first(function (OrgPartner $orgPartner) use ($order) {
                return in_array(
                    $order->customer_id,
                    data_get($orgPartner->data, 'intercompany_customers', []),
                );
            });
    }
}
