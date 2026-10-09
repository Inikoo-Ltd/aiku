<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * Open purchase orders of an agent as columns per state. Settled orders only stay on the board for a month;
 * each column shows its oldest orders first and caps how many it renders, linking to the list for the rest.
 */
class ShowAgentPurchaseOrdersBoard extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    private const int CARDS_PER_COLUMN = 100;

    private const int SETTLED_DAYS = 30;

    /**
     * @var array<string, string> state => column the waiting time is measured from
     */
    private const array WAITING_SINCE = [
        'in_process' => 'purchase_orders.date',
        'submitted'  => 'submitted_at',
        'confirmed'  => 'confirmed_at',
        'settled'    => 'settled_at',
    ];

    public function handle(Agent $agent, ?int $supplierId = null, ?int $organisationId = null): array
    {
        $organisation         = $agent->organisation;
        $groupToAgentExchange = GetCurrencyExchange::run($organisation->group->currency, $organisation->currency);
        $currency             = $groupToAgentExchange ? $organisation->currency->code : $organisation->group->currency->code;
        $toAgentCurrency      = 'grp_exchange * '.(float) ($groupToAgentExchange ?: 1);

        $boardPurchaseOrders = fn (): Builder => DB::table('purchase_orders')
            ->where('purchase_orders.agent_id', $agent->id)
            ->where('purchase_orders.parent_type', 'OrgSupplier')
            ->whereNull('purchase_orders.deleted_at')
            ->where(function (Builder $query) {
                $query->whereIn('purchase_orders.state', [
                    PurchaseOrderStateEnum::IN_PROCESS->value,
                    PurchaseOrderStateEnum::SUBMITTED->value,
                    PurchaseOrderStateEnum::CONFIRMED->value,
                ])->orWhere(function (Builder $query) {
                    $query->where('purchase_orders.state', PurchaseOrderStateEnum::SETTLED->value)
                        ->where('settled_at', '>=', now()->subDays(self::SETTLED_DAYS));
                });
            });

        $suppliers = $boardPurchaseOrders()
            ->selectRaw('parent_id as id, parent_name as name, count(*) as number')
            ->groupBy('parent_id', 'parent_name')
            ->orderBy('parent_name')
            ->get()
            ->map(fn ($row) => ['value' => (int) $row->id, 'label' => $row->name.' ('.$row->number.')'])
            ->values();

        $organisations = $boardPurchaseOrders()
            ->join('organisations', 'organisations.id', '=', 'purchase_orders.organisation_id')
            ->selectRaw('organisations.id, organisations.name, count(*) as number')
            ->groupBy('organisations.id', 'organisations.name')
            ->orderBy('organisations.name')
            ->get()
            ->map(fn ($row) => ['value' => (int) $row->id, 'label' => $row->name.' ('.$row->number.')'])
            ->values();

        $filtered = fn (): Builder => $boardPurchaseOrders()
            ->when($supplierId, fn (Builder $query) => $query->where('purchase_orders.parent_id', $supplierId))
            ->when($organisationId, fn (Builder $query) => $query->where('purchase_orders.organisation_id', $organisationId));

        $columns = collect(self::WAITING_SINCE)->map(function (string $since, string $state) use ($filtered, $toAgentCurrency, $organisation) {
            $summary = $filtered()->where('purchase_orders.state', $state)
                ->selectRaw("count(*) as number, coalesce(sum(cost_total * {$toAgentCurrency}), 0) as total")
                ->first();

            $cards = $filtered()->where('purchase_orders.state', $state)
                ->leftJoin('organisations', 'organisations.id', '=', 'purchase_orders.organisation_id')
                ->selectRaw(
                    "purchase_orders.id, purchase_orders.slug, purchase_orders.reference, purchase_orders.parent_name, purchase_orders.agent_order_reference,
                    organisations.name as organisation, purchase_orders.cost_total * {$toAgentCurrency} as amount,
                    purchase_orders.estimated_received_at,
                    floor(extract(epoch from now() - coalesce({$since}, purchase_orders.date)) / 86400)::int as days_waiting"
                )
                ->orderByRaw("coalesce({$since}, purchase_orders.date) asc")
                ->limit(self::CARDS_PER_COLUMN)
                ->get()
                ->map(fn ($row) => [
                    'id'           => $row->id,
                    'reference'    => $row->reference,
                    'supplier'     => $row->parent_name,
                    'organisation' => $row->organisation,
                    'agent_order'  => $row->agent_order_reference,
                    'amount'       => (float) $row->amount,
                    'days_waiting' => (int) $row->days_waiting,
                    'is_overdue'   => $row->estimated_received_at !== null && $row->estimated_received_at < now(),
                    'route'        => [
                        'name'       => 'grp.org.procurement.purchase_orders.show',
                        'parameters' => [$organisation->slug, $row->slug],
                    ],
                ])
                ->values();

            return [
                'key'   => $state,
                'label' => PurchaseOrderStateEnum::labels()[$state],
                'icon'  => PurchaseOrderStateEnum::stateIcon()[$state]['icon'],
                'count' => (int) $summary->number,
                'total' => (float) $summary->total,
                'cards' => $cards,
                'route' => [
                    'name'       => 'grp.org.agent.purchase_orders.index',
                    'parameters' => [
                        'organisation' => $organisation->slug,
                        '_query'       => ['elements[state]' => $state, 'sort' => 'date'],
                    ],
                ],
            ];
        })->values()->all();

        return [
            'currency'      => $currency,
            'settled_days'  => self::SETTLED_DAYS,
            'columns'       => $columns,
            'suppliers'     => $suppliers,
            'organisations' => $organisations,
            'filters'       => ['supplier' => $supplierId, 'organisation' => $organisationId],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        return $this->handle(
            $agent,
            $request->integer('supplier') ?: null,
            $request->integer('organisation') ?: null
        );
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Procurement/AgentPurchaseOrdersBoard',
            [
                'title'       => __('Purchase orders board'),
                'breadcrumbs' => array_merge(
                    ShowProcurementDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('Purchase orders'),
                                'route' => [
                                    'name'       => 'grp.org.agent.purchase_orders.dashboard',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('Board'),
                                'route' => [
                                    'name'       => 'grp.org.agent.purchase_orders.board',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                    ]
                ),
                'pageHead'    => [
                    'icon'  => [
                        'title' => __('Purchase orders board'),
                        'icon'  => 'fal fa-columns',
                    ],
                    'title' => __('Purchase orders board'),
                ],
                'data'        => $data,
            ]
        );
    }
}
