<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:35:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\Mail\Mailshot\UI\IndexMailshots;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\HasCatalogueAuthorisation;
use App\Enums\UI\Catalogue\FamilyTabsEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\CRM\CustomersResource;
use App\Http\Resources\Mail\MailshotResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowFamily extends OrgAction
{
    use HasCatalogueAuthorisation;
    use WithFamilySubNavigation;


    private Organisation|ProductCategory|Shop $parent;

    public function handle(ProductCategory $family): ProductCategory
    {
        return $family;
    }


    public function asController(Organisation $organisation, ProductCategory $family, ActionRequest $request): ProductCategory
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(FamilyTabsEnum::values());

        return $this->handle($family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ProductCategory $family, ActionRequest $request): ProductCategory
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(FamilyTabsEnum::values());

        return $this->handle($family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $family, ActionRequest $request): ProductCategory
    {
        $this->parent = $department;

        $this->initialisationFromShop($shop, $request)->withTab(FamilyTabsEnum::values());

        return $this->handle($family);
    }

    public function inSubDepartmentInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ProductCategory $family, ActionRequest $request): ProductCategory
    {
        $this->parent = $subDepartment;

        $this->initialisationFromShop($shop, $request)->withTab(FamilyTabsEnum::values());

        return $this->handle($family);
    }

    public function htmlResponse(ProductCategory $family, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Catalogue/Family',
            [
                'title'       => __('family'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $family,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($family, $request),
                    'next'     => $this->getNext($family, $request),
                ],
                'pageHead'    => [
                    'title'   => $family->name,
                    'model'   => '',
                    'icon'    => [
                        'icon'  => ['fal', 'fa-folder'],
                        'title' => __('department')
                    ],
                    'actions' => [
                        $family->webpage ?
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'tooltip' => __('To Webpage'),
                            'label'   => __('To Webpage'),
                            'icon'  => ["fal", "fa-drafting-compass"],
                            'route' => [
                                'name'       => 'grp.org.shops.show.web.webpages.show',
                                'parameters' => [
                                    'organisation' => $this->organisation->slug,
                                    'shop'         => $this->shop->slug,
                                    'website'      => $this->shop->website->slug,
                                    'webpage'      => $family->webpage->slug
                                ]
                            ]
                        ] : [
                            'type'  => 'button',
                            'style' => 'edit',
                            'tooltip' => __('Create Webpage'),
                            'label'   => __('Create Webpage'),
                            'icon'  => ["fal", "fa-drafting-compass"],
                            'route' => [
                                'name'       => 'grp.models.webpages.product_category.store',
                                'parameters' => $family->id,
                                'method'     => 'post'
                            ]
                        ],
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,
                        $this->canDelete ? [
                            'type'  => 'button',
                            'style' => 'delete',
                            'route' => [
                                'name'       => 'shops.show.families.remove',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false
                    ],
                    'subNavigation' => $this->getFamilySubNavigation($family, $this->parent, $request)

                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => FamilyTabsEnum::navigation()
                ],

                FamilyTabsEnum::SHOWCASE->value => $this->tab == FamilyTabsEnum::SHOWCASE->value ?
                    fn () => GetProductCategoryShowcase::run($family)
                    : Inertia::lazy(fn () => GetProductCategoryShowcase::run($family)),

                FamilyTabsEnum::CUSTOMERS->value => $this->tab == FamilyTabsEnum::CUSTOMERS->value ?
                    fn () => CustomersResource::collection(IndexCustomers::run(parent : $family->shop, prefix: FamilyTabsEnum::CUSTOMERS->value))
                    : Inertia::lazy(fn () => CustomersResource::collection(IndexCustomers::run(parent : $family->shop, prefix: FamilyTabsEnum::CUSTOMERS->value))),
                // FamilyTabsEnum::MAILSHOTS->value => $this->tab == FamilyTabsEnum::MAILSHOTS->value ?
                //     fn () => MailshotResource::collection(IndexMailshots::run($family))
                //     : Inertia::lazy(fn () => MailshotResource::collection(IndexMailshots::run($family))),


            ]
        )->table(IndexCustomers::make()->tableStructure(parent: $family->shop, prefix: FamilyTabsEnum::CUSTOMERS->value))
            ->table(IndexMailshots::make()->tableStructure($family));

    }


    public function jsonResponse(ProductCategory $family): DepartmentsResource
    {
        return new DepartmentsResource($family);
    }

    public function getBreadcrumbs(ProductCategory $family, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (ProductCategory $family, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Families')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $family->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };


        return match ($routeName) {
            'grp.org.shops.show.catalogue.families.show' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $family,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.families.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.families.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.families.show' =>
            array_merge(
                (new ShowDepartment())->getBreadcrumbs('grp.org.shops.show.catalogue.departments.show', $routeParameters),
                $headCrumb(
                    $family,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.families.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.families.show',
                            'parameters' => $routeParameters


                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.sub-departments.show.family.show' =>
            array_merge(
                (new ShowSubDepartment())->getBreadcrumbs('grp.org.shops.show.catalogue.departments.show.sub-departments.show', $routeParameters),
                $headCrumb(
                    $family,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.sub-departments.show.family.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.sub-departments.show.family.show',
                            'parameters' => $routeParameters


                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(ProductCategory $family, ActionRequest $request): ?array
    {
        $previous = ProductCategory::where('code', '<', $family->code)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(ProductCategory $family, ActionRequest $request): ?array
    {
        $next = ProductCategory::where('code', '>', $family->code)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?ProductCategory $family, string $routeName): ?array
    {
        if (!$family) {
            return null;
        }

        return match ($routeName) {
            'shops.families.show' => [
                'label' => $family->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'department' => $family->slug
                    ]
                ]
            ],
            'shops.show.families.show' => [
                'label' => $family->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'shop'       => $family->shop->slug,
                        'department' => $family->slug
                    ]
                ]
            ],
            default => [] // Add a default case to handle unmatched route names
        };
    }
}
