<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 13:52:57 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\OrgAgent\UI;

use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\Procurement\ProcurementNote\UI\IndexProcurementNotes;
use App\Actions\Procurement\SupplierAgreedTerm\UI\GetAgreement;
use App\Actions\Procurement\SupplierAgreedTerm\UI\IndexSupplierAgreedTerms;
use App\Http\Resources\Procurement\ProcurementNoteResource;
use App\Http\Resources\Procurement\SupplierAgreedTermResource;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\SupplyChain\Agent\UI\GetAgentHistoryAuditScope;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Enums\SupplyChain\SupplyChainAttachmentScopeEnum;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\Agent\UI\WithAgentEditAction;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Enums\UI\Procurement\OrgAgentTabsEnum;
use App\Actions\SupplyChain\AgentPayment\UI\IndexAgentLedger;
use App\Actions\SupplyChain\DepositRequest\UI\IndexAgentMoneyRequests;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Models\SupplyChain\Agent;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\SupplyChain\DepositRequest\MatchAgentMoneyTransfers;
use App\Models\SysAdmin\User;
use App\Models\SupplyChain\AgentPayment;
use App\Actions\SupplyChain\DepositRequest\ApproveAgentMoneyRequest;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Actions\SupplyChain\DepositRequest\PayAgentMoneyRequests;
use App\Models\SupplyChain\DepositRequest;
use App\Models\SupplyChain\DepositRequestItem;
use Illuminate\Support\Facades\DB;
use App\Actions\Procurement\SupplierMessage\UI\IndexSupplierMessages;
use App\Http\Resources\Procurement\SupplierMessagesResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Procurement\OrgAgentResource;
use App\Models\Procurement\OrgAgent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgAgent extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithOrgAgentSubNavigation;
    use WithAgentOrganisation;
    use WithAgentEditAction;

    public function handle(OrgAgent $orgAgent): OrgAgent
    {
        return $orgAgent;
    }

    public function asController(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): RedirectResponse|OrgAgent
    {
        $this->initialisation($organisation, $request)->withTab(OrgAgentTabsEnum::values());
        $this->authorizeProcurementRecord($orgAgent);

        return $this->handle($orgAgent);
    }

    public function htmlResponse(OrgAgent $orgAgent, ActionRequest $request): Response
    {
        $forAgent = $this->organisation->type === OrganisationTypeEnum::AGENT || $orgAgent->organisation_id !== $this->organisation->id;

        $history = fn () => HistoryResource::collection(IndexHistory::run($orgAgent, OrgAgentTabsEnum::HISTORY->value, auditScope: GetAgentHistoryAuditScope::run($orgAgent->agent, ['type' => 'OrgAgent', 'id' => $orgAgent->id, 'label' => $orgAgent->agent->code], $orgAgent->organisation_id)));

        return Inertia::render(
            'Procurement/OrgAgent',
            [
                'title'       => '(' . $orgAgent->agent->organisation->code . ') ' . __('Agent'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($orgAgent, $request),
                    'next'     => $this->getNext($orgAgent, $request),
                ],
                'suspended_alert' => $orgAgent->status ? null : [
                    'status'      => 'warning',
                    'title'       => __('Suspended agent'),
                    'description' => __('We no longer work with this agent. Its suppliers, orders and deliveries stay here for reference.'),
                ],
                'pageHead'    => [
                    'model'         => __('Agent'),
                    'icon'          =>
                        [
                            'icon'  => ['fal', 'people-arrows'],
                            'title' => __('Agent')
                        ],
                    'subNavigation' => $this->getOrgAgentNavigation($orgAgent),
                    'title'         => $orgAgent->agent->organisation->name,
                    'actions'       => [
                        $this->agentEditAction(
                            'grp.org.procurement.org_agents.show.edit',
                            $request->route()->originalParameters()
                        ),
                    ],
                    'meta'          => $this->organisation->type === OrganisationTypeEnum::AGENT ? [] : [
                        ...($request->user()->authTo(['supply-chain.view', 'compliance.view']) ? [[
                            'label'    => __('Group view'),
                            'route'    => ['name' => 'grp.supply-chain.agents.show', 'parameters' => [$orgAgent->agent->slug]],
                            'leftIcon' => ['icon' => 'fal fa-sitemap', 'tooltip' => __('This agent across the group: users, settings and every organisation')],
                        ]] : []),
                        [
                            'name'     => trans_choice('supplier|suppliers', $orgAgent->stats->number_org_suppliers),
                            'number'   => $orgAgent->stats->number_org_suppliers,
                            'route'    => [
                                'name'       => 'grp.org.procurement.org_agents.show.suppliers.index',
                                'parameters' => [$this->organisation->slug, $orgAgent->slug],
                            ],
                            'leftIcon' => [
                                'icon'    => 'fal fa-person-dolly',
                                'tooltip' => __('Suppliers'),
                            ],
                        ],
                        [
                            'name'     => trans_choice('product|products', $orgAgent->stats->number_org_supplier_products),
                            'number'   => $orgAgent->stats->number_org_supplier_products,
                            'route'    => [
                                'name'       => 'grp.org.procurement.org_agents.show.supplier_products.index',
                                'parameters' => [$this->organisation->slug, $orgAgent->slug],
                            ],
                            'leftIcon' => [
                                'icon'    => 'fal fa-box-usd',
                                'tooltip' => __('Products'),
                            ],
                        ],
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => OrgAgentTabsEnum::navigation()
                ],

                OrgAgentTabsEnum::SHOWCASE->value => $this->tab == OrgAgentTabsEnum::SHOWCASE->value ?
                    fn () => [...GetOrgAgentShowcase::run($orgAgent, $this->organisation), 'agentCards' => $forAgent ? [] : [IndexOrgAgents::make()->agentCard($orgAgent)]]
                    : Inertia::optional(fn () => [...GetOrgAgentShowcase::run($orgAgent, $this->organisation), 'agentCards' => $forAgent ? [] : [IndexOrgAgents::make()->agentCard($orgAgent)]]),
                OrgAgentTabsEnum::DETAILS->value => $this->tab == OrgAgentTabsEnum::DETAILS->value ?
                    fn () => GetOrgAgentShowcase::run($orgAgent, $this->organisation)
                    : Inertia::optional(fn () => GetOrgAgentShowcase::run($orgAgent, $this->organisation)),
                'cover'            => Inertia::defer(fn () => $forAgent ? [] : IndexOrgAgents::make()->coverByAgent(collect([$orgAgent]))),
                'cardSections'     => IndexOrgAgents::cardSections($request->user()),
                'overviewSections' => (object) ($request->user()->settings['overview_sections'] ?? []),

                OrgAgentTabsEnum::MONEY->value => $this->tab == OrgAgentTabsEnum::MONEY->value ?
                    fn () => $this->money($orgAgent, $request)
                    : Inertia::optional(fn () => $this->money($orgAgent, $request)),

                OrgAgentTabsEnum::INBOX->value => $this->tab == OrgAgentTabsEnum::INBOX->value ?
                    fn () => SupplierMessagesResource::collection(IndexSupplierMessages::run($orgAgent, OrgAgentTabsEnum::INBOX->value))->additional(['compose' => IndexSupplierMessages::composeData($this->organisation, $request->user(), $orgAgent)])
                    : Inertia::optional(fn () => SupplierMessagesResource::collection(IndexSupplierMessages::run($orgAgent, OrgAgentTabsEnum::INBOX->value))->additional(['compose' => IndexSupplierMessages::composeData($this->organisation, $request->user(), $orgAgent)])),

                OrgAgentTabsEnum::ATTACHMENTS->value => $this->tab == OrgAgentTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($orgAgent->agent, OrgAgentTabsEnum::ATTACHMENTS->value))
                    : Inertia::optional(fn () => AttachmentsResource::collection(IndexAttachments::run($orgAgent->agent, OrgAgentTabsEnum::ATTACHMENTS->value))),

                'attachmentRoutes' => [
                    'attachRoute' => [
                        'name'       => 'grp.models.agent.attachment.attach',
                        'parameters' => ['agent' => $orgAgent->agent->id],
                    ],
                    'detachRoute' => [
                        'method'     => 'delete',
                        'name'       => 'grp.models.agent.attachment.detach',
                        'parameters' => ['agent' => $orgAgent->agent->id],
                    ],
                ],
                'attachmentScopes' => SupplyChainAttachmentScopeEnum::options(),

                'agreement' => GetAgreement::run($orgAgent->agent, $orgAgent->organisation, $request->user(), $forAgent),

                OrgAgentTabsEnum::AGREED_TERMS->value => $this->tab == OrgAgentTabsEnum::AGREED_TERMS->value ?
                    fn () => SupplierAgreedTermResource::collection(IndexSupplierAgreedTerms::run($orgAgent->agent, OrgAgentTabsEnum::AGREED_TERMS->value, $orgAgent->organisation, $forAgent))
                    : Inertia::optional(fn () => SupplierAgreedTermResource::collection(IndexSupplierAgreedTerms::run($orgAgent->agent, OrgAgentTabsEnum::AGREED_TERMS->value, $orgAgent->organisation, $forAgent))),

                OrgAgentTabsEnum::NOTES->value => $this->tab == OrgAgentTabsEnum::NOTES->value ?
                    fn () => ProcurementNoteResource::collection(IndexProcurementNotes::run($orgAgent->agent, OrgAgentTabsEnum::NOTES->value, $orgAgent->organisation, $forAgent))
                    : Inertia::optional(fn () => ProcurementNoteResource::collection(IndexProcurementNotes::run($orgAgent->agent, OrgAgentTabsEnum::NOTES->value, $orgAgent->organisation, $forAgent))),

                OrgAgentTabsEnum::HISTORY->value => $this->tab == OrgAgentTabsEnum::HISTORY->value ? $history : Inertia::optional($history)
            ]
        )->table(IndexSupplierAgreedTerms::make()->tableStructure(prefix: OrgAgentTabsEnum::AGREED_TERMS->value))
            ->table(IndexProcurementNotes::make()->tableStructure(prefix: OrgAgentTabsEnum::NOTES->value))
            ->table(IndexHistory::make()->tableStructure(prefix: OrgAgentTabsEnum::HISTORY->value, withRecordColumn: true))
            ->table(IndexSupplierMessages::make()->tableStructure($orgAgent, prefix: OrgAgentTabsEnum::INBOX->value))
            ->table(IndexAgentMoneyRequests::make()->tableStructure($orgAgent->agent, $this->organisation, prefix: OrgAgentTabsEnum::MONEY->value, forClient: true))
            ->table(IndexAttachments::make()->tableStructure(prefix: OrgAgentTabsEnum::ATTACHMENTS->value));
    }


    public function jsonResponse(OrgAgent $orgAgent): OrgAgentResource
    {
        return new OrgAgentResource($orgAgent);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (OrgAgent $orgAgent, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Agents')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $orgAgent->agent->code,
                        ],
                    ],
                    'suffix'         => $suffix,
                ],
            ];
        };

        $orgAgent = OrgAgent::where('slug', $routeParameters['orgAgent'])->first();

        return match ($routeName) {
            'grp.org.procurement.org_agents.show',
            'grp.org.procurement.org_agents.show.edit',
            'grp.org.procurement.org_agents.show.org-stocks.index',
            'grp.org.procurement.org_agents.show.purchase-orders.index',
            'grp.org.procurement.org_agents.show.purchase-orders.show',
            'grp.org.procurement.org_agents.show.stock-deliveries.index',
            'grp.org.procurement.org_agents.show.supplier_products.index',
            'grp.org.procurement.org_agents.show.supplier_products.show',
            'grp.org.procurement.org_agents.show.suppliers.index',
            'grp.org.procurement.org_agents.show.suppliers.show' =>
            array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
                $headCrumb(
                    $orgAgent,
                    [
                        'index' => [
                            'name'       => 'grp.org.procurement.org_agents.index',
                            'parameters' => Arr::only($routeParameters, 'organisation')
                        ],
                        'model' => [
                            'name'       => 'grp.org.procurement.org_agents.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'orgAgent'])
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(OrgAgent $orgAgent, ActionRequest $request): ?array
    {
        $previous = $this->siblings($orgAgent)->where('slug', '<', $orgAgent->slug)->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request);
    }

    public function getNext(OrgAgent $orgAgent, ActionRequest $request): ?array
    {
        $next = $this->siblings($orgAgent)->where('slug', '>', $orgAgent->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request);
    }

    private function siblings(OrgAgent $orgAgent): Builder
    {
        $viewingOrganisation = request()->route('organisation');

        if ($viewingOrganisation instanceof Organisation && $this->getOrganisationAgent($viewingOrganisation)) {
            return OrgAgent::where('agent_id', $orgAgent->agent_id);
        }

        return OrgAgent::where('organisation_id', $orgAgent->organisation_id)->whereHas('agent');
    }

    private function getNavigation(?OrgAgent $orgAgent, ActionRequest $request): ?array
    {
        if (!$orgAgent) {
            return null;
        }

        return [
            'label' => $orgAgent->agent_id === request()->route('organisation')?->agent?->id ? $orgAgent->organisation->name : $orgAgent->agent->organisation->name,
            'route' => [
                'name'       => $request->route()->getName(),
                'parameters' => array_merge(
                    $request->route()->originalParameters(),
                    ['orgAgent' => $orgAgent->slug]
                ),
            ],
        ];
    }


    /**
     * The client's wallet with the agent and the money the agent asked it for, which it pays from here.
     *
     * @return array<string, mixed>
     */
    private function money(OrgAgent $orgAgent, ActionRequest $request): array
    {
        $agent         = $orgAgent->agent;
        $agentCurrency = $agent->organisation->currency->code;
        $wallet        = collect(IndexAgentLedger::make()->wallets($agent))
            ->first(fn (array $wallet) => $wallet['organisation_id'] === $this->organisation->id && $wallet['currency_code'] === $agentCurrency)
            ?? ['received' => 0.0, 'paid' => 0.0, 'balance' => 0.0];
        $user       = $request->user();
        $clientRate = $agent->organisation->currency_id !== $this->organisation->currency_id
            ? GetCurrencyExchange::make()->cachedOrStored($agent->organisation->currency, $this->organisation->currency)
            : null;

        return [
            'agent_currency_code'  => $agentCurrency,
            'client_currency_code' => $this->organisation->currency->code,
            'client_rate'          => $clientRate,
            'wallet'               => ['received' => $wallet['received'], 'paid' => $wallet['paid'], 'balance' => $wallet['balance']],
            'to_pay'               => $this->toPay($agent, $clientRate),
            'pay_route'            => ($canPay = PayAgentMoneyRequests::canPayAll($orgAgent, $user))
                ? ['name' => 'grp.models.org_agent.money_requests.pay', 'parameters' => ['orgAgent' => $orgAgent->id], 'method' => 'post']
                : null,
            'bank_lines'           => $canPay ? $this->bankLines($orgAgent) : [],
            'bank_transfers'       => $this->bankTransfers($orgAgent, $user),
            'requests'            => IndexAgentMoneyRequests::make()->handle($agent, $this->organisation, OrgAgentTabsEnum::MONEY->value)
                ->through(fn (DepositRequest $depositRequest) => IndexAgentMoneyRequests::row($depositRequest, $user, true)),
        ];
    }

    /**
     * Transfers the client's banks sent the agent that did not settle its approved requests on their own, for a payer to confirm.
     *
     * @return list<array<string, mixed>>
     */
    private function bankLines(OrgAgent $orgAgent): array
    {
        $matcher       = MatchAgentMoneyTransfers::make();
        $due           = $matcher->due($orgAgent);
        $agentCurrency = $orgAgent->agent->organisation->currency->code;

        return $matcher->candidates($orgAgent)->map(function (array $candidate) use ($orgAgent, $due, $agentCurrency) {
            $line   = $candidate['line'];
            $amount = $candidate['received'] ?? $due;

            return [
                'id'                => $line->id,
                'date'              => MatchAgentMoneyTransfers::bookedDate($line, $orgAgent),
                'bank'              => $line->bankConnection->name,
                'payee'             => $line->counterparty_name,
                'reference'         => $line->reference,
                'sent_amount'       => abs((float) $line->amount),
                'sent_currency'     => $line->currency->code,
                'received_amount'   => $candidate['received'],
                'currency_code'     => $agentCurrency,
                'amount_to_record'  => $amount,
                'too_much'          => $amount > $due + 0.005,
                'set_aside'         => $candidate['dismissed'],
                'pay_route'         => ['name' => 'grp.models.org_agent.money_requests.pay_from_bank', 'parameters' => ['orgAgent' => $orgAgent->id, 'bankTransaction' => $line->id]],
                'dismiss_route'     => ['name' => 'grp.models.org_agent.money_requests.bank_line.dismiss', 'parameters' => ['orgAgent' => $orgAgent->id, 'bankTransaction' => $line->id]],
            ];
        })->values()->all();
    }

    /**
     * Transfers set as paid from the client's bank in the last 60 days, and older ones the bank has since reversed or
     * changed, one row per bank line, each with what it paid and the undo when the user may pay all of it.
     *
     * @return list<array<string, mixed>>
     */
    private function bankTransfers(OrgAgent $orgAgent, ?User $user): array
    {
        return AgentPayment::where('agent_id', $orgAgent->agent_id)
            ->where('organisation_id', $orgAgent->organisation_id)
            ->whereNotNull('deposit_request_id')
            ->whereNotNull('data->'.MatchAgentMoneyTransfers::LINE_KEY)
            ->with(['depositRequest.agent', 'currency:id,code', 'bankTransaction.bankConnection:id,name', 'originalCurrency:id,code'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (AgentPayment $transfer) => $transfer->data[MatchAgentMoneyTransfers::LINE_KEY])
            ->map(function ($transfers) use ($orgAgent, $user) {
                $first   = $transfers->first();
                $line    = $transfers->firstWhere('bank_transaction_id', '!==', null)?->bankTransaction;
                $problem = $line && ($line->status !== BankTransactionStatusEnum::BOOKED || $line->match_state === BankTransactionMatchStateEnum::REVIEW);

                return [
                    'id'            => $first->id,
                    'date'          => $first->date?->toDateString(),
                    'bank'          => $line?->bankConnection?->name,
                    'reference'     => $first->reference,
                    'amount'        => round($transfers->sum(fn (AgentPayment $transfer) => (float) $transfer->amount), 2),
                    'currency_code' => $first->currency->code,
                    'sent_amount'   => $transfers->every(fn (AgentPayment $transfer) => $transfer->original_amount !== null) ? round($transfers->sum(fn (AgentPayment $transfer) => (float) $transfer->original_amount), 2) : null,
                    'sent_currency' => $first->originalCurrency?->code,
                    'requests'      => $transfers->map(fn (AgentPayment $transfer) => $transfer->depositRequest->reference ?? '#'.$transfer->deposit_request_id)->unique()->values()->all(),
                    'problem'       => $problem,
                    'undo_route'    => $transfers->every(fn (AgentPayment $transfer) => ApproveAgentMoneyRequest::canPay($transfer->depositRequest, $user))
                        ? ['name' => 'grp.models.org_agent.money_requests.bank_transfer.undo', 'parameters' => ['orgAgent' => $orgAgent->id, 'agentPayment' => $first->id]]
                        : null,
                ];
            })
            ->filter(fn (array $row) => $row['problem'] || $row['date'] >= now()->subDays(60)->toDateString())
            ->values()
            ->all();
    }

    /**
     * What the client still has to send for its open money requests, across every page of the table: approved ones are the amount to pay now.
     *
     * Requests are always in the agent's currency, so the agent-to-client rate converts them.
     *
     * @return array{currency_code: string|null, amount: float, client_amount: float|null, references: list<string>, requests: list<array<string, mixed>>, waiting_approval: float}
     */
    private function toPay(Agent $agent, ?float $clientRate): array
    {
        $open = DepositRequest::query()
            ->where('deposit_requests.agent_id', $agent->id)
            ->where('deposit_requests.organisation_id', $this->organisation->id)
            ->where('deposit_requests.state', DepositRequestStateEnum::REQUESTED)
            ->join('currencies', 'currencies.id', 'deposit_requests.currency_id')
            ->select(['deposit_requests.id', 'deposit_requests.reference', 'deposit_requests.approved_at', 'currencies.code as currency_code'])
            ->selectSub(DB::table('deposit_request_items')->whereColumn('deposit_request_id', 'deposit_requests.id')->selectRaw('coalesce(sum(amount), 0)'), 'total')
            ->selectSub(DB::table('agent_payments')->whereColumn('deposit_request_id', 'deposit_requests.id')->whereNull('deleted_at')->selectRaw('coalesce(sum(amount), 0)'), 'paid')
            ->with(['items.purchaseOrder:id,reference', 'items.stockDelivery:id,reference', 'items.agentPayment:id,type,notes'])
            ->orderBy('deposit_requests.requested_at')
            ->orderBy('deposit_requests.id')
            ->get()
            ->map(fn (DepositRequest $depositRequest) => [
                'reference'     => $depositRequest->reference ?? '#'.$depositRequest->id,
                'approved'      => $depositRequest->approved_at !== null,
                'currency_code' => $depositRequest->currency_code,
                'lines'         => $depositRequest->items->map(fn (DepositRequestItem $item) => [
                    'description' => $item->purchaseOrder ? $item->kind->describeForPurchaseOrder() : DepositRequestItem::describeExpense($item),
                    'reference'   => $item->purchaseOrder?->reference ?? $item->stockDelivery?->reference,
                    'amount'      => round((float) $item->amount, 2),
                ])->values()->all(),
                'total'         => round((float) $depositRequest->total, 2),
                'paid'          => round((float) $depositRequest->paid, 2),
                'due'           => max(0, round((float) $depositRequest->total - (float) $depositRequest->paid, 2)),
            ])
            ->filter(fn (array $request) => $request['due'] > 0);

        $approved = $open->where('approved', true);
        $amount   = round($approved->sum('due'), 2);

        return [
            'currency_code'    => $approved->first()['currency_code'] ?? $open->first()['currency_code'] ?? null,
            'amount'           => $amount,
            'client_amount'    => $clientRate && $amount > 0 ? round($amount * $clientRate, 2) : null,
            'references'       => $approved->pluck('reference')->values()->all(),
            'requests'         => $approved->map(fn (array $request) => Arr::except($request, ['approved']))->values()->all(),
            'waiting_approval' => round($open->where('approved', false)->sum('due'), 2),
        ];
    }
}
