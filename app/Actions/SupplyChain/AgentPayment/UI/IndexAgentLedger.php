<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentPayment\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\AgentPayment\DeleteAgentPayment;
use App\Actions\SupplyChain\AgentPayment\ImportAgentLedgerExcel;
use App\Actions\SupplyChain\AspoDeposit\UI\ShowAgentAccountingDashboard;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AgentPayment;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The agent's ledger with its clients: every transfer a client sent and every payment made from it, with each
 * client's wallet (received, paid out, balance) worked out from the lines, so it replaces the Excel balance sheet.
 */
class IndexAgentLedger extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    private Agent $agent;

    /**
     * @return array<string, array{label: string, elements: array<string, array{0: string, 1: null}>, engine: Closure}>
     */
    private function getElementGroups(Agent $agent): array
    {
        return [
            'client'    => [
                'label'    => __('Client'),
                'elements' => $this->clients($agent)->mapWithKeys(fn (Organisation $client) => [(string) $client->id => [$client->code, null]])->all(),
                'engine'   => fn ($query, $elements) => $query->whereIn('agent_payments.organisation_id', $elements),
            ],
            'review'    => [
                'label'    => __('Review status'),
                'elements' => ['needs_review' => [__('Needs review'), null]],
                'engine'   => fn ($query, $elements) => $query->where('agent_payments.needs_review', true),
            ],
            'unallocated' => [
                'label'    => __('Container'),
                'elements' => ['unallocated' => [__('Not on a container'), null]],
                'engine'   => fn ($query, $elements) => $query->whereNull('agent_payments.stock_delivery_id')
                    ->whereNotIn('agent_payments.type', array_merge(AgentPaymentTypeEnum::moneyInValues(), [AgentPaymentTypeEnum::SUPPLIER_PAYMENT->value, AgentPaymentTypeEnum::SUPPLIER_DEPOSIT->value, AgentPaymentTypeEnum::SUPPLIER_BALANCE->value])),
            ],
            'direction' => [
                'label'    => __('Money'),
                'elements' => ['in' => [__('In'), null], 'out' => [__('Out'), null]],
                'engine'   => fn ($query, $elements) => count($elements) === 1
                    ? ($elements[0] === 'in'
                        ? $query->whereIn('agent_payments.type', AgentPaymentTypeEnum::moneyInValues())
                        : $query->whereNotIn('agent_payments.type', AgentPaymentTypeEnum::moneyInValues()))
                    : $query,
            ],
        ];
    }

    public function handle(Agent $agent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($query) => $query
                ->whereAnyWordStartWith('agent_payments.reference', $value)
                ->orWhereAnyWordStartWith('purchase_orders.reference', $value)
                ->orWhereAnyWordStartWith('stock_deliveries.reference', $value)
                ->orWhereAnyWordStartWith('suppliers.code', $value)
                ->orWhere('agent_payments.notes', 'ILIKE', "%$value%"));
        });

        $query = QueryBuilder::for(AgentPayment::class)
            ->where('agent_payments.agent_id', $agent->id)
            ->join('organisations', 'organisations.id', 'agent_payments.organisation_id')
            ->join('currencies', 'currencies.id', 'agent_payments.currency_id')
            ->leftJoin('currencies as original_currencies', 'original_currencies.id', 'agent_payments.original_currency_id')
            ->leftJoin('purchase_orders', 'purchase_orders.id', 'agent_payments.purchase_order_id')
            ->leftJoin('suppliers', 'suppliers.id', 'agent_payments.supplier_id')
            ->leftJoin('stock_deliveries', 'stock_deliveries.id', 'agent_payments.stock_delivery_id')
            ->leftJoin('media', 'media.id', 'agent_payments.media_id')
            ->leftJoin('org_stocks', 'org_stocks.id', 'agent_payments.org_stock_id');

        foreach ($this->getElementGroups($agent) as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
                optional: in_array($key, ['review', 'unallocated'], true),
            );
        }

        return $query
            ->select([
                'agent_payments.*',
                'organisations.code as client_code',
                'currencies.code as currency_code',
                'original_currencies.code as original_currency_code',
                'purchase_orders.reference as purchase_order_reference',
                'purchase_orders.slug as purchase_order_slug',
                'suppliers.code as supplier_code',
                'stock_deliveries.reference as stock_delivery_reference',
                'stock_deliveries.slug as stock_delivery_slug',
                'media.ulid as receipt_ulid',
                'org_stocks.code as org_stock_code',
            ])
            ->with('agent:id,organisation_id')
            ->defaultSort('-date')
            ->allowedSorts(['date', 'amount'])
            ->orderByDesc('agent_payments.id')
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    /**
     * @return Collection<int, Organisation>
     */
    private function clients(Agent $agent): Collection
    {
        return Organisation::whereIn('id', OrgAgent::where('agent_id', $agent->id)->select('organisation_id'))
            ->with('currency:id,code')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'currency_id']);
    }

    /**
     * What each client sent and what was paid from it, by currency; a client with no lines yet shows at zero.
     *
     * @return list<array{organisation_id: int, code: string, name: string, currency_code: string, received: float, paid: float, balance: float}>
     */
    public function wallets(Agent $agent): array
    {
        $moneyIn = AgentPaymentTypeEnum::moneyInValues();
        $sums    = DB::table('agent_payments')
            ->join('currencies', 'currencies.id', 'agent_payments.currency_id')
            ->where('agent_payments.agent_id', $agent->id)
            ->whereNull('agent_payments.deleted_at')
            ->groupBy('agent_payments.organisation_id', 'currencies.code')
            ->selectRaw('agent_payments.organisation_id, currencies.code as currency_code')
            ->selectRaw('sum(case when agent_payments.type in ('.implode(',', array_fill(0, count($moneyIn), '?')).') then agent_payments.amount else 0 end) as received', $moneyIn)
            ->selectRaw('sum(case when agent_payments.type not in ('.implode(',', array_fill(0, count($moneyIn), '?')).') then agent_payments.amount else 0 end) as paid', $moneyIn)
            ->get()
            ->groupBy('organisation_id');

        $agentCurrency = $agent->organisation->currency->code;
        $orgAgents     = OrgAgent::where('agent_id', $agent->id)->get(['id', 'organisation_id', 'agent_fee_percentage'])->keyBy('organisation_id');

        return $this->clients($agent)->flatMap(function (Organisation $client) use ($sums, $agentCurrency, $orgAgents) {
            $rows = $sums->get($client->id) ?? collect([(object) ['currency_code' => $agentCurrency, 'received' => 0, 'paid' => 0]]);

            return $rows->map(fn ($row) => [
                'organisation_id' => $client->id,
                'code'            => $client->code,
                'name'            => $client->name,
                'currency_code'   => $row->currency_code,
                'received'        => round((float) $row->received, 2),
                'paid'            => round((float) $row->paid, 2),
                'balance'         => round((float) $row->received - (float) $row->paid, 2),
                'org_agent_id'    => $orgAgents->get($client->id)?->id,
                'agent_fee_percentage' => $orgAgents->get($client->id)?->agent_fee_percentage !== null ? (float) $orgAgents->get($client->id)->agent_fee_percentage : null,
            ]);
        })->values()->all();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($this->agent) as $key => $elementGroup) {
                $table->elementGroup(key: $key, label: $elementGroup['label'], elements: $elementGroup['elements']);
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(['title' => __('No money recorded yet')])
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'client_code', label: __('Client'), canBeHidden: false)
                ->column(key: 'type', label: __('Type'), canBeHidden: false)
                ->column(key: 'for', label: __('For'), canBeHidden: false)
                ->column(key: 'money_in', label: __('In'), tooltip: __('Money into the client\'s wallet held by the agent: transfers the client sent and refunds from suppliers. Increases the wallet balance'), canBeHidden: false, align: 'right', tooltipIcon: true)
                ->column(key: 'money_out', label: __('Out'), tooltip: __('Money the agent spent from the client\'s wallet: supplier payments, charges and the agent fee. Decreases the wallet balance'), canBeHidden: false, align: 'right', tooltipIcon: true)
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false)
                ->column(key: 'actions', label: '', canBeHidden: false, align: 'right')
                ->defaultSort('-date');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);
        $this->agent = $agent;

        return $this->handle($agent);
    }

    public function htmlResponse(LengthAwarePaginator $entries, ActionRequest $request): Response
    {
        $user    = $request->user();
        $clients = $this->clients($this->agent);
        $canEdit = $user->authTo("procurement.{$this->organisation->id}.edit");

        return Inertia::render(
            'Org/Procurement/AgentLedger',
            [
                'title'       => __('Ledger'),
                'breadcrumbs' => array_merge(
                    ShowAgentAccountingDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('Ledger'),
                                'route' => [
                                    'name'       => 'grp.org.agent.accounting.ledger.index',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                    ]
                ),
                'pageHead'    => [
                    'icon'  => ['title' => __('Ledger'), 'icon' => 'fal fa-book'],
                    'title' => __('Ledger'),
                ],
                'wallets'     => $this->wallets($this->agent),
                'form'        => $canEdit ? [
                    'store_route'     => ['name' => 'grp.models.agent.ledger_entry.store', 'parameters' => ['agent' => $this->agent->id]],
                    'agent_fee_route_name' => 'grp.models.org_agent.agent_fee.update',
                    'currency_code'   => $this->organisation->currency->code,
                    'clients'         => $clients->map(fn (Organisation $client) => [
                        'id'            => $client->id,
                        'label'         => $client->code.' · '.$client->name,
                        'currency_code' => $client->currency->code,
                    ])->values()->all(),
                    'types'           => collect(AgentPaymentTypeEnum::cases())->map(fn (AgentPaymentTypeEnum $type) => [
                        'value'          => $type->value,
                        'label'          => AgentPaymentTypeEnum::labels()[$type->value],
                        'money_in'       => $type->isMoneyIn(),
                        'needs_order'    => $type->isSupplierPayment(),
                    ])->values()->all(),
                    'purchase_orders' => $this->openPurchaseOrders($clients),
                    'containers'      => $this->recentContainers($clients),
                ] : null,
                'data'        => $entries->through(fn (AgentPayment $entry) => [
                    'id'                     => $entry->id,
                    'date'                   => $entry->date->toDateString(),
                    'client_code'            => $entry->client_code,
                    'type'                   => $entry->type->value,
                    'type_label'             => AgentPaymentTypeEnum::labels()[$entry->type->value],
                    'money_in'               => $entry->type->isMoneyIn() ? (float) $entry->amount : null,
                    'money_out'              => $entry->type->isMoneyIn() ? null : (float) $entry->amount,
                    'currency_code'          => $entry->currency_code,
                    'original_amount'        => $entry->original_amount ? (float) $entry->original_amount : null,
                    'original_currency_code' => $entry->original_currency_code,
                    'supplier_code'          => $entry->supplier_code,
                    'purchase_order'         => $entry->purchase_order_slug ? [
                        'reference' => $entry->purchase_order_reference,
                        'route'     => ['name' => 'grp.org.agent.purchase_orders.show', 'parameters' => [$this->organisation->slug, $entry->purchase_order_slug]],
                    ] : null,
                    'stock_delivery'         => $entry->stock_delivery_slug ? [
                        'reference' => $entry->stock_delivery_reference,
                        'route'     => ['name' => 'grp.org.agent.stock_deliveries.show', 'parameters' => [$this->organisation->slug, $entry->stock_delivery_slug]],
                    ] : null,
                    'reference'              => $entry->reference,
                    'notes'                  => $entry->notes,
                    'receipt'                => $entry->receipt_ulid,
                    'expected_arrival'       => $entry->expectedArrival()?->gte(today()) ? $entry->expectedArrival()->toDateString() : null,
                    'purchase_order_id'      => $entry->purchase_order_id,
                    'stock_delivery_id'      => $entry->stock_delivery_id,
                    'org_stock_id'           => $entry->org_stock_id,
                    'org_stock_code'         => $entry->org_stock_code,
                    'organisation_id'        => $entry->organisation_id,
                    'needs_review'           => $entry->needs_review,
                    'imported'               => Arr::get($entry->data ?? [], 'source') === ImportAgentLedgerExcel::SOURCE,
                    'review_reason'          => $entry->review_reason ? __($entry->review_reason) : null,
                    'update_route'           => $canEdit && !$entry->isRecordedByClient()
                        ? ['name' => 'grp.models.agent_payment.update', 'parameters' => ['agentPayment' => $entry->id]]
                        : null,
                    'delete_route'           => DeleteAgentPayment::canDelete($entry, $user)
                        ? ['name' => 'grp.models.agent_payment.delete', 'parameters' => ['agentPayment' => $entry->id]]
                        : null,
                ]),
            ]
        )->table($this->tableStructure());
    }

    /**
     * @param  Collection<int, Organisation>  $clients
     *
     * @return list<array{id: int, organisation_id: int, label: string}>
     */
    private function openPurchaseOrders(Collection $clients): array
    {
        return PurchaseOrder::where('agent_id', $this->agent->id)
            ->whereIn('organisation_id', $clients->pluck('id'))
            ->trackedThroughAgent()
            ->where(fn ($query) => $query
                ->whereIn('state', [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED])
                ->orWhere(fn ($query) => $query->where('state', PurchaseOrderStateEnum::SETTLED)->where('date', '>=', now()->subMonths(6))))
            ->orderByDesc('date')
            ->get(['id', 'organisation_id', 'reference', 'parent_code'])
            ->map(fn (PurchaseOrder $purchaseOrder) => [
                'id'              => $purchaseOrder->id,
                'organisation_id' => $purchaseOrder->organisation_id,
                'label'           => $purchaseOrder->reference.' · '.$purchaseOrder->parent_code,
            ])->values()->all();
    }

    /**
     * @param  Collection<int, Organisation>  $clients
     *
     * @return list<array{id: int, organisation_id: int, label: string, skos: list<array{id: int, label: string|null}>}>
     */
    private function recentContainers(Collection $clients): array
    {
        $containers = StockDelivery::where('agent_id', $this->agent->id)
            ->whereIn('organisation_id', $clients->pluck('id'))
            ->whereNotIn('state', [StockDeliveryStateEnum::CANCELLED, StockDeliveryStateEnum::NOT_RECEIVED])
            ->where('created_at', '>=', now()->subYear())
            ->orderByDesc('created_at')
            ->get(['id', 'organisation_id', 'reference', 'is_costed']);

        $containers->where('is_costed', false)
            ->load(['items' => fn ($query) => $query->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)->select(['id', 'stock_delivery_id', 'org_stock_id'])->with('orgStock:id,code')]);

        return $containers
            ->map(fn (StockDelivery $stockDelivery) => [
                'id'              => $stockDelivery->id,
                'organisation_id' => $stockDelivery->organisation_id,
                'label'           => $stockDelivery->reference,
                'skos'            => $stockDelivery->is_costed ? [] : $stockDelivery->items
                    ->map(fn (StockDeliveryItem $item) => ['id' => $item->org_stock_id, 'label' => $item->orgStock?->code])
                    ->unique('id')->sortBy('label')->values()->all(),
            ])->values()->all();
    }
}
