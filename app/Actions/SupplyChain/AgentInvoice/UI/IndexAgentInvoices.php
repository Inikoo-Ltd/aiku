<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentInvoice\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\AgentInvoice\StoreAgentInvoice;
use App\Actions\SupplyChain\AspoDeposit\UI\ShowAgentAccountingDashboard;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AgentInvoice;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * Every invoice the agent has issued for a container, with what has been paid towards it.
 */
class IndexAgentInvoices extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    public const array FILTERS = ['balance_due', 'paid', 'at_agent'];

    public function handle(Agent $agent, ?string $search = null, ?string $filter = null): LengthAwarePaginator
    {
        $paid = '(coalesce((select sum(amount) from stock_delivery_deposit_applications where stock_delivery_id = agent_invoices.stock_delivery_id and deleted_at is null), 0)'
            .' + coalesce((select sum(amount) from agent_payments where stock_delivery_id = agent_invoices.stock_delivery_id and deleted_at is null), 0))';

        return AgentInvoice::query()
            ->where('agent_invoices.agent_id', $agent->id)
            ->select('agent_invoices.*')
            ->selectRaw("$paid as paid_amount")
            ->with(['currency:id,code', 'organisation:id,name', 'stockDelivery:id,slug,reference,state'])
            ->when($filter === 'balance_due', fn (Builder $query) => $query->whereRaw("agent_invoices.total_amount - $paid > 0.005"))
            ->when($filter === 'paid', fn (Builder $query) => $query->whereRaw("agent_invoices.total_amount - $paid <= 0.005"))
            ->when($filter === 'at_agent', fn (Builder $query) => $query->whereHas('stockDelivery', fn (Builder $query) => $query->whereIn('state', StoreAgentInvoice::OPEN_STATES)))
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereAnyWordStartWith('agent_invoices.reference', $search)
                ->orWhereHas('stockDelivery', fn (Builder $query) => $query->whereAnyWordStartWith('stock_deliveries.reference', $search))))
            ->orderByDesc('agent_invoices.id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AgentInvoice $invoice) => [
                'id'            => $invoice->id,
                'reference'     => $invoice->reference,
                'date'          => $invoice->date->toDateString(),
                'currency_code' => $invoice->currency->code,
                'total_amount'  => (float) $invoice->total_amount,
                'paid_amount'   => round((float) $invoice->paid_amount, 2),
                'balance_due'   => round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2),
                'organisation'  => $invoice->organisation?->name,
                'pdf_route'     => [
                    'name'       => 'grp.org.agent.agent_invoices.pdf',
                    'parameters' => [$this->organisation->slug, $invoice->id],
                ],
                'container'     => [
                    'reference'   => $invoice->stockDelivery->reference,
                    'state'       => $invoice->stockDelivery->state->value,
                    'state_label' => StockDeliveryStateEnum::labels()[$invoice->stockDelivery->state->value],
                    'at_agent'    => in_array($invoice->stockDelivery->state, StoreAgentInvoice::OPEN_STATES, true),
                    'route'       => [
                        'name'       => 'grp.org.procurement.stock_deliveries.show',
                        'parameters' => [$this->organisation->slug, $invoice->stockDelivery->slug],
                    ],
                ],
            ]);
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        $filter = $request->string('filter')->value();

        return $this->handle(
            $agent,
            $request->string('search')->trim()->value() ?: null,
            in_array($filter, self::FILTERS, true) ? $filter : null
        );
    }

    public function htmlResponse(LengthAwarePaginator $invoices, ActionRequest $request): Response
    {
        $filter = $request->string('filter')->value();

        return Inertia::render(
            'Org/Procurement/AgentInvoices',
            [
                'title'       => __('Invoices'),
                'breadcrumbs' => array_merge(
                    ShowAgentAccountingDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('Invoices'),
                                'route' => [
                                    'name'       => 'grp.org.agent.accounting.invoices.index',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                    ]
                ),
                'pageHead'    => [
                    'icon'  => [
                        'title' => __('Invoices'),
                        'icon'  => 'fal fa-file-invoice',
                    ],
                    'title' => __('Invoices'),
                ],
                'search'      => $request->string('search')->value(),
                'filter'      => in_array($filter, self::FILTERS, true) ? $filter : '',
                'filters'     => [
                    ['label' => __('Balance due'), 'value' => 'balance_due'],
                    ['label' => __('Paid'), 'value' => 'paid'],
                    ['label' => __('Container at agent'), 'value' => 'at_agent'],
                ],
                'data'        => $invoices,
            ]
        );
    }
}
