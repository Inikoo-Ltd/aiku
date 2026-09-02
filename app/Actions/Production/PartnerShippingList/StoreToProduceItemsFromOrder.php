<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

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

    /** @return array<int, PartnerShoppingListItem> */
    public function handle(Order $order): array
    {
        $items = [];
        if ($order->salesChannel?->code === 'intercompany') {
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

            /** @var OrgStock $orgStock */
            foreach ($product->orgStocks as $orgStock) {
                $hasArtefact = Artefact::where('organisation_id', $order->organisation_id)
                    ->where('org_stock_id', $orgStock->id)
                    ->exists();
                if (!$hasArtefact) {
                    continue;
                }

                $skosNeeded = (float) $transaction->quantity_ordered * (float) ($orgStock->pivot->quantity ?: 1);
                $shortfall  = round($skosNeeded - (float) $orgStock->quantity_in_locations, 3);
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
