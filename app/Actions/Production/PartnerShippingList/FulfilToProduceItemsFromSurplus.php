<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\PartnerShoppingListItem;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilToProduceItemsFromSurplus
{
    use AsAction;

    /**
     * Surplus booked into stock covers the oldest own lines still waiting in the backlog, customer
     * orders and restock requests alike: a covered line is dismissed, a partly covered one keeps
     * only what is still missing.
     * ponytail: lines already in Preparing and partner lines are left alone, someone chose to make
     * those or they ship to a bay; widen if the board keeps showing covered cards.
     */
    public function handle(OrgStock $orgStock, float $surplusSkos): int
    {
        $fulfilled = 0;

        $lines = PartnerShoppingListItem::query()
            ->where('organisation_id', $orgStock->organisation_id)
            ->where('stock_id', $orgStock->stock_id)
            ->whereNull('partner_organisation_id')
            ->whereNull('job_order_id')
            ->whereNull('preparing_at')
            ->whereNull('pre_picked_at')
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($lines as $line) {
            if ($surplusSkos <= 0) {
                break;
            }

            $quantity = (float) $line->quantity;

            if ($surplusSkos >= $quantity) {
                $line->update(['state' => ShoppingListItemStateEnum::DISMISSED]);
                $surplusSkos -= $quantity;
                $fulfilled++;
            } else {
                $line->update(['quantity' => round($quantity - $surplusSkos, 3)]);
                $surplusSkos = 0;
            }
        }

        return $fulfilled;
    }
}
