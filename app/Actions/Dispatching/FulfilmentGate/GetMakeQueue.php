<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 30 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\FulfilmentGate;

use App\InertiaTable\InertiaTable;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMakeQueue
{
    use AsObject;

    /**
     * @return array{usage_days: int, horizon_days: int, shelf_life_safety_factor: float, weight_blocked_paid: float, weight_partner_quantity: float, weight_stock_cover: float}
     */
    public function settings(Organisation $organisation): array
    {
        $settings = Arr::get($organisation->settings, 'make_queue', []);

        return [
            'usage_days'               => (int) ($settings['usage_days'] ?? 90),
            'horizon_days'             => (int) ($settings['horizon_days'] ?? 90),
            'shelf_life_safety_factor' => (float) ($settings['shelf_life_safety_factor'] ?? 0.5),
            'weight_blocked_paid'      => (float) ($settings['weight_blocked_paid'] ?? 1),
            'weight_partner_quantity'  => (float) ($settings['weight_partner_quantity'] ?? 5),
            'weight_stock_cover'       => (float) ($settings['weight_stock_cover'] ?? 100),
        ];
    }

    public function handle(Organisation $organisation): LengthAwarePaginator
    {
        $config = $this->settings($organisation);
        $prefix = 'make_queue';
        InertiaTable::updateQueryBuilderParameters($prefix);

        $usage = DB::table('delivery_note_items')
            ->join('delivery_notes', 'delivery_notes.id', 'delivery_note_items.delivery_note_id')
            ->where('delivery_notes.state', 'dispatched')
            ->where('delivery_notes.dispatched_at', '>=', now()->subDays($config['usage_days']))
            ->groupBy('delivery_note_items.org_stock_id')
            ->select('delivery_note_items.org_stock_id', DB::raw('sum(delivery_note_items.quantity_picked) as quantity'));

        $gateDemand = DB::table('transactions')
            ->join('orders', 'orders.id', 'transactions.order_id')
            ->join('product_has_org_stocks', 'product_has_org_stocks.product_id', 'transactions.model_id')
            ->join('org_stocks as gate_org_stocks', 'gate_org_stocks.id', 'product_has_org_stocks.org_stock_id')
            ->where('transactions.model_type', 'Product')
            ->whereNull('transactions.deleted_at')
            ->where('orders.organisation_id', $organisation->id)
            ->whereNotNull('orders.at_gate_at')
            ->groupBy('product_has_org_stocks.org_stock_id')
            ->select(
                'product_has_org_stocks.org_stock_id',
                DB::raw("greatest(sum(coalesce(product_has_org_stocks.quantity, 1) * (transactions.quantity_ordered + transactions.quantity_bonus)) - max(gate_org_stocks.quantity_available), 0) as shortfall_quantity"),
                DB::raw("sum(transactions.net_amount) filter (where orders.pay_status = 'paid' and gate_org_stocks.quantity_available < coalesce(product_has_org_stocks.quantity, 1) * (transactions.quantity_ordered + transactions.quantity_bonus)) as blocked_paid_amount")
            );

        $partnerDemand = DB::table('partner_shopping_list_items')
            ->join('org_stocks as seller_org_stocks', function ($join) use ($organisation) {
                $join->on('seller_org_stocks.stock_id', 'partner_shopping_list_items.stock_id')
                    ->where('seller_org_stocks.organisation_id', $organisation->id);
            })
            ->where('partner_shopping_list_items.partner_organisation_id', $organisation->id)
            ->where('partner_shopping_list_items.state', 'open')
            ->whereNull('partner_shopping_list_items.deleted_at')
            ->groupBy('seller_org_stocks.id')
            ->select('seller_org_stocks.id as org_stock_id', DB::raw('sum(partner_shopping_list_items.quantity) as quantity'));

        $usageDaily = 'coalesce(usage.quantity, 0) / '.$config['usage_days'];
        $shelfCap   = 'least('.$config['horizon_days'].', coalesce(artefacts.shelf_life_days * '.$config['shelf_life_safety_factor'].', '.$config['horizon_days'].'))';

        // ponytail: deterministic explainable score, weights are org settings; AI planner pass later
        $score = '('
            .$config['weight_blocked_paid'].' * coalesce(gate_demand.blocked_paid_amount, 0)'
            .' + '.$config['weight_partner_quantity'].' * coalesce(partner_demand.quantity, 0)'
            .' + '.$config['weight_stock_cover'].' * ('.$usageDaily.') / (org_stocks.quantity_available + 1)'
            .')';

        $suggested = 'ceil(greatest('
            .'coalesce(gate_demand.shortfall_quantity, 0), '
            .'coalesce(partner_demand.quantity, 0), '
            .'('.$usageDaily.') * '.$shelfCap.' - org_stocks.quantity_available'
            .'))';

        $base = DB::table('artefacts')
            ->join('org_stocks', 'org_stocks.id', 'artefacts.org_stock_id')
            ->leftJoinSub($usage, 'usage', 'usage.org_stock_id', 'org_stocks.id')
            ->leftJoinSub($gateDemand, 'gate_demand', 'gate_demand.org_stock_id', 'org_stocks.id')
            ->leftJoinSub($partnerDemand, 'partner_demand', 'partner_demand.org_stock_id', 'org_stocks.id')
            ->where('artefacts.organisation_id', $organisation->id)
            ->whereRaw($suggested.' > 0')
            ->whereRaw($score.' > 0')
            ->select([
                'artefacts.id as artefact_id',
                'artefacts.code as artefact_code',
                'artefacts.shelf_life_days',
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.quantity_available',
                DB::raw('coalesce(usage.quantity, 0) as usage_quantity'),
                DB::raw("round(($usageDaily)::numeric, 2) as usage_daily"),
                DB::raw("case when coalesce(usage.quantity, 0) > 0 then round((org_stocks.quantity_available / ($usageDaily))::numeric, 1) end as days_cover"),
                DB::raw('coalesce(gate_demand.shortfall_quantity, 0) as shortfall_quantity'),
                DB::raw('coalesce(gate_demand.blocked_paid_amount, 0) as blocked_paid_amount'),
                DB::raw('coalesce(partner_demand.quantity, 0) as partner_quantity'),
                DB::raw($suggested.' as suggested_quantity'),
                DB::raw("round(($score)::numeric, 2) as score"),
            ]);

        return QueryBuilder::for(Order::query()->withoutGlobalScopes()->fromSub($base, $prefix))
            ->defaultSort('-score')
            ->allowedSorts(['org_stock_code', 'quantity_available', 'days_cover', 'blocked_paid_amount', 'partner_quantity', 'suggested_quantity', 'score'])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }
}
