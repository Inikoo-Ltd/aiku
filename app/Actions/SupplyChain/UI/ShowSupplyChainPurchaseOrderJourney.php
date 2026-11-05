<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\UI;

use App\Actions\Helpers\Country\UI\IsEuropeanUnion;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\DeleteEmptyPurchaseOrders;
use App\Actions\UI\WithInertia;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderJourneyStageEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
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

    private const array PER_PAGE_OPTIONS = [10, 25, 50, 100];

    private const array TABS = ['open', 'in_process', 'submitted', 'confirmed', 'needs_action', 'import', 'completed'];

    private const array SORTS = ['needs_action', 'newest', 'oldest', 'value', 'lowest_value'];

    private const int FINISHED_WINDOW_DAYS = 60;

    private const array RECEIVED_STATES = ['received', 'checked', 'placed'];

    private const array STATUSES = ['overdue', 'at_risk', 'on_track', 'completed'];

    private const array FILTER_GROUPS = ['organisation', 'journey', 'agent', 'supplier', 'buyer', 'type', 'country', 'stage', 'status'];

    /** @var array<string, float> units of each currency per pivot currency */
    private array $rates = [];

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo('supply-chain.edit');

        return $request->user()->authTo('supply-chain.view');
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        return [];
    }

    public function handle(ActionRequest $request, ?Agent $agent = null): array
    {
        $today   = now()->startOfDay();
        $ribbons = $this->ribbons($agent ? $agent->group : $this->group, agent: $agent);

        $active = collect(self::FILTER_GROUPS)->mapWithKeys(fn (string $group) => [$group => $request->query($group) ?: null])->all();

        $active['tab']    = in_array($request->query('tab'), self::TABS) ? $request->query('tab') : 'open';
        $active['sort']   = in_array($request->query('sort'), self::SORTS) ? $request->query('sort') : 'needs_action';
        $active['search'] = trim((string) $request->query('search')) ?: null;

        $anyTab   = $ribbons->filter(fn (array $ribbon) => $this->passes($ribbon, $active, 'tab'))->values();
        $filtered = $anyTab->filter(fn (array $ribbon) => $this->inTab($ribbon, $active['tab']))->values();

        $open     = $anyTab->where('status', '!=', 'completed');
        $openRate = fn (string $status) => $open->count() ? (int) round(100 * $open->where('status', $status)->count() / $open->count()) : 0;

        $statusOrder = array_flip(self::STATUSES);
        $sorted      = match ($active['sort']) {
            'newest'       => $filtered->sortByDesc('created_at'),
            'oldest'       => $filtered->sortBy('created_at'),
            'value'        => $filtered->sortByDesc(fn (array $ribbon) => $ribbon['amount_grp'] ?? 0),
            'lowest_value' => $filtered->sortBy(fn (array $ribbon) => $ribbon['amount_grp'] ?? 0),
            default        => $filtered->sort(function (array $a, array $b) use ($statusOrder) {
                return [$statusOrder[$a['status']], -$a['days_overdue'], $a['eta'] ?? '', $a['created_at']]
                    <=> [$statusOrder[$b['status']], -$b['days_overdue'], $b['eta'] ?? '', $b['created_at']];
            }),
        };
        $sorted = $sorted->values();

        $perPage  = in_array($request->integer('per_page'), self::PER_PAGE_OPTIONS) ? $request->integer('per_page') : 25;
        $active['per_page'] = $perPage;

        $page     = max(1, $request->integer('page', 1));
        $lastPage = max(1, (int) ceil($sorted->count() / $perPage));
        $page     = min($page, $lastPage);

        return [
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
                'completed'        => $anyTab->where('status', 'completed')->count(),
                'in_process'       => $open->where('is_in_process', true)->count(),
                'submitted'        => $open->where('state', 'submitted')->count(),
                'confirmed'        => $open->where('state', 'confirmed')->count(),
                'needs_action'     => $open->whereIn('status', ['overdue', 'at_risk'])->count(),
                'import'           => $open->where('is_import', true)->count(),
            ],
            'blockages'  => $this->blockages($open),
            'quickStats' => $this->quickStats($anyTab, $open, $today),
            'ribbons'    => $sorted->forPage($page, $perPage)->values()->all(),
            'pagination' => [
                'page'      => $page,
                'last_page' => $lastPage,
                'total'     => $sorted->count(),
                'per_page'  => $perPage,
            ],
        ];
    }

    /**
     * Every open order of the group, or the one asked for, with its stages planned, as the board shows them. With a
     * supplier, only the orders sent to it, as its portal lists them; with an agent, only the orders it handles.
     * Drafts were never sent to either, so they stay out.
     */
    public function ribbons(Group $group, ?int $purchaseOrderId = null, ?Supplier $supplier = null, ?Agent $agent = null): Collection
    {
        $this->group = $group;
        $this->rates = $purchaseOrderId || $supplier ? [] : $this->latestRates();
        $today       = now()->startOfDay();

        return collect($this->rows($purchaseOrderId, $supplier, $agent))->map(fn (object $row) => $this->toRibbon($row, $today));
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, markable: bool}>
     */
    public function stages(): array
    {
        return collect(PurchaseOrderJourneyStageEnum::cases())->map(fn (PurchaseOrderJourneyStageEnum $stage) => [
            'key'         => $stage->value,
            'label'       => PurchaseOrderJourneyStageEnum::labels()[$stage->value],
            'description' => PurchaseOrderJourneyStageEnum::descriptions()[$stage->value],
            'markable'    => $stage->markColumn() !== null,
        ])->all();
    }

    /**
     * @return array<int, object>
     */
    private function rows(?int $purchaseOrderId = null, ?Supplier $supplier = null, ?Agent $agent = null): array
    {
        $supplierCondition = $supplier
            ? " and po.state <> 'in_process' and po.parent_type = 'OrgSupplier'
                and po.parent_id in (select org_suppliers.id from org_suppliers where org_suppliers.supplier_id = :supplier_id)"
            : '';
        $agentCondition = $agent ? " and po.state <> 'in_process' and po.agent_id = :agent_id" : '';

        $finishedSince = now()->subDays(self::FINISHED_WINDOW_DAYS);

        return DB::select(
            "select po.id, po.slug, po.reference, po.parent_type, po.agent_id, po.state, po.delivery_state, po.data,
                po.cost_total, po.cost_items, po.grp_exchange, po.created_at, po.submitted_at, po.settled_at,
                po.deposit_amount, po.deposit_paid_at, po.production_started_at, po.produced_at, po.qc_passed_at,
                po.approved_ready_at, po.handed_over_at, po.balance_paid_at, po.compliance_complete_at,
                {$this->sharedColumns()},
                suppliers.slug as supplier_slug, suppliers.code as supplier_code, suppliers.name as supplier_name,
                suppliers.company_name as supplier_company_name,
                suppliers.data as supplier_data,
                partners.slug as partner_slug, partners.code as partner_code, partners.name as partner_name,
                countries.code as country_code, countries.name as country_name,
                {$this->deliveryColumns()}, containers.container_lines, containers.lines_on_containers, containers.last_on_container_at,
                coalesce(delivery.estimated_arrival, po.estimated_received_at::date::text) as estimated_received_at,
                draft_lines.lines as draft_lines, draft_lines.cancelled_lines as draft_cancelled_lines,
                (".DeleteEmptyPurchaseOrders::deletableCondition().") as is_deletable
            from purchase_orders po
            {$this->sharedJoins()}
            left join currencies on currencies.id = po.currency_id
            left join suppliers on suppliers.id = po.supplier_id
            left join organisations partners on partners.id = po.partner_id
            left join addresses on addresses.id = coalesce(suppliers.address_id, agent_organisations.address_id, partners.address_id)
            left join countries on countries.id = coalesce(addresses.country_id, partners.country_id)
            {$this->deliveryLateral()}
            {$this->containersLateral()}
            {$this->linesLateral('purchase_order_transactions.purchase_order_id = po.id')}
            {$this->draftLinesLateral()}
            where {$this->openPurchaseOrderCondition()}{$supplierCondition}{$agentCondition}".($purchaseOrderId ? ' and po.id = :purchase_order_id' : ''),
            [
                'group_id'                => $this->group->id,
                'finished_since'          => $finishedSince,
                'finished_since_unlinked' => $finishedSince,
                ...($purchaseOrderId ? ['purchase_order_id' => $purchaseOrderId] : []),
                ...($supplier ? ['supplier_id' => $supplier->id] : []),
                ...($agent ? ['agent_id' => $agent->id] : []),
            ]
        );
    }

    /**
     * A stage counts as reached only once every live delivery of the order has reached it, and only then does it
     * carry a date; the delivery's own arrival estimate is kept while it is still on its way.
     */
    private function deliveryLateral(): string
    {
        return "left join lateral (
                select count(*) as deliveries,
                    bool_and(stock_deliveries.state in ('dispatched', 'received', 'checked', 'booking_in', 'booked_in', 'placed')) as all_dispatched,
                    bool_and(stock_deliveries.state in ('received', 'checked', 'booking_in', 'booked_in', 'placed')) as all_received,
                    bool_and(stock_deliveries.state = 'placed') as all_placed,
                    bool_and(stock_deliveries.arrived_at is not null or stock_deliveries.state in ('received', 'checked', 'booking_in', 'booked_in', 'placed')) as all_arrived,
                    max(stock_deliveries.dispatched_at) as dispatched_at,
                    max(stock_deliveries.arrived_at) as arrived_at,
                    max(stock_deliveries.received_at) as received_at,
                    max(stock_deliveries.placed_at) as placed_at,
                    max(stock_deliveries.data ->> 'estimated_receiving_date')
                        filter (where stock_deliveries.state not in ('received', 'checked', 'booking_in', 'booked_in', 'placed')) as estimated_arrival
                from purchase_order_stock_delivery
                join stock_deliveries on stock_deliveries.id = purchase_order_stock_delivery.stock_delivery_id
                where purchase_order_stock_delivery.purchase_order_id = po.id
                    and stock_deliveries.state not in ('cancelled', 'not_received')
            ) delivery on true";
    }

    /**
     * How many of the order's lines sit on a live stock delivery of this order, matched as the order page matches them
     * (StoreStockDeliveryFromPurchaseOrder::deliveryItemsOfTransaction), and when the last of them was put there.
     * Quantities are not compared: a line only partly in a container counts as in one, the price of a cheap board query.
     */
    private function containersLateral(): string
    {
        return "left join lateral (
                select count(*) as container_lines,
                    count(on_delivery.at) as lines_on_containers,
                    max(on_delivery.at) as last_on_container_at
                from purchase_order_transactions pot
                left join lateral (
                    select max(stock_delivery_items.created_at) as at
                    from stock_delivery_items
                    join purchase_order_stock_delivery on purchase_order_stock_delivery.stock_delivery_id = stock_delivery_items.stock_delivery_id
                        and purchase_order_stock_delivery.purchase_order_id = pot.purchase_order_id
                    join stock_deliveries on stock_deliveries.id = stock_delivery_items.stock_delivery_id
                    where stock_deliveries.deleted_at is null
                        and stock_deliveries.state not in ('cancelled', 'not_received')
                        and stock_delivery_items.state not in ('cancelled', 'not_received')
                        and ((stock_delivery_items.data->>'purchase_order_transaction_id')::bigint = pot.id
                            or (stock_delivery_items.data->>'purchase_order_transaction_id' is null and stock_delivery_items.org_stock_id = pot.org_stock_id))
                ) on_delivery on true
                where pot.purchase_order_id = po.id
                    and pot.supplier_product_id is not null
                    and pot.state in ('submitted', 'confirmed', 'settled')
            ) containers on true";
    }

    /**
     * Lines of an order not received yet, counted for the preview shown before an empty order is deleted.
     */
    private function draftLinesLateral(): string
    {
        return "left join lateral (
                select count(*) as lines,
                    count(*) filter (where purchase_order_transactions.state = 'cancelled') as cancelled_lines
                from purchase_order_transactions
                where po.state in ('in_process', 'submitted', 'confirmed')
                    and purchase_order_transactions.purchase_order_id = po.id
                    and purchase_order_transactions.deleted_at is null
            ) draft_lines on true";
    }

    private function deliveryColumns(): string
    {
        return 'delivery.deliveries, delivery.all_dispatched, delivery.all_received, delivery.all_placed, delivery.all_arrived,
            delivery.dispatched_at, delivery.arrived_at, delivery.received_at, delivery.placed_at';
    }

    private function sharedColumns(): string
    {
        return 'po.buyer_id, users.contact_name as buyer_name, users.username as buyer_username,
            organisations.slug as organisation_slug, organisations.code as organisation_code, organisations.name as organisation_name,
            organisation_countries.code as organisation_country_code,
            currencies.code as currency_code,
            agents.slug as agent_slug, agents.code as agent_code, agents.name as agent_name,
            agents.data as agent_data, agents.settings as agent_settings,
            lines.is_npo, lines.sellable_products, lines.online_products, lines.online_at';
    }

    private function sharedJoins(): string
    {
        return 'join organisations on organisations.id = po.organisation_id
            left join countries organisation_countries on organisation_countries.id = organisations.country_id
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
                or (
                    po.state = 'settled' and po.delivery_state = 'placed'
                    and po.settled_at >= :finished_since_unlinked
                    and not exists (select 1 from purchase_order_stock_delivery where purchase_order_stock_delivery.purchase_order_id = po.id)
                )
            )";
    }

    /**
     * NPO when a line's stock was never received in that organisation before the order was raised.
     * ponytail: stock that arrived without a delivery (a handful of Aurora-era purchase movements) still reads as new;
     * checking org_stock_movements costs seconds per page, add a dated first-purchase column if it matters.
     * Online when every sellable stock on the lines has a product for sale on a live webpage in the order's
     * organisation; a new stock with no product yet counts as not online, since the brief ends the journey only once
     * the products are created in the system and online.
     */
    private function linesLateral(string $linesFilter): string
    {
        return "left join lateral (
                select
                    bool_or(not line.received_before) as is_npo,
                    count(*) filter (where line.sellable or (not line.received_before and not line.has_any_product)) as sellable_products,
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
                        exists (
                            select 1 from product_has_org_stocks
                            where product_has_org_stocks.org_stock_id = stock_lines.org_stock_id
                        ) as has_any_product,
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
                    offset 0
                ) line
            ) lines on true";
    }

    /**
     * @return array<string, mixed>
     */
    private function toRibbon(object $row, Carbon $today): array
    {
        $journey = match (true) {
            $row->agent_id !== null && $row->parent_type === 'OrgSupplier' => 'agent',
            $row->parent_type === 'OrgPartner'                              => 'partner',
            default                                                         => 'supplier',
        };

        $data          = json_decode((string) $row->data, true) ?: [];
        $agentData     = json_decode((string) $row->agent_data, true) ?: [];
        $agentSettings = json_decode((string) $row->agent_settings, true) ?: [];
        $supplierData  = json_decode((string) $row->supplier_data, true) ?: [];

        $delivery = $this->deliveryProgress($row);
        $po       = $journey === 'agent' ? Agent::poJourneyFromSettings($agentSettings, true) : null;
        $readyOn  = (int) $row->container_lines > 0 && (int) $row->lines_on_containers === (int) $row->container_lines;

        $journeyData = GetPurchaseOrderJourney::run([
            'journey'                 => $journey,
            'state'                   => $row->state,
            'delivery_state'          => $delivery['state'],
            'created_at'              => $row->created_at,
            'submitted_at'            => $row->submitted_at,
            'is_npo'                  => (bool) $row->is_npo,
            'has_deposit'             => (float) $row->deposit_amount > 0,
            'no_deposit'              => $row->deposit_amount !== null && (float) $row->deposit_amount == 0,
            'deposit_paid_at'         => $row->deposit_paid_at,
            'production_started_at'   => $row->production_started_at,
            'produced_at'             => $row->produced_at,
            'qc_passed_at'            => $row->qc_passed_at,
            'estimated_production_at' => Arr::get($data, 'estimated_production_date'),
            'approved_ready_at'       => $row->approved_ready_at,
            'estimated_received_at'   => $row->estimated_received_at,
            'dispatched_at'           => $delivery['dispatched_at'],
            'arrived'                 => $delivery['arrived'],
            'arrived_at'              => $delivery['arrived_at'],
            'received_at'             => $delivery['received_at'],
            'placed_at'               => $delivery['placed_at'],
            'sellable_products'       => (int) $row->sellable_products,
            'online_products'         => (int) $row->online_products,
            'online_at'               => $row->online_at,
            'delivery_time'           => $journey === 'agent' ? Arr::get($agentData, 'delivery_time') : Arr::get($supplierData, 'delivery_time'),
            'production_waiting_time' => $journey !== 'partner' ? Arr::get($supplierData, 'production_waiting_time') : null,
            'stage_days'              => $po ? array_map(fn (array $stage) => $stage['days'], $po['stages']) : [],
            'stage_texts'             => $po['stages'] ?? [],
            'cho_mode'                => $po['cho_mode'] ?? null,
            'handed_over_at'          => $row->handed_over_at,
            'on_containers'           => $readyOn || $row->delivery_state === 'ready_to_ship',
            'on_containers_at'        => $row->last_on_container_at,
            'ready_checks'            => $po['ready_checks'] ?? null,
            'balance_paid_at'         => $row->balance_paid_at,
            'compliance_complete_at'  => $row->compliance_complete_at,
        ], $today);

        $suppliers = match ($journey) {
            'partner' => $row->partner_slug ? [['slug' => 'partner-'.$row->partner_slug, 'code' => $row->partner_code]] : [],
            default   => $row->supplier_slug ? [['slug' => $row->supplier_slug, 'code' => $row->supplier_code]] : [],
        };

        $amount = $row->cost_items ?? $row->cost_total;

        $supplierCompany = $journey === 'partner' ? $row->partner_name : ($row->supplier_company_name ?: $row->supplier_name);

        return [
            'key'               => 'po-'.$row->id,
            'id'                => $row->id,
            'slug'              => $row->slug,
            'reference'         => $row->reference,
            'supplier_code'     => $journey === 'agent' ? $row->supplier_code : null,
            'supplier_name'     => $journey === 'agent' ? $row->supplier_name : null,
            'organisation_slug' => $row->organisation_slug,
            'organisation_code' => $row->organisation_code,
            'organisation_name' => $row->organisation_name,
            'supplier_company'  => $supplierCompany,
            'supplier_nickname' => $journey !== 'partner' && $row->supplier_name && $row->supplier_name !== $supplierCompany ? $row->supplier_name : null,
            'agent_name'        => $journey === 'agent' ? $row->agent_name : null,
            'journey'           => $journey,
            'agent_slug'        => $row->agent_slug,
            'parent_code'       => $row->agent_code ?? $row->supplier_code ?? $row->partner_code,
            'parent_name'       => $row->agent_name ?? $row->supplier_name ?? $row->partner_name,
            'suppliers'         => $suppliers,
            'country_code'      => $row->country_code,
            'country_name'      => $row->country_name,
            'is_in_process'     => $row->state === 'in_process',
            'is_deletable'      => (bool) $row->is_deletable,
            'state'             => $row->state,
            'lines'             => (bool) $row->is_deletable ? (int) $row->draft_lines : null,
            'cancelled_lines'   => (bool) $row->is_deletable ? (int) $row->draft_cancelled_lines : null,
            'is_import'         => $row->state !== 'in_process' && $journey !== 'partner' && $this->crossesCustoms($row->organisation_country_code, $row->country_code),
            'buyer_id'          => $row->buyer_id,
            'buyer_name'        => $row->buyer_id ? strtok((string) ($row->buyer_name ?: $row->buyer_username), ' ') : null,
            'placed_by'         => $row->buyer_id ? ($row->buyer_name ?: $row->buyer_username) : null,
            'type'              => $row->is_npo ? 'npo' : 'reorder',
            'amount'            => $amount !== null ? (float) $amount : null,
            'currency_code'     => $row->currency_code,
            'amount_grp'        => $this->toGroupCurrency($amount, $row->currency_code, $row->grp_exchange),
            'created_at'        => Carbon::parse($row->created_at)->toDateString(),
            'placed_at'         => $delivery['placed_at'] ? Carbon::parse($delivery['placed_at'])->toDateString() : null,
            'current_stage'     => $journeyData['current_stage'],
            'current_label'     => $journeyData['current_stage'] ? PurchaseOrderJourneyStageEnum::waitingLabels()[$journeyData['current_stage']] : null,
            'status'            => $journeyData['status'],
            'days_overdue'      => $journeyData['days_overdue'],
            'eta'               => $journeyData['eta'],
            'lead_time'         => $journeyData['lead_time'],
            'segments'          => $journeyData['segments'],
            'route'             => [
                'name'       => 'grp.org.procurement.purchase_orders.show',
                'parameters' => [
                    'organisation'  => $row->organisation_slug,
                    'purchaseOrder' => $row->slug,
                ],
            ],
            'mark_route'        => ['name' => 'grp.models.purchase-order.journey_stage', 'parameters' => ['purchaseOrder' => $row->id]],
        ];
    }

    /**
     * With deliveries, a stage is reached only when every delivery reached it; without any, the order's own delivery
     * state stands, and its settled date is the best date known for the goods arriving.
     *
     * @return array{state: ?string, dispatched_at: ?string, arrived: bool, arrived_at: ?string, received_at: ?string, placed_at: ?string}
     */
    private function deliveryProgress(object $row): array
    {
        if ((int) $row->deliveries > 0) {
            return [
                'state'         => match (true) {
                    (bool) $row->all_placed     => 'placed',
                    (bool) $row->all_received   => 'received',
                    (bool) $row->all_dispatched => 'dispatched',
                    default                     => 'in_process',
                },
                'dispatched_at' => $row->all_dispatched ? $row->dispatched_at : null,
                'arrived'       => (bool) $row->all_arrived,
                'arrived_at'    => $row->all_arrived ? $row->arrived_at : null,
                'received_at'   => $row->all_received ? $row->received_at : null,
                'placed_at'     => $row->all_placed ? $row->placed_at : null,
            ];
        }

        return [
            'state'         => $row->delivery_state,
            'dispatched_at' => null,
            'arrived'       => false,
            'arrived_at'    => null,
            'received_at'   => in_array($row->delivery_state, self::RECEIVED_STATES) ? $row->settled_at : null,
            'placed_at'     => $row->delivery_state === 'placed' ? $row->settled_at : null,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function latestRates(): array
    {
        $rates = DB::table('currency_exchanges')
            ->join('currencies', 'currencies.id', '=', 'currency_exchanges.currency_id')
            ->where('currency_exchanges.date', '>=', now()->subDays(90)->toDateString())
            ->where('currency_exchanges.exchange', '>', 0)
            ->selectRaw('distinct on (currency_exchanges.currency_id) currencies.code, currency_exchanges.exchange')
            ->orderBy('currency_exchanges.currency_id')
            ->orderByDesc('currency_exchanges.date')
            ->get()
            ->mapWithKeys(fn (object $rate) => [$rate->code => (float) $rate->exchange])
            ->all();

        $rates[config('app.currency_exchange.pivot')] = 1.0;

        return $rates;
    }

    private function toGroupCurrency(mixed $amount, ?string $currencyCode, mixed $storedExchange): ?float
    {
        if ($amount === null) {
            return null;
        }

        $currencyRate = $this->rates[$currencyCode] ?? null;
        $groupRate    = $this->rates[$this->group->currency->code] ?? null;
        if ($currencyRate && $groupRate) {
            return round((float) $amount * $groupRate / $currencyRate, 2);
        }

        return (float) $storedExchange > 0 ? round((float) $amount * (float) $storedExchange, 2) : null;
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

        if ($except !== 'tab' && !$this->inTab($ribbon, $active['tab'])) {
            return false;
        }

        if ($active['search'] !== null) {
            $haystack = mb_strtolower(implode(' ', [
                $ribbon['reference'],
                $ribbon['parent_code'],
                $ribbon['parent_name'],
                $ribbon['supplier_name'],
                $ribbon['supplier_company'],
                $ribbon['supplier_nickname'],
                $ribbon['agent_name'],
                $ribbon['buyer_name'],
                ...collect($ribbon['suppliers'])->pluck('code')->all(),
            ]));

            if (!str_contains($haystack, mb_strtolower($active['search']))) {
                return false;
            }
        }

        return true;
    }

    private function inTab(array $ribbon, string $tab): bool
    {
        return match ($tab) {
            'in_process'   => $ribbon['is_in_process'] && $ribbon['status'] !== 'completed',
            'submitted'    => $ribbon['state'] === 'submitted' && $ribbon['status'] !== 'completed',
            'confirmed'    => $ribbon['state'] === 'confirmed' && $ribbon['status'] !== 'completed',
            'needs_action' => in_array($ribbon['status'], ['overdue', 'at_risk']),
            'import'       => $ribbon['is_import'] && $ribbon['status'] !== 'completed',
            'completed'    => $ribbon['status'] === 'completed',
            default        => $ribbon['status'] !== 'completed',
        };
    }

    /**
     * Import is an order from a supplier outside the organisation's customs territory: any other country for a UK
     * organisation, a country outside the EU for an EU one.
     */
    private function crossesCustoms(?string $organisationCountryCode, ?string $supplierCountryCode): bool
    {
        if (!$organisationCountryCode || !$supplierCountryCode || $organisationCountryCode === $supplierCountryCode) {
            return false;
        }

        return !(IsEuropeanUnion::run($organisationCountryCode) && IsEuropeanUnion::run($supplierCountryCode));
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
        $labels = PurchaseOrderJourneyStageEnum::waitingLabels();

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
        $dueSoon  = $open->filter(fn (array $ribbon) => $ribbon['status'] !== 'overdue' && $ribbon['eta'] && Carbon::parse($ribbon['eta'])->lte($today->copy()->addDays(30)));
        $oldest   = $open->min('created_at');
        $online   = $placed->map(function (array $ribbon) {
            $segment = collect($ribbon['segments'])->firstWhere('key', 'products_online');

            return $segment && $segment['state'] === 'done' && $segment['done_at']
                ? Carbon::parse($ribbon['placed_at'])->diffInDays(Carbon::parse($segment['done_at']))
                : null;
        })->filter(fn ($days) => $days !== null);

        return [
            'average_lead_days'  => $placed->count()
                ? (int) round($placed->avg(fn (array $ribbon) => Carbon::parse($ribbon['created_at'])->diffInDays(Carbon::parse($ribbon['placed_at']))))
                : null,
            'average_online_days' => $online->count() ? (int) round($online->avg()) : null,
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
        $journeyData = null;
        $journey     = function (string $key) use (&$journeyData, $request) {
            $journeyData ??= $this->handle($request);

            return $journeyData[$key];
        };

        return Inertia::render(
            'SupplyChain/SupplyChainPurchaseOrderJourney',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('PO Overview'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-route'],
                        'title' => __('PO Overview'),
                    ],
                    'title' => __('PO Overview'),
                ],
                'groupCurrency' => $this->group->currency->code,
                'canMark'       => $this->canEdit,
                'canDelete'     => $this->canEdit,
                'deleteRoute'   => ['name' => 'grp.models.purchase-orders.empty.delete', 'parameters' => []],
                'stages'        => $this->stages(),
                'filters'    => Inertia::defer(fn () => $journey('filters'), 'journey'),
                'active'     => Inertia::defer(fn () => $journey('active'), 'journey'),
                'summary'    => Inertia::defer(fn () => $journey('summary'), 'journey'),
                'blockages'  => Inertia::defer(fn () => $journey('blockages'), 'journey'),
                'quickStats' => Inertia::defer(fn () => $journey('quickStats'), 'journey'),
                'ribbons'    => Inertia::defer(fn () => $journey('ribbons'), 'journey'),
                'pagination' => Inertia::defer(fn () => $journey('pagination'), 'journey'),
            ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return ShowSupplyChainDashboard::make()->getBreadcrumbs();
    }
}
