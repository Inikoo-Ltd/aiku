<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner\Hydrators;

use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgPartnerHydrateShoppingListItems implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(OrgPartner $orgPartner): string
    {
        return $orgPartner->id;
    }

    public function handle(OrgPartner $orgPartner): void
    {
        $orgPartner->stats()->update([
            'number_shopping_list_items'      => $orgPartner->shoppingListItems()->count(),
            'number_open_shopping_list_items' => $orgPartner->shoppingListItems()
                ->whereIn('state', [
                    ShoppingListItemStateEnum::OPEN,
                    ShoppingListItemStateEnum::DISMISS_PROPOSED,
                ])
                ->count(),
            'open_shopping_list_items_value' => (float) DB::table('partner_shopping_list_items')
                ->where('org_partner_id', $orgPartner->id)
                ->whereIn('state', [
                    ShoppingListItemStateEnum::OPEN->value,
                    ShoppingListItemStateEnum::DISMISS_PROPOSED->value,
                ])
                ->whereNull('deleted_at')
                ->selectRaw('coalesce(sum(quantity * coalesce('.PartnerShoppingListItem::pricePerSkoSql().', 0)), 0) as total')
                ->value('total'),
        ]);
    }
}
