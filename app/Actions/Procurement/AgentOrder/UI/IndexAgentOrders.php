<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\AgentOrder\ResolveAgentOrderReference;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The supplier orders placed through an agent, grouped back into the agent orders staff placed
 * them in.
 */
class IndexAgentOrders extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithOrgAgentSubNavigation;

    private OrgAgent $orgAgent;

    public function handle(OrgAgent $orgAgent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('purchase_orders.agent_order_reference', $value)
                    ->orWhereStartWith('purchase_orders.parent_code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(PurchaseOrder::class)
            ->where('purchase_orders.organisation_id', $orgAgent->organisation_id)
            ->where('purchase_orders.agent_id', $orgAgent->agent_id)
            ->where('purchase_orders.parent_type', 'OrgSupplier')
            ->whereNotNull('purchase_orders.agent_order_reference')
            ->groupBy('purchase_orders.agent_order_reference')
            ->select('purchase_orders.agent_order_reference as reference')
            ->selectRaw('count(*) as number_supplier_orders')
            ->selectRaw("string_agg(purchase_orders.parent_code, ', ' order by purchase_orders.parent_code) as suppliers")
            ->selectRaw('sum(purchase_orders.number_current_purchase_order_transactions) as number_items')
            ->selectRaw('sum(purchase_orders.cost_total * coalesce(purchase_orders.org_exchange, 1)) as org_total_cost')
            ->selectRaw('min(purchase_orders.date) as date')
            ->selectRaw('array_to_json(array_agg(distinct purchase_orders.state)) as states')
            ->selectRaw('max(purchase_orders.id) as last_id')
            ->defaultSort('-last_id')
            ->allowedSorts(['reference', 'date', 'number_supplier_orders', 'org_total_cost', 'last_id'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('Agent order'), __('Agent orders')])
                ->column(key: 'state', label: __('State'), canBeHidden: false)
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'suppliers', label: __('Suppliers'), canBeHidden: false)
                ->column(key: 'number_items', label: __('Items'), canBeHidden: false, align: 'right')
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'org_total_cost', label: __('Amount'), canBeHidden: false, sortable: true, type: 'currency')
                ->defaultSort('-last_id');
        };
    }

    public function asController(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): LengthAwarePaginator
    {
        abort_unless($orgAgent->organisation_id === $organisation->id, 404);
        $this->orgAgent = $orgAgent;
        $this->initialisation($organisation, $request);

        return $this->handle($orgAgent);
    }

    public function htmlResponse(LengthAwarePaginator $agentOrders, ActionRequest $request): Response
    {
        $openReference = ResolveAgentOrderReference::make()->openAgentOrderReference($this->orgAgent);
        $currency      = $this->organisation->currency->code;

        return Inertia::render(
            'Procurement/AgentOrders',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Agent orders'),
                'pageHead'    => [
                    'title'         => $this->orgAgent->agent->organisation->name,
                    'icon'          => ['icon' => ['fal', 'fa-people-arrows'], 'title' => __('Agent orders')],
                    'afterTitle'    => ['label' => __('Agent orders')],
                    'iconRight'     => ['icon' => 'fal fa-boxes'],
                    'subNavigation' => $this->getOrgAgentNavigation($this->orgAgent),
                    'actions'       => $this->canEdit ? [
                        $openReference ? [
                            'type'  => 'button',
                            'style' => 'secondary',
                            'icon'  => 'fal fa-boxes',
                            'label' => __('Open :reference', ['reference' => $openReference]),
                            'route' => [
                                'name'       => 'grp.org.procurement.org_agents.show.agent_orders.show',
                                'parameters' => [$this->organisation->slug, $this->orgAgent->slug, $openReference],
                            ],
                        ] : [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('Agent order'),
                            'route' => [
                                'method'     => 'post',
                                'name'       => 'grp.models.org-agent.agent-order.store',
                                'parameters' => ['orgAgent' => $this->orgAgent->id],
                            ],
                        ],
                    ] : [],
                ],
                'data'        => $agentOrders->through(fn ($row) => [
                    'reference'              => $row->reference,
                    'number_supplier_orders' => (int) $row->number_supplier_orders,
                    'suppliers'              => $row->suppliers,
                    'number_items'           => (int) $row->number_items,
                    'date'                   => $row->date,
                    'org_total_cost'         => $row->org_total_cost,
                    'org_currency_code'      => $currency,
                    'state'                  => $this->stateOf(json_decode($row->states, true) ?? []),
                    'route'                  => [
                        'name'       => 'grp.org.procurement.org_agents.show.agent_orders.show',
                        'parameters' => [$this->organisation->slug, $this->orgAgent->slug, $row->reference],
                    ],
                ]),
            ]
        )->table($this->tableStructure());
    }

    /**
     * One state for the agent order: the state its supplier orders share, or the earliest one while
     * they are still apart.
     *
     * @param  array<int, string>  $states
     * @return array{value: string, label: string, icon: array<string, mixed>}
     */
    public static function stateOf(array $states): array
    {
        $order = array_map(fn (PurchaseOrderStateEnum $state) => $state->value, PurchaseOrderStateEnum::cases());
        $live  = array_values(array_diff($states, [PurchaseOrderStateEnum::CANCELLED->value, PurchaseOrderStateEnum::NOT_RECEIVED->value])) ?: $states;
        usort($live, fn ($a, $b) => array_search($a, $order) <=> array_search($b, $order));
        $state = PurchaseOrderStateEnum::from($live[0] ?? PurchaseOrderStateEnum::IN_PROCESS->value);

        return [
            'value' => $state->value,
            'label' => $state->labels()[$state->value].(count(array_unique($states)) > 1 ? ' +' : ''),
            'icon'  => $state->stateIcon()[$state->value],
        ];
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowOrgAgent::make()->getBreadcrumbs('grp.org.procurement.org_agents.show', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Agent orders'),
                        'icon'  => 'fal fa-bars',
                        'route' => [
                            'name'       => 'grp.org.procurement.org_agents.show.agent_orders.index',
                            'parameters' => $routeParameters,
                        ],
                    ],
                ],
            ]
        );
    }
}
