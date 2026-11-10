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
use App\Models\SupplyChain\DepositRequest;
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
                    fn () => GetOrgAgentShowcase::run($orgAgent, $this->organisation)
                    : Inertia::optional(fn () => GetOrgAgentShowcase::run($orgAgent, $this->organisation)),

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
            ->table(IndexAgentMoneyRequests::make()->tableStructure(prefix: OrgAgentTabsEnum::MONEY->value, forClient: true))
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
        $user = $request->user();

        return [
            'agent_currency_code'  => $agentCurrency,
            'client_currency_code' => $this->organisation->currency->code,
            'wallet'               => ['received' => $wallet['received'], 'paid' => $wallet['paid'], 'balance' => $wallet['balance']],
            'requests'             => IndexAgentMoneyRequests::make()->handle($agent, $this->organisation, OrgAgentTabsEnum::MONEY->value)
                ->through(fn (DepositRequest $depositRequest) => IndexAgentMoneyRequests::row($depositRequest, $user, true)),
        ];
    }
}
