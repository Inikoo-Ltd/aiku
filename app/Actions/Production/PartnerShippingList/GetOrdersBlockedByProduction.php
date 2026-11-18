<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Paid orders held back from the warehouse for two days or more because production has not filled their bottles yet.
 * A fill line is matched the way the To Produce board matches it: the product code is its SKO code and the fill artefact's size.
 */
class GetOrdersBlockedByProduction
{
    use AsAction;

    public const int STALE_DAYS = 2;

    public function handle(Organisation|Shop $parent): int
    {
        return Order::query()
            ->where($parent instanceof Shop ? 'orders.shop_id' : 'orders.organisation_id', $parent->id)
            ->where('orders.state', OrderStateEnum::SUBMITTED)
            ->paySettled()
            ->where('orders.submitted_at', '<', today()->subDays(self::STALE_DAYS - 1))
            ->whereExists(
                PartnerShoppingListItem::query()
                    ->join('transactions', 'transactions.id', 'partner_shopping_list_items.transaction_id')
                    ->join('products', fn ($join) => $join->on('products.id', 'transactions.model_id')->where('transactions.model_type', 'Product'))
                    ->join('org_stocks', 'org_stocks.id', 'partner_shopping_list_items.org_stock_id')
                    ->join('artefacts', fn ($join) => $join->whereNull('artefacts.org_stock_id')
                        ->whereNull('artefacts.deleted_at')
                        ->whereIn('artefacts.state', ArtefactStateEnum::launched())
                        ->whereRaw("lower(products.code) = lower(org_stocks.code || '-' || (artefacts.settings->>'fills_size'))"))
                    ->whereColumn('transactions.order_id', 'orders.id')
                    ->where('partner_shopping_list_items.state', ShoppingListItemStateEnum::OPEN)
            )
            ->count();
    }

    /**
     * @return array{count: int, days: int, route: array{name: string, parameters: array<string, string>}}|null
     */
    public function notice(Organisation|Shop $parent): ?array
    {
        $organisation = $parent instanceof Shop ? $parent->organisation : $parent;
        $production   = Production::where('organisation_id', $organisation->id)->orderBy('id')->first();
        if (!$production) {
            return null;
        }

        $count = $this->handle($parent);

        return $count ? [
            'count' => $count,
            'days'  => self::STALE_DAYS,
            'route' => [
                'name'       => 'grp.org.productions.show.to_produce.index',
                'parameters' => ['organisation' => $organisation->slug, 'production' => $production->slug, 'blocking' => '1'],
            ],
        ] : null;
    }
}
