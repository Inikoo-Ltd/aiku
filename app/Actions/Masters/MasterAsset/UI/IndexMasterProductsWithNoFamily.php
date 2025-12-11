<?php

/*
 * author Louis Perez
 * created on 25-11-2025-14h-37m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\WithMasterDepartmentSubNavigation;
use App\Actions\Masters\MasterProductCategory\WithMasterFamilySubNavigation;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Http\Resources\Masters\MasterProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Http\Resources\Api\Dropshipping\OpenShopsInMasterShopResource;
use App\Actions\Catalogue\Shop\UI\IndexOpenShopsInMasterShop;

class IndexMasterProductsWithNoFamily extends GrpAction
{
    use WithMasterCatalogueSubNavigation;
    use WithMasterDepartmentSubNavigation;
    use WithMasterFamilySubNavigation;
    use WithMastersAuthorisation;

    private Group|MasterShop $parent;


    public function handle(Group|MasterShop|MasterProductCategory $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('master_assets.code', $value)
                    ->orWhereStartWith('master_assets.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $queryBuilder = QueryBuilder::for(MasterAsset::class);
        $queryBuilder->where('is_main', true);
        $queryBuilder->leftJoin('master_asset_stats', 'master_assets.id', '=', 'master_asset_stats.master_asset_id');
        $queryBuilder->whereNull('master_assets.master_family_id');
        $queryBuilder->leftJoin('groups', 'master_assets.group_id', 'groups.id');
        $queryBuilder->leftJoin('currencies', 'groups.currency_id', 'currencies.id');

        foreach (IndexMasterProducts::make()->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }


        $queryBuilder->select(
            [
                'master_assets.id',
                'master_assets.code',
                'master_assets.name',
                'master_assets.slug',
                'master_assets.status',
                'master_assets.price',
                'master_assets.unit',
                'master_assets.units',
                'master_assets.rrp',
                'master_asset_stats.number_current_assets as used_in',
                'currencies.code as currency_code',
            ]
        );
        if ($parent instanceof Group) {
            $queryBuilder->where('master_assets.group_id', $parent->id);
            $queryBuilder->leftJoin('master_shops', 'master_shops.id', 'master_assets.master_shop_id');
            $queryBuilder->leftJoin('master_product_categories as departments', 'departments.id', 'master_assets.master_department_id');
            $queryBuilder->leftJoin('master_product_categories as families', 'families.id', 'master_assets.master_family_id');

            $queryBuilder->addSelect([
                'families.slug as master_family_slug',
                'families.code as master_family_code',
                'families.name as master_family_name',
                'departments.slug as master_department_slug',
                'departments.code as master_department_code',
                'departments.name as master_department_name',
                'master_shops.slug as master_shop_slug',
                'master_shops.code as master_shop_code',
                'master_shops.name as master_shop_name',
            ]);
        } elseif ($parent instanceof MasterShop) {
            $queryBuilder->where('master_assets.master_shop_id', $parent->id);
            $queryBuilder->leftJoin('master_product_categories as departments', 'departments.id', 'master_assets.master_department_id');
            $queryBuilder->leftJoin('master_product_categories as families', 'families.id', 'master_assets.master_family_id');
            $queryBuilder->addSelect([
                'families.slug as master_family_slug',
                'families.code as master_family_code',
                'families.name as master_family_name',
                'departments.slug as master_department_slug',
                'departments.code as master_department_code',
                'departments.name as master_department_name'
            ]);
        } elseif ($parent instanceof MasterProductCategory) {
            if ($parent->type == MasterProductCategoryTypeEnum::FAMILY) {
                $queryBuilder->where('master_assets.master_family_id', $parent->id);
            } elseif ($parent->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
                $queryBuilder->where('master_assets.master_department_id', $parent->id);
            } else {
                $queryBuilder->where('master_assets.master_sub_department_id', $parent->id);
            }


            $queryBuilder->leftJoin('master_product_categories as departments', 'departments.id', 'master_assets.master_department_id');
            $queryBuilder->leftJoin('master_product_categories as families', 'families.id', 'master_assets.master_family_id');
            $queryBuilder->addSelect([
                'families.slug as master_family_slug',
                'families.code as master_family_code',
                'families.name as master_family_name',
                'departments.slug as master_department_slug',
                'departments.code as master_department_code',
                'departments.name as master_department_name'
            ]);
        } else {
            abort(419);
        }

        return $queryBuilder
            ->defaultSort('master_assets.code')
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|MasterShop|MasterProductCategory $parent, ?array $modelOperations = null, $prefix = null): \Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach (IndexMasterProducts::make()->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => __("No master shops found"),
                    ],
                );

            if ($parent instanceof Group) {
                $table->column('master_shop_code', __('Shop'), sortable: true);
                $table->column('master_department_code', __('Department'), sortable: true);
                $table->column('master_family_code', __('Family'), sortable: true);
            }

            $table
                ->column(key: 'status_icon', label: '', canBeHidden: false, searchable: true, type: 'icon')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'unit', label: __('Unit'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'used_in', label: __('Used in'), tooltip: __('Current products with this master'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function jsonResponse(LengthAwarePaginator $masterAssets): AnonymousResourceCollection
    {
        return MasterProductsResource::collection($masterAssets);
    }

    public function htmlResponse(LengthAwarePaginator $masterAssets, ActionRequest $request): Response
    {
        $title = __('Orphan Master Products');

        $icon          = '';
        $model         = null;
        $afterTitle    = null;
        $iconRight     = null;
        $subNavigation = null;
        $familyId      = null;

        $routes = [];
        $shopsData = null;
        if ($this->parent instanceof MasterShop) {
            $masterShop    = $this->parent;
            $subNavigation = $this->getMasterShopNavigation($this->parent);
            $title         = $this->parent->name;
            $model         = '';
            $icon          = [
                'icon'  => ['fal', 'fa-store-alt'],
                'title' => __('Master shop')
            ];
            $afterTitle    = [
                'label' => __('Master Products')
            ];
            $iconRight     = [
                'icon' => 'fal fa-cube',
            ];

            $routes = [
                'master_families_route' => [
                    'name'       => 'grp.json.master-family.all-master-family',
                    'parameters' => [
                        'masterShop'                    => $masterShop->slug,
                        'withMasterProductCategoryStat' => true,
                    ]
                ],
                'submit_orphan_route'   => [
                    'name'       => 'grp.models.master_family.bulk_add_family',
                    'parameters' => [
                    ]
                ]
            ];

            $shopsData = OpenShopsInMasterShopResource::collection(IndexOpenShopsInMasterShop::run($masterShop, 'shops'));

        }


        return Inertia::render(
            'Masters/MasterProducts',
            [
                'breadcrumbs'        => $this->getBreadcrumbs(
                    $this->parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'              => $title,
                'familyId'           => $familyId,
                'currency'           => $this->parent->group->currency->code,
                'pageHead'           => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                ],
                'data'               => MasterProductsResource::collection($masterAssets),
                'editable_table'     => true,
                'shopsData'          => $shopsData,
                'is_orphan_products' => true,
                'routes'             => $routes,

            ]
        )->table($this->tableStructure($this->parent));
    }

    public function getBreadcrumbs(Group|MasterShop|MasterProductCategory $parent, string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Master Products'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => trim('('.__('Orphan').') '.$suffix)
                ],
            ];
        };

        return match ($routeName) {
            'grp.masters.master_shops.show.master_products_orphan' =>
            array_merge(
                ShowMasterShop::make()->getBreadcrumbs($parent, $routeName),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => Arr::only($routeParameters, ['masterShop']),
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $group        = group();
        $this->parent = $group;
        $this->initialisation($group, $request);

        return $this->handle($group);
    }

    public function inMasterShop(MasterShop $masterShop, ActionRequest $request): LengthAwarePaginator
    {
        $group        = group();
        $this->parent = $masterShop;
        $this->initialisation($group, $request);

        return $this->handle($masterShop);
    }
}
