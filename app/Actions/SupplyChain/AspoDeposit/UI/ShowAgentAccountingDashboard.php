<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AspoDeposit\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\SupplyChain\AspoDeposit\AspoDepositStateEnum;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AspoDeposit;
use App\Models\SupplyChain\DepositRequest;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * What our organisations owe the agent: deposits it paid sub-suppliers that are still pending, and the deposit requests not yet settled.
 */
class ShowAgentAccountingDashboard extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    public function handle(Agent $agent): array
    {
        $pendingByOrganisation = DB::table('aspo_deposits')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'aspo_deposits.purchase_order_id')
            ->join('organisations', 'organisations.id', '=', 'purchase_orders.organisation_id')
            ->join('currencies', 'currencies.id', '=', 'aspo_deposits.currency_id')
            ->where('aspo_deposits.agent_id', $agent->id)
            ->where('aspo_deposits.state', AspoDepositStateEnum::PENDING->value)
            ->groupBy('organisations.id', 'organisations.name', 'organisations.code', 'currencies.code')
            ->selectRaw('organisations.id, organisations.name, organisations.code, currencies.code as currency_code, count(*) as number_deposits, sum(aspo_deposits.amount) as amount')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => [
                'id'              => $row->id,
                'name'            => $row->name,
                'code'            => $row->code,
                'currency_code'   => $row->currency_code,
                'number_deposits' => (int) $row->number_deposits,
                'amount'          => (float) $row->amount,
            ])
            ->values();

        $openRequests = $agent->depositRequests()
            ->where('state', DepositRequestStateEnum::REQUESTED)
            ->with('currency:id,code')
            ->withCount('items')
            ->withSum('items as outstanding', DB::raw('case when paid_at is null then amount else 0 end'))
            ->withSum('items as total', 'amount')
            ->orderBy('requested_at')
            ->get()
            ->map(fn (DepositRequest $request) => [
                'id'            => $request->id,
                'reference'     => $request->reference,
                'currency_code' => $request->currency->code,
                'requested_at'  => $request->requested_at,
                'number_items'  => $request->items_count,
                'total'         => (float) $request->total,
                'outstanding'   => (float) $request->outstanding,
            ])
            ->values();

        $recentlySettled = $agent->depositRequests()
            ->where('state', DepositRequestStateEnum::SETTLED)
            ->with('currency:id,code')
            ->withSum('items as total', 'amount')
            ->orderByDesc('settled_at')
            ->limit(5)
            ->get()
            ->map(fn (DepositRequest $request) => [
                'id'            => $request->id,
                'reference'     => $request->reference,
                'currency_code' => $request->currency->code,
                'settled_at'    => $request->settled_at,
                'total'         => (float) $request->total,
            ])
            ->values();

        $recentlyPaid = $agent->deposits()
            ->where('state', AspoDepositStateEnum::PAID_TO_SUPPLIER)
            ->with(['currency:id,code', 'purchaseOrder:id,reference,organisation_id', 'purchaseOrder.organisation:id,name'])
            ->orderByDesc('paid_to_supplier_at')
            ->limit(5)
            ->get()
            ->map(fn (AspoDeposit $deposit) => [
                'id'                  => $deposit->id,
                'reference'           => $deposit->reference,
                'currency_code'       => $deposit->currency->code,
                'amount'              => (float) $deposit->amount,
                'paid_to_supplier_at' => $deposit->paid_to_supplier_at,
                'purchase_order'      => $deposit->purchaseOrder?->reference,
                'organisation'        => $deposit->purchaseOrder?->organisation?->name,
            ])
            ->values();

        $sumByCurrency = fn ($rows, string $field) => $rows->groupBy('currency_code')
            ->map(fn ($group, $code) => ['currency_code' => $code, 'amount' => (float) $group->sum($field)])
            ->values();

        return [
            'summary' => [
                'pending_deposits'        => (int) $pendingByOrganisation->sum('number_deposits'),
                'pending_deposits_amount' => $sumByCurrency($pendingByOrganisation, 'amount'),
                'open_requests'           => $openRequests->count(),
                'open_requests_amount'    => $sumByCurrency($openRequests, 'outstanding'),
                'settled_requests'        => $agent->depositRequests()->where('state', DepositRequestStateEnum::SETTLED)->count(),
            ],
            'pending_by_organisation' => $pendingByOrganisation,
            'open_requests'           => $openRequests,
            'recently_settled'        => $recentlySettled,
            'recently_paid'           => $recentlyPaid,
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        return $this->handle($agent);
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Procurement/AgentAccountingDashboard',
            [
                'title'       => __('Accounting'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'pageHead'    => [
                    'icon'  => [
                        'title' => __('Accounting'),
                        'icon'  => 'fal fa-file-invoice-dollar',
                    ],
                    'title' => __('Accounting'),
                ],
                'routes'      => [
                    'deposits'         => ['name' => 'grp.org.agent.accounting.deposits.index', 'parameters' => [$this->organisation->slug]],
                    'deposit_requests' => ['name' => 'grp.org.agent.accounting.deposit_requests.index', 'parameters' => [$this->organisation->slug]],
                ],
                'data'        => $data,
            ]
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
                        'label' => __('Accounting'),
                        'route' => [
                            'name'       => 'grp.org.agent.accounting.dashboard',
                            'parameters' => [$routeParameters['organisation']],
                        ],
                    ],
                ],
            ]
        );
    }
}
