<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Dashboard;

use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Per dashboard section, the last --days: how many times customers added a product from it, and how many
 * of those products were ordered: the basket they went to was submitted with the product still in it.
 */
class ReportRetinaDashboardBasketAdds
{
    use AsAction;

    public string $commandSignature = 'retina:dashboard-basket-adds-report {--days=30}';

    public string $commandDescription = 'Adds to basket from each customer dashboard section and how many were ordered';

    public function handle(int $days): array
    {
        return DB::select(
            "with adds as (
                select section, customer_id, order_id, product_id, count(*) as clicks
                from retina_dashboard_basket_adds
                where created_at >= now() - make_interval(days => ?)
                group by section, customer_id, order_id, product_id
            )
            select adds.section,
                sum(adds.clicks) as clicks,
                count(distinct adds.customer_id) as customers,
                count(*) as products_added,
                count(ordered.id) as products_ordered,
                count(distinct ordered.order_id) as orders,
                coalesce(sum(ordered.grp_net_amount), 0) as ordered_net_amount
            from adds
            left join lateral (
                select t.id, t.order_id, t.grp_net_amount
                from transactions t join orders o on o.id = t.order_id
                where t.order_id = adds.order_id and t.model_type = 'Product' and t.model_id = adds.product_id
                  and t.deleted_at is null and o.state not in (?, ?)
                limit 1
            ) ordered on true
            group by adds.section
            order by clicks desc",
            [$days, OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value]
        );
    }

    public function asCommand(Command $command): int
    {
        $command->table(
            ['Section', 'Clicks', 'Customers', 'Products added', 'Products ordered', 'Orders', 'Ordered net (group currency)'],
            array_map(fn ($row) => [
                $row->section,
                $row->clicks,
                $row->customers,
                $row->products_added,
                $row->products_ordered,
                $row->orders,
                $row->ordered_net_amount,
            ], $this->handle((int) $command->option('days')))
        );

        return 0;
    }
}
