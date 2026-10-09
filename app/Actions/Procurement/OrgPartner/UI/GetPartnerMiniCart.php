<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 29 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner\UI;

use App\Actions\Procurement\OrgPartner\GetPartnerBuyingPriceFactor;
use App\Actions\Procurement\OrgPartner\GetPartnerProductionLanes;
use App\Actions\Procurement\OrgPartner\GetPartnerSellingShopIds;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPartnerMiniCart
{
    use AsObject;

    public const int BASKET_LINES_SHOWN = 20;

    public const int ORDERED_LINES_SHOWN = 10;

    public function handle(OrgPartner $orgPartner): array
    {
        $priceSql = PartnerShoppingListItem::pricePerSkoSql(GetPartnerSellingShopIds::run($orgPartner->partner));
        $exchange = $orgPartner->exchangeToOrgCurrency() * GetPartnerBuyingPriceFactor::run($orgPartner);

        $basket  = $this->summary($orgPartner, ShoppingListItemStateEnum::DRAFT, $priceSql);
        $ordered = $this->summary($orgPartner, ShoppingListItemStateEnum::OPEN, $priceSql);

        return [
            'partner_name' => $orgPartner->partner->name,
            'title'        => __('Basket'),
            'list_label'   => __('Go to Basket'),
            'count'        => (int) $basket->lines,
            'total'        => round((float) $basket->value * $exchange, 2),
            'currency'     => $orgPartner->organisation->currency->code,
            'items'        => $this->lines($orgPartner, ShoppingListItemStateEnum::DRAFT, self::BASKET_LINES_SHOWN),
            'listRoute'    => [
                'name'       => 'grp.org.procurement.org_partners.show.shopping_list.index',
                'parameters' => [$orgPartner->organisation->slug, $orgPartner->id],
            ],
            'ordered'      => [
                'count'     => (int) $ordered->lines,
                'total'     => round((float) $ordered->value * $exchange, 2),
                'items'     => $this->withStages($orgPartner, $this->lines($orgPartner, ShoppingListItemStateEnum::OPEN, self::ORDERED_LINES_SHOWN)),
                'listRoute' => [
                    'name'       => 'grp.org.procurement.org_partners.show.shopping_list.sent',
                    'parameters' => [$orgPartner->organisation->slug, $orgPartner->id],
                ],
            ],
        ];
    }

    private function summary(OrgPartner $orgPartner, ShoppingListItemStateEnum $state, string $priceSql): object
    {
        return PartnerShoppingListItem::query()
            ->where('org_partner_id', $orgPartner->id)
            ->where('state', $state)
            ->toBase()
            ->selectRaw("count(*) as lines, coalesce(sum(quantity * coalesce($priceSql, 0)), 0) as value")
            ->first();
    }

    private function lines(OrgPartner $orgPartner, ShoppingListItemStateEnum $state, int $limit): Collection
    {
        return PartnerShoppingListItem::query()
            ->leftJoin('org_stocks', 'org_stocks.id', 'partner_shopping_list_items.org_stock_id')
            ->where('partner_shopping_list_items.org_partner_id', $orgPartner->id)
            ->where('partner_shopping_list_items.state', $state)
            ->select([
                'partner_shopping_list_items.id',
                'partner_shopping_list_items.quantity',
                'partner_shopping_list_items.state',
                'partner_shopping_list_items.pre_picked_at',
                'partner_shopping_list_items.created_at',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
            ])
            ->selectRaw("(select pc.name from product_has_org_stocks phos
                join products pr on pr.id = phos.product_id
                join product_categories pc on pc.id = pr.family_id
                where phos.org_stock_id = org_stocks.id
                limit 1) as family_name")
            ->orderByDesc('partner_shopping_list_items.created_at')
            ->limit($limit)
            ->toBase()
            ->get();
    }

    private function withStages(OrgPartner $orgPartner, Collection $lines): Collection
    {
        $stages = GetPartnerProductionLanes::make()->stagesOf($orgPartner, $lines);

        return $lines->each(fn ($line) => $line->stage = $stages[$line->id]);
    }
}
