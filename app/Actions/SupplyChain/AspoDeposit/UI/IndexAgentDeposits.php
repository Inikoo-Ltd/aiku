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
use App\Enums\SupplyChain\AspoDeposit\AspoDepositStateEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AspoDeposit;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * Every deposit the agent paid a sub-supplier up front for one of our purchase orders.
 */
class IndexAgentDeposits extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    public function handle(Agent $agent, ?string $search = null, ?string $state = null): LengthAwarePaginator
    {
        return $agent->deposits()
            ->with(['currency:id,code', 'purchaseOrder:id,slug,reference,organisation_id', 'purchaseOrder.organisation:id,name,code'])
            ->when($state, fn ($query) => $query->where('aspo_deposits.state', $state))
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->whereAnyWordStartWith('aspo_deposits.reference', $search)
                ->orWhereHas('purchaseOrder', fn ($query) => $query->whereAnyWordStartWith('purchase_orders.reference', $search))))
            ->orderByDesc('aspo_deposits.id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AspoDeposit $deposit) => [
                'id'                  => $deposit->id,
                'reference'           => $deposit->reference,
                'amount'              => (float) $deposit->amount,
                'currency_code'       => $deposit->currency->code,
                'state'               => $deposit->state->value,
                'state_label'         => AspoDepositStateEnum::labels()[$deposit->state->value],
                'created_at'          => $deposit->created_at,
                'paid_to_supplier_at' => $deposit->paid_to_supplier_at,
                'refunded_at'         => $deposit->refunded_at,
                'cancelled_at'        => $deposit->cancelled_at,
                'notes'               => $deposit->notes,
                'organisation'        => $deposit->purchaseOrder?->organisation?->name,
                'purchase_order'      => $deposit->purchaseOrder ? [
                    'reference' => $deposit->purchaseOrder->reference,
                    'route'     => [
                        'name'       => 'grp.org.procurement.purchase_orders.show',
                        'parameters' => [$this->organisation->slug, $deposit->purchaseOrder->slug],
                    ],
                ] : null,
            ]);
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        $state = $request->string('state')->value();

        return $this->handle(
            $agent,
            $request->string('search')->trim()->value() ?: null,
            AspoDepositStateEnum::tryFrom($state)?->value
        );
    }

    public function htmlResponse(LengthAwarePaginator $deposits, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Procurement/AgentDeposits',
            [
                'title'       => __('Deposits'),
                'breadcrumbs' => array_merge(
                    ShowAgentAccountingDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('Deposits'),
                                'route' => [
                                    'name'       => 'grp.org.agent.accounting.deposits.index',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                    ]
                ),
                'pageHead'    => [
                    'icon'  => [
                        'title' => __('Deposits'),
                        'icon'  => 'fal fa-hand-holding-usd',
                    ],
                    'title' => __('Deposits'),
                ],
                'search'      => $request->string('search')->value(),
                'state'       => AspoDepositStateEnum::tryFrom($request->string('state')->value())?->value ?? '',
                'states'      => collect(AspoDepositStateEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                'data'        => $deposits,
            ]
        );
    }
}
