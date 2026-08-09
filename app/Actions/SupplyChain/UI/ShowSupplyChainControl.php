<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\UI;

use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
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
            'stalled_aspos'      => $this->stalledAspos(),
            'deposits_at_risk'   => $this->depositsAtRisk(),
            'pos_without_action' => $this->posWithoutAgentAction(),
            'agent_scorecard'    => $this->agentScorecard(),
        ];
    }

    private function baseAspoQuery()
    {
        return AgentSupplierPurchaseOrder::query()
            ->leftJoin('suppliers', 'suppliers.id', 'agent_supplier_purchase_orders.supplier_id')
            ->leftJoin('agents', 'agents.id', 'suppliers.agent_id')
            ->leftJoin('currencies', 'currencies.id', 'agent_supplier_purchase_orders.currency_id')
            ->where('agent_supplier_purchase_orders.group_id', $this->group->id)
            ->whereNotIn('agent_supplier_purchase_orders.delivery_state', ['received', 'checked', 'placed', 'cancelled'])
            ->where('agent_supplier_purchase_orders.state', '!=', 'cancelled')
            ->whereRaw("(agent_supplier_purchase_orders.data -> 'housekeeping') IS NULL");
    }

    private function stalledAspos(): array
    {
        $query = $this->baseAspoQuery()
            ->where(function ($q) {
                $q->where('agent_supplier_purchase_orders.estimated_received_at', '<', now())
                    ->orWhere(function ($q2) {
                        $q2->whereNull('agent_supplier_purchase_orders.estimated_received_at')
                            ->where('agent_supplier_purchase_orders.date', '<', now()->subDays(60));
                    });
            });

        $ageExpr = 'EXTRACT(DAY FROM (now() - COALESCE(agent_supplier_purchase_orders.estimated_received_at, agent_supplier_purchase_orders.date)))';

        $rows = (clone $query)
            ->select([
                'agent_supplier_purchase_orders.slug',
                'agent_supplier_purchase_orders.reference',
                'agents.code as agent_code',
                'agents.slug as agent_slug',
                'suppliers.code as supplier_code',
                'agent_supplier_purchase_orders.date',
                'agent_supplier_purchase_orders.estimated_received_at',
                'agent_supplier_purchase_orders.cost_total',
                'currencies.code as currency_code',
                DB::raw("$ageExpr as days_stalled"),
            ])
            ->orderByRaw("COALESCE(agent_supplier_purchase_orders.estimated_received_at, agent_supplier_purchase_orders.date) asc")
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
        $query = $this->baseAspoQuery()->whereNotNull('agent_supplier_purchase_orders.deposit_paid_at');

        $rows = (clone $query)
            ->select([
                'agent_supplier_purchase_orders.slug',
                'agent_supplier_purchase_orders.reference',
                'agents.code as agent_code',
                'agents.slug as agent_slug',
                'suppliers.code as supplier_code',
                'agent_supplier_purchase_orders.deposit_amount',
                'agent_supplier_purchase_orders.deposit_paid_at',
                'currencies.code as currency_code',
                DB::raw('EXTRACT(DAY FROM (now() - agent_supplier_purchase_orders.deposit_paid_at)) as days_since'),
            ])
            ->orderBy('agent_supplier_purchase_orders.deposit_paid_at')
            ->limit(self::LIMIT)
            ->get();

        $exposure = (clone $query)
            ->select(['currencies.code as currency_code', DB::raw('sum(agent_supplier_purchase_orders.deposit_amount) as total')])
            ->groupBy('currencies.code')
            ->get();

        return [
            'rows'     => $rows,
            'total'    => (clone $query)->count(),
            'exposure' => $exposure,
        ];
    }

    private function posWithoutAgentAction(): array
    {
        $query = DB::table('purchase_orders')
            ->join('org_agents', function ($join) {
                $join->on('org_agents.id', '=', 'purchase_orders.parent_id')
                    ->where('purchase_orders.parent_type', '=', 'OrgAgent');
            })
            ->join('agents', 'agents.id', '=', 'org_agents.agent_id')
            ->join('organisations', 'organisations.id', '=', 'purchase_orders.organisation_id')
            ->where('purchase_orders.group_id', $this->group->id)
            ->whereIn('purchase_orders.state', ['submitted', 'confirmed'])
            ->where('purchase_orders.submitted_at', '<', now()->subDays(14))
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('purchase_order_transactions')
                    ->whereColumn('purchase_order_transactions.purchase_order_id', 'purchase_orders.id')
                    ->whereNotNull('purchase_order_transactions.agent_supplier_purchase_order_id');
            });

        $rows = (clone $query)
            ->select([
                'purchase_orders.slug',
                'purchase_orders.reference',
                'organisations.slug as organisation_slug',
                'organisations.name as organisation_name',
                'agents.code as agent_code',
                'agents.slug as agent_slug',
                'purchase_orders.submitted_at',
                DB::raw('EXTRACT(DAY FROM (now() - purchase_orders.submitted_at)) as days_waiting'),
            ])
            ->orderBy('purchase_orders.submitted_at')
            ->limit(self::LIMIT)
            ->get();

        return [
            'rows'  => $rows,
            'total' => (clone $query)->count(),
        ];
    }

    private function agentScorecard(): array
    {
        $ageExpr = 'EXTRACT(DAY FROM (now() - COALESCE(agent_supplier_purchase_orders.estimated_received_at, agent_supplier_purchase_orders.date)))';

        $rows = DB::table('agents')
            ->leftJoin('suppliers', 'suppliers.agent_id', '=', 'agents.id')
            ->leftJoin('agent_supplier_purchase_orders', function ($join) {
                $join->on('agent_supplier_purchase_orders.supplier_id', '=', 'suppliers.id')
                    ->whereNull('agent_supplier_purchase_orders.deleted_at');
            })
            ->leftJoin('currencies', 'currencies.id', '=', 'agent_supplier_purchase_orders.currency_id')
            ->where('agents.group_id', $this->group->id)
            ->groupBy('agents.id', 'agents.code', 'agents.slug')
            ->select([
                'agents.id',
                'agents.code',
                'agents.slug',
                DB::raw("count(*) filter (where agent_supplier_purchase_orders.id is not null and agent_supplier_purchase_orders.delivery_state not in ('received','checked','placed','cancelled') and agent_supplier_purchase_orders.state != 'cancelled' and (agent_supplier_purchase_orders.data -> 'housekeeping') is null) as open_aspos"),
                DB::raw("max($ageExpr) filter (where agent_supplier_purchase_orders.delivery_state not in ('received','checked','placed','cancelled') and agent_supplier_purchase_orders.state != 'cancelled' and (agent_supplier_purchase_orders.data -> 'housekeeping') is null) as oldest_stalled_days"),
                DB::raw("count(*) filter (where agent_supplier_purchase_orders.state != 'cancelled') as total_aspos"),
                DB::raw("count(*) filter (where agent_supplier_purchase_orders.delivery_state in ('received','checked','placed') and agent_supplier_purchase_orders.state != 'cancelled') as delivered_aspos"),
            ])
            ->having(DB::raw('count(agent_supplier_purchase_orders.id)'), '>', 0)
            ->orderByDesc('open_aspos')
            ->limit(self::LIMIT)
            ->get();

        $agentIds = $rows->pluck('id');

        $deposits = DB::table('agent_supplier_purchase_orders')
            ->join('suppliers', 'suppliers.id', '=', 'agent_supplier_purchase_orders.supplier_id')
            ->join('currencies', 'currencies.id', '=', 'agent_supplier_purchase_orders.currency_id')
            ->whereIn('suppliers.agent_id', $agentIds)
            ->whereNotIn('agent_supplier_purchase_orders.delivery_state', ['received', 'checked', 'placed', 'cancelled'])
            ->where('agent_supplier_purchase_orders.state', '!=', 'cancelled')
            ->whereRaw("(agent_supplier_purchase_orders.data -> 'housekeeping') IS NULL")
            ->whereNotNull('agent_supplier_purchase_orders.deposit_amount')
            ->select(['suppliers.agent_id', 'currencies.code as currency_code', DB::raw('sum(agent_supplier_purchase_orders.deposit_amount) as total')])
            ->groupBy('suppliers.agent_id', 'currencies.code')
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
            $row->delivered_ratio = $row->total_aspos > 0 ? round($row->delivered_aspos / $row->total_aspos, 2) : null;

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
                'stalled_aspos'      => $data['stalled_aspos'],
                'deposits_at_risk'   => $data['deposits_at_risk'],
                'pos_without_action' => $data['pos_without_action'],
                'agent_scorecard'    => $data['agent_scorecard'],
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
