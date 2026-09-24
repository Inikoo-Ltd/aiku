<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\UI;

use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderJourneyStageEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowSupplyChainPurchaseOrderJourney extends OrgAction
{
    use AsAction;
    use WithInertia;

    private const int PER_PAGE = 50;

    private const int FINISHED_WINDOW_DAYS = 60;

    private const array RECEIVED_STATES = ['received', 'checked', 'placed'];

    private const array STATUSES = ['overdue', 'at_risk', 'on_track', 'completed'];

    private const array FILTER_GROUPS = ['organisation', 'journey', 'agent', 'supplier', 'buyer', 'type', 'country', 'stage', 'status'];

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo('supply-chain.edit');

        return $request->user()->authTo('supply-chain.view');
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($request);
    }

    public function handle(ActionRequest $request): array
    {
        $today   = now()->startOfDay();
        $view    = $request->query('view') === 'purchase_orders' ? 'purchase_orders' : 'supplier_orders';
        $ribbons = collect($this->rows($view === 'supplier_orders'))->map(fn (object $row) => $this->toRibbon($row, $today));

        $active = collect(self::FILTER_GROUPS)->mapWithKeys(fn (string $group) => [$group => $request->query($group) ?: null])->all();

        $active['problems_only'] = $request->boolean('problems_only');
        $active['search']        = trim((string) $request->query('search')) ?: null;

        $filtered = $ribbons->filter(fn (array $ribbon) => $this->passes($ribbon, $active))->values();

        $open     = $filtered->where('status', '!=', 'completed');
        $openRate = fn (string $status) => $open->count() ? (int) round(100 * $open->where('status', $status)->count() / $open->count()) : 0;

        $statusOrder = array_flip(self::STATUSES);
        $sorted      = $filtered->sort(function (array $a, array $b) use ($statusOrder) {
            return [$statusOrder[$a['status']], -$a['days_overdue'], $a['eta'] ?? '', $a['created_at']]
                <=> [$statusOrder[$b['status']], -$b['days_overdue'], $b['eta'] ?? '', $b['created_at']];
        })->values();

        $page     = max(1, $request->integer('page', 1));
        $lastPage = max(1, (int) ceil($sorted->count() / self::PER_PAGE));
        $page     = min($page, $lastPage);

        return [
            'view'       => $view,
            'filters'    => $this->facets($ribbons, $active),
            'active'     => $active,
            'summary'    => [
                'open'             => $open->count(),
                'open_value'       => round($open->sum('amount_grp'), 2),
                'on_track'         => $open->where('status', 'on_track')->count(),
                'on_track_percent' => $openRate('on_track'),
                'at_risk'          => $open->where('status', 'at_risk')->count(),
                'at_risk_percent'  => $openRate('at_risk'),
                'overdue'          => $open->where('status', 'overdue')->count(),
                'overdue_percent'  => $openRate('overdue'),
                'completed'        => $filtered->where('status', 'completed')->count(),
            ],
            'blockages'  => $this->blockages($open),
            'quickStats' => $this->quickStats($filtered, $open, $today),
            'ribbons'    => $sorted->forPage($page, self::PER_PAGE)->values()->all(),
            'pagination' => [
                'page'      => $page,
                'last_page' => $lastPage,
                'total'     => $sorted->count(),
                'per_page'  => self::PER_PAGE,
            ],
        ];
    }

    /**
     * @return array<int, object>
     */
    private function rows(bool $splitAgentOrders): array
    {
        $bindings = [
            'group_id'       => $this->group->id,
            'finished_since' => now()->subDays(self::FINISHED_WINDOW_DAYS),
        ];

        $splitCondition = $splitAgentOrders
            ? "and not (po.parent_type = 'OrgAgent' and exists (
                    select 1 from agent_supplier_purchase_orders split
                    where split.purchase_order_id = po.id and split.deleted_at is null and (split.data -> 'housekeeping') is null
                ))"
            : '';

        $purchaseOrders = DB::select(
            "select 'po' as row_type, po.id, po.slug, po.reference, po.parent_type, po.state, po.delivery_state, po.data,
                po.cost_total, po.cost_items, po.grp_exchange, po.created_at, po.submitted_at, po.settled_at,
                po.deposit_amount, po.deposit_paid_at, po.sample_approved_at, po.produced_at, po.qc_passed_at,
                po.handed_over_at, po.estimated_received_at,
                po.reference as purchase_order_reference, po.slug as purchase_order_slug,
                {$this->sharedColumns()},
                suppliers.slug as supplier_slug, suppliers.code as supplier_code, suppliers.name as supplier_name,
                suppliers.data as supplier_data,
                partners.slug as partner_slug, partners.code as partner_code, partners.name as partner_name,
                countries.code as country_code, countries.name as country_name,
                delivery.dispatched_at, delivery.received_at, delivery.placed_at,
                aspo.deposit_paid_at as aspo_deposit_paid_at, aspo.approved_ready_at,
                aspo.qc_passed_at as aspo_qc_passed_at, aspo.handed_over_at as aspo_handed_over_at,
                line_suppliers.suppliers as line_suppliers
            from purchase_orders po
            {$this->sharedJoins()}
            left join currencies on currencies.id = po.currency_id
            left join suppliers on suppliers.id = po.supplier_id
            left join organisations partners on partners.id = po.partner_id
            left join addresses on addresses.id = coalesce(suppliers.address_id, agent_organisations.address_id, partners.address_id)
            left join countries on countries.id = coalesce(addresses.country_id, partners.country_id)
            left join lateral (
                select max(stock_deliveries.dispatched_at) as dispatched_at,
                    max(stock_deliveries.received_at) as received_at,
                    max(stock_deliveries.placed_at) as placed_at
                from purchase_order_stock_delivery
                join stock_deliveries on stock_deliveries.id = purchase_order_stock_delivery.stock_delivery_id
                where purchase_order_stock_delivery.purchase_order_id = po.id
                    and stock_deliveries.state not in ('cancelled', 'not_received')
            ) delivery on true
            left join lateral (
                select
                    case when bool_and(asp.deposit_paid_at is not null) filter (where asp.deposit_amount > 0) then max(asp.deposit_paid_at) end as deposit_paid_at,
                    max(asp.approved_ready_at) as approved_ready_at,
                    case when bool_and(asp.qc_passed_at is not null) then max(asp.qc_passed_at) end as qc_passed_at,
                    case when bool_and(asp.handed_over_at is not null) then max(asp.handed_over_at) end as handed_over_at
                from agent_supplier_purchase_orders asp
                where asp.purchase_order_id = po.id and asp.deleted_at is null
            ) aspo on true
            {$this->linesLateral('purchase_order_transactions.purchase_order_id = po.id')}
            left join lateral (
                select json_agg(distinct jsonb_build_object('slug', line_supplier.slug, 'code', line_supplier.code)) as suppliers
                from purchase_order_transactions
                join supplier_products on supplier_products.id = purchase_order_transactions.supplier_product_id
                join suppliers line_supplier on line_supplier.id = supplier_products.supplier_id
                where purchase_order_transactions.purchase_order_id = po.id
            ) line_suppliers on true
            where {$this->openPurchaseOrderCondition()}
                {$splitCondition}",
            $bindings
        );

        if (!$splitAgentOrders) {
            return $purchaseOrders;
        }

        $supplierOrders = DB::select(
            "select 'aspo' as row_type, asp.id, asp.slug, asp.reference, 'OrgAgent' as parent_type, asp.state,
                progress.delivery_state, po.data,
                asp.cost_total, asp.cost_items,
                coalesce(asp.grp_exchange, case when asp.currency_id = po.currency_id then po.grp_exchange end) as grp_exchange,
                asp.created_at,
                case when asp.source_id is null then asp.submitted_at else asp.created_at end as submitted_at,
                null as settled_at,
                asp.deposit_amount, asp.deposit_paid_at, asp.sample_approved_at, asp.produced_at, asp.qc_passed_at,
                asp.handed_over_at, coalesce(asp.estimated_received_at, po.estimated_received_at) as estimated_received_at,
                po.reference as purchase_order_reference, po.slug as purchase_order_slug,
                {$this->sharedColumns()},
                suppliers.slug as supplier_slug, suppliers.code as supplier_code, suppliers.name as supplier_name,
                suppliers.data as supplier_data,
                null as partner_slug, null as partner_code, null as partner_name,
                countries.code as country_code, countries.name as country_name,
                delivery.dispatched_at, delivery.received_at, delivery.placed_at,
                null as aspo_deposit_paid_at, asp.approved_ready_at,
                null as aspo_qc_passed_at, null as aspo_handed_over_at,
                null as line_suppliers
            from agent_supplier_purchase_orders asp
            join purchase_orders po on po.id = asp.purchase_order_id
            {$this->sharedJoins()}
            left join currencies on currencies.id = asp.currency_id
            left join suppliers on suppliers.id = asp.supplier_id
            left join addresses on addresses.id = coalesce(suppliers.address_id, agent_organisations.address_id)
            left join countries on countries.id = addresses.country_id
            left join lateral (
                select count(*) as active_lines,
                    case
                        when bool_and(purchase_order_transactions.delivery_state = 'settled') then 'placed'
                        when bool_and(purchase_order_transactions.delivery_state in ('received', 'checked', 'settled')) then 'received'
                        when bool_and(purchase_order_transactions.delivery_state in ('dispatched', 'received', 'checked', 'settled')) then 'dispatched'
                        else 'in_process'
                    end as delivery_state
                from purchase_order_transactions
                where purchase_order_transactions.agent_supplier_purchase_order_id = asp.id
                    and purchase_order_transactions.state not in ('cancelled', 'not_received')
                    and purchase_order_transactions.delivery_state not in ('cancelled', 'not_received')
            ) progress on true
            left join lateral (
                select max(stock_deliveries.dispatched_at) as dispatched_at,
                    max(stock_deliveries.received_at) as received_at,
                    max(stock_deliveries.placed_at) as placed_at
                from purchase_order_stock_delivery
                join stock_deliveries on stock_deliveries.id = purchase_order_stock_delivery.stock_delivery_id
                where purchase_order_stock_delivery.purchase_order_id = po.id
                    and stock_deliveries.state not in ('cancelled', 'not_received')
                    and exists (
                        select 1 from stock_delivery_items
                        join purchase_order_transactions on purchase_order_transactions.org_stock_id = stock_delivery_items.org_stock_id
                        where stock_delivery_items.stock_delivery_id = stock_deliveries.id
                            and purchase_order_transactions.agent_supplier_purchase_order_id = asp.id
                    )
            ) delivery on true
            {$this->linesLateral('purchase_order_transactions.agent_supplier_purchase_order_id = asp.id')}
            where asp.deleted_at is null
                and (asp.data -> 'housekeeping') is null
                and asp.state not in ('cancelled', 'not_received')
                and progress.active_lines > 0
                and {$this->openPurchaseOrderCondition()}",
            $bindings
        );

        return array_merge($purchaseOrders, $supplierOrders);
    }

    private function sharedColumns(): string
    {
        return 'po.buyer_id, users.contact_name as buyer_name, users.username as buyer_username,
            organisations.slug as organisation_slug, organisations.code as organisation_code,
            currencies.code as currency_code,
            agents.slug as agent_slug, agents.code as agent_code, agents.name as agent_name,
            agents.data as agent_data, agents.settings as agent_settings,
            lines.is_npo, lines.sellable_products, lines.online_products, lines.online_at';
    }

    private function sharedJoins(): string
    {
        return 'join organisations on organisations.id = po.organisation_id
            left join users on users.id = po.buyer_id
            left join agents on agents.id = po.agent_id
            left join organisations agent_organisations on agent_organisations.id = agents.organisation_id';
    }

    private function openPurchaseOrderCondition(): string
    {
        return "po.group_id = :group_id
            and po.deleted_at is null
            and (po.data -> 'housekeeping') is null
            and (
                po.state in ('in_process', 'submitted', 'confirmed')
                or (po.state = 'settled' and po.delivery_state in ('received', 'checked'))
                or (
                    po.state = 'settled' and po.delivery_state = 'placed'
                    and exists (
                        select 1 from purchase_order_stock_delivery
                        join stock_deliveries on stock_deliveries.id = purchase_order_stock_delivery.stock_delivery_id
                        where purchase_order_stock_delivery.purchase_order_id = po.id
                            and stock_deliveries.placed_at >= :finished_since
                    )
                )
            )";
    }

    /**
     * NPO when a line's stock never arrived in that organisation before the order was raised; online when every
     * sellable stock on the lines has a product for sale on a live webpage in the order's organisation.
     */
    private function linesLateral(string $linesFilter): string
    {
        return "left join lateral (
                select
                    bool_or(not line.received_before) as is_npo,
                    count(*) filter (where line.sellable) as sellable_products,
                    count(*) filter (where line.sellable and line.online_at is not null) as online_products,
                    max(line.online_at) filter (where line.sellable) as online_at
                from (
                    select stock_lines.org_stock_id,
                        exists (
                            select 1 from stock_delivery_items
                            join stock_deliveries on stock_deliveries.id = stock_delivery_items.stock_delivery_id
                            where stock_delivery_items.org_stock_id = stock_lines.org_stock_id
                                and stock_deliveries.received_at < po.created_at
                                and stock_deliveries.state not in ('cancelled', 'not_received')
                        ) as received_before,
                        exists (
                            select 1 from product_has_org_stocks
                            join products on products.id = product_has_org_stocks.product_id
                            where product_has_org_stocks.org_stock_id = stock_lines.org_stock_id
                                and products.organisation_id = po.organisation_id
                                and products.state <> 'discontinued'
                        ) as sellable,
                        (
                            select min(coalesce(webpages.live_at, webpages.created_at)) from product_has_org_stocks
                            join products on products.id = product_has_org_stocks.product_id
                            join webpages on webpages.id = products.webpage_id
                            where product_has_org_stocks.org_stock_id = stock_lines.org_stock_id
                                and products.organisation_id = po.organisation_id
                                and products.is_for_sale
                                and products.status <> 'coming-soon'
                                and webpages.state = 'live'
                        ) as online_at
                    from (
                        select distinct purchase_order_transactions.org_stock_id
                        from purchase_order_transactions
                        where {$linesFilter}
                            and purchase_order_transactions.org_stock_id is not null
                            and purchase_order_transactions.state not in ('cancelled', 'not_received')
                    ) stock_lines
                ) line
            ) lines on true";
    }

    /**
     * @return array<string, mixed>
     */
    private function toRibbon(object $row, Carbon $today): array
    {
        $isSupplierOrder = $row->row_type === 'aspo';

        $journey = match ($row->parent_type) {
            'OrgAgent'   => 'agent',
            'OrgPartner' => 'partner',
            default      => 'supplier',
        };

        $data          = json_decode((string) $row->data, true) ?: [];
        $agentData     = json_decode((string) $row->agent_data, true) ?: [];
        $agentSettings = json_decode((string) $row->agent_settings, true) ?: [];
        $supplierData  = json_decode((string) $row->supplier_data, true) ?: [];

        $receivedAt = $row->received_at ?: (in_array($row->delivery_state, self::RECEIVED_STATES) ? $row->settled_at : null);

        $journeyData = GetPurchaseOrderJourney::run([
            'journey'                 => $journey,
            'state'                   => $row->state,
            'delivery_state'          => $row->delivery_state,
            'created_at'              => $row->created_at,
            'submitted_at'            => $row->submitted_at,
            'is_npo'                  => (bool) $row->is_npo,
            'has_deposit'             => (float) $row->deposit_amount > 0,
            'sample_approved_at'      => $row->sample_approved_at,
            'deposit_paid_at'         => $row->deposit_paid_at ?: $row->aspo_deposit_paid_at,
            'produced_at'             => $row->produced_at,
            'qc_passed_at'            => $row->qc_passed_at ?: $row->aspo_qc_passed_at,
            'handed_over_at'          => $row->handed_over_at ?: $row->aspo_handed_over_at,
            'estimated_production_at' => Arr::get($data, 'estimated_production_date') ?: $row->approved_ready_at,
            'estimated_received_at'   => $row->estimated_received_at,
            'dispatched_at'           => $row->dispatched_at,
            'received_at'             => $receivedAt,
            'placed_at'               => $row->placed_at,
            'sellable_products'       => (int) $row->sellable_products,
            'online_products'         => (int) $row->online_products,
            'online_at'               => $row->online_at,
            'delivery_time'           => $journey === 'agent' ? Arr::get($agentData, 'delivery_time') : Arr::get($supplierData, 'delivery_time'),
            'production_waiting_time' => $journey !== 'partner' ? Arr::get($supplierData, 'production_waiting_time') : null,
            'stage_days'              => $journey === 'agent' ? Arr::get($agentSettings, 'journey_stage_days', []) : [],
        ], $today);

        $suppliers = match (true) {
            $isSupplierOrder, $journey === 'supplier' => $row->supplier_slug ? [['slug' => $row->supplier_slug, 'code' => $row->supplier_code]] : [],
            $journey === 'partner' => $row->partner_slug ? [['slug' => 'partner-'.$row->partner_slug, 'code' => $row->partner_code]] : [],
            default => json_decode((string) $row->line_suppliers, true) ?: [],
        };

        $amount = $row->cost_total ?? $row->cost_items;

        return [
            'key'               => $row->row_type.'-'.$row->id,
            'id'                => $row->id,
            'is_supplier_order' => $isSupplierOrder,
            'is_split_pending'  => $journey === 'agent' && !$isSupplierOrder,
            'slug'              => $row->slug,
            'reference'         => $row->reference,
            'purchase_order_reference' => $row->purchase_order_reference,
            'supplier_code'     => $isSupplierOrder ? $row->supplier_code : null,
            'supplier_name'     => $isSupplierOrder ? $row->supplier_name : null,
            'organisation_slug' => $row->organisation_slug,
            'organisation_code' => $row->organisation_code,
            'journey'           => $journey,
            'agent_slug'        => $row->agent_slug,
            'parent_code'       => $row->agent_code ?? $row->supplier_code ?? $row->partner_code,
            'parent_name'       => $row->agent_name ?? $row->supplier_name ?? $row->partner_name,
            'suppliers'         => $suppliers,
            'country_code'      => $row->country_code,
            'country_name'      => $row->country_name,
            'buyer_id'          => $row->buyer_id,
            'buyer_name'        => $row->buyer_id ? strtok((string) ($row->buyer_name ?: $row->buyer_username), ' ') : null,
            'type'              => $row->is_npo ? 'npo' : 'reorder',
            'amount'            => $amount !== null ? (float) $amount : null,
            'currency_code'     => $row->currency_code,
            'amount_grp'        => $amount !== null && $row->grp_exchange !== null ? round((float) $amount * (float) $row->grp_exchange, 2) : null,
            'created_at'        => Carbon::parse($row->created_at)->toDateString(),
            'placed_at'         => $row->placed_at ? Carbon::parse($row->placed_at)->toDateString() : null,
            'current_stage'     => $journeyData['current_stage'],
            'status'            => $journeyData['status'],
            'days_overdue'      => $journeyData['days_overdue'],
            'eta'               => $journeyData['eta'],
            'segments'          => $journeyData['segments'],
            'route'             => $isSupplierOrder
                ? [
                    'name'       => 'grp.supply-chain.agent_supplier_purchase_orders.show',
                    'parameters' => ['agentSupplierPurchaseOrder' => $row->slug],
                ]
                : [
                    'name'       => 'grp.org.procurement.purchase_orders.show',
                    'parameters' => [
                        'organisation'  => $row->organisation_slug,
                        'purchaseOrder' => $row->slug,
                    ],
                ],
            'mark_route'        => $isSupplierOrder
                ? ['name' => 'grp.models.agent_supplier_purchase_order.journey_stage', 'parameters' => ['agentSupplierPurchaseOrder' => $row->id]]
                : ['name' => 'grp.models.purchase-order.journey_stage', 'parameters' => ['purchaseOrder' => $row->id]],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function filterValues(array $ribbon, string $group): array
    {
        return match ($group) {
            'organisation' => [$ribbon['organisation_slug'] => $ribbon['organisation_code']],
            'journey'      => [$ribbon['journey'] => $this->journeyLabels()[$ribbon['journey']]],
            'agent'        => $ribbon['agent_slug'] ? [$ribbon['agent_slug'] => $ribbon['parent_code']] : [],
            'supplier'     => collect($ribbon['suppliers'])->pluck('code', 'slug')->all(),
            'buyer'        => $ribbon['buyer_id'] ? [(string) $ribbon['buyer_id'] => $ribbon['buyer_name']] : ['none' => __('Unknown')],
            'type'         => [$ribbon['type'] => $ribbon['type'] === 'npo' ? __('NPO') : __('Reorder')],
            'country'      => $ribbon['country_code'] ? [$ribbon['country_code'] => $ribbon['country_name']] : [],
            'stage'        => $ribbon['current_stage'] ? [$ribbon['current_stage'] => PurchaseOrderJourneyStageEnum::labels()[$ribbon['current_stage']]] : [],
            'status'       => [$ribbon['status'] => $this->statusLabels()[$ribbon['status']]],
            default        => [],
        };
    }

    private function passes(array $ribbon, array $active, ?string $except = null): bool
    {
        foreach (self::FILTER_GROUPS as $group) {
            if ($group !== $except && $active[$group] !== null && !array_key_exists((string) $active[$group], $this->filterValues($ribbon, $group))) {
                return false;
            }
        }

        if ($except !== 'status' && $active['problems_only'] && !in_array($ribbon['status'], ['overdue', 'at_risk'])) {
            return false;
        }

        if ($active['search'] !== null) {
            $haystack = mb_strtolower(implode(' ', [
                $ribbon['reference'],
                $ribbon['purchase_order_reference'],
                $ribbon['parent_code'],
                $ribbon['parent_name'],
                $ribbon['buyer_name'],
                ...collect($ribbon['suppliers'])->pluck('code')->all(),
            ]));

            if (!str_contains($haystack, mb_strtolower($active['search']))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, array<int, array{value: string, label: string, count: int}>>
     */
    private function facets(Collection $ribbons, array $active): array
    {
        $fixedOrder = [
            'journey' => array_keys($this->journeyLabels()),
            'type'    => ['npo', 'reorder'],
            'stage'   => PurchaseOrderJourneyStageEnum::values(),
            'status'  => self::STATUSES,
        ];

        $facets = [];
        foreach (self::FILTER_GROUPS as $group) {
            $counts = [];
            $labels = [];
            foreach ($ribbons as $ribbon) {
                if (!$this->passes($ribbon, $active, $group)) {
                    continue;
                }
                foreach ($this->filterValues($ribbon, $group) as $value => $label) {
                    $counts[$value] = ($counts[$value] ?? 0) + 1;
                    $labels[$value] = $label;
                }
            }

            $options = collect($counts)->map(fn (int $count, $value) => [
                'value' => (string) $value,
                'label' => (string) $labels[$value],
                'count' => $count,
            ]);

            if ($active[$group] !== null && !$options->has($active[$group])) {
                $options->put($active[$group], ['value' => (string) $active[$group], 'label' => (string) $active[$group], 'count' => 0]);
            }

            $facets[$group] = isset($fixedOrder[$group])
                ? $options->sortBy(fn (array $option) => array_search($option['value'], $fixedOrder[$group]))->values()->all()
                : $options->sortBy([['count', 'desc'], ['label', 'asc']])->values()->all();
        }

        return $facets;
    }

    /**
     * @return array<int, array{stage: string, label: string, count: int, max_days_overdue: int}>
     */
    private function blockages(Collection $open): array
    {
        $labels = PurchaseOrderJourneyStageEnum::labels();

        return $open->where('status', 'overdue')
            ->groupBy('current_stage')
            ->map(fn (Collection $group, string $stage) => [
                'stage'            => $stage,
                'label'            => $labels[$stage],
                'count'            => $group->count(),
                'max_days_overdue' => (int) $group->max('days_overdue'),
            ])
            ->sortByDesc('count')
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function quickStats(Collection $filtered, Collection $open, Carbon $today): array
    {
        $placed   = $filtered->filter(fn (array $ribbon) => $ribbon['placed_at'] !== null);
        $dueSoon  = $open->filter(fn (array $ribbon) => $ribbon['eta'] && Carbon::parse($ribbon['eta'])->lte($today->copy()->addDays(30)));
        $oldest   = $open->min('created_at');

        return [
            'average_lead_days'  => $placed->count()
                ? (int) round($placed->avg(fn (array $ribbon) => Carbon::parse($ribbon['created_at'])->diffInDays(Carbon::parse($ribbon['placed_at']))))
                : null,
            'oldest_open_days'   => $oldest ? (int) Carbon::parse($oldest)->diffInDays($today) : null,
            'open_value'         => round($open->sum('amount_grp'), 2),
            'due_30_days'        => $dueSoon->count(),
            'due_30_days_value'  => round($dueSoon->sum('amount_grp'), 2),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function journeyLabels(): array
    {
        return [
            'agent'    => __('Agent'),
            'supplier' => __('Direct supplier'),
            'partner'  => __('Inter-company'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function statusLabels(): array
    {
        return [
            'overdue'   => __('Overdue'),
            'at_risk'   => __('At risk'),
            'on_track'  => __('On track'),
            'completed' => __('Completed'),
        ];
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'SupplyChain/SupplyChainPurchaseOrderJourney',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('PO journey'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-route'],
                        'title' => __('PO journey'),
                    ],
                    'title' => __('PO journey'),
                ],
                'view'          => $data['view'],
                'groupCurrency' => $this->group->currency->code,
                'canMark'       => $this->canEdit,
                'stages'        => collect(PurchaseOrderJourneyStageEnum::cases())->map(fn (PurchaseOrderJourneyStageEnum $stage) => [
                    'key'         => $stage->value,
                    'label'       => PurchaseOrderJourneyStageEnum::labels()[$stage->value],
                    'description' => PurchaseOrderJourneyStageEnum::descriptions()[$stage->value],
                ])->all(),
                'filters'    => $data['filters'],
                'active'     => $data['active'],
                'summary'    => $data['summary'],
                'blockages'  => $data['blockages'],
                'quickStats' => $data['quickStats'],
                'ribbons'    => $data['ribbons'],
                'pagination' => $data['pagination'],
            ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return ShowSupplyChainDashboard::make()->getBreadcrumbs();
    }
}
