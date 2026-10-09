<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\UI;

use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowSupplyChainControl extends OrgAction
{
    use AsAction;
    use WithInertia;

    private const LIMIT = 50;

    private const DELIVERED_OR_CLOSED_STATES = ['received', 'checked', 'placed', 'cancelled', 'not_received'];

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo('supply-chain.edit');

        return $request->user()->authTo('supply-chain.view');
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle();
    }

    public function handle(): array
    {
        return [
            'stalled_purchase_orders' => $this->stalledPurchaseOrders(),
            'deposits_at_risk'        => $this->depositsAtRisk(),
            'agent_scorecard'         => $this->agentScorecard(),
        ];
    }

    private function baseQuery()
    {
        return PurchaseOrder::query()
            ->join('agents', 'agents.id', 'purchase_orders.agent_id')
            ->join('organisations', 'organisations.id', 'purchase_orders.organisation_id')
            ->leftJoin('suppliers', 'suppliers.id', 'purchase_orders.supplier_id')
            ->leftJoin('currencies', 'currencies.id', 'purchase_orders.currency_id')
            ->where('purchase_orders.group_id', $this->group->id)
            ->where('purchase_orders.parent_type', 'OrgSupplier')
            ->whereIn('purchase_orders.state', [PurchaseOrderStateEnum::SUBMITTED->value, PurchaseOrderStateEnum::CONFIRMED->value])
            ->whereNotIn('purchase_orders.delivery_state', self::DELIVERED_OR_CLOSED_STATES)
            ->whereRaw("(purchase_orders.data -> 'housekeeping') IS NULL");
    }

    private function stalledPurchaseOrders(): array
    {
        $query = $this->baseQuery()
            ->where(function ($q) {
                $q->where('purchase_orders.estimated_received_at', '<', now())
                    ->orWhere(function ($q2) {
                        $q2->whereNull('purchase_orders.estimated_received_at')
                            ->whereRaw('COALESCE(purchase_orders.date, purchase_orders.submitted_at) < ?', [now()->subDays(60)]);
                    });
            });

        $ageExpr = 'EXTRACT(DAY FROM (now() - COALESCE(purchase_orders.estimated_received_at, purchase_orders.date, purchase_orders.submitted_at)))';

        $rows = (clone $query)
            ->select([
                'purchase_orders.slug',
                'purchase_orders.reference',
                'organisations.slug as organisation_slug',
                'organisations.code as organisation_code',
                'agents.code as agent_code',
                'agents.slug as agent_slug',
                'suppliers.code as supplier_code',
                'purchase_orders.date',
                'purchase_orders.estimated_received_at',
                'purchase_orders.cost_total',
                'currencies.code as currency_code',
                DB::raw("$ageExpr as days_stalled"),
            ])
            ->orderByRaw('COALESCE(purchase_orders.estimated_received_at, purchase_orders.date, purchase_orders.submitted_at) asc')
            ->limit(self::LIMIT)
            ->get();

        $buckets = (clone $query)
            ->select(DB::raw("$ageExpr as days_stalled"))
            ->get()
            ->reduce(function (array $carry, $row) {
                $days = (int) $row->days_stalled;
                if ($days > 365) {
                    $carry['over_1y']++;
                } elseif ($days > 180) {
                    $carry['180_365']++;
                } elseif ($days >= 60) {
                    $carry['60_180']++;
                }

                return $carry;
            }, ['60_180' => 0, '180_365' => 0, 'over_1y' => 0]);

        return [
            'rows'    => $rows,
            'total'   => (clone $query)->count(),
            'buckets' => $buckets,
        ];
    }

    private function depositsAtRisk(): array
    {
        $query = $this->baseQuery()->whereNotNull('purchase_orders.deposit_paid_at');

        $rows = (clone $query)
            ->select([
                'purchase_orders.slug',
                'purchase_orders.reference',
                'organisations.slug as organisation_slug',
                'organisations.code as organisation_code',
                'agents.code as agent_code',
                'agents.slug as agent_slug',
                'suppliers.code as supplier_code',
                'purchase_orders.deposit_amount',
                'purchase_orders.deposit_paid_at',
                'currencies.code as currency_code',
                DB::raw('EXTRACT(DAY FROM (now() - purchase_orders.deposit_paid_at)) as days_since'),
            ])
            ->orderBy('purchase_orders.deposit_paid_at')
            ->limit(self::LIMIT)
            ->get();

        $exposure = (clone $query)
            ->select(['currencies.code as currency_code', DB::raw('sum(purchase_orders.deposit_amount) as total')])
            ->groupBy('currencies.code')
            ->get();

        return [
            'rows'     => $rows,
            'total'    => (clone $query)->count(),
            'exposure' => $exposure,
        ];
    }

    private function agentScorecard(): array
    {
        $delivered = "('received','checked','placed')";
        $open      = "purchase_orders.state in ('submitted','confirmed') and purchase_orders.delivery_state not in ('received','checked','placed','cancelled','not_received') and (purchase_orders.data -> 'housekeeping') is null";
        $ageExpr   = 'EXTRACT(DAY FROM (now() - COALESCE(purchase_orders.estimated_received_at, purchase_orders.date, purchase_orders.submitted_at)))';

        $rows = DB::table('agents')
            ->join('purchase_orders', function ($join) {
                $join->on('purchase_orders.agent_id', '=', 'agents.id')
                    ->where('purchase_orders.parent_type', '=', 'OrgSupplier')
                    ->whereNull('purchase_orders.deleted_at')
                    ->whereIn('purchase_orders.state', ['submitted', 'confirmed', 'settled']);
            })
            ->where('agents.group_id', $this->group->id)
            ->groupBy('agents.id', 'agents.code', 'agents.slug')
            ->select([
                'agents.id',
                'agents.code',
                'agents.slug',
                DB::raw("count(*) filter (where $open) as open_purchase_orders"),
                DB::raw("max($ageExpr) filter (where $open) as oldest_stalled_days"),
                DB::raw('count(*) as total_purchase_orders'),
                DB::raw("count(*) filter (where purchase_orders.delivery_state in $delivered) as delivered_purchase_orders"),
            ])
            ->orderByDesc('open_purchase_orders')
            ->limit(self::LIMIT)
            ->get();

        $deposits = $this->baseQuery()
            ->whereIn('purchase_orders.agent_id', $rows->pluck('id'))
            ->whereNotNull('purchase_orders.deposit_amount')
            ->select(['purchase_orders.agent_id', 'currencies.code as currency_code', DB::raw('sum(purchase_orders.deposit_amount) as total')])
            ->groupBy('purchase_orders.agent_id', 'currencies.code')
            ->get()
            ->groupBy('agent_id');

        $rows = $rows->map(function ($row) use ($deposits) {
            $agentDeposits = $deposits->get($row->id, collect())->sortByDesc('total');
            $row->deposits_outstanding = $agentDeposits->isEmpty()
                ? null
                : [
                    'amount'   => $agentDeposits->first()->total,
                    'currency' => $agentDeposits->first()->currency_code,
                    'has_more' => $agentDeposits->count() > 1,
                ];
            $row->delivered_ratio = $row->total_purchase_orders > 0 ? round($row->delivered_purchase_orders / $row->total_purchase_orders, 2) : null;

            return $row;
        });

        return [
            'rows' => $rows->values(),
        ];
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'SupplyChain/SupplyChainControl',
            [
                'breadcrumbs'        => $this->getBreadcrumbs(),
                'title'              => __('Command & control'),
                'pageHead'           => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-radar'],
                        'title' => __('Command & control'),
                    ],
                    'title' => __('Command & control'),
                ],
                'stalled_purchase_orders' => $data['stalled_purchase_orders'],
                'deposits_at_risk'        => $data['deposits_at_risk'],
                'agent_scorecard'         => $data['agent_scorecard'],
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
                            'name' => 'grp.supply-chain.control.dashboard',
                        ],
                        'label' => __('Command & control'),
                    ],
                ],
            ]
        );
    }
}
