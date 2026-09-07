<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 29 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner\UI;

use App\Actions\Procurement\OrgPartner\GetPartnerBuyingPriceFactor;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPartnerMiniCart
{
    use AsObject;

    public function handle(OrgPartner $orgPartner): array
    {
        $items = PartnerShoppingListItem::query()
            ->leftJoin('org_stocks', 'org_stocks.id', 'partner_shopping_list_items.org_stock_id')
            ->where('partner_shopping_list_items.org_partner_id', $orgPartner->id)
            ->where('partner_shopping_list_items.state', ShoppingListItemStateEnum::OPEN->value)
            ->select([
                'partner_shopping_list_items.id',
                'partner_shopping_list_items.quantity',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
            ])
            ->selectRaw("(select pc.name from product_has_org_stocks phos
                join products pr on pr.id = phos.product_id
                join product_categories pc on pc.id = pr.family_id
                where phos.org_stock_id = org_stocks.id
                limit 1) as family_name")
            ->orderByDesc('partner_shopping_list_items.created_at')
            ->get();

        return [
            'partner_name' => $orgPartner->partner->name,
            'count'      => $orgPartner->stats->number_open_shopping_list_items,
            'total'      => round((float) $orgPartner->stats->open_shopping_list_items_value * $orgPartner->exchangeToOrgCurrency() * GetPartnerBuyingPriceFactor::run($orgPartner), 2),
            'currency'   => $orgPartner->organisation->currency->code,
            'items'      => $items,
            'listRoute'  => [
                'name'       => 'grp.org.procurement.org_partners.show.shopping_list.index',
                'parameters' => [$orgPartner->organisation->slug, $orgPartner->id],
            ],
        ];
    }
}
