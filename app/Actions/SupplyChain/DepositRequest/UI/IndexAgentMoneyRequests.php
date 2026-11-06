<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\DepositRequest\UI;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\DepositRequest\ApproveAgentMoneyRequest;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\AspoDeposit\UI\ShowAgentAccountingDashboard;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestItemKindEnum;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\DepositRequest;
use App\Models\SupplyChain\DepositRequestItem;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * An agent's money requests: on the agent's side every client's, on a client's agent page only its own, where
 * it pays them. Each shows what was asked for, what has been paid against it and what is still due.
 */
class IndexAgentMoneyRequests extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    private Agent $agent;

    public function handle(Agent $agent, ?Organisation $client = null, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(DepositRequest::class)
            ->where('deposit_requests.agent_id', $agent->id)
            ->whereNotNull('deposit_requests.organisation_id')
            ->when($client, fn ($query) => $query->where('deposit_requests.organisation_id', $client->id))
            ->join('organisations', 'organisations.id', 'deposit_requests.organisation_id')
            ->join('currencies', 'currencies.id', 'deposit_requests.currency_id');

        $query->whereElementGroup(
            key: 'state',
            allowedElements: DepositRequestStateEnum::values(),
            engine: fn ($query, $elements) => $query->whereIn('deposit_requests.state', $elements),
            prefix: $prefix,
        );

        return $query
            ->select(['deposit_requests.*', 'organisations.code as client_code', 'organisations.slug as client_slug', 'currencies.code as currency_code'])
            ->selectSub(DB::table('deposit_request_items')->whereColumn('deposit_request_id', 'deposit_requests.id')->selectRaw('coalesce(sum(amount), 0)'), 'total')
            ->selectSub(DB::table('agent_payments')->whereColumn('deposit_request_id', 'deposit_requests.id')->whereNull('deleted_at')->selectRaw('coalesce(sum(amount), 0)'), 'paid')
            ->with(['requester:id,username,contact_name,nickname', 'approver:id,username,contact_name,nickname', 'items.purchaseOrder' => fn ($query) => $query
                ->leftJoin('suppliers', 'suppliers.id', 'purchase_orders.supplier_id')
                ->select(['purchase_orders.id', 'purchase_orders.slug', 'purchase_orders.reference', 'purchase_orders.agent_order_reference', 'suppliers.code as supplier_code', 'suppliers.name as supplier_name'])])
            ->defaultSort('-requested_at')
            ->allowedSorts(['requested_at', 'reference'])
            ->allowedFilters([AllowedFilter::callback('global', fn ($query, $value) => $query->where(fn ($query) => $query
                ->whereAnyWordStartWith('deposit_requests.reference', $value)
                ->orWhereExists(fn ($exists) => $exists->from('deposit_request_items')
                    ->join('purchase_orders', 'purchase_orders.id', 'deposit_request_items.purchase_order_id')
                    ->whereColumn('deposit_request_items.deposit_request_id', 'deposit_requests.id')
                    ->where(fn ($match) => $match->whereAnyWordStartWith('purchase_orders.reference', $value)->orWhereAnyWordStartWith('purchase_orders.agent_order_reference', $value)))))])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(DepositRequest $depositRequest, ?User $user, bool $forClient): array
    {
        $total  = round((float) $depositRequest->total, 2);
        $paid   = round((float) $depositRequest->paid, 2);
        $open   = $depositRequest->state === DepositRequestStateEnum::REQUESTED;
        $canPay = $forClient && $open && ApproveAgentMoneyRequest::canPay($depositRequest, $user);

        $purchaseOrderRoute = fn (?PurchaseOrder $purchaseOrder) => $purchaseOrder ? [
            'name'       => $forClient ? 'grp.org.procurement.purchase_orders.show' : 'grp.org.agent.purchase_orders.show',
            'parameters' => [$forClient ? $depositRequest->client_slug : $depositRequest->agent->organisation->slug, $purchaseOrder->slug],
        ] : null;

        return [
            'id'            => $depositRequest->id,
            'reference'     => $depositRequest->reference ?? '#'.$depositRequest->id,
            'client_code'   => $depositRequest->client_code,
            'requested_at'  => $depositRequest->requested_at,
            'state'         => $depositRequest->state->value,
            'state_label'   => DepositRequestStateEnum::labels()[$depositRequest->state->value],
            'currency_code' => $depositRequest->currency_code,
            'total'         => $total,
            'paid'          => $paid,
            'outstanding'   => $open ? max(0, round($total - $paid, 2)) : 0,
            'notes'         => $depositRequest->notes,
            'requested_by'  => $depositRequest->requester?->chatName(),
            'approved_at'   => $depositRequest->approved_at,
            'approved_by'   => $depositRequest->approver?->chatName(),
            'lines'         => $depositRequest->items->map(fn (DepositRequestItem $item) => [
                'kind'          => $item->kind->value,
                'kind_label'    => DepositRequestItemKindEnum::labels()[$item->kind->value],
                'reference'     => $item->purchaseOrder?->reference,
                'route'         => $purchaseOrderRoute($item->purchaseOrder),
                'description'   => $item->purchaseOrder ? $item->kind->describeForPurchaseOrder() : DepositRequestItemKindEnum::labels()[$item->kind->value],
                'supplier_code' => $item->purchaseOrder?->supplier_code,
                'supplier_name' => $item->purchaseOrder?->supplier_name,
                'amount'        => (float) $item->amount,
            ])->values()->all(),
            'cancel_route'  => !$forClient && $open && $paid == 0 && !$depositRequest->isApproved() && $user?->authTo("procurement.{$depositRequest->agent->organisation_id}.edit")
                ? ['name' => 'grp.models.deposit_request.cancel', 'parameters' => ['depositRequest' => $depositRequest->id], 'method' => 'patch']
                : null,
            'approve_route' => $canPay && !$depositRequest->isApproved()
                ? ['name' => 'grp.models.deposit_request.approve', 'parameters' => ['depositRequest' => $depositRequest->id], 'method' => 'patch']
                : null,
            'pay_route'     => $canPay && $depositRequest->isApproved()
                ? ['name' => 'grp.models.deposit_request.pay', 'parameters' => ['depositRequest' => $depositRequest->id], 'method' => 'post']
                : null,
        ];
    }

    public function tableStructure(?string $prefix = null, bool $forClient = false): Closure
    {
        return function (InertiaTable $table) use ($prefix, $forClient) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table->elementGroup(
                key: 'state',
                label: __('State'),
                elements: collect(DepositRequestStateEnum::labels())->map(fn ($label) => [$label, null])->all(),
            );

            $table->withGlobalSearch()->withEmptyState(['title' => __('No money requests')]);
            $table->column(key: 'requested_at', label: __('Requested'), canBeHidden: false, sortable: true)
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true);
            if (!$forClient) {
                $table->column(key: 'client_code', label: __('Client'), canBeHidden: false);
            }
            $table->column(key: 'lines', label: __('For'), canBeHidden: false)
                ->column(key: 'state', label: __('State'), canBeHidden: false)
                ->column(key: 'total', label: __('Asked'), canBeHidden: false, align: 'right')
                ->column(key: 'paid', label: __('Paid'), canBeHidden: false, align: 'right')
                ->column(key: 'outstanding', label: __('Due'), canBeHidden: false, align: 'right')
                ->column(key: 'actions', label: '', canBeHidden: false, align: 'right')
                ->defaultSort('-requested_at');
        };
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowAgentAccountingDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Money requests'),
                        'route' => [
                            'name'       => 'grp.org.agent.accounting.deposit_requests.index',
                            'parameters' => [$routeParameters['organisation']],
                        ],
                    ],
                ],
            ]
        );
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);
        $this->agent = $agent;

        return $this->handle($agent);
    }

    public function htmlResponse(LengthAwarePaginator $requests, ActionRequest $request): Response
    {
        $user = $request->user();

        return Inertia::render(
            'Org/Procurement/AgentMoneyRequests',
            [
                'title'       => __('Money requests'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'pageHead'    => [
                    'icon'  => ['title' => __('Money requests'), 'icon' => 'fal fa-money-check-alt'],
                    'title' => __('Money requests'),
                ],
                'create_route' => $user->authTo("procurement.{$this->organisation->id}.edit")
                    ? ['name' => 'grp.org.agent.accounting.deposit_requests.create', 'parameters' => [$this->organisation->slug]]
                    : null,
                'data'        => $requests->through(fn (DepositRequest $depositRequest) => self::row($depositRequest->setRelation('agent', $this->agent), $user, false)),
            ]
        )->table($this->tableStructure());
    }
}
