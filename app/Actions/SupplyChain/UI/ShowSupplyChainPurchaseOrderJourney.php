<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\UI;

use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowSupplyChainPurchaseOrderJourney extends OrgAction
{
    use AsAction;
    use WithInertia;

    private const OPEN_STATES = ['in_process', 'submitted', 'confirmed'];

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
        $active = [
            'organisation'  => $request->query('organisation'),
            'agent'         => $request->query('agent'),
            'supplier'      => $request->query('supplier'),
            'country'       => $request->query('country'),
            'buyer'         => null,
            'stage'         => $request->query('stage'),
            'status'        => $request->query('status'),
            'problems_only' => $request->boolean('problems_only'),
        ];

        $rows = $this->baseQuery()->get();

        $ribbons = $rows->map(fn ($row) => $this->toRibbon($row))->values();

        if ($active['organisation']) {
            $ribbons = $ribbons->where('organisation_slug', $active['organisation']);
        }
        if ($active['agent']) {
            $ribbons = $ribbons->where('parent_type', 'agent')->where('parent_slug', $active['agent']);
        }
        if ($active['supplier']) {
            $ribbons = $ribbons->where('parent_type', 'supplier')->where('parent_slug', $active['supplier']);
        }
        if ($active['country']) {
            $ribbons = $ribbons->where('country_code', $active['country']);
        }
        if ($active['stage']) {
            $ribbons = $ribbons->where('current_stage', $active['stage']);
        }
        if ($active['status']) {
            $ribbons = $ribbons->where('status', $active['status']);
        }
        if ($active['problems_only']) {
            $ribbons = $ribbons->whereIn('status', ['at_risk', 'overdue']);
        }

        $summary = [
            'total'     => $ribbons->count(),
            'on_track'  => $ribbons->where('status', 'on_track')->count(),
            'at_risk'   => $ribbons->where('status', 'at_risk')->count(),
            'overdue'   => $ribbons->where('status', 'overdue')->count(),
        ];

        $statusOrder    = ['overdue' => 0, 'at_risk' => 1, 'on_track' => 2];
        $sortedRibbons  = $ribbons
            ->sortBy([
                fn ($a, $b) => ($statusOrder[$a['status']] ?? 3) <=> ($statusOrder[$b['status']] ?? 3),
                fn ($a, $b) => ($b['days_overdue'] ?? 0) <=> ($a['days_overdue'] ?? 0),
                fn ($a, $b) => $a['created_at'] <=> $b['created_at'],
            ])
            ->values()
            ->take(100);

        return [
            'filters'  => $this->buildFilterOptions($rows),
            'active'   => $active,
            'summary'  => $summary,
            'ribbons'  => $sortedRibbons->values()->all(),
        ];
    }

    private function baseQuery()
    {
        return DB::table('purchase_orders')
            ->leftJoin('organisations', 'organisations.id', '=', 'purchase_orders.organisation_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'purchase_orders.currency_id')
            ->leftJoin('org_agents', function ($join) {
                $join->on('org_agents.id', '=', 'purchase_orders.parent_id')
                    ->where('purchase_orders.parent_type', '=', 'OrgAgent');
            })
            ->leftJoin('agents', 'agents.id', '=', 'org_agents.agent_id')
            ->leftJoin('org_suppliers', function ($join) {
                $join->on('org_suppliers.id', '=', 'purchase_orders.parent_id')
                    ->where('purchase_orders.parent_type', '=', 'OrgSupplier');
            })
            ->leftJoin('suppliers', 'suppliers.id', '=', 'org_suppliers.supplier_id')
            ->leftJoin('addresses', 'addresses.id', '=', 'suppliers.address_id')
            ->leftJoin('countries', 'countries.id', '=', 'addresses.country_id')
            ->where('purchase_orders.group_id', $this->group->id)
            ->where(function ($query) {
                $query->whereIn('purchase_orders.state', self::OPEN_STATES)
                    ->orWhere(function ($settled) {
                        $settled->where('purchase_orders.state', 'settled')
                            ->whereIn('purchase_orders.delivery_state', ['received', 'checked']);
                    });
            })
            ->where('purchase_orders.parent_type', '!=', 'Partner')
            ->whereRaw("(purchase_orders.data -> 'housekeeping') IS NULL")
            ->select([
                'purchase_orders.id',
                'purchase_orders.slug',
                'purchase_orders.reference',
                'purchase_orders.parent_type',
                'purchase_orders.data',
                'purchase_orders.cost_total',
                'purchase_orders.created_at',
                'purchase_orders.submitted_at',
                'purchase_orders.confirmed_at',
                'purchase_orders.settled_at',
                'purchase_orders.delivery_state',
                'purchase_orders.estimated_received_at',
                'organisations.slug as organisation_slug',
                'organisations.code as organisation_code',
                'currencies.code as currency_code',
                'agents.slug as agent_slug',
                'agents.code as agent_code',
                'agents.name as agent_name',
                'agents.production_lead_days as agent_production_lead_days',
                'suppliers.slug as supplier_slug',
                'suppliers.code as supplier_code',
                'suppliers.name as supplier_name',
                'countries.code as country_code',
                'countries.name as country_name',
                DB::raw('(select max(agent_supplier_purchase_orders.deposit_paid_at) from purchase_order_transactions
                    join agent_supplier_purchase_orders on agent_supplier_purchase_orders.id = purchase_order_transactions.agent_supplier_purchase_order_id
                    where purchase_order_transactions.purchase_order_id = purchase_orders.id) as deposit_paid_at'),
                DB::raw('(select max(stock_deliveries.dispatched_at) from purchase_order_stock_delivery
                    join stock_deliveries on stock_deliveries.id = purchase_order_stock_delivery.stock_delivery_id
                    where purchase_order_stock_delivery.purchase_order_id = purchase_orders.id and stock_deliveries.state != \'cancelled\') as dispatched_at'),
                DB::raw('(select max(stock_deliveries.received_at) from purchase_order_stock_delivery
                    join stock_deliveries on stock_deliveries.id = purchase_order_stock_delivery.stock_delivery_id
                    where purchase_order_stock_delivery.purchase_order_id = purchase_orders.id and stock_deliveries.state != \'cancelled\') as received_at'),
                DB::raw('(select max(stock_deliveries.checked_at) from purchase_order_stock_delivery
                    join stock_deliveries on stock_deliveries.id = purchase_order_stock_delivery.stock_delivery_id
                    where purchase_order_stock_delivery.purchase_order_id = purchase_orders.id and stock_deliveries.state != \'cancelled\') as checked_at'),
                DB::raw('(select max(stock_deliveries.placed_at) from purchase_order_stock_delivery
                    join stock_deliveries on stock_deliveries.id = purchase_order_stock_delivery.stock_delivery_id
                    where purchase_order_stock_delivery.purchase_order_id = purchase_orders.id and stock_deliveries.state != \'cancelled\') as placed_at'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRibbon(object $row): array
    {
        $isAgent    = $row->parent_type === 'OrgAgent';
        $parentType = $isAgent ? 'agent' : 'supplier';
        $parentCode = $isAgent ? $row->agent_code : $row->supplier_code;
        $parentName = $isAgent ? $row->agent_name : $row->supplier_name;
        $parentSlug = $isAgent ? $row->agent_slug : $row->supplier_slug;

        $data                    = json_decode((string) $row->data, true) ?: [];
        $estimatedProductionDate = Arr::get($data, 'estimated_production_date');

        $dispatchTarget = $estimatedProductionDate;
        if (!$dispatchTarget && $row->confirmed_at && $isAgent && $row->agent_production_lead_days !== null) {
            $dispatchTarget = Carbon::parse($row->confirmed_at)->addDays((int) $row->agent_production_lead_days);
        }

        $segments = [
            $this->segment('created', __('Created'), $row->created_at, null),
            $this->segment('submitted', __('Submitted'), $row->submitted_at, null),
            $this->segment('confirmed', __('Confirmed'), $row->confirmed_at, null),
        ];

        if ($isAgent) {
            $segments[] = $this->segment('deposit_paid', __('Deposit paid'), $row->deposit_paid_at, null);
        }

        $segments[] = $this->segment('dispatched', __('Dispatched'), $row->dispatched_at, $dispatchTarget);
        $receivedAt = $row->received_at ?: (in_array($row->delivery_state, ['received', 'checked', 'placed']) ? $row->settled_at : null);
        $segments[] = $this->segment('in_transit', __('In transit'), $receivedAt, $row->estimated_received_at);
        $segments[] = $this->segment('received', __('Received'), $receivedAt, null);
        $segments[] = $this->segment('checked', __('Checked'), $row->checked_at, null);
        $segments[] = $this->segment('placed', __('Placed'), $row->placed_at, null);
        $segments[] = $this->segment('online', __('Online'), null, null);

        $this->applySegmentStates($segments);

        $currentSegment = collect($segments)->first(fn ($segment) => $segment['state'] !== 'done' && $segment['state'] !== 'future')
            ?? end($segments);

        $status      = $currentSegment['state'];
        $daysOverdue = $currentSegment['days_overdue'] ?? 0;

        if (!$currentSegment['target_at']) {
            $createdOver60Days = Carbon::parse($row->created_at)->lt(now()->subDays(60));
            $notDispatched     = !$row->dispatched_at;
            if ($createdOver60Days && $notDispatched) {
                $status      = 'overdue';
                $daysOverdue = (int) Carbon::parse($row->created_at)->addDays(60)->diffInDays(now());
                foreach ($segments as &$segment) {
                    if ($segment['key'] === $currentSegment['key']) {
                        $segment['state']        = 'overdue';
                        $segment['days_overdue'] = $daysOverdue;
                    }
                }
                unset($segment);
            } else {
                $status = 'on_track';
            }
        }

        return [
            'slug'              => $row->slug,
            'reference'         => $row->reference,
            'organisation_slug' => $row->organisation_slug,
            'organisation_code' => $row->organisation_code,
            'parent_type'       => $parentType,
            'parent_slug'       => $parentSlug,
            'parent_code'       => $parentCode,
            'parent_name'       => $parentName,
            'country_code'      => $row->country_code,
            'buyer_name'        => null,
            'amount'            => $row->cost_total !== null ? (string) $row->cost_total : null,
            'currency_code'     => $row->currency_code,
            'created_at'        => $row->created_at,
            'current_stage'     => $currentSegment['key'],
            'status'            => $status,
            'days_overdue'      => $daysOverdue,
            'route'             => [
                'name'       => 'grp.org.procurement.purchase_orders.show',
                'parameters' => [
                    'organisation'  => $row->organisation_slug,
                    'purchaseOrder' => $row->slug,
                ],
            ],
            'segments'          => $segments,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function segment(string $key, string $label, mixed $doneAt, mixed $targetAt): array
    {
        return [
            'key'          => $key,
            'label'        => $label,
            'done_at'      => $doneAt,
            'target_at'    => $targetAt,
            'state'        => null,
            'days_overdue' => 0,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $segments
     */
    private function applySegmentStates(array &$segments): void
    {
        $currentAssigned = false;

        foreach ($segments as &$segment) {
            if ($segment['done_at']) {
                $segment['state'] = 'done';

                continue;
            }

            if ($currentAssigned) {
                $segment['state'] = 'future';

                continue;
            }

            $currentAssigned = true;

            if (!$segment['target_at']) {
                $segment['state'] = 'on_track';

                continue;
            }

            $target = Carbon::parse($segment['target_at']);

            if ($target->isPast()) {
                $segment['state']        = 'overdue';
                $segment['days_overdue'] = (int) $target->diffInDays(now());
            } elseif ($target->lte(now()->addDays(7))) {
                $segment['state'] = 'at_risk';
            } else {
                $segment['state'] = 'on_track';
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFilterOptions($rows): array
    {
        return [
            'organisations' => $rows->pluck('organisation_code', 'organisation_slug')
                ->filter()
                ->map(fn ($code, $slug) => ['slug' => $slug, 'code' => $code])
                ->unique('slug')
                ->values()
                ->all(),
            'agents' => $rows->where('parent_type', 'OrgAgent')
                ->pluck('agent_code', 'agent_slug')
                ->filter()
                ->map(fn ($code, $slug) => ['slug' => $slug, 'code' => $code])
                ->unique('slug')
                ->values()
                ->all(),
            'suppliers' => $rows->where('parent_type', 'OrgSupplier')
                ->pluck('supplier_code', 'supplier_slug')
                ->filter()
                ->map(fn ($code, $slug) => ['slug' => $slug, 'code' => $code])
                ->unique('slug')
                ->values()
                ->all(),
            'countries' => $rows->pluck('country_name', 'country_code')
                ->filter(fn ($name, $code) => $code)
                ->map(fn ($name, $code) => ['code' => $code, 'name' => $name])
                ->unique('code')
                ->values()
                ->all(),
            'buyers' => [],
            'stages' => collect([
                'created', 'submitted', 'confirmed', 'deposit_paid', 'dispatched',
                'in_transit', 'received', 'checked', 'placed', 'online',
            ])->map(fn ($key) => ['key' => $key, 'label' => __(ucfirst(str_replace('_', ' ', $key)))])->values()->all(),
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
                'filters' => $data['filters'],
                'active'  => $data['active'],
                'summary' => $data['summary'],
                'ribbons' => $data['ribbons'],
            ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowSupplyChainDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.supply-chain.po_journey.dashboard',
                        ],
                        'label' => __('PO journey'),
                    ],
                ],
            ]
        );
    }
}
