<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 10:21:46 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Enums\SupplyChain\SupplyChainAttachmentScopeEnum;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgSupplier\WithOrgSupplierSubNavigation;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Enums\UI\SupplyChain\SupplierTabsEnum;
use App\Actions\Procurement\SupplierMessage\UI\IndexSupplierMessages;
use App\Http\Resources\Procurement\SupplierMessagesResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Procurement\OrgSupplierResource;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\SysAdmin\Organisation;
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
                    'actions'       => [
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
                    fn () => GetOrgSupplierShowcase::run($orgSupplier, $this->organisation)
                    : Inertia::optional(fn () => GetOrgSupplierShowcase::run($orgSupplier, $this->organisation)),

                SupplierTabsEnum::INBOX->value => $this->tab == SupplierTabsEnum::INBOX->value ?
                    fn () => SupplierMessagesResource::collection(IndexSupplierMessages::run($orgSupplier, SupplierTabsEnum::INBOX->value))->additional(['compose' => IndexSupplierMessages::composeData($this->organisation, $request->user(), $orgSupplier)])
                    : Inertia::optional(fn () => SupplierMessagesResource::collection(IndexSupplierMessages::run($orgSupplier, SupplierTabsEnum::INBOX->value))->additional(['compose' => IndexSupplierMessages::composeData($this->organisation, $request->user(), $orgSupplier)])),

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
            ->table(IndexSupplierMessages::make()->tableStructure($orgSupplier, prefix: SupplierTabsEnum::INBOX->value))
            ->table(IndexAttachments::make()->tableStructure(prefix: SupplierTabsEnum::ATTACHMENTS->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (OrgSupplier $orgSupplier, array $routeParameters, $suffix) {
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
                            'label' => $orgSupplier->supplier->code,
                        ],
                    ],
                    'suffix'         => $suffix,
                ],
            ];
        };

        $orgSupplier = OrgSupplier::where('slug', $routeParameters['orgSupplier'])->first();

        return match ($routeName) {
            'grp.org.procurement.org_suppliers.show',
            'grp.org.procurement.org_suppliers.show.supplier_products.index',
            'grp.org.procurement.org_suppliers.show.purchase_orders.index',
            'grp.org.procurement.org_suppliers.show.stock_deliveries.index' =>
            array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $orgSupplier,
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
            'grp.org.procurement.org_agents.show.suppliers.show' =>
            array_merge(
                ShowOrgAgent::make()->getBreadcrumbs($routeName, $routeParameters),
                $headCrumb(
                    $orgSupplier,
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
        $previous = $this->siblings($orgSupplier)->where('slug', '<', $orgSupplier->slug)->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request);
    }

    public function getNext(OrgSupplier $orgSupplier, ActionRequest $request): ?array
    {
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

}
