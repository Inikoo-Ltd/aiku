<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Actions\Procurement\OrgPartner\Hydrators\OrgPartnerHydrateShoppingListItems;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\PartnerShoppingListItem;
use Lorisleiva\Actions\Concerns\AsAction;

class ReopenPartnerShoppingListItemsOfTransactions
{
    use AsAction;

    /**
     * @param  array<int>  $transactionIds
     */
    public function handle(array $transactionIds): int
    {
        $items = PartnerShoppingListItem::query()
            ->whereIn('transaction_id', $transactionIds)
            ->whereNotNull('partner_organisation_id')
            ->where('state', ShoppingListItemStateEnum::ORDERED)
            ->get();

        foreach ($items as $item) {
            $openSibling = $item->job_order_id || $item->pre_picked_at
                ? null
                : PartnerShoppingListItem::openPartnerLineFor($item->org_partner_id, $item->org_stock_id)->first();

            if ($openSibling) {
                $openSibling->increment('quantity', (float) $item->quantity);
                $item->delete();
            } else {
                $item->update([
                    'state'          => ShoppingListItemStateEnum::OPEN,
                    'transaction_id' => null,
                ]);
            }
        }

        $items->unique('org_partner_id')->each(
            fn (PartnerShoppingListItem $item) => OrgPartnerHydrateShoppingListItems::dispatch($item->orgPartner)
        );

        return $items->count();
    }
}
