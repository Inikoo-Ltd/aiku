<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 10:21:46 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Procurement\ProcurementNote\UI\IndexProcurementNotes;
use App\Actions\Procurement\SupplierAgreedTerm\UI\GetAgreement;
use App\Actions\Procurement\SupplierAgreedTerm\UI\IndexSupplierAgreedTerms;
use App\Http\Resources\Procurement\ProcurementNoteResource;
use App\Http\Resources\Procurement\SupplierAgreedTermResource;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Enums\SupplyChain\SupplyChainAttachmentScopeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgSupplier\WithOrgSupplierSubNavigation;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\Supplier\UI\GetSupplierDeclarations;
use App\Enums\UI\SupplyChain\SupplierTabsEnum;
use App\Actions\Procurement\SupplierMessage\UI\IndexSupplierMessages;
use App\Http\Resources\Procurement\SupplierMessagesResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Procurement\OrgSupplierResource;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use App\Support\AgentOrganisationSelection;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgSupplier extends OrgAction
{
    use WithAgentOrganisation;
    use WithOrgSupplierSubNavigation;
    use WithProcurementAuthorisation;

    private OrgAgent|Organisation $parent;

    public function handle(OrgSupplier $orgSupplier): OrgSupplier
    {
        return $orgSupplier;
    }

    public function asController(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): OrgSupplier
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(SupplierTabsEnum::values());
        $this->authorizeProcurementRecord($orgSupplier);

        return $this->handle($orgSupplier);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, OrgSupplier $orgSupplier, ActionRequest $request): OrgSupplier
    {
        $this->parent = $orgAgent;
        $this->initialisation($organisation, $request)->withTab(SupplierTabsEnum::values());
        $this->authorizeProcurementRecord($orgSupplier);

        return $this->handle($orgSupplier);
    }

    public function htmlResponse(OrgSupplier $orgSupplier, ActionRequest $request): Response
    {
        $forAgent              = $this->organisation->type === OrganisationTypeEnum::AGENT || $orgSupplier->organisation_id !== $this->organisation->id;
        $selection             = AgentOrganisationSelection::fromRequest($request, 'supplier');
        $supplier              = $orgSupplier->supplier;
        $agreementOrganisation = $selection ? $selection->selected : $orgSupplier->organisation;
        $inboxParent           = $selection ? $supplier : $orgSupplier;

        return Inertia::render(
            'Procurement/OrgSupplier',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'navigation'  => [
                    'previous' => $this->getPrevious($orgSupplier, $request),
                    'next'     => $this->getNext($orgSupplier, $request),
                ],
                'title'       => '(' . $orgSupplier->supplier->code . ') ' . __('Supplier'),
                'pageHead'    => [
                    'title'         => $orgSupplier->supplier->name,
                    'icon'          => [
                        'icon'  => 'fal fa-person-dolly',
                        'title' => __('Supplier')
                    ],
                    'model'         => __('Supplier'),
                    'subNavigation' => $this->getOrgSupplierNavigation($orgSupplier),
                    'actions'       => $selection ? $this->agentSupplierActions($selection, $supplier, $request) : [
                        $this->getOrgSupplierPurchaseOrderAction($orgSupplier),
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Edit'),
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => SupplierTabsEnum::navigation(),
                ],

                SupplierTabsEnum::SHOWCASE->value => $this->tab == SupplierTabsEnum::SHOWCASE->value ?
                    fn () => GetOrgSupplierShowcase::run($orgSupplier, $this->organisation, $this->procurementOrgAgent())
                    : Inertia::optional(fn () => GetOrgSupplierShowcase::run($orgSupplier, $this->organisation, $this->procurementOrgAgent())),

                'agreement' => GetAgreement::run($supplier, $agreementOrganisation, $request->user(), $forAgent)
                    + ['print_route' => $forAgent ? null : ['name' => 'grp.org.procurement.org_suppliers.show.agreement.pdf', 'parameters' => [$this->organisation->slug, $orgSupplier->slug]]]
                    + ['by_organisation' => $selection && !$selection->selected ? $this->agreementsByOrganisation($selection, $supplier, $request) : null]
                    + ($selection ? GetAgreement::make()->agentSupplierNoteForm($supplier, $selection->selected, $request->user()) : []),

                SupplierTabsEnum::AGREED_TERMS->value => $this->tab == SupplierTabsEnum::AGREED_TERMS->value ?
                    fn () => SupplierAgreedTermResource::collection(IndexSupplierAgreedTerms::run($supplier, SupplierTabsEnum::AGREED_TERMS->value, $agreementOrganisation, $forAgent))
                    : Inertia::optional(fn () => SupplierAgreedTermResource::collection(IndexSupplierAgreedTerms::run($supplier, SupplierTabsEnum::AGREED_TERMS->value, $agreementOrganisation, $forAgent))),

                SupplierTabsEnum::NOTES->value => $this->tab == SupplierTabsEnum::NOTES->value ?
                    fn () => ProcurementNoteResource::collection(IndexProcurementNotes::run($supplier, SupplierTabsEnum::NOTES->value, $agreementOrganisation, $forAgent))
                    : Inertia::optional(fn () => ProcurementNoteResource::collection(IndexProcurementNotes::run($supplier, SupplierTabsEnum::NOTES->value, $agreementOrganisation, $forAgent))),

                SupplierTabsEnum::INBOX->value => $this->tab == SupplierTabsEnum::INBOX->value ?
                    fn () => SupplierMessagesResource::collection(IndexSupplierMessages::run($inboxParent, SupplierTabsEnum::INBOX->value, $selection ? $this->organisation : null))->additional(['compose' => IndexSupplierMessages::composeData($this->organisation, $request->user(), $inboxParent)])
                    : Inertia::optional(fn () => SupplierMessagesResource::collection(IndexSupplierMessages::run($inboxParent, SupplierTabsEnum::INBOX->value, $selection ? $this->organisation : null))->additional(['compose' => IndexSupplierMessages::composeData($this->organisation, $request->user(), $inboxParent)])),

                SupplierTabsEnum::DECLARATIONS->value => $this->tab == SupplierTabsEnum::DECLARATIONS->value ?
                    fn () => GetSupplierDeclarations::run($orgSupplier->supplier)
                    : Inertia::optional(fn () => GetSupplierDeclarations::run($orgSupplier->supplier)),

                SupplierTabsEnum::ATTACHMENTS->value => $this->tab == SupplierTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($orgSupplier->supplier, SupplierTabsEnum::ATTACHMENTS->value))
                    : Inertia::optional(fn () => AttachmentsResource::collection(IndexAttachments::run($orgSupplier->supplier, SupplierTabsEnum::ATTACHMENTS->value))),

                'attachmentRoutes' => [
                    'attachRoute' => [
                        'name'       => 'grp.models.supplier.attachment.attach',
                        'parameters' => ['supplier' => $orgSupplier->supplier->id],
                    ],
                    'detachRoute' => [
                        'method'     => 'delete',
                        'name'       => 'grp.models.supplier.attachment.detach',
                        'parameters' => ['supplier' => $orgSupplier->supplier->id],
                    ],
                ],
                'attachmentScopes' => SupplyChainAttachmentScopeEnum::options(),

                SupplierTabsEnum::HISTORY->value => $this->tab == SupplierTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($orgSupplier, SupplierTabsEnum::HISTORY->value))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($orgSupplier, SupplierTabsEnum::HISTORY->value))),
            ]
        )->table(IndexHistory::make()->tableStructure(prefix: SupplierTabsEnum::HISTORY->value))
            ->table(IndexSupplierAgreedTerms::make()->tableStructure(prefix: SupplierTabsEnum::AGREED_TERMS->value))
            ->table(IndexProcurementNotes::make()->tableStructure(prefix: SupplierTabsEnum::NOTES->value, withOrganisation: AgentOrganisationSelection::fromRequest($request, 'supplier') !== null))
            ->table(IndexSupplierMessages::make()->tableStructure(AgentOrganisationSelection::fromRequest($request, 'supplier') ? $orgSupplier->supplier : $orgSupplier, prefix: SupplierTabsEnum::INBOX->value))
            ->table(IndexAttachments::make()->tableStructure(prefix: SupplierTabsEnum::ATTACHMENTS->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (?string $label, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Suppliers')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $label,
                        ],
                    ],
                    'suffix'         => $suffix,
                ],
            ];
        };

        $label = isset($routeParameters['supplier'])
            ? Supplier::where('slug', $routeParameters['supplier'])->value('code')
            : OrgSupplier::where('slug', $routeParameters['orgSupplier'] ?? null)->first()?->supplier->code;
        $selection = AgentOrganisationSelection::fromRequest(parameter: 'supplier');
        if ($selection && isset($routeParameters['supplier'])) {
            $routeParameters = $selection->route('', Arr::only($routeParameters, ['organisation', 'supplier']))['parameters'];
        }

        return match ($routeName) {
            'grp.org.procurement.org_suppliers.show',
            'grp.org.procurement.org_suppliers.show.supplier_products.index',
            'grp.org.procurement.org_suppliers.show.purchase_orders.index',
            'grp.org.procurement.org_suppliers.show.stock_deliveries.index' =>
            array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $label,
                    [
                        'index' => [
                            'name'       => 'grp.org.procurement.org_suppliers.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.procurement.org_suppliers.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.procurement.org_agents.show.suppliers.show',
            'grp.org.procurement.org_agents.show.suppliers.show.supplier_products.index',
            'grp.org.procurement.org_agents.show.suppliers.show.purchase_orders.index',
            'grp.org.procurement.org_agents.show.suppliers.show.stock_deliveries.index' =>
            array_merge(
                ShowOrgAgent::make()->getBreadcrumbs($routeName, $routeParameters),
                $headCrumb(
                    $label,
                    [
                        'index' => [
                            'name'       => 'grp.org.procurement.org_agents.show.suppliers.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.procurement.org_agents.show.suppliers.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function jsonResponse(OrgSupplier $orgSupplier): OrgSupplierResource
    {
        return new OrgSupplierResource($orgSupplier);
    }

    public function getPrevious(OrgSupplier $orgSupplier, ActionRequest $request): ?array
    {
        if (AgentOrganisationSelection::fromRequest($request, 'supplier')) {
            return $this->getAgentSupplierNavigation($orgSupplier->supplier, '<');
        }

        $previous = $this->siblings($orgSupplier)->where('slug', '<', $orgSupplier->slug)->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request);
    }

    public function getNext(OrgSupplier $orgSupplier, ActionRequest $request): ?array
    {
        if (AgentOrganisationSelection::fromRequest($request, 'supplier')) {
            return $this->getAgentSupplierNavigation($orgSupplier->supplier, '>');
        }

        $next = $this->siblings($orgSupplier)->where('slug', '>', $orgSupplier->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request);
    }

    private function siblings(OrgSupplier $orgSupplier): Builder
    {
        $query = OrgSupplier::where('organisation_id', $orgSupplier->organisation_id)->whereHas('supplier');

        if (isset($this->parent) && $this->parent instanceof OrgAgent) {
            $query->where('org_agent_id', $this->parent->id);
        } elseif ($orgSupplier->org_agent_id) {
            $query->where('org_agent_id', $orgSupplier->org_agent_id);
        } else {
            $query->whereNull('org_agent_id');
        }

        return $query;
    }

    private function getNavigation(?OrgSupplier $orgSupplier, ActionRequest $request): ?array
    {
        if (!$orgSupplier) {
            return null;
        }

        return [
            'label' => $orgSupplier->supplier->name,
            'route' => [
                'name'       => $request->route()->getName(),
                'parameters' => array_merge(
                    $request->route()->originalParameters(),
                    ['orgSupplier' => $orgSupplier->slug]
                ),
            ],
        ];
    }

    /**
     * Previous / next walk the agent's suppliers in the order of its suppliers list, by code; the arrows carry the
     * page's query, so the selected organisation stays.
     */
    private function getAgentSupplierNavigation(Supplier $supplier, string $direction): ?array
    {
        $organisation = $this->organisation ?? request()->route('organisation');
        $agent        = $this->getOrganisationAgent($organisation);
        $sibling      = Supplier::where('agent_id', $agent->id)
            ->whereHas('orgSuppliers', fn (Builder $query) => $query->whereIn('org_agent_id', OrgAgent::where('agent_id', $agent->id)->select('id')))
            ->whereRaw("(code, id) $direction (?, ?)", [$supplier->code, $supplier->id])
            ->orderBy('code', $direction === '<' ? 'desc' : 'asc')
            ->orderBy('id', $direction === '<' ? 'desc' : 'asc')
            ->first();

        return $sibling ? [
            'label' => $sibling->name,
            'route' => [
                'name'       => request()->route()->getName(),
                'parameters' => [...request()->route()->originalParameters(), 'supplier' => $sibling->slug],
            ],
        ] : null;
    }

    /**
     * @return array<int, array<string, mixed>|false>
     */
    private function agentSupplierActions(AgentOrganisationSelection $selection, Supplier $supplier, ActionRequest $request): array
    {
        $parameters = ['organisation' => $this->organisation->slug, 'supplier' => $supplier->slug];

        return [
            $this->getOrgSupplierPurchaseOrderAction($selection->pageRow),
            $request->user()->authTo("procurement.{$this->organisation->id}.edit") ? [
                'type'  => 'button',
                'style' => 'edit',
                'label' => __('Edit'),
                'route' => $selection->route('grp.org.procurement.org_suppliers.edit', $parameters),
            ] : false,
        ];
    }

    /**
     * In All, the Agreement tab lists how each buying organisation stands with the supplier; a row opens that
     * organisation's agreement.
     *
     * @return array<int, array<string, mixed>>
     */
    private function agreementsByOrganisation(AgentOrganisationSelection $selection, Supplier $supplier, ActionRequest $request): array
    {
        return $selection->rows->map(function (OrgSupplier $orgSupplier) use ($supplier, $request) {
            $agreement = GetAgreement::run($supplier, $orgSupplier->organisation, $request->user(), true);

            return [
                'organisation_code' => $orgSupplier->organisation->code,
                'organisation_name' => $orgSupplier->organisation->name,
                'terms'             => count($agreement['terms']),
                'notes'             => count($agreement['notes']),
                'open_incidents'    => $agreement['open_incidents'],
                'route'             => [
                    'name'       => 'grp.org.procurement.org_suppliers.show',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'supplier'     => $supplier->slug,
                        '_query'       => ['organisation' => $orgSupplier->organisation->slug, 'tab' => SupplierTabsEnum::AGREED_TERMS->value],
                    ],
                ],
            ];
        })->values()->all();
    }
}
