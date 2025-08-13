<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 10:21:46 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgSupplier\WithOrgSupplierSubNavigation;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\UI\SupplyChain\SupplierTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Procurement\OrgSupplierResource;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgSupplier extends OrgAction
{
    use WithOrgSupplierSubNavigation;

    private OrgAgent|Organisation $parent;
    public function handle(OrgSupplier $orgSupplier): OrgSupplier
    {
        return $orgSupplier;
    }


    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->authTo("procurement.{$this->organisation->id}.edit");
        $this->canDelete = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function asController(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): OrgSupplier
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(SupplierTabsEnum::values());

        return $this->handle($orgSupplier);
    }

    public function maya(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): OrgSupplier
    {
        $this->maya   = true;
        $this->initialisation($organisation, $request)->withTab(SupplierTabsEnum::values());
        return $this->handle($orgSupplier);
    }
    /** @noinspection PhpUnusedParameterInspection */
    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, OrgSupplier $orgSupplier, ActionRequest $request): OrgSupplier
    {
        $this->parent = $orgAgent;
        $this->initialisation($organisation, $request)->withTab(SupplierTabsEnum::values());

        return $this->handle($orgSupplier);
    }

    public function htmlResponse(OrgSupplier $orgSupplier, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/OrgSupplier',
            [
                'title'       => __('supplier'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($orgSupplier, $request),
                    'next'     => $this->getNext($orgSupplier, $request),
                ],
                'pageHead'    => [
                    'icon'    => [
                        'icon'  => 'fal fa-person-dolly',
                        'title' => __('supplier')
                    ],
                    'title'   => $orgSupplier->supplier->name,
                    'model'     => __('Supplier'),
                    'subNavigation' => $this->getOrgSupplierNavigation($orgSupplier),
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                        $this->canDelete ? [
                            'type'  => 'button',
                            'style' => 'delete',
                            'route' => [
                                'name'       => 'grp.org.procurement.org_suppliers.remove',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => SupplierTabsEnum::navigation()
                ],

                SupplierTabsEnum::SHOWCASE->value => $this->tab == SupplierTabsEnum::SHOWCASE->value ?
                    fn() => GetOrgSupplierShowcase::run($orgSupplier)
                    : Inertia::lazy(fn() => GetOrgSupplierShowcase::run($orgSupplier)),

                SupplierTabsEnum::HISTORY->value => $this->tab == SupplierTabsEnum::HISTORY->value ?
                    fn() => HistoryResource::collection(IndexHistory::run($orgSupplier))
                    : Inertia::lazy(fn() => HistoryResource::collection(IndexHistory::run($orgSupplier)))
            ]
        )->table(IndexHistory::make()->tableStructure(prefix: SupplierTabsEnum::HISTORY->value));
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
                ShowOrgAgent::make()->getBreadcrumbs($routeParameters),
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
        $previous = OrgSupplier::where('slug', '<', $orgSupplier->slug)->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(OrgSupplier $orgSupplier, ActionRequest $request): ?array
    {
        $next = OrgSupplier::where('slug', '>', $orgSupplier->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?OrgSupplier $orgSupplier, string $routeName): ?array
    {
        if (!$orgSupplier) {
            return null;
        }

        return match ($routeName) {
            'grp.org.procurement.org_suppliers.show' => [
                'label' => $orgSupplier->supplier->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'    => $orgSupplier->organisation->slug,
                        'orgSupplier'     => $orgSupplier->slug
                    ]

                ]
            ],
            'grp.org.procurement.org_agents.show.suppliers.show' => [
                'label' => $orgSupplier->supplier->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'    => $orgSupplier->organisation->slug,
                        'orgAgent'        => $this->parent->slug,
                        'orgSupplier'     => $orgSupplier->slug
                    ]

                ]
            ],
        };
    }
}
