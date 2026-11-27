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
     * $fillsOnly picks up fill lines whose product had no SKO when the order was submitted (HELP-4786).
     *
     * Bottles filled to order take their oil from a bulk SKO. When that bulk is made in-house and can not
     * cover the order's bottles, the missing oil is raised as a restock line of the bulk with no order
     * line on it: a bulk line tied to the order line counts as a bottle still to fill (HELP-4989).
     *
     * @return array<int, PartnerShoppingListItem>
     */
    public function handle(Order $order, bool $fromPartnerPurchaseOrder = false, bool $fillsOnly = false): array
    {
        $items = [];
        $bulks = [];
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
                $bulk    = FillToOrder::make()->bulkOrgStock($product);
                $items[] = PartnerShoppingListItem::create([
                    'group_id'        => $order->group_id,
                    'organisation_id' => $order->organisation_id,
                    'stock_id'        => $bulk->stock_id,
                    'org_stock_id'    => $bulk->id,
                    'quantity'        => (float) $transaction->quantity_ordered,
                    'transaction_id'  => $transaction->id,
                ]);
                $bulks[$bulk->id] ??= ['org_stock' => $bulk, 'needed' => 0.0];
                $bulks[$bulk->id]['needed'] += (float) $transaction->quantity_ordered * (float) ($bulk->pivot->quantity ?: 1);
                continue;
            }

            if ($fillsOnly) {
                continue;
            }

            /** @var OrgStock $orgStock */
            foreach ($product->orgStocks as $orgStock) {
                $items[] = $this->shortfallLine($order, $orgStock, (float) $transaction->quantity_ordered * (float) ($orgStock->pivot->quantity ?: 1), $transaction);
            }
        }

        foreach ($bulks as $bulk) {
            $items[] = $this->shortfallLine($order, $bulk['org_stock'], $bulk['needed']);
        }

        return array_values(array_filter($items));
    }

    private function shortfallLine(Order $order, OrgStock $orgStock, float $skosNeeded, ?Transaction $transaction = null): ?PartnerShoppingListItem
    {
        if (!Artefact::where('organisation_id', $order->organisation_id)->where('org_stock_id', $orgStock->id)->exists()) {
            return null;
        }

        $shortfall = round($skosNeeded - max((float) $orgStock->quantity_available, 0), 3);
        if ($shortfall <= 0) {
            return null;
        }

        return PartnerShoppingListItem::create([
            'group_id'        => $order->group_id,
            'organisation_id' => $order->organisation_id,
            'stock_id'        => $orgStock->stock_id,
            'org_stock_id'    => $orgStock->id,
            'quantity'        => $shortfall,
            'transaction_id'  => $transaction?->id,
        ]);
    }
}
