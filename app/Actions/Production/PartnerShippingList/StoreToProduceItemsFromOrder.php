<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Actions\Production\JobOrder\FillToOrder;
use App\Models\Catalogue\Product;
use App\Models\Inventory\OrgStock;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Artefact;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreToProduceItemsFromOrder
{
    use AsAction;

    /**
     * Intercompany orders are left out, since a hub's partner orders are cut from lines already on its
     * To Produce list. The exception is a purchase order to a partner that is not the hub, which
     * arrives as a plain order. That order leaves out bottles filled to order: a fill line holds the
     * order until it is filled, and the partner purchase order needs the delivery note at once.
     *
     * @return array<int, PartnerShoppingListItem>
     */
    public function handle(Order $order, bool $fromPartnerPurchaseOrder = false): array
    {
        $items = [];
        if (!$fromPartnerPurchaseOrder && $order->salesChannel?->code === 'intercompany') {
            return $items;
        }

        $transactions = $order->transactions()
            ->where('model_type', 'Product')
            ->whereDoesntHave('partnerShoppingListItems')
            ->get();

        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            /** @var Product|null $product */
            $product = $transaction->model;
            if (!$product) {
                continue;
            }

            if ($fillArtefact = FillToOrder::make()->artefactFor($product)) {
                if ($fromPartnerPurchaseOrder) {
                    continue;
                }
                $items[] = PartnerShoppingListItem::create([
                    'group_id'        => $order->group_id,
                    'organisation_id' => $order->organisation_id,
                    'stock_id'        => $product->orgStocks->first()->stock_id,
                    'org_stock_id'    => $product->orgStocks->first()->id,
                    'quantity'        => (float) $transaction->quantity_ordered,
                    'transaction_id'  => $transaction->id,
                ]);
                continue;
            }

            /** @var OrgStock $orgStock */
            foreach ($product->orgStocks as $orgStock) {
                $hasArtefact = Artefact::where('organisation_id', $order->organisation_id)
                    ->where('org_stock_id', $orgStock->id)
                    ->exists();
                if (!$hasArtefact) {
                    continue;
                }

                $skosNeeded = (float) $transaction->quantity_ordered * (float) ($orgStock->pivot->quantity ?: 1);
                $shortfall  = round($skosNeeded - max((float) $orgStock->quantity_available, 0), 3);
                if ($shortfall <= 0) {
                    continue;
                }

                $items[] = PartnerShoppingListItem::create([
                    'group_id'        => $order->group_id,
                    'organisation_id' => $order->organisation_id,
                    'stock_id'        => $orgStock->stock_id,
                    'org_stock_id'    => $orgStock->id,
                    'quantity'        => $shortfall,
                    'transaction_id'  => $transaction->id,
                ]);
            }
        }

        return $items;
    }
}
