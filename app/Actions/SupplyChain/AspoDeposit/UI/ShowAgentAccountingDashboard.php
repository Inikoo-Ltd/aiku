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
use App\Actions\SupplyChain\AgentPayment\UI\IndexAgentLedger;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent's money with its clients at a glance: each client's balance in the ledger, the money requests still due, the ledger lines waiting for review and the latest payments to factories.
 */
class ShowAgentAccountingDashboard extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    public function handle(Agent $agent): array
    {
        $wallets = collect(IndexAgentLedger::make()->wallets($agent));

        $requests = fn (DepositRequestStateEnum $state) => DB::table('deposit_requests')
            ->join('organisations', 'organisations.id', 'deposit_requests.organisation_id')
            ->join('currencies', 'currencies.id', 'deposit_requests.currency_id')
            ->where('deposit_requests.agent_id', $agent->id)
            ->where('deposit_requests.state', $state->value)
            ->select(['deposit_requests.id', 'deposit_requests.reference', 'deposit_requests.requested_at', 'deposit_requests.settled_at', 'organisations.code as client_code', 'currencies.code as currency_code'])
            ->selectSub(DB::table('deposit_request_items')->whereColumn('deposit_request_id', 'deposit_requests.id')->selectRaw('coalesce(sum(amount), 0)'), 'total')
            ->selectSub(DB::table('agent_payments')->whereColumn('deposit_request_id', 'deposit_requests.id')->whereNull('deleted_at')->selectRaw('coalesce(sum(amount), 0)'), 'paid');

        $openRequests = $requests(DepositRequestStateEnum::REQUESTED)
            ->orderBy('deposit_requests.requested_at')
            ->get()
            ->map(fn ($request) => [
                'id'            => $request->id,
                'reference'     => $request->reference,
                'client_code'   => $request->client_code,
                'currency_code' => $request->currency_code,
                'requested_at'  => $request->requested_at,
                'total'         => round((float) $request->total, 2),
                'outstanding'   => max(0, round((float) $request->total - (float) $request->paid, 2)),
            ]);

        $recentlySettled = $requests(DepositRequestStateEnum::SETTLED)
            ->orderByDesc('deposit_requests.settled_at')
            ->limit(5)
            ->get()
            ->map(fn ($request) => [
                'id'            => $request->id,
                'reference'     => $request->reference,
                'client_code'   => $request->client_code,
                'currency_code' => $request->currency_code,
                'settled_at'    => $request->settled_at,
                'total'         => round((float) $request->total, 2),
            ]);

        $recentlyPaid = DB::table('agent_payments')
            ->join('organisations', 'organisations.id', 'agent_payments.organisation_id')
            ->join('currencies', 'currencies.id', 'agent_payments.currency_id')
            ->leftJoin('purchase_orders', 'purchase_orders.id', 'agent_payments.purchase_order_id')
            ->leftJoin('suppliers', 'suppliers.id', 'agent_payments.supplier_id')
            ->where('agent_payments.agent_id', $agent->id)
            ->whereIn('agent_payments.type', AgentPaymentTypeEnum::supplierPaymentValues())
            ->whereNull('agent_payments.deleted_at')
            ->whereNotNull('agent_payments.date')
            ->orderByDesc('agent_payments.date')
            ->orderByDesc('agent_payments.id')
            ->limit(5)
            ->get(['agent_payments.id', 'agent_payments.date', 'agent_payments.amount', 'currencies.code as currency_code', 'organisations.code as client_code', 'purchase_orders.reference as purchase_order', 'suppliers.code as supplier_code'])
            ->map(fn ($line) => [
                'id'             => $line->id,
                'date'           => $line->date,
                'amount'         => round((float) $line->amount, 2),
                'currency_code'  => $line->currency_code,
                'client_code'    => $line->client_code,
                'purchase_order' => $line->purchase_order,
                'supplier_code'  => $line->supplier_code,
            ]);

        $sumByCurrency = fn ($rows, string $field) => $rows->groupBy('currency_code')
            ->map(fn ($group, $code) => ['currency_code' => $code, 'amount' => round((float) $group->sum($field), 2)])
            ->values();

        return [
            'summary' => [
                'balance'          => $sumByCurrency($wallets, 'balance'),
                'clients_owing'    => $wallets->where('balance', '<', 0)->count(),
                'open_requests'    => $openRequests->count(),
                'open_requests_amount' => $sumByCurrency($openRequests, 'outstanding'),
                'needs_review'     => DB::table('agent_payments')->where('agent_id', $agent->id)->where('needs_review', true)->whereNull('deleted_at')->count(),
            ],
            'wallets'          => $wallets->values(),
            'open_requests'    => $openRequests->values(),
            'recently_settled' => $recentlySettled->values(),
            'recently_paid'    => $recentlyPaid->values(),
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
                    'ledger'           => ['name' => 'grp.org.agent.accounting.ledger.index', 'parameters' => ['organisation' => $this->organisation->slug]],
                    'deposit_requests' => ['name' => 'grp.org.agent.accounting.deposit_requests.index', 'parameters' => ['organisation' => $this->organisation->slug]],
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
