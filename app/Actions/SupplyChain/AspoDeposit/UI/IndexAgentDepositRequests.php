<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AspoDeposit\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\DepositRequest;
use App\Models\SupplyChain\DepositRequestItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The requests through which our organisations reimburse the agent for the deposits it paid, with the item each organisation owes.
 */
class IndexAgentDepositRequests extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    public function handle(Agent $agent, ?string $search = null, ?string $state = null): LengthAwarePaginator
    {
        return $agent->depositRequests()
            ->with(['currency:id,code', 'items.organisation:id,name,code', 'items.aspoDeposit:id,reference'])
            ->when($state, fn ($query) => $query->where('deposit_requests.state', $state))
            ->when($search, fn ($query) => $query->whereAnyWordStartWith('deposit_requests.reference', $search))
            ->orderByDesc('deposit_requests.id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (DepositRequest $request) => [
                'id'            => $request->id,
                'reference'     => $request->reference,
                'currency_code' => $request->currency->code,
                'state'         => $request->state->value,
                'state_label'   => DepositRequestStateEnum::labels()[$request->state->value],
                'requested_at'  => $request->requested_at,
                'settled_at'    => $request->settled_at,
                'cancelled_at'  => $request->cancelled_at,
                'total'         => (float) $request->items->sum('amount'),
                'outstanding'   => (float) $request->items->whereNull('paid_at')->sum('amount'),
                'number_items'  => $request->items->count(),
                'items'         => $request->items->map(fn (DepositRequestItem $item) => [
                    'id'           => $item->id,
                    'organisation' => $item->organisation->name,
                    'deposit'      => $item->aspoDeposit?->reference,
                    'amount'       => (float) $item->amount,
                    'exchange'     => (float) $item->exchange,
                    'paid_at'      => $item->paid_at,
                ])->values(),
            ]);
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        return $this->handle(
            $agent,
            $request->string('search')->trim()->value() ?: null,
            DepositRequestStateEnum::tryFrom($request->string('state')->value())?->value
        );
    }

    public function htmlResponse(LengthAwarePaginator $depositRequests, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Procurement/AgentDepositRequests',
            [
                'title'       => __('Deposit requests'),
                'breadcrumbs' => array_merge(
                    ShowAgentAccountingDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('Deposit requests'),
                                'route' => [
                                    'name'       => 'grp.org.agent.accounting.deposit_requests.index',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                    ]
                ),
                'pageHead'    => [
                    'icon'  => [
                        'title' => __('Deposit requests'),
                        'icon'  => 'fal fa-money-check-alt',
                    ],
                    'title' => __('Deposit requests'),
                ],
                'search'      => $request->string('search')->value(),
                'state'       => DepositRequestStateEnum::tryFrom($request->string('state')->value())?->value ?? '',
                'states'      => collect(DepositRequestStateEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                'data'        => $depositRequests,
            ]
        );
    }
}
