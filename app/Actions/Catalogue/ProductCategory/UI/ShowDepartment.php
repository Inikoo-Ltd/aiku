<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:35:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\ProductCategory\RelatedProductCategories\GetRelatedProductCategories;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\Discounts\Offer\UI\IndexOffers;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\Catalogue\DepartmentTabsEnum;
use App\Exports\Catalogue\WebsiteStructureExport;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\Catalogue\OffersResource;
use App\Http\Resources\Catalogue\ProductCategoryTimeSeriesResource;
use App\Http\Resources\CRM\CustomersResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowDepartment extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithDepartmentSubNavigation;
    use WithWebpageActions;
    use WithDepartmentNavigation;

    private Organisation|Shop $parent;

    public function handle(ProductCategory $department): ProductCategory
    {
        if ($department->type != ProductCategoryTypeEnum::DEPARTMENT) {
            abort(404);
        }

        return $department;
    }

    public function inOrganisation(Organisation $organisation, ProductCategory $department, ActionRequest $request): ProductCategory
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(DepartmentTabsEnum::values());

        return $this->handle($department);
    }

    public function asController(Organisation $organisation, Shop $shop, ProductCategory $department, ActionRequest $request): ProductCategory
    {

        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(DepartmentTabsEnum::values());
        return $this->handle($department);
    }

    /**
     * @return array{type: string, key: string, label: string, tooltip: string, icon: array<int, string>, fields: array<int, array{key: string, label: string}>, states: array<int, array{key: string, label: string}>, download_route: array<string, array{name: string, parameters: array<string, string>}>}
     */
    public function getWebsiteStructureExportAction(ProductCategory $department): array
    {
        $definitions = WebsiteStructureExport::fieldDefinitions();
        $stateLabels = ProductCategoryStateEnum::labels();

        $parameters = [
            'organisation' => $department->organisation->slug,
            'shop'         => $department->shop->slug,
            'department'   => $department->slug,
        ];

        $downloadRoute = fn (string $type) => [
            'name'       => 'grp.org.shops.show.catalogue.departments.show.export',
            'parameters' => array_merge($parameters, ['type' => $type]),
        ];

        return [
            'type'           => 'button',
            'key'            => 'export',
            'label'          => __('Export Structure'),
            'tooltip'        => __('Export the website structure of this department: its sub departments, families and collections, with their SEO fields'),
            'icon'           => ['fal', 'fa-sitemap'],
            'fields'         => array_map(fn ($key) => [
                'key'   => $key,
                'label' => __($definitions[$key]['heading']),
            ], array_keys($definitions)),
            'states'         => array_map(fn (ProductCategoryStateEnum $state) => [
                'key'   => $state->value,
                'label' => $stateLabels[$state->value],
            ], ProductCategoryStateEnum::cases()),
            'download_route' => [
                'xlsx' => $downloadRoute('xlsx'),
                'csv'  => $downloadRoute('csv'),
            ],
        ];
    }

    public function htmlResponse(ProductCategory $department, ActionRequest $request): Response
    {

        $urlMaster                              = null;
        if ($department->master_product_category_id) {
            $urlMaster = [
                'name'       => 'grp.majordomo.redirect_master_product_category',
                'parameters' => [
                    $department->masterProductCategory->id
                ]
            ];
        }

        return Inertia::render(
            'Org/Catalogue/Department',
            [
                'title'       => __('Department').' '.$department->code.'@'.$department->shop->code,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPreviousModel($department, $request),
                    'next'     => $this->getNextModel($department, $request),
                ],
                'mini_breadcrumbs' => array_filter(
                    [
                        [
                            'label' => $department->name,
                            'to'    => [
                                'name'       => 'grp.org.shops.show.catalogue.departments.show',
                                'parameters' => [
                                    'organisation' => $this->organisation->slug,
                                    'shop'         => $this->shop->slug,
                                    'department'   => $department->slug
                                ]
                            ],
                            'tooltip' => 'Department',
                            'icon' => ['fal', 'folder-tree']
                        ],
                    ],
                ),
                'pageHead'    => [
                    'title'         => $department->name,
                    'model'        => __('Department'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-folder-tree'],
                        'title' => __('Department')
                    ],
                    'iconRight' => $department->state->stateIcon()[$department->state->value],
                    'actions'       => [
                        $this->getWebsiteStructureExportAction($department),
                        $this->getWebpageActions($department),
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,
                        !$department->children()->exists() ? [
                            'type'  => 'button',
                            'style' => 'delete',
                            'key'   => 'delete',
                            'route' => [
                                'name'       => 'grp.models.product_category.delete',
                                'parameters' => [
                                    'productCategory' => $department->id,
                                ],
                                'method' => 'delete',
                            ]
                        ] : false,
                    ],
                    'subNavigation' => $this->getDepartmentSubNavigation($department)
                ],
                'url_master'       => $urlMaster,
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => DepartmentTabsEnum::navigation()
                ],
                'shop_data' => [
                    'id'            => $department->shop->id,
                    'slug'          => $department->shop->slug,
                    'currency_code' => $department->shop->currency->code,
                    'default_dates' => [
                        'start' => now()->toDateString(),
                    ],
                ],
                'product_category_id' => $department->id,

                DepartmentTabsEnum::SHOWCASE->value => $this->tab == DepartmentTabsEnum::SHOWCASE->value ?
                    fn () => GetProductCategoryShowcase::run($department)
                    : Inertia::optional(fn () => GetProductCategoryShowcase::run($department)),

                'salesData' => $this->tab == DepartmentTabsEnum::SHOWCASE->value ?
                    fn () => GetProductCategoryTimeSeriesData::run($department)
                    : Inertia::optional(fn () => GetProductCategoryTimeSeriesData::run($department)),

                DepartmentTabsEnum::SALES->value => $this->tab == DepartmentTabsEnum::SALES->value ?
                    fn () => ProductCategoryTimeSeriesResource::collection(IndexProductCategoryTimeSeries::run($department, DepartmentTabsEnum::SALES->value))
                    : Inertia::optional(fn () => ProductCategoryTimeSeriesResource::collection(IndexProductCategoryTimeSeries::run($department, DepartmentTabsEnum::SALES->value))),

                DepartmentTabsEnum::CUSTOMERS->value => $this->tab == DepartmentTabsEnum::CUSTOMERS->value ?
                    fn () => CustomersResource::collection(IndexCustomers::run(parent: $department->shop, prefix: 'customers'))
                    : Inertia::optional(fn () => CustomersResource::collection(IndexCustomers::run(parent: $department->shop, prefix: 'customers'))),

                DepartmentTabsEnum::RELATED_PRODUCT_CATEGORY->value => $this->tab == DepartmentTabsEnum::RELATED_PRODUCT_CATEGORY->value ?
                    fn () => GetRelatedProductCategories::run($department)
                    : Inertia::optional(fn () => GetRelatedProductCategories::run($department)),

                DepartmentTabsEnum::IMAGES->value => $this->tab == DepartmentTabsEnum::IMAGES->value ?
                    fn () =>  GetProductCategoryImages::run($department)
                    : Inertia::optional(fn () => GetProductCategoryImages::run($department)),

                DepartmentTabsEnum::HISTORY->value => $this->tab == DepartmentTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($department, DepartmentTabsEnum::HISTORY->value))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($department, DepartmentTabsEnum::HISTORY->value))),

                DepartmentTabsEnum::OFFERS->value => $this->tab == DepartmentTabsEnum::OFFERS->value ?
                    fn () => OffersResource::collection(IndexOffers::make()->inProductCategory(parent: $department, prefix: DepartmentTabsEnum::OFFERS->value))
                    : Inertia::optional(fn () => OffersResource::collection(IndexOffers::make()->inProductCategory(parent: $department, prefix: DepartmentTabsEnum::OFFERS->value))),

            ]
        )
        ->table(IndexCustomers::make()->tableStructure(parent: $department->shop, prefix: 'customers'))
        ->table(IndexHistory::make()->tableStructure(prefix: DepartmentTabsEnum::HISTORY->value))
        ->table(IndexProductCategoryTimeSeries::make()->tableStructure(DepartmentTabsEnum::SALES->value))
        ->table(IndexOffers::make()->tableStructure(parent: $department, prefix: DepartmentTabsEnum::OFFERS->value));
    }


    public function jsonResponse(ProductCategory $department): DepartmentsResource
    {
        return new DepartmentsResource($department);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (ProductCategory $department, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Departments')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $department->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        $department = ProductCategory::where('slug', $routeParameters['department'])->first();

        return match ($routeName) {

            'grp.org.shops.show.catalogue.departments.show' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $department,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }


}
