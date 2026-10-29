<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryTypeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStageEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * Where the agent's open orders are, one row per client organisation: purchase orders until they are made, the goods
 * waiting in the agent's warehouse, then the shipments carrying them until they leave. Every number opens the purchase order or shipment list filtered to it.
 */
class ShowAgentOrdersBacklog extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    /**
     * @var array<string, array{0: array<int, string>, 1: string, 2: string}> key => [progress buckets, label, icon]
     */
    public const array PURCHASE_ORDER_STAGES = [
        'po_submitted' => [['submitted'], 'To do', 'fal fa-paper-plane'],
        'po_in_factory' => [['confirmed', 'at_factory'], 'In factory', 'fal fa-industry-alt'],
    ];

    /**
     * @var array<string, array{0: array<int, string>, 1: string, 2: string}> key => [states, label, icon]
     */
    public const array SHIPMENT_STAGES = [
        'sd_preparing'  => [['in_process', 'confirmed'], 'Preparing', 'fal fa-box-open'],
        'sd_ready'      => [['ready_to_ship'], 'Ready to ship', 'fal fa-pallet'],
        'sd_dispatched' => [['dispatched'], 'On the way', 'fal fa-ship'],
    ];

    /**
     * Goods received in the agent's own warehouse and not loaded into a container yet.
     */
    public const array WAREHOUSE_STAGE = ['wh_received', 'In stock', 'fal fa-warehouse-alt'];

    public function handle(Agent $agent, Organisation $organisation): array
    {
        $progress     = PurchaseOrderStageEnum::progressSql();
        $factoryStage = PurchaseOrderStageEnum::factoryStageSql();

        $purchaseOrders = DB::table('purchase_orders')
            ->where('agent_id', $agent->id)
            ->whereNull('deleted_at')
            ->whereIn('state', [PurchaseOrderStateEnum::SUBMITTED->value, PurchaseOrderStateEnum::CONFIRMED->value])
            ->selectRaw("organisation_id, ($progress) as stage, ($factoryStage) as factory_stage, count(*) as number, coalesce(sum(number_current_purchase_order_transactions), 0) as lines")
            ->groupBy('organisation_id', 'stage', 'factory_stage')
            ->get();

        $factoryStages = [];
        foreach ($purchaseOrders as $row) {
            if (in_array($row->stage, self::PURCHASE_ORDER_STAGES['po_in_factory'][0])) {
                $factoryStages[$row->organisation_id][$row->factory_stage] = ($factoryStages[$row->organisation_id][$row->factory_stage] ?? 0) + (int) $row->number;
            }
        }

        $purchaseOrders = $purchaseOrders->map(fn ($row) => [$row->organisation_id, collect(self::PURCHASE_ORDER_STAGES)->search(fn ($stage) => in_array($row->stage, $stage[0])), (int) $row->number, (int) $row->lines]);

        $shipments = DB::table('stock_deliveries')
            ->where('agent_id', $agent->id)
            ->whereNull('deleted_at')
            ->whereIn('state', collect(self::SHIPMENT_STAGES)->pluck(0)->flatten()->all())
            ->selectRaw('organisation_id, state, count(*) as number, coalesce(sum(number_stock_delivery_items_except_cancelled), 0) as lines')
            ->groupBy('organisation_id', 'state')
            ->get()
            ->map(fn ($row) => [$row->organisation_id, collect(self::SHIPMENT_STAGES)->search(fn ($stage) => in_array($row->state, $stage[0])), (int) $row->number, (int) $row->lines]);

        $warehouse = DB::table('agent_warehouse_stocks')
            ->where('agent_id', $agent->id)
            ->where('quantity', '>', 0)
            ->selectRaw('organisation_id, count(distinct purchase_order_id) as number, count(*) as lines')
            ->groupBy('organisation_id')
            ->get()
            ->map(fn ($row) => [$row->organisation_id, self::WAREHOUSE_STAGE[0], (int) $row->number, (int) $row->lines]);

        $counts = [];
        $lines  = [];
        foreach ($purchaseOrders->concat($shipments)->concat($warehouse) as [$organisationId, $key, $number, $productLines]) {
            if ($key) {
                $counts[$organisationId][$key] = ($counts[$organisationId][$key] ?? 0) + $number;
                $lines[$organisationId][$key]  = ($lines[$organisationId][$key] ?? 0) + $productLines;
            }
        }

        $clients = Organisation::whereIn('id', array_keys($counts))->orderBy('code')->get(['id', 'slug', 'code']);

        $cell = fn (string $key, int $value, int $productLines, ?string $clientSlug, array $stages) => [
            'value'        => $value,
            'lines'        => $productLines,
            'lines_route'  => $key === self::WAREHOUSE_STAGE[0] ? $this->drillDown($organisation, $key, $clientSlug) : $this->linesRoute($organisation, $key, $clientSlug),
            'route_target' => $this->drillDown($organisation, $key, $clientSlug),
            ...($key === 'po_in_factory' ? [
                'breakdown' => collect(PurchaseOrderStageEnum::factoryStageLabels())->map(fn (string $label, string $stage) => [
                    'value'        => $stages[$stage] ?? 0,
                    'tooltip'      => $label,
                    'route_target' => $this->drillDown($organisation, $key, $clientSlug, $stage),
                ])->values()->all(),
            ] : []),
        ];

        $keys = array_merge(array_keys(self::PURCHASE_ORDER_STAGES), [self::WAREHOUSE_STAGE[0]], array_keys(self::SHIPMENT_STAGES));

        return [
            'dimension' => [
                'key'   => 'client',
                'label' => __('Client'),
                'items' => $clients->map(fn (Organisation $client) => [
                    'key'          => $client->slug,
                    'label'        => strtoupper($client->code),
                    'route_target' => [
                        'name'       => 'grp.org.agent.purchase_orders.index',
                        'parameters' => ['organisation' => $organisation->slug, '_query' => ['filter[organisation]' => $client->slug]],
                    ],
                ])->all(),
            ],
            'metrics'   => [
                $this->metricGroup('desk', __('Desk'), Arr::only(self::PURCHASE_ORDER_STAGES, ['po_submitted'])),
                $this->metricGroup('factory', __('At supplier'), Arr::only(self::PURCHASE_ORDER_STAGES, ['po_in_factory'])),
                $this->metricGroup('warehouse', __('In warehouse'), [self::WAREHOUSE_STAGE[0] => self::WAREHOUSE_STAGE] + Arr::except(self::SHIPMENT_STAGES, ['sd_dispatched'])),
                $this->metricGroup('transit', __('In transit'), ['sd_dispatched' => [[], self::SHIPMENT_STAGES['sd_dispatched'][1], StockDeliveryTypeEnum::usualFor($organisation)->icon()]]),
            ],
            'data'      => $clients->mapWithKeys(fn (Organisation $client) => [
                $client->slug => collect($keys)->mapWithKeys(fn (string $key) => [
                    $key => $cell($key, $counts[$client->id][$key] ?? 0, $lines[$client->id][$key] ?? 0, $client->slug, $factoryStages[$client->id] ?? []),
                ])->all(),
            ])->all(),
            'totals'    => collect($keys)->mapWithKeys(fn (string $key) => [
                $key => $cell($key, collect($counts)->sum(fn ($row) => $row[$key] ?? 0), collect($lines)->sum(fn ($row) => $row[$key] ?? 0), null, collect(PurchaseOrderStageEnum::factoryStageLabels())->map(fn ($label, $stage) => collect($factoryStages)->sum(fn ($row) => $row[$stage] ?? 0))->all()),
            ])->all(),
            'row_totals' => [],
        ];
    }

    private function metricGroup(string $key, string $label, array $stages): array
    {
        return [
            'key'   => $key,
            'label'   => $label,
            'caption' => $label,
            'type'    => 'group',
            'items' => collect($stages)->map(fn ($stage, $stageKey) => [
                'key'   => $stageKey,
                'label' => __($stage[1]),
                'icon'  => $stage[2],
                ...($stageKey === 'po_in_factory' ? [
                    'breakdown_icons' => collect(PurchaseOrderStageEnum::factoryStageLabels())->map(fn (string $label, string $factoryStage) => [
                        'icon'    => PurchaseOrderStageEnum::factoryStageIcons()[$factoryStage],
                        'tooltip' => $label,
                    ])->values()->all(),
                ] : []),
            ])->values()->all(),
        ];
    }

    private function linesRoute(Organisation $organisation, string $key, ?string $clientSlug): array
    {
        return [
            'name'       => 'grp.org.agent.orders.lines',
            'parameters' => [
                'organisation' => $organisation->slug,
                '_query'       => array_merge(['stage' => $key], $clientSlug ? ['filter[organisation]' => $clientSlug] : []),
            ],
        ];
    }

    private function drillDown(Organisation $organisation, string $key, ?string $clientSlug, ?string $factoryStage = null): array
    {
        $query = $clientSlug ? ['filter[organisation]' => $clientSlug] : [];

        if ($key === self::WAREHOUSE_STAGE[0]) {
            return [
                'name'       => 'grp.org.agent.warehouse.index',
                'parameters' => ['organisation' => $organisation->slug, ...($query ? ['_query' => $query] : [])],
            ];
        }

        if ($key === 'po_submitted') {
            return [
                'name'       => 'grp.org.agent.incoming_purchase_orders.index',
                'parameters' => ['organisation' => $organisation->slug, ...($query ? ['_query' => $query] : [])],
            ];
        }

        if (isset(self::PURCHASE_ORDER_STAGES[$key])) {
            return [
                'name'       => 'grp.org.agent.purchase_orders.index',
                'parameters' => [
                    'organisation' => $organisation->slug,
                    '_query'       => array_merge($query, $factoryStage ? ['elements[factory]' => $factoryStage] : ['elements[progress]' => implode(',', self::PURCHASE_ORDER_STAGES[$key][0])]),
                ],
            ];
        }

        return [
            'name'       => 'grp.org.agent.stock_deliveries.index',
            'parameters' => [
                'organisation' => $organisation->slug,
                '_query'       => array_merge($query, ['elements[state]' => implode(',', self::SHIPMENT_STAGES[$key][0])]),
            ],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        return $this->handle($agent, $organisation);
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Procurement/AgentOrdersBacklog',
            [
                'title'       => __('Orders'),
                'breadcrumbs' => array_merge(
                    ShowProcurementDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('Orders'),
                                'route' => [
                                    'name'       => 'grp.org.agent.orders.index',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                    ]
                ),
                'pageHead'    => [
                    'icon'  => [
                        'title' => __('Orders'),
                        'icon'  => 'fal fa-tasks',
                    ],
                    'title' => __('Orders'),
                ],
                'data'        => $data,
            ]
        );
    }
}
