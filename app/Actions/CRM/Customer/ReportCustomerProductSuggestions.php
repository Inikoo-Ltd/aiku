<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * AI suggestions against bought together: of the products first suggested to a customer at least --days
 * ago, how many they added to a basket and how many they ordered within --days, per half.
 *
 * ponytail: a product counts wherever it was added from (dashboard, website, repeat order), and an add
 * counts even if the line was later removed from the basket; both halves are measured the same way, so
 * the difference between them is what the suggestions did.
 */
class ReportCustomerProductSuggestions
{
    use AsAction;

    public string $commandSignature = 'customers:product-suggestions-report {--days=14}';

    public string $commandDescription = 'Compare add to basket and orders from AI suggestions with bought together suggestions';

    public function handle(int $days): array
    {
        return DB::select(
            "with shown as (
                select arm, model is not null as by_ai, customer_id, product_id, min(generated_at) as first_shown_at
                from customer_product_suggestions
                group by arm, by_ai, customer_id, product_id
                having min(generated_at) <= now() - make_interval(days => ?)
            )
            select shown.arm, shown.by_ai,
                count(distinct shown.customer_id) as customers,
                count(*) as suggested,
                count(*) filter (where exists (
                    select 1 from transactions t
                    where t.customer_id = shown.customer_id and t.model_type = 'Product' and t.model_id = shown.product_id
                      and t.created_at between shown.first_shown_at and shown.first_shown_at + make_interval(days => ?)
                )) as added_to_basket,
                count(*) filter (where exists (
                    select 1 from transactions t join orders o on o.id = t.order_id
                    where t.customer_id = shown.customer_id and t.model_type = 'Product' and t.model_id = shown.product_id
                      and t.deleted_at is null and o.state not in (?, ?)
                      and t.submitted_at between shown.first_shown_at and shown.first_shown_at + make_interval(days => ?)
                )) as ordered
            from shown
            group by shown.arm, shown.by_ai
            order by shown.arm, shown.by_ai",
            [$days, $days, OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value, $days]
        );
    }

    public function asCommand(Command $command): int
    {
        $rows = $this->handle((int) $command->option('days'));

        $command->table(
            ['Half', 'Picked by AI', 'Customers', 'Suggested', 'Added to basket', 'Ordered', 'Ordered per 100'],
            array_map(fn ($row) => [
                $row->arm,
                $row->by_ai ? 'yes' : 'no',
                $row->customers,
                $row->suggested,
                $row->added_to_basket,
                $row->ordered,
                $row->suggested ? round(100 * $row->ordered / $row->suggested, 2) : 0,
            ], $rows)
        );

        return 0;
    }
}
