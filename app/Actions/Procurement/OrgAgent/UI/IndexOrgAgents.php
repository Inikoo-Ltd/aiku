<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 19:07:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\UI;

use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\GetAgentStockCoverBuckets;
use App\Actions\Procurement\OrgAgent\GetAgentSupplierPerformance;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexOrgAgents extends OrgAction
{
    use WithProcurementAuthorisation;

    private const COVER_BUCKETS = ['out', 'w1', 'w2', 'w3'];

    /**
     * A stock delivery not dispatched yet is the next container being filled at the agent.
     */
    private const NEXT_CONTAINER_STATES = [
        StockDeliveryStateEnum::IN_PROCESS->value,
        StockDeliveryStateEnum::CONFIRMED->value,
        StockDeliveryStateEnum::READY_TO_SHIP->value,
    ];

    /**
     * @return Collection<int, OrgAgent>
     */
    public function handle(Organisation $organisation): Collection
    {
        return OrgAgent::where('organisation_id', $organisation->id)
            ->with(['agent.organisation', 'agent.currency', 'stats'])
            ->get()
            ->sortBy(fn (OrgAgent $orgAgent) => [!$orgAgent->status, $orgAgent->agent->code])
            ->values();
    }

    public function asController(Organisation $organisation, ActionRequest $request): Collection
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    /**
     * @return array<string, mixed>
     */
    public function agentCard(OrgAgent $orgAgent): array
    {
        $agent    = $orgAgent->agent;
        $location = $agent->organisation?->location ?? [];

        return [
            'id'                   => $orgAgent->id,
            'slug'                 => $orgAgent->slug,
            'code'                 => $agent->code,
            'name'                 => $agent->name,
            'status'               => $orgAgent->status,
            'country_code'         => $location[0] ?? null,
            'country_name'         => $location[1] ?? null,
            'currency_code'        => $agent->currency?->code,
            'suppliers'            => (int) $orgAgent->stats?->number_org_suppliers,
            'last_submitted_at'    => $this->lastSubmittedAt($orgAgent),
            'pipeline'             => $this->pipeline($orgAgent),
            'current'              => $this->agentOrderRows($orgAgent)->concat($this->stockDeliveryRows($orgAgent))->values()->all(),
        ];
    }

    private function agentPurchaseOrders(OrgAgent $orgAgent): Builder
    {
        return DB::table('purchase_orders')
            ->where('organisation_id', $orgAgent->organisation_id)
            ->where('agent_id', $orgAgent->agent_id)
            ->where('parent_type', 'OrgSupplier')
            ->whereNull('deleted_at');
    }

    private function lastSubmittedAt(OrgAgent $orgAgent): ?string
    {
        return $this->agentPurchaseOrders($orgAgent)->max('submitted_at');
    }

    /**
     * Where the open supplier orders placed through the agent are, counted per supplier order
     * since the supplier orders of one agent order move at their own pace. Arrival at the
     * agent's warehouse is not recorded yet, so it is not a stage.
     *
     * @return array{stages: array<int, array{stage: string, label: string, orders: int, value: float}>, late: int, no_eta: int}
     */
    private function pipeline(OrgAgent $orgAgent): array
    {
        $stage = "case
            when state = 'in_process' then 'preparing'
            when state = 'submitted' then 'submitted'
            when delivery_state = 'ready_to_ship' then 'ready_to_ship'
            when delivery_state = 'dispatched' then 'dispatched'
            else 'confirmed' end";

        $rows = $this->agentPurchaseOrders($orgAgent)
            ->whereIn('state', array_merge([PurchaseOrderStateEnum::IN_PROCESS->value], GetAgentSupplierPerformance::OPEN_STATES))
            ->whereNotIn('delivery_state', GetAgentSupplierPerformance::CLOSED_DELIVERY_STATES)
            ->whereRaw("(data -> 'housekeeping') is null")
            ->groupByRaw($stage)
            ->selectRaw("$stage as stage, count(*) as orders,
                coalesce(sum(cost_total * coalesce(org_exchange, 1)), 0) as value,
                count(*) filter (where state <> 'in_process' and estimated_received_at < now()) as late,
                count(*) filter (where state <> 'in_process' and estimated_received_at is null) as no_eta")
            ->get()
            ->keyBy('stage');

        $labels = [
            'preparing'     => __('Preparing'),
            'submitted'     => __('Sent, not confirmed'),
            'confirmed'     => __('Confirmed, producing'),
            'ready_to_ship' => __('Ready to ship'),
            'dispatched'    => __('In a container'),
        ];

        return [
            'stages' => collect($labels)
                ->filter(fn ($label, $stage) => $rows->has($stage))
                ->map(fn ($label, $stage) => [
                    'stage'  => $stage,
                    'label'  => $label,
                    'orders' => (int) $rows[$stage]->orders,
                    'value'  => round((float) $rows[$stage]->value, 2),
                ])
                ->values()
                ->all(),
            'late'   => (int) $rows->sum('late'),
            'no_eta' => (int) $rows->sum('no_eta'),
        ];
    }

    /**
     * Agent orders still being prepared or not yet delivered, each the group of supplier orders
     * staff placed together, worst state first.
     */
    private function agentOrderRows(OrgAgent $orgAgent): Collection
    {
        $stateLabels = PurchaseOrderStateEnum::labels();
        $openStates  = array_merge([PurchaseOrderStateEnum::IN_PROCESS->value], GetAgentSupplierPerformance::OPEN_STATES);

        return $this->agentPurchaseOrders($orgAgent)
            ->whereNotNull('agent_order_reference')
            ->whereIn('state', $openStates)
            ->whereNotIn('delivery_state', GetAgentSupplierPerformance::CLOSED_DELIVERY_STATES)
            ->whereRaw("(data -> 'housekeeping') is null")
            ->groupBy('agent_order_reference')
            ->selectRaw("agent_order_reference as reference,
                min(case state when 'in_process' then 1 when 'submitted' then 2 else 3 end) as state_rank,
                count(*) as supplier_orders,
                coalesce(sum(number_current_purchase_order_transactions), 0) as lines,
                coalesce(sum(cost_total * coalesce(org_exchange, 1)), 0) as value,
                min(coalesce(submitted_at, date, created_at)) as date")
            ->orderByRaw('min(coalesce(submitted_at, date, created_at))')
            ->get()
            ->map(function ($agentOrder) use ($orgAgent, $stateLabels) {
                $state = [1 => PurchaseOrderStateEnum::IN_PROCESS, 2 => PurchaseOrderStateEnum::SUBMITTED, 3 => PurchaseOrderStateEnum::CONFIRMED][$agentOrder->state_rank]->value;

                return [
                    'type'            => 'agent_order',
                    'reference'       => $agentOrder->reference,
                    'state'           => $state,
                    'state_label'     => $stateLabels[$state],
                    'lines'           => (int) $agentOrder->lines,
                    'supplier_orders' => (int) $agentOrder->supplier_orders,
                    'value'           => round((float) $agentOrder->value, 2),
                    'date'            => $agentOrder->date,
                    'url'             => route('grp.org.procurement.org_agents.show.agent_orders.show', [$orgAgent->organisation->slug, $orgAgent->slug, $agentOrder->reference]),
                ];
            });
    }

    private function stockDeliveryRows(OrgAgent $orgAgent): Collection
    {
        $stateLabels = StockDeliveryStateEnum::labels();

        return DB::table('stock_deliveries')
            ->where('organisation_id', $orgAgent->organisation_id)
            ->where('agent_id', $orgAgent->agent_id)
            ->whereIn('state', GetAgentSupplierPerformance::OPEN_DELIVERY_STATES)
            ->whereNull('deleted_at')
            ->selectRaw('slug, reference, state, number_stock_delivery_items_except_cancelled as lines, coalesce(date, created_at) as date')
            ->orderByRaw('coalesce(date, created_at)')
            ->get()
            ->map(fn ($stockDelivery) => [
                'type'        => in_array($stockDelivery->state, self::NEXT_CONTAINER_STATES, true) ? 'next_container' : 'stock_delivery',
                'reference'   => $stockDelivery->reference,
                'state'       => $stockDelivery->state,
                'state_label' => $stateLabels[$stockDelivery->state] ?? $stockDelivery->state,
                'lines'       => (int) $stockDelivery->lines,
                'date'        => $stockDelivery->date,
                'url'         => route('grp.org.procurement.stock_deliveries.show', [$orgAgent->organisation->slug, $stockDelivery->slug]),
            ]);
    }

    /**
     * The urgent end of each active agent's stock cover. Slow on agents with thousands of
     * products, so it is sent deferred.
     *
     * @param  Collection<int, OrgAgent>  $orgAgents
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function coverByAgent(Collection $orgAgents): array
    {
        return $orgAgents
            ->filter(fn (OrgAgent $orgAgent) => $orgAgent->status)
            ->mapWithKeys(fn (OrgAgent $orgAgent) => [
                $orgAgent->id => collect(GetAgentStockCoverBuckets::run($orgAgent)['buckets'])
                    ->whereIn('bucket', self::COVER_BUCKETS)
                    ->map(fn (array $bucket) => [
                        'bucket'    => $bucket['bucket'],
                        'label'     => $bucket['label'],
                        'count'     => $bucket['count'],
                        'untouched' => $bucket['untouched'],
                        'bestsellers' => collect($bucket['ranks'])->whereIn('rank', ['A', 'B'])->sum('count'),
                        'lost'      => round($bucket['lost']),
                        'lost_untouched' => round($bucket['lost_untouched']),
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    public function htmlResponse(Collection $orgAgents, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/OrgAgents',
            [
                'breadcrumbs'       => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'             => __('Agents'),
                'pageHead'          => [
                    'model' => __('Procurement'),
                    'title' => __('Agents'),
                    'icon'  => [
                        'title' => __('Agents'),
                        'icon'  => 'fal fa-people-arrows',
                    ],
                ],
                'currency_code'     => $this->organisation->currency->code,
                'agents'            => $orgAgents->map(fn (OrgAgent $orgAgent) => $this->agentCard($orgAgent))->all(),
                'cover'             => Inertia::defer(fn () => $this->coverByAgent($orgAgents)),
            ],
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Agents'),
                        'icon'  => 'fal fa-bars',
                        'route' => [
                            'name'       => 'grp.org.procurement.org_agents.index',
                            'parameters' => ['organisation' => $routeParameters['organisation']],
                        ],
                    ],
                ],
            ],
        );
    }
}
