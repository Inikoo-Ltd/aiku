<?php
/*
 * author Arya Permana - Kirin
 * created on 19-06-2025-09h-08m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\Catalogue\WithSubDepartmentSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\Catalogue\ProductCategoryTabsEnum;
use App\Http\Resources\Catalogue\FamiliesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexFamiliesWithNoDepartment extends OrgAction
{
    use WithCatalogueAuthorisation;

    private bool $sales = true;

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request)->withTab(ProductCategoryTabsEnum::values());

        return $this->handle(shop: $shop, prefix: ProductCategoryTabsEnum::INDEX->value);
    }

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('product_categories.name', $value)
                    ->orWhereStartWith('product_categories.code', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(ProductCategory::class);

        foreach ($this->getElementGroups($shop) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }


        $queryBuilder->leftJoin('shops', 'product_categories.shop_id', 'shops.id');
        $queryBuilder->leftJoin('organisations', 'product_categories.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftJoin('product_category_sales_intervals', 'product_category_sales_intervals.product_category_id', 'product_categories.id');
        $queryBuilder->leftJoin('product_category_ordering_intervals', 'product_category_ordering_intervals.product_category_id', 'product_categories.id');
        $queryBuilder->where('product_categories.shop_id', $shop->id);
        $queryBuilder->whereNull('product_categories.department_id');

        return $queryBuilder
            ->defaultSort('product_categories.code')
            ->select([
                'product_categories.id',
                'product_categories.slug',
                'product_categories.code',
                'product_categories.name',
                'product_categories.state',
                'product_categories.description',
                'product_categories.created_at',
                'product_categories.image_id',
                'product_categories.updated_at',
                'product_category_stats.number_current_products',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'product_category_sales_intervals.sales_grp_currency_all as sales_all',
                'product_category_ordering_intervals.invoices_all as invoices_all',

            ])
            ->leftJoin('product_category_stats', 'product_categories.id', 'product_category_stats.product_category_id')
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->allowedSorts(['code', 'name', 'shop_code', 'number_current_products'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop $shop, ?array $modelOperations = null, $prefix = null, $canEdit = false): Closure
    {
        return function (InertiaTable $table) use ($shop, $modelOperations, $prefix, $canEdit) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($shop) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->defaultSort('code')
                ->withEmptyState(
                    [
                        'title' => __("No families found"),
                        'count' => $shop->stats->number_families,
                    ]
                )
                ->withGlobalSearch()
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->withModelOperations($modelOperations);


                $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
            
        };
    }

    public function jsonResponse(LengthAwarePaginator $families): AnonymousResourceCollection
    {
        return FamiliesResource::collection($families);
    }

    public function htmlResponse(LengthAwarePaginator $families, ActionRequest $request): Response
    {
        $navigation = ProductCategoryTabsEnum::navigation();

        unset($navigation[ProductCategoryTabsEnum::SALES->value]);


        $title      = __('families');
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-folder'],
            'title' => __('family')
        ];
        $afterTitle = null;
        $iconRight  = null;
        $routes = null;


        return Inertia::render(
            'Org/Catalogue/Families',
            [
                'breadcrumbs'                         => $this->getBreadcrumbs(
                    $this->shop,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'                               => __('families'),
                'pageHead'                            => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                ],
                'routes'                              => $routes,
                'data'                                => FamiliesResource::collection($families),
                'tabs'                                => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],

                // 'routes' => [   // TODO: Kirin
                //     'families_route' => [
                //         'name' => 'grp.json.shop.families',
                //         'parameters' => [
                //             'shop' => $this->shop->slug
                //         ]
                //     ],
                //     'submit_route' => [
                //         'name' => 'grp.models.family.move_products',
                //         'parameters' => []
                //     ]
                // ],
                ProductCategoryTabsEnum::INDEX->value => $this->tab == ProductCategoryTabsEnum::INDEX->value ?
                    fn () => FamiliesResource::collection($families)
                    : Inertia::lazy(fn () => FamiliesResource::collection($families)),
            ]
        )->table($this->tableStructure(shop: $this->shop, modelOperations: null, canEdit: false, prefix: ProductCategoryTabsEnum::INDEX->value));
    }

    public function getBreadcrumbs(Shop $shop, string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Families'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.catalogue.families.no_department.index' => array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    protected function getElementGroups($parent): array
    {
        return
            [
                'state' => [
                    'label'    => __('State'),
                    'elements' => array_merge_recursive(
                        ProductCategoryStateEnum::labels(),
                        ProductCategoryStateEnum::countFamily($parent)
                    ),
                    'engine'   => function ($query, $elements) {
                        $query->whereIn('product_categories.state', $elements);
                    }
                ]
            ];
    }
}
