<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026, Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Masters\MasterAsset\Json\GetMasterProductsPricingSales;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Records, per org stock, how the last 12 months of sales rank it among the org stocks this
 * organisation makes (1 top 10%, 2 next 20%, 3 next 30%, 4 the rest) among those that sold, 4 for anything with no sales.
 *
 * Also records, the one non-partner customer who took the largest part of the
 * quantity dispatched over the last 12 months and the share that part is of the total.
 * A partner intercompany customer is never recorded as the top customer.
 */
class OrgStockHydrateTopCustomerShare
{
    use AsAction;

    public string $commandSignature = 'org_stocks:hydrate_top_customer_share {organisation?}';

    public static function partnerCustomersSql(int $organisationId): string
    {
        return "select op.customer_id from org_partners op where op.organisation_id = $organisationId and op.customer_id is not null
            union
            select ic.value::int from org_partners op,
                jsonb_each_text(case when jsonb_typeof(op.data->'intercompany_customers') = 'object' then op.data->'intercompany_customers' else '{}'::jsonb end) ic
            where op.organisation_id = $organisationId";
    }

    public function handle(Organisation $organisation): int
    {
        $this->hydrateSalesRank($organisation);

        $partners = self::partnerCustomersSql($organisation->id);

        return DB::update("
            with per_customer as (
                select dni.org_stock_id, dn.customer_id, sum(dni.quantity_dispatched) as quantity
                from delivery_note_items dni
                join delivery_notes dn on dn.id = dni.delivery_note_id
                where dni.organisation_id = ?
                    and dni.created_at >= now() - interval '12 months'
                    and dni.quantity_dispatched > 0
                    and dni.org_stock_id is not null
                    and dn.customer_id is not null
                group by dni.org_stock_id, dn.customer_id
            ),
            totals as (
                select org_stock_id, sum(quantity) as total from per_customer group by org_stock_id
            ),
            tops as (
                select distinct on (org_stock_id) org_stock_id, customer_id, quantity
                from per_customer
                order by org_stock_id, quantity desc
            )
            update org_stock_stats s set
                top_customer_id = case when tops.customer_id in ($partners) then null else tops.customer_id end,
                top_customer_dispatch_share = case when tops.customer_id in ($partners) or totals.total is null then null else round(tops.quantity / totals.total, 4) end,
                top_customer_dispatched_12m = totals.total
            from org_stocks os
            left join tops on tops.org_stock_id = os.id
            left join totals on totals.org_stock_id = os.id
            where s.org_stock_id = os.id and os.organisation_id = ?
        ", [$organisation->id, $organisation->id]);
    }

    private function hydrateSalesRank(Organisation $organisation): void
    {
        $periods = GetMasterProductsPricingSales::periodKeys('year')['current'];
        $in      = implode(',', array_fill(0, count($periods), '?'));

        DB::update("
            with revenue as (
                select os.id as org_stock_id, coalesce(sum(r.sales_org_currency_external), 0) as sales
                from org_stocks os
                join (select distinct org_stock_id from artefacts where deleted_at is null and org_stock_id is not null) a on a.org_stock_id = os.id
                left join org_stock_time_series ts on ts.org_stock_id = os.id and ts.frequency = 'monthly'
                left join org_stock_time_series_records r on r.org_stock_time_series_id = ts.id and r.period in ($in)
                where os.organisation_id = ?
                group by os.id
            ),
            ranked as (
                select org_stock_id, sales,
                    row_number() over (partition by sales > 0 order by sales desc, org_stock_id)::numeric / count(*) over (partition by sales > 0) as position
                from revenue
            )
            update org_stock_stats s set
                sales_12m = ranked.sales,
                sales_rank_12m = case
                    when ranked.sales <= 0 then 4
                    when ranked.position <= 0.10 then 1
                    when ranked.position <= 0.30 then 2
                    when ranked.position <= 0.60 then 3
                    else 4 end
            from ranked
            where s.org_stock_id = ranked.org_stock_id
        ", [...$periods, $organisation->id]);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $organisations = Organisation::query()
            ->whereIn('id', DB::table('org_stocks')->select('organisation_id')->distinct())
            ->when($command->argument('organisation'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get();

        foreach ($organisations as $organisation) {
            $command->info($organisation->slug.': '.$this->handle($organisation).' org stocks');
        }

        return 0;
    }
}
