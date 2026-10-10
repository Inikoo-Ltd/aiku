<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What an agent's staff should act on today: orders waiting for their confirmation, orders past the date they
 * promised, confirmed orders not yet in a container, and the containers themselves. Drafts the buying
 * organisation has not sent yet are not theirs to act on, so they are left out.
 */
class GetAgentDashboardPurchaseOrders
{
    use AsObject;

    private const int CONFIRMATION_DAYS = 7;

    private const int STALE_DAYS = 30;

    public function handle(Agent $agent, Organisation $organisation): array
    {
        $groupToAgentExchange = GetCurrencyExchange::run($organisation->group->currency, $organisation->currency);
        $currency             = $groupToAgentExchange ? $organisation->currency->code : $organisation->group->currency->code;
        $toAgentCurrency      = 'grp_exchange * '.(float) ($groupToAgentExchange ?: 1);
        $sent     = [PurchaseOrderStateEnum::SUBMITTED->value, PurchaseOrderStateEnum::CONFIRMED->value];

        $openPurchaseOrders = fn (): Builder => DB::table('purchase_orders')
            ->where('purchase_orders.agent_id', $agent->id)
            ->where('purchase_orders.parent_type', 'OrgSupplier')
            ->whereNull('purchase_orders.deleted_at')
            ->whereIn('purchase_orders.state', $sent);

        $summary = $openPurchaseOrders()->selectRaw(
            "count(*) as open_count,
            coalesce(sum(cost_total * {$toAgentCurrency}), 0) as open_value,
            count(*) filter (where state = 'submitted') as unconfirmed,
            count(*) filter (where state = 'submitted' and submitted_at < now() - interval '".self::CONFIRMATION_DAYS." days') as unconfirmed_over_week,
            count(*) filter (where state = 'submitted' and submitted_at < now() - interval '".self::STALE_DAYS." days') as unconfirmed_stale,
            count(*) filter (where estimated_received_at < now()) as overdue,
            coalesce(sum(cost_total * {$toAgentCurrency}) filter (where estimated_received_at < now()), 0) as overdue_value,
            count(*) filter (where estimated_received_at >= now() and estimated_received_at < now() + interval '14 days') as due_soon,
            count(*) filter (where state = 'confirmed' and delivery_state = 'in_process') as confirmed_without_container,
            coalesce(sum(cost_total * {$toAgentCurrency}) filter (where state = 'confirmed' and delivery_state = 'in_process'), 0) as confirmed_without_container_value"
        )->first();

        $containers = DB::table('stock_deliveries')
            ->where('agent_id', $agent->id)
            ->whereNull('deleted_at')
            ->selectRaw('state, count(*) as number')
            ->groupBy('state')
            ->pluck('number', 'state');

        $route = fn (string $name, array $parameters = [], array $query = []) => [
            'name'       => $name,
            'parameters' => array_filter(['organisation' => $organisation->slug, ...$parameters, '_query' => $query ?: null]),
        ];

        $purchaseOrdersRoute = fn (array $query) => $route('grp.org.procurement.purchase_orders.index', [], $query);
        $containersRoute     = fn (StockDeliveryStateEnum ...$states) => $route(
            'grp.org.procurement.stock_deliveries.index',
            [],
            ['elements[state]' => implode(',', array_map(fn ($state) => $state->value, $states))]
        );
        $containerCount = fn (StockDeliveryStateEnum ...$states) => array_sum(array_map(fn ($state) => (int) ($containers[$state->value] ?? 0), $states));

        return [
            'currency' => $currency,
            'summary'  => [
                'open_count'                        => (int) $summary->open_count,
                'open_value'                        => (float) $summary->open_value,
                'unconfirmed'                       => (int) $summary->unconfirmed,
                'unconfirmed_over_week'             => (int) $summary->unconfirmed_over_week,
                'unconfirmed_stale'                 => (int) $summary->unconfirmed_stale,
                'overdue'                           => (int) $summary->overdue,
                'overdue_value'                     => (float) $summary->overdue_value,
                'due_soon'                          => (int) $summary->due_soon,
                'confirmed_without_container'       => (int) $summary->confirmed_without_container,
                'confirmed_without_container_value' => (float) $summary->confirmed_without_container_value,
                'confirmation_days'                 => self::CONFIRMATION_DAYS,
                'stale_days'                        => self::STALE_DAYS,
                'routes'                            => [
                    'open'                        => $purchaseOrdersRoute(['elements[state]' => implode(',', $sent), 'sort' => 'date']),
                    'unconfirmed'                 => $purchaseOrdersRoute(['elements[state]' => PurchaseOrderStateEnum::SUBMITTED->value, 'sort' => 'date']),
                    'unconfirmed_over_week'       => $purchaseOrdersRoute(['elements[attention]' => 'unconfirmed_over_7_days', 'sort' => 'date']),
                    'unconfirmed_stale'           => $purchaseOrdersRoute(['elements[attention]' => 'unconfirmed_over_30_days', 'sort' => 'date']),
                    'overdue'                     => $purchaseOrdersRoute(['elements[attention]' => 'past_expected_date', 'sort' => 'date']),
                    'confirmed_without_container' => $purchaseOrdersRoute([
                        'elements[state]'          => PurchaseOrderStateEnum::CONFIRMED->value,
                        'elements[delivery_state]' => PurchaseOrderDeliveryStateEnum::IN_PROCESS->value,
                    ]),
                ],
            ],
            'attention' => $openPurchaseOrders()
                ->leftJoin('organisations', 'organisations.id', '=', 'purchase_orders.organisation_id')
                ->where(function (Builder $query) {
                    $query->where('estimated_received_at', '<', now())
                        ->orWhere(function (Builder $query) {
                            $query->where('purchase_orders.state', PurchaseOrderStateEnum::SUBMITTED->value)
                                ->where('submitted_at', '<', now()->subDays(self::CONFIRMATION_DAYS));
                        });
                })
                ->selectRaw(
                    'purchase_orders.id, purchase_orders.slug, purchase_orders.reference, purchase_orders.parent_name, purchase_orders.state,
                    purchase_orders.cost_total * purchase_orders.'.$toAgentCurrency.' as amount, purchase_orders.agent_order_reference, organisations.name as organisation,
                    (estimated_received_at < now()) as is_overdue,
                    greatest(0, floor(extract(epoch from now() - estimated_received_at) / 86400))::int as days_late,
                    floor(extract(epoch from now() - submitted_at) / 86400)::int as days_waiting'
                )
                ->orderByRaw('(estimated_received_at < now()) desc nulls last')
                ->orderByRaw('case when estimated_received_at < now() then estimated_received_at else submitted_at end asc')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'id'              => $row->id,
                    'reference'       => $row->reference,
                    'supplier'        => $row->parent_name,
                    'organisation'    => $row->organisation,
                    'agent_order'     => $row->agent_order_reference,
                    'state'           => $row->state,
                    'state_label'     => PurchaseOrderStateEnum::labels()[$row->state],
                    'amount'          => (float) $row->amount,
                    'is_overdue'      => (bool) $row->is_overdue,
                    'days_late'       => (int) $row->days_late,
                    'days_waiting'    => (int) $row->days_waiting,
                    'route'           => $route('grp.org.procurement.purchase_orders.show', ['purchaseOrder' => $row->slug]),
                ])
                ->values(),
            'suppliers' => $openPurchaseOrders()
                ->join('org_suppliers', 'org_suppliers.id', '=', 'purchase_orders.parent_id')
                ->selectRaw(
                    "purchase_orders.parent_id, purchase_orders.parent_name, org_suppliers.slug,
                    count(*) as open_count, sum(cost_total * {$toAgentCurrency}) as open_value,
                    count(*) filter (where state = 'submitted') as unconfirmed,
                    count(*) filter (where estimated_received_at < now()) as overdue,
                    floor(extract(epoch from now() - min(submitted_at) filter (where state = 'submitted')) / 86400)::int as oldest_unconfirmed_days"
                )
                ->groupBy('purchase_orders.parent_id', 'purchase_orders.parent_name', 'org_suppliers.slug')
                ->orderByDesc('open_value')
                ->limit(8)
                ->get()
                ->map(fn ($row) => [
                    'id'                      => $row->parent_id,
                    'name'                    => $row->parent_name,
                    'open_count'              => (int) $row->open_count,
                    'open_value'              => (float) $row->open_value,
                    'unconfirmed'             => (int) $row->unconfirmed,
                    'overdue'                 => (int) $row->overdue,
                    'oldest_unconfirmed_days' => $row->oldest_unconfirmed_days === null ? null : (int) $row->oldest_unconfirmed_days,
                    'route'                   => $route('grp.org.procurement.org_suppliers.show', ['orgSupplier' => $row->slug]),
                ])
                ->values(),
            'agent_orders' => $openPurchaseOrders()
                ->whereNotNull('purchase_orders.agent_order_reference')
                ->selectRaw(
                    "agent_order_reference, count(*) as open_count, sum(cost_total * {$toAgentCurrency}) as open_value,
                    count(*) filter (where state = 'submitted') as unconfirmed,
                    count(*) filter (where state = 'confirmed') as confirmed,
                    count(*) filter (where estimated_received_at < now()) as overdue,
                    count(distinct parent_id) as suppliers,
                    floor(extract(epoch from now() - min(submitted_at)) / 86400)::int as oldest_days,
                    min(estimated_received_at) as expected_at"
                )
                ->groupBy('agent_order_reference')
                ->orderByRaw('min(submitted_at) asc')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'reference'   => $row->agent_order_reference,
                    'open_count'  => (int) $row->open_count,
                    'open_value'  => (float) $row->open_value,
                    'unconfirmed' => (int) $row->unconfirmed,
                    'confirmed'   => (int) $row->confirmed,
                    'overdue'     => (int) $row->overdue,
                    'suppliers'   => (int) $row->suppliers,
                    'oldest_days' => (int) $row->oldest_days,
                    'expected_at' => $row->expected_at,
                    'route'       => $purchaseOrdersRoute(['filter[global]' => $row->agent_order_reference, 'elements[state]' => implode(',', $sent)]),
                ])
                ->values(),
            'organisations' => $openPurchaseOrders()
                ->join('organisations', 'organisations.id', '=', 'purchase_orders.organisation_id')
                ->selectRaw('organisations.name, organisations.code, count(*) as open_count, sum(cost_total * '.$toAgentCurrency.') as open_value')
                ->groupBy('organisations.id', 'organisations.name', 'organisations.code')
                ->orderByDesc('open_value')
                ->get()
                ->map(fn ($row) => [
                    'name'       => $row->name,
                    'code'       => $row->code,
                    'open_count' => (int) $row->open_count,
                    'open_value' => (float) $row->open_value,
                ])
                ->values(),
            'containers' => [
                [
                    'key'   => 'preparing',
                    'label' => __('Preparing'),
                    'value' => $containerCount(StockDeliveryStateEnum::IN_PROCESS, StockDeliveryStateEnum::CONFIRMED),
                    'route' => $containersRoute(StockDeliveryStateEnum::IN_PROCESS, StockDeliveryStateEnum::CONFIRMED),
                ],
                [
                    'key'   => 'ready',
                    'label' => __('Ready to ship'),
                    'value' => $containerCount(StockDeliveryStateEnum::READY_TO_SHIP),
                    'route' => $containersRoute(StockDeliveryStateEnum::READY_TO_SHIP),
                ],
                [
                    'key'   => 'dispatched',
                    'label' => __('On the way'),
                    'value' => $containerCount(StockDeliveryStateEnum::DISPATCHED),
                    'route' => $containersRoute(StockDeliveryStateEnum::DISPATCHED),
                ],
                [
                    'key'   => 'arrived',
                    'label' => __('Arrived, being checked'),
                    'value' => $containerCount(StockDeliveryStateEnum::RECEIVED, StockDeliveryStateEnum::CHECKED, StockDeliveryStateEnum::BOOKING_IN),
                    'route' => $containersRoute(StockDeliveryStateEnum::RECEIVED, StockDeliveryStateEnum::CHECKED, StockDeliveryStateEnum::BOOKING_IN),
                ],
            ],
        ];
    }
}
